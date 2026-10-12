<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Transport\CurlMeliTransport;
use App\Integrations\MercadoLibre\Transport\RemoteHostPolicy;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Core\Database\Connection;
use App\Modules\Settings\SystemSettingsRepository;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditHandler;
use App\Modules\Sales\Audit\SalesAuditRepairHandler;
use App\Modules\Billing\BillingPeriodSyncHandler;
use App\Modules\Billing\BillingDetailNormalizer;
use App\Work\WorkRepository;
use Tests\Support\TestDatabase;
use DateTimeImmutable;
use PDO;
use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Auth\PasswordService;
use App\Modules\Financial\FinancialReadModel;
use App\Modules\Sales\Audit\SalesAuditWindow;
use Slim\Psr7\Factory\ServerRequestFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MeliHttpSimulationTest extends TestCase
{
    /** @var resource|null */
    private $server = null;
    private ?string $directory = null;
    private string $baseUrl = '';
    private bool $expectedUnexpected = false;
    private PDO $pdo;
    private MeliClient $client;
    private OAuthRefreshService $tokens;
    private WorkRepository $work;

    protected function tearDown(): void
    {
        try {
            if (!$this->expectedUnexpected) {
                foreach ($this->trace() as $row) {
                    self::assertTrue($row['matched'], 'Unexpected simulator request.');
                }
            }
        } finally {
            $this->stopServer();
        }
    }

    public function testSimulatorRejectsNonTestEnvironment(): void
    {
        $router = dirname(__DIR__) . '/Support/MeliSim/router.php';
        $process = proc_open([PHP_BINARY, $router], [1=>['pipe','w'],2=>['pipe','w']], $pipes,
            dirname(__DIR__, 2), ['APP_ENV'=>'production']);
        self::assertIsResource($process);
        try {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
        } finally {
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process);
        }
        self::assertSame(2, $exit, (string) $stderr);
        self::assertSame('MLSIM_TEST_ONLY', trim((string) $stdout));
    }

    public function testUnexpectedRequestIsTracedAndFails(): void
    {
        $this->startServer('ORDER_HAPPY_EXACT');
        $this->expectedUnexpected = true;
        $response = $this->transport()->send('POST', $this->baseUrl . '/oauth/token?secret=do-not-log',
            ['Authorization'=>'Bearer do-not-log'], 'do-not-log');
        self::assertSame(500, $response->status);
        $rows = $this->trace();
        self::assertCount(1, $rows);
        self::assertFalse($rows[0]['matched']);
        self::assertSame('UNKNOWN', $rows[0]['operation']);
        self::assertStringNotContainsString('do-not-log', json_encode($rows, JSON_THROW_ON_ERROR));
        self::assertSame([], $rows[0]['query']);
    }

    public function testExcessRequestAfterFiniteScenarioIsTracedAndFails(): void
    {
        $this->startServer('ORDER_HAPPY_EXACT');
        $this->expectedUnexpected = true;
        $url = $this->baseUrl . '/orders/9007199254740993';
        self::assertSame(200, $this->transport()->send('GET', $url, [], null)->status);
        self::assertSame(500, $this->transport()->send('GET', $url, [], null)->status);
        $rows = $this->trace();
        self::assertCount(2, $rows);
        self::assertSame([1,2], array_column($rows, 'seq'));
        self::assertSame([true,false], array_column($rows, 'matched'));
    }

    public function testOwnedProcessAlwaysStops(): void
    {
        $this->startServer('ORDER_HAPPY_EXACT');
        $url = $this->baseUrl;
        $directory = $this->directory;
        self::assertSame([], $this->trace(), 'TCP readiness must not create an HTTP request.');
        try {
            throw new RuntimeException('Fixture failure');
        } catch (RuntimeException) {
            $this->stopServer();
        }
        self::assertDirectoryDoesNotExist((string) $directory);
        $socket = @stream_socket_client(str_replace('http://','tcp://',$url), $errno, $error, 0.05);
        if (is_resource($socket)) { fclose($socket); }
        self::assertFalse($socket, 'Owned server must no longer listen.');
    }

    public function testSimulatorRejectsAnyNonLiteralLoopbackHost(): void
    {
        $this->expectException(RuntimeException::class);
        $this->startServer('ORDER_HAPPY_EXACT', 'localhost');
    }

    public function testOrderThroughCurlSimulatorPersistsExactFactsWithExactlyOneGet(): void
    {
        $this->domain('ORDER_HAPPY_EXACT');
        $claim = $this->enqueueClaim('order.sync', ['order_id'=>'9007199254740993']);
        self::assertTrue($this->syncOrder($claim));
        $order = $this->pdo->query('SELECT external_order_id,total_amount,buyer_id FROM orders')->fetch(PDO::FETCH_ASSOC);
        self::assertSame('9007199254740993', $order['external_order_id']);
        self::assertSame('90071992547409.1234', $order['total_amount']);
        self::assertSame('9007199254740995', $order['buyer_id']);
        $items = $this->pdo->query('SELECT unit_price,sale_fee FROM order_items ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame('90071992547409.1234', $items[0]['unit_price']);
        self::assertSame('1.2345', $items[0]['sale_fee']);
        self::assertNull($items[1]['sale_fee']);
        self::assertSame('done', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        $this->assertCalls(['orders.get']);
        self::assertTrue($this->trace()[0]['authorization_present']);
    }

    public function testOrderPartialHeaderMakesExactlyOneGetWithoutCanonicalMutation(): void
    {
        $this->domain('ORDER_PARTIAL_HEADER');
        self::assertFalse($this->syncOrder($this->enqueueClaim('order.sync', ['order_id'=>'9007199254740993'])));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        self::assertSame('failed', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        $this->assertCalls(['orders.get']);
    }

    public function testOrderInvalidJsonMakesExactlyOneGetWithoutCanonicalMutation(): void
    {
        $this->domain('INVALID_JSON');
        self::assertFalse($this->syncOrder($this->enqueueClaim('order.sync', ['order_id'=>'9007199254740993'])));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame('pending', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        $this->assertCalls(['orders.get']);
    }

    public function testAuditRepairUsesExactlySearchOrderSearchAndReachesValid(): void
    {
        $this->domain('AUDIT_REPAIR_1');
        [$audit, $run, $claim] = $this->auditClaim();
        self::assertTrue($this->capture($audit, $claim));
        $repair = $this->nextClaim();
        $repairHandler = new SalesAuditRepairHandler($audit, $this->work);
        self::assertTrue($repairHandler->processCurrentClaim($repair['id'], $repair['claim_token'], $run, 1, 1, $this->now()));
        $this->eligible($repair['id'], '2099-01-01');
        $order = $this->nextClaim();
        self::assertSame('order.sync', $order['type']);
        self::assertTrue($this->syncOrder($order));
        $this->eligible($repair['id']);
        $repair = $this->nextClaim();
        self::assertTrue($repairHandler->processCurrentClaim($repair['id'], $repair['claim_token'], $run, 1, 1, $this->now()));
        $confirmation = $this->nextClaim();
        self::assertTrue($this->capture($audit, $confirmation, 'B'));
        self::assertSame('valid', $audit->runStatus($run, 1, 1));
        $this->assertCalls(['orders.search','orders.get','orders.search']);
    }

    public function testAuditPartial206MakesOneGetWithoutAdvancingAuthority(): void
    {
        $this->domain('AUDIT_PARTIAL_206');
        [$audit, $run, $claim] = $this->auditClaim();
        $before = $this->pdo->query('SELECT * FROM sales_audit_runs')->fetchAll();
        self::assertFalse($this->capture($audit, $claim));
        self::assertSame($before, $this->pdo->query('SELECT * FROM sales_audit_runs')->fetchAll());
        self::assertSame(0, $audit->observationCount($run));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        $this->assertCalls(['orders.search']);
        self::assertSame(206, $this->trace()[0]['status']);
    }

    public function testBillingTwoPagesAndTerminalUseExactlyThreeGetsAndOneWork(): void
    {
        $this->domain('BILLING_TWO_PAGES');
        $claim = $this->billingClaim();
        $id = $claim['id'];
        foreach (['101','102','102'] as $index => $cursor) {
            self::assertSame($id, $claim['id']);
            self::assertTrue($this->billing($claim));
            $period = $this->pdo->query('SELECT cursor_last_id,sync_state FROM billing_periods')->fetch(PDO::FETCH_ASSOC);
            self::assertSame($cursor, $period['cursor_last_id']);
            self::assertSame($index === 2 ? 'caught_up' : 'pending', $period['sync_state']);
            self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
            if ($index < 2) {
                $this->eligible($id);
                $claim = $this->nextClaim();
            }
        }
        self::assertSame('done', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        self::assertSame(['90071992547409.1234','1.2345'], $this->pdo->query('SELECT detail_amount FROM billing_details ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(['COP','COP'], $this->pdo->query('SELECT currency_id FROM billing_details ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        $this->assertCalls(['billing.period.details','billing.period.details','billing.period.details']);
        self::assertSame(['0','101','102'], array_column(array_column($this->trace(), 'query'), 'from_id'));
    }

    public function testBillingPartialHeaderMakesOneGetAndDefersWithoutFactsOrCursor(): void
    {
        $this->domain('BILLING_PARTIAL_HEADER');
        $claim = $this->billingClaim();
        self::assertFalse($this->billing($claim));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame('pending', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        self::assertSame('meli_billing_partial', $this->pdo->query('SELECT last_error_code FROM work_items')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        $this->assertCalls(['billing.period.details']);
    }

    public function testBilling429MakesOneGetAndCooldownFollowupDispatchesZero(): void
    {
        $this->domain('FIRST_CALL_429');
        $claim = $this->billingClaim();
        $before = new DateTimeImmutable('now UTC');
        self::assertFalse($this->billing($claim));
        $deadline = new DateTimeImmutable((string) $this->pdo->query('SELECT blocked_until FROM meli_cooldowns')->fetchColumn(), new \DateTimeZone('UTC'));
        self::assertGreaterThanOrEqual($before->modify('+60 seconds')->getTimestamp(), $deadline->getTimestamp());
        $this->assertCalls(['billing.period.details']);
        self::assertSame(429, $this->trace()[0]['status']);
        $this->eligible($claim['id']);
        $followup = $this->nextClaim();
        self::assertSame($claim['id'], $followup['id']);
        self::assertFalse($this->billing($followup));
        $this->assertCalls(['billing.period.details']);
        self::assertSame('pending', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT attempts FROM work_items')->fetchColumn());
    }

    public function testDisabledSessionProducesZeroSimulatorCalls(): void
    {
        $this->domain('ORDER_HAPPY_EXACT');
        $user = $this->pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES(1,'synthetic@example.test',?,'active')");
        $user->execute([(new PasswordService())->hash('synthetic-password')]);
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        self::assertTrue((new AuthService($this->pdo))->login('synthetic@example.test', 'synthetic-password'));
        $this->pdo->exec("UPDATE users SET status='disabled' WHERE id=1");
        $before = $this->trace();
        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales'));
        self::assertSame(403, $response->getStatusCode());
        self::assertSame($before, $this->trace());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        $this->assertCalls([]);
    }

    public function testStaleRepairClaimProducesZeroSimulatorCalls(): void
    {
        $this->domain('AUDIT_REPAIR_1');
        [$audit, $run, $claim] = $this->auditClaim();
        $audit->acceptRemoteTotal($run, 1, 1, 1);
        $audit->recordObservation($run, '9007199254740993', new DateTimeImmutable('2026-09-10T12:00:00Z'));
        $audit->persistCanonicalFingerprint($run, 1, 1, SalesAuditWindow::forSitePeriod('MCO', '2026-09-01'));
        self::assertSame('repairing', $audit->advanceCapturedRun($run, 1, 1));
        $beforeWork = $this->pdo->query('SELECT * FROM work_items')->fetchAll();
        $beforeRun = $this->pdo->query('SELECT * FROM sales_audit_runs')->fetchAll();
        $beforeTrace = $this->trace();
        self::assertFalse((new SalesAuditRepairHandler($audit, $this->work))->processCurrentClaim(
            $claim['id'], str_repeat('0',32), $run, 1, 1, $this->now()));
        self::assertSame($beforeWork, $this->pdo->query('SELECT * FROM work_items')->fetchAll());
        self::assertSame($beforeRun, $this->pdo->query('SELECT * FROM sales_audit_runs')->fetchAll());
        self::assertSame($beforeTrace, $this->trace());
        self::assertSame(0, (int) $this->pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn());
        $this->assertCalls([]);
    }

    public function testFinancialReadModelProducesZeroSimulatorCalls(): void
    {
        $this->domain('ORDER_HAPPY_EXACT');
        $this->pdo->exec("INSERT INTO orders(id,company_id,account_id,external_order_id,status,date_created,total_amount,currency_id) VALUES(1,1,1,'501','paid','2026-09-10','42.0000','COP')");
        $this->pdo->exec("INSERT INTO order_items(order_id,external_item_id,title,quantity,unit_price,sale_fee,currency_id) VALUES(1,'SYNTHETIC','Synthetic',2,21,1.2345,'COP')");
        $before = $this->trace();
        $result = (new FinancialReadModel($this->pdo))->forOrder(1, 1, '501');
        self::assertSame('KNOWN', $result['coverage']['sale_fee']);
        self::assertSame('2.46900000', $result['operational']['sale_fee_total']);
        self::assertSame('UNKNOWN', $result['coverage']['billing_identity']);
        self::assertSame('COP', $result['currency']);
        self::assertSame($before, $this->trace());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        $this->assertCalls([]);
    }

    public function testSimulatorScopeIsExactlyEightScenariosAndThreeConsumedOperations(): void
    {
        $scenarios = json_decode((string) file_get_contents(dirname(__DIR__) . '/Support/MeliSim/scenarios.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['ORDER_HAPPY_EXACT','ORDER_PARTIAL_HEADER','INVALID_JSON','AUDIT_REPAIR_1',
            'AUDIT_PARTIAL_206','BILLING_TWO_PAGES','BILLING_PARTIAL_HEADER','FIRST_CALL_429'], array_keys($scenarios));
        $operations = [];
        foreach ($scenarios as $steps) {
            foreach ($steps as $step) { $operations[] = $step['operation']; }
        }
        $operations = array_values(array_unique($operations));
        sort($operations);
        self::assertSame(['billing.period.details','orders.get','orders.search'], $operations);
    }

    /** @return array{SalesAuditRepository,int,array<string,mixed>} */
    private function auditClaim(): array
    {
        $audit = new SalesAuditRepository($this->pdo);
        $run = $audit->createCapturingRun(1, 1, '2026-09-01', SalesAuditRepository::CONTRACT_VERSION, $this->now());
        return [$audit, $run, $this->enqueueClaim('sales.audit', ['run_id'=>$run,'offset'=>0,'limit'=>50])];
    }

    /** @param array<string,mixed> $claim */
    private function capture(SalesAuditRepository $audit, array $claim, string $pass = 'A'): bool
    {
        return (new SalesAuditHandler($this->work, $audit, $this->client, $this->tokens))->processCurrentClaim(
            $claim['id'], $claim['claim_token'], 1, 1, $claim['payload'], $this->now(), $pass);
    }

    /** @return array<string,mixed> */
    private function billingClaim(): array
    {
        return $this->enqueueClaim('billing.period.sync', ['period_key'=>'2026-09-01','document_type'=>'BILL','from_id'=>'0']);
    }

    /** @param array<string,mixed> $claim */
    private function billing(array $claim): bool
    {
        return (new BillingPeriodSyncHandler($this->work, $this->client, $this->tokens, new BillingDetailNormalizer()))->syncCurrentClaim(
            $claim['id'], $claim['claim_token'], 1, 1, $claim['payload']['period_key'], $claim['payload']['document_type'],
            $claim['payload']['from_id'], $this->now());
    }

    private function eligible(int $id, string $date = '2000-01-01'): void
    {
        $update = $this->pdo->prepare('UPDATE work_items SET available_at=? WHERE id=?');
        $update->execute([$date, $id]);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-12T00:00:00Z');
    }

    private function domain(string $scenario): void
    {
        $config = TestDatabase::config();
        if ($config->dbHost !== '127.0.0.1' || $config->dbName !== 'erp_meli2_test' || $config->appEnv !== 'test') {
            throw new RuntimeException('MLSIM_DISPOSABLE_LOCAL_DATABASE_ONLY');
        }
        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Synthetic','mlsim-synthetic')");
        $this->pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES(1,1,'100','MCO','connected')");
        $cipher = new TokenCipher($config->appKey);
        $insert = $this->pdo->prepare('INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at) VALUES(1,?,?,?)');
        $insert->execute([$cipher->encrypt('synthetic-access'),$cipher->encrypt('unused-synthetic-refresh'),'2099-01-01 00:00:00']);
        $this->startServer($scenario);
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $this->client = new MeliClient($this->transport(), new SystemSettingsRepository($this->pdo), $operations,
            $this->baseUrl, cooldowns: new MeliCooldownRepository($this->pdo), minRequestIntervalMs: 0);
        $this->tokens = new OAuthRefreshService($this->pdo, Connection::fromConfig($config), $this->client, $cipher,
            'synthetic-client', 'synthetic-secret', 'mlsim.oauth');
        $this->work = new WorkRepository($this->pdo);
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private function enqueueClaim(string $type, array $payload): array
    {
        $this->work->enqueue(1, 1, 'company:1:account:1', $type, null, 'mlsim:' . $type, $payload, new DateTimeImmutable('2000-01-01Z'));
        return $this->nextClaim();
    }

    /** @return array<string,mixed> */
    private function nextClaim(): array
    {
        $claim = $this->work->claimNext();
        self::assertIsArray($claim);
        return $claim;
    }

    /** @param array<string,mixed> $claim */
    private function syncOrder(array $claim): bool
    {
        return (new SyncOrderHandler($this->work, $this->client, $this->tokens))->syncCurrentClaim(
            $claim['id'], $claim['claim_token'], 1, 1, $claim['payload']['order_id'], new DateTimeImmutable('2026-10-12T00:00:00Z'));
    }

    /** @param list<string> $operations */
    private function assertCalls(array $operations): void
    {
        $trace = $this->trace();
        self::assertCount(count($operations), $trace);
        self::assertSame($operations, array_column($trace, 'operation'));
        self::assertSame(array_fill(0, count($operations), 'GET'), array_column($trace, 'method'));
        self::assertSame(array_fill(0, count($operations), true), array_column($trace, 'matched'));
        if ($operations !== []) {
            self::assertSame(range(1, count($operations)), array_column($trace, 'seq'));
        }
    }

    private function startServer(string $scenario, string $host = '127.0.0.1'): void
    {
        if ($host !== '127.0.0.1' || getenv('APP_ENV') !== 'test') {
            throw new RuntimeException('MLSIM_LITERAL_LOOPBACK_TEST_ONLY');
        }
        $router = dirname(__DIR__) . '/Support/MeliSim/router.php';
        self::assertFileExists($router);
        $this->directory = sys_get_temp_dir() . '/erp-meli2-mlsim-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
        file_put_contents($this->directory . '/requests.jsonl', '');
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            self::assertIsResource($reservation);
            $address = stream_socket_get_name($reservation, false);
            self::assertIsString($address);
            fclose($reservation);
            $this->baseUrl = 'http://' . $address;
            if (parse_url($this->baseUrl, PHP_URL_HOST) !== '127.0.0.1') {
                throw new RuntimeException('MLSIM_BASE_URL_REJECTED');
            }
            $this->server = proc_open([PHP_BINARY, '-S', $address, $router],
                [0=>['pipe','r'],1=>['file',$this->directory.'/stdout.txt','w'],2=>['file',$this->directory.'/stderr.txt','w']],
                $pipes, dirname(__DIR__, 2), array_merge(array_filter([
                    'SystemRoot'=>getenv('SystemRoot'), 'WINDIR'=>getenv('WINDIR'),
                    'TEMP'=>sys_get_temp_dir(), 'TMP'=>sys_get_temp_dir(),
                ], static fn($value): bool => is_string($value)),
                    ['APP_ENV'=>'test','MLSIM_SCENARIO'=>$scenario,'MLSIM_ROOT'=>$this->directory]),
                ['bypass_shell'=>true]);
            self::assertIsResource($this->server);
            fclose($pipes[0]);
            $deadline = hrtime(true) + 3_000_000_000;
            do {
                if (!proc_get_status($this->server)['running']) { break; }
                $socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
                if (is_resource($socket)) {
                    fclose($socket);
                    $stderr = (string) file_get_contents($this->directory . '/stderr.txt');
                    if (proc_get_status($this->server)['running'] && str_contains($stderr, $this->baseUrl) && str_contains($stderr, 'started')) {
                        return;
                    }
                }
                usleep(10_000); // Startup readiness only; never used to advance Work or cooldown.
            } while (hrtime(true) < $deadline);
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        throw new RuntimeException('MLSIM_OWNED_SERVER_START_FAILED: ' . file_get_contents($this->directory . '/stderr.txt'));
    }

    private function stopServer(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        if ($this->directory !== null) {
            $parent = realpath(dirname($this->directory));
            if ($parent !== realpath(sys_get_temp_dir()) || !preg_match('/^erp-meli2-mlsim-[a-f0-9]{16}$/D', basename($this->directory)) || is_link($this->directory)) {
                throw new RuntimeException('MLSIM_UNSAFE_CLEANUP');
            }
            foreach (['requests.jsonl','stdout.txt','stderr.txt'] as $name) {
                $path = $this->directory . '/' . $name;
                if (is_file($path)) { unlink($path); }
            }
            rmdir($this->directory);
            $this->directory = null;
        }
    }

    /** @return list<array<string,mixed>> */
    private function trace(): array
    {
        if ($this->directory === null) { return []; }
        $lines = file($this->directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        return array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
    }

    private function transport(): CurlMeliTransport
    {
        return new CurlMeliTransport(new RemoteHostPolicy(), 'test');
    }
}
