<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Database\Connection;
use App\Core\Security\Csrf;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Billing\BillingDetailNormalizer;
use App\Modules\Billing\BillingPeriodSyncHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class BillingOperatorHttpRouteTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing <Company>','billing'),(2,'Foreign','foreign')");
        $this->pdo->exec("INSERT INTO users(id,email,password_hash,status) VALUES(1,'billing@example.test','unused','active')");
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $this->pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES
            (10,1,'100','MCO','Seller <script>','connected'),(11,1,'101','MCO','Other','connected'),
            (20,2,'200','MCO','Foreign','connected'),(30,1,'300','MCO','Disabled','disabled')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'admin'];
    }

    /** @param array<string,mixed> $body @param array<string,mixed> $query */
    private function request(string $method, array $body = [], array $query = []): ResponseInterface
    {
        $usage = $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll();
        $cooldowns = $this->pdo->query('SELECT * FROM meli_cooldowns')->fetchAll();
        $response = Bootstrap::create()->handle((new ServerRequestFactory())
            ->createServerRequest($method, $method === 'POST' ? '/billing/syncs' : '/billing')
            ->withParsedBody($body)->withQueryParams($query));
        self::assertSame($usage, $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll());
        self::assertSame($cooldowns, $this->pdo->query('SELECT * FROM meli_cooldowns')->fetchAll());
        return $response;
    }

    /** @return array<string,mixed> */
    private function input(): array
    {
        return ['account_id' => '10', 'period_key' => '2026-09-01', 'document_type' => 'BILL', 'csrf_token' => (new Csrf())->token()];
    }

    public function testProducerRegistersOwnStreamWithoutHttpOrBillingFacts(): void
    {
        $response = $this->request('POST', $this->input() + ['company_id' => 2]);
        self::assertSame(303, $response->getStatusCode());
        $row = $this->pdo->query('SELECT * FROM work_items')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['company_id']);
        self::assertSame(10, (int) $row['account_id']);
        self::assertSame('billing.period.sync', $row['type']);
        self::assertSame('pending', $row['status']);
        self::assertSame(['period_key' => '2026-09-01', 'document_type' => 'BILL', 'from_id' => '0'], json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertStringStartsWith('/billing?', $response->getHeaderLine('Location'));
    }

    #[DataProvider('authorityCases')]
    public function testProducerRejectsUnauthorizedActorOrAccount(string $case, int $status): void
    {
        $input = $this->input();
        match ($case) {
            'anonymous' => $_SESSION = [],
            'disabled' => $this->pdo->exec("UPDATE users SET status='disabled'"),
            'member' => $this->pdo->exec("UPDATE company_users SET role='member'"),
            'foreign-session' => $_SESSION['company_id'] = 2,
            'foreign-account' => $input['account_id'] = '20',
            'disconnected' => $input['account_id'] = '30',
            'csrf' => $input['csrf_token'] = 'invalid',
        };
        self::assertSame($status, $this->request('POST', $input)->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public static function authorityCases(): iterable
    {
        yield 'anonymous' => ['anonymous', 403];
        yield 'disabled' => ['disabled', 403];
        yield 'stale admin session' => ['member', 403];
        yield 'foreign session' => ['foreign-session', 403];
        yield 'foreign account' => ['foreign-account', 422];
        yield 'disconnected' => ['disconnected', 422];
        yield 'csrf' => ['csrf', 419];
    }

    #[DataProvider('invalidInputs')]
    public function testProducerRejectsInvalidScalarInputWithoutEnqueue(string $field, mixed $value): void
    {
        $input = $this->input();
        $input[$field] = $value;
        self::assertSame(422, $this->request('POST', $input)->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public static function invalidInputs(): iterable
    {
        foreach (['account_id', 'period_key', 'document_type'] as $field) {
            foreach ([null, [], new \stdClass(), true, 1.5] as $index => $value) {
                yield $field . '-' . $index => [$field, $value];
            }
        }
        foreach (['0', '-1', '1e1', '10 ', '99999999999999999999999'] as $value) {
            yield 'account-' . $value => ['account_id', $value];
        }
        foreach (['2026-09', '2026-13-01', '2026-09-02', '0000-01-01', '2026-9-01', '2026-09-01 '] as $value) {
            yield 'period-' . $value => ['period_key', $value];
        }
        foreach (['INVOICE', 'bill', '', 'BILL '] as $value) {
            yield 'type-' . $value => ['document_type', $value];
        }
    }

    public function testProducerAcceptsCreditNoteAsIndependentStream(): void
    {
        $input = $this->input();
        self::assertSame(303, $this->request('POST', $input)->getStatusCode());
        $input['document_type'] = 'CREDIT_NOTE';
        self::assertSame(303, $this->request('POST', $input)->getStatusCode());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public function testActiveDuplicatePreservesRunningClaimAndDurableProgress(): void
    {
        self::assertSame(303, $this->request('POST', $this->input())->getStatusCode());
        $this->period('101');
        $this->pdo->exec("UPDATE work_items SET status='running',payload_json=JSON_SET(payload_json,'$.from_id','101'),
            claim_token=REPEAT('a',32),claimed_at=UTC_TIMESTAMP(6),available_at='2030-01-01 00:00:00',attempts=2");
        $before = $this->pdo->query('SELECT * FROM work_items')->fetchAll(PDO::FETCH_ASSOC);
        $period = $this->pdo->query('SELECT * FROM billing_periods')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame(303, $this->request('POST', $this->input())->getStatusCode());
        self::assertSame($before, $this->pdo->query('SELECT * FROM work_items')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($period, $this->pdo->query('SELECT * FROM billing_periods')->fetchAll(PDO::FETCH_ASSOC));
    }

    #[DataProvider('resumeCases')]
    public function testExplicitResubmitUsesDurableCursorWithStableIdentity(?string $cursor, string $previousStatus): void
    {
        self::assertSame(303, $this->request('POST', $this->input())->getStatusCode());
        if ($cursor !== null) {
            $this->period($cursor, $previousStatus === 'done' ? 'caught_up' : 'pending');
        }
        $this->pdo->exec("UPDATE work_items SET status='$previousStatus',finished_at=UTC_TIMESTAMP(6)");
        $first = $this->pdo->query('SELECT * FROM work_items')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(303, $this->request('POST', $this->input())->getStatusCode());
        $rows = $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $rows);
        self::assertSame($first, $rows[0]);
        self::assertSame($rows[0]['dedupe_key'], $rows[1]['dedupe_key']);
        self::assertSame('company:1:account:10', $rows[1]['scope_key']);
        self::assertSame(hash('sha256', '2026-09-01:BILL'), $rows[1]['dedupe_key']);
        self::assertSame($cursor ?? '0', json_decode($rows[1]['payload_json'], true, 512, JSON_THROW_ON_ERROR)['from_id']);
        self::assertSame('pending', $rows[1]['status']);
    }

    public static function resumeCases(): iterable
    {
        yield 'failed before progress' => [null, 'failed'];
        yield 'failed after progress' => ['101', 'failed'];
        yield 'completed prior stream' => ['101', 'done'];
    }

    #[DataProvider('caughtUpPages')]
    public function testCaughtUpExplicitResyncThroughUnmodifiedHandler(string $body, string $cursor, string $state): void
    {
        $this->period('101', 'caught_up');
        self::assertSame(303, $this->request('POST', $this->input())->getStatusCode());
        $work = new WorkRepository($this->pdo);
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        self::assertSame('101', $claim['payload']['from_id']);
        $cipher = new TokenCipher(TestDatabase::config()->appKey);
        $token = $this->pdo->prepare('INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at) VALUES(10,?,?,?)');
        $token->execute([$cipher->encrypt('synthetic-access'), $cipher->encrypt('synthetic-refresh'), '2099-01-01']);
        $transport = new class($body) implements MeliTransport {
            public int $calls = 0;
            public function __construct(private readonly string $body) {}
            public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
            {
                TestCase::assertSame('GET', $method);
                TestCase::assertStringContainsString('from_id=101', $url);
                $this->calls++;
                return new MeliTransportResponse(200, [], $this->body);
            }
        };
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient($transport, new SystemSettingsRepository($this->pdo), $operations, 'https://api.mercadolibre.com', minRequestIntervalMs: 0);
        $tokens = new OAuthRefreshService($this->pdo, Connection::fromConfig(TestDatabase::config()), $client, $cipher, 'synthetic-client', 'synthetic-secret', 'lc2c1.oauth');
        $handler = new BillingPeriodSyncHandler($work, $client, $tokens, new BillingDetailNormalizer());
        self::assertTrue($handler->syncCurrentClaim($claim['id'], $claim['claim_token'], 1, 10, '2026-09-01', 'BILL', '101', new DateTimeImmutable('now UTC')));
        $period = $this->pdo->query('SELECT cursor_last_id,sync_state FROM billing_periods')->fetch(PDO::FETCH_ASSOC);
        self::assertSame(['cursor_last_id' => $cursor, 'sync_state' => $state], $period);
        self::assertSame(1, $transport->calls);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        self::assertSame($state === 'caught_up' ? 0 : 1, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertSame($state === 'caught_up' ? 'done' : 'pending', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        if ($state === 'pending') {
            self::assertSame('102', json_decode($this->pdo->query('SELECT payload_json FROM work_items')->fetchColumn(), true, 512, JSON_THROW_ON_ERROR)['from_id']);
            self::assertSame(hash('sha256', '2026-09-01:BILL'), $this->pdo->query('SELECT dedupe_key FROM work_items')->fetchColumn());
        }
    }

    public static function caughtUpPages(): iterable
    {
        yield 'no new facts' => ['{"results":[]}', '101', 'caught_up'];
        yield 'late valid facts' => ['{"results":[{"charge_info":{"detail_id":"102","detail_amount":"10.2500","detail_type":"CHARGE"},"currency_info":{"currency_id":"COP"}}],"last_id":"102"}', '102', 'pending'];
    }

    public function testIndependentAccountsAndPeriodsHaveIndependentActiveWork(): void
    {
        $input = $this->input();
        foreach ([['10', '2026-09-01'], ['11', '2026-09-01'], ['10', '2026-08-01']] as [$account, $period]) {
            $input['account_id'] = $account;
            $input['period_key'] = $period;
            self::assertSame(303, $this->request('POST', $input)->getStatusCode());
        }
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public function testConcurrentProducerRequestsConvergeOnOneActiveWork(): void
    {
        $children = [];
        $this->pdo->beginTransaction();
        $this->pdo->query('SELECT id FROM meli_accounts WHERE id=10 FOR UPDATE')->fetchColumn();
        $script = <<<'PHP'
require getcwd() . '/vendor/autoload.php';
$pdo = \App\Core\Database\Connection::fromConfig(\Tests\Support\TestDatabase::config());
session_start();
$_SESSION = ['user_id'=>1,'company_id'=>1,'csrf_token'=>str_repeat('c',64)];
echo $pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), "\n";
flush();
$request = (new \Slim\Psr7\Factory\ServerRequestFactory())->createServerRequest('POST','/billing/syncs')
    ->withParsedBody(['account_id'=>'10','period_key'=>'2026-09-01','document_type'=>'BILL','csrf_token'=>str_repeat('c',64)]);
echo (new \App\Modules\Billing\BillingController($pdo))->sync($request, (new \Slim\Psr7\Factory\ResponseFactory())->createResponse())->getStatusCode();
PHP;
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
                self::assertIsResource($process);
                $children[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2]];
            }
            $ids = [];
            foreach ($children as $child) {
                $ids[] = (int) trim((string) fgets($child['out']));
            }
            $statement = $this->pdo->prepare("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID IN (?,?) AND INFO LIKE '%FROM meli_accounts%' AND INFO LIKE '%FOR UPDATE%'");
            $deadline = hrtime(true) + 5_000_000_000;
            do {
                $statement->execute($ids);
                $waiting = (int) $statement->fetchColumn();
                if ($waiting === 2) { break; }
                usleep(10_000);
            } while (hrtime(true) < $deadline);
            self::assertSame(2, $waiting, 'Two independent producer transactions must overlap at the account barrier.');
            $this->pdo->commit();
            while (($child = array_shift($children)) !== null) {
                $output = stream_get_contents($child['out']);
                $error = stream_get_contents($child['err']);
                fclose($child['out']); fclose($child['err']);
                self::assertSame(0, proc_close($child['process']), (string) $error);
                self::assertSame('303', $output);
            }
            self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM work_items WHERE status IN ('pending','running')")->fetchColumn());
        } finally {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            foreach ($children as $child) {
                proc_terminate($child['process']);
                fclose($child['out']); fclose($child['err']); proc_close($child['process']);
            }
        }
    }

    private function period(string $cursor, string $state = 'pending'): void
    {
        $statement = $this->pdo->prepare('INSERT INTO billing_periods(company_id,account_id,period_key,document_type,cursor_last_id,sync_state,last_synced_at) VALUES(1,10,\'2026-09-01\',\'BILL\',?,?,\'2026-10-12 00:00:00\')');
        $statement->execute([$cursor, $state]);
    }

    public function testRegisteredRequestIsVisibleBeforeAnyBillingProgress(): void
    {
        $response = $this->request('POST', $this->input());
        self::assertSame(303, $response->getStatusCode());
        parse_str((string) parse_url($response->getHeaderLine('Location'), PHP_URL_QUERY), $query);
        $html = (string) $this->request('GET', query: $query)->getBody();
        self::assertStringContainsString('Solicitud registrada', $html);
        self::assertStringNotContainsString('Al día con la fuente consultada', $html);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    #[DataProvider('visibleStates')]
    public function testVisibleStateUsesRequestAndDurableProgressWithoutPartialFlag(string $state, string $workStatus, string $message): void
    {
        self::assertSame(303, $this->request('POST', $this->input())->getStatusCode());
        $this->period('<cursor>', $state);
        $this->pdo->exec("UPDATE work_items SET status='$workStatus',claim_token=REPEAT('s',32),last_error_safe='SECRET_ERROR',payload_json=JSON_SET(payload_json,'$.secret','SECRET_PAYLOAD'),attempts=77");
        $query = ['account_id'=>'10','period_key'=>'2026-09-01','document_type'=>'BILL'];
        $before = $this->pdo->query('SELECT * FROM work_items')->fetchAll();
        $html = (string) $this->request('GET', query: $query)->getBody();
        self::assertStringContainsString($message, $html);
        self::assertStringContainsString('&lt;cursor&gt;', $html);
        self::assertStringContainsString('Billing &lt;Company&gt;', $html);
        self::assertStringContainsString('Seller &lt;script&gt;', $html);
        foreach (['SECRET_ERROR','SECRET_PAYLOAD',str_repeat('s',32),'claim_token','payload_json','attempts'] as $secret) {
            self::assertStringNotContainsString($secret, $html);
        }
        self::assertSame($before, $this->pdo->query('SELECT * FROM work_items')->fetchAll());
        $this->pdo->exec('UPDATE billing_periods SET partial_flag=1');
        self::assertSame($html, (string) $this->request('GET', query: $query)->getBody());
    }

    public static function visibleStates(): iterable
    {
        yield 'in progress' => ['pending','pending','Sincronizando'];
        yield 'previous terminal' => ['caught_up','done','Al día con la fuente consultada'];
        yield 'failed' => ['pending','failed','Requiere revisión'];
        yield 'resync after previous terminal' => ['caught_up','pending','Solicitud registrada'];
    }

    public function testFailedRequestWithoutProgressIsVisibleAndNeverAutomaticallyRestarted(): void
    {
        self::assertSame(303, $this->request('POST', $this->input())->getStatusCode());
        $this->pdo->exec("UPDATE work_items SET status='failed'");
        $html = (string) $this->request('GET', query: ['account_id'=>'10','period_key'=>'2026-09-01','document_type'=>'BILL'])->getBody();
        self::assertStringContainsString('Requiere revisión', $html);
        self::assertSame('failed', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public function testBillingPageIsTenantScopedAndMembersCannotStartSync(): void
    {
        $this->period('101');
        $this->pdo->exec("INSERT INTO billing_periods(company_id,account_id,period_key,document_type,cursor_last_id) VALUES(2,20,'2026-07-01','BILL','FOREIGN_CURSOR')");
        $this->pdo->exec("UPDATE company_users SET role='member'");
        $html = (string) $this->request('GET')->getBody();
        self::assertStringContainsString('2026-09-01', $html);
        self::assertStringNotContainsString('2026-07-01', $html);
        self::assertStringNotContainsString('action="/billing/syncs"', $html);
        self::assertStringContainsString('href="/billing"', $html);
        self::assertSame(404, $this->request('GET', query: ['account_id'=>'20','period_key'=>'2026-07-01','document_type'=>'BILL'])->getStatusCode());
        self::assertSame(422, $this->request('GET', query: ['account_id'=>[]])->getStatusCode());
        $this->pdo->exec("UPDATE users SET status='disabled'");
        self::assertSame(403, $this->request('GET')->getStatusCode());
        $_SESSION = [];
        self::assertSame(403, $this->request('GET')->getStatusCode());
    }
}
