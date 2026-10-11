<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SyncOrderHandlerTokenFailureTest extends TestCase
{
    public function testOAuthPreflightDatabaseFailureEscapesWithoutBusinessMutationOrHttp(): void
    {
        $fixture = $this->expiredConnectedFixture([]);
        $item = $this->enqueueOrder($fixture['work'], $fixture['company_id'], $fixture['account_id'], '200000000008');
        $fixture['pdo']->exec('DROP TABLE meli_tokens');

        $failure = null;
        try {
            $fixture['handler']->syncCurrentClaim(
                $item['claim']['id'],
                $item['claim']['claim_token'],
                $fixture['company_id'],
                $fixture['account_id'],
                '200000000008',
                new DateTimeImmutable('2026-10-09T18:00:00+00:00'),
            );
        } catch (PDOException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(PDOException::class, $failure);
        self::assertCount(0, $fixture['transport']->requests);
        $row = $fixture['pdo']->query(
            'SELECT status,attempts,claim_token,last_error_code FROM work_items WHERE id=' . $item['id']
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('running', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNotNull($row['claim_token']);
        self::assertNull($row['last_error_code']);
        self::assertSame(
            'connected',
            $fixture['pdo']->query('SELECT status FROM meli_accounts WHERE id=' . $fixture['account_id'])->fetchColumn(),
        );
    }

    public function testRefreshRateLimitDefersWorkOnceAndKeepsAccountConnected(): void
    {
        $fixture = $this->expiredConnectedFixture([
            new MeliTransportResponse(429, ['retry-after' => '60'], '{"error":"too_many_requests"}'),
        ]);
        $item = $this->enqueueOrder($fixture['work'], $fixture['company_id'], $fixture['account_id'], '200000000007');

        self::assertFalse($fixture['handler']->syncCurrentClaim(
            $item['claim']['id'],
            $item['claim']['claim_token'],
            $fixture['company_id'],
            $fixture['account_id'],
            '200000000007',
            new DateTimeImmutable('2026-10-09T18:00:00+00:00'),
        ));

        $row = $fixture['pdo']->query(
            'SELECT status,attempts,available_at,last_error_code FROM work_items WHERE id=' . $item['id']
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertSame('meli_rate_limited', $row['last_error_code']);
        self::assertSame(
            $fixture['pdo']->query('SELECT blocked_until FROM meli_cooldowns LIMIT 1')->fetchColumn(),
            $row['available_at'],
        );
        self::assertCount(1, $fixture['transport']->requests);
        self::assertStringEndsWith('/oauth/token', $fixture['transport']->requests[0]['url']);
        self::assertSame(
            'connected',
            $fixture['pdo']->query('SELECT status FROM meli_accounts WHERE id=' . $fixture['account_id'])->fetchColumn(),
        );
        self::assertSame(
            0,
            (int) $fixture['pdo']->query('SELECT refresh_version FROM meli_tokens WHERE account_id=' . $fixture['account_id'])->fetchColumn(),
        );
    }

    public function testUncertainRefreshServerErrorMarksAttentionAndStopsNextWorkBeforeHttp(): void
    {
        $fixture = $this->expiredConnectedFixture([
            new MeliTransportResponse(500, [], '{"error":"service_unavailable"}'),
            new MeliTransportResponse(500, [], '{"error":"must_not_repeat"}'),
        ]);
        $first = $this->enqueueOrder($fixture['work'], $fixture['company_id'], $fixture['account_id'], '200000000005');

        self::assertFalse($fixture['handler']->syncCurrentClaim(
            $first['claim']['id'],
            $first['claim']['claim_token'],
            $fixture['company_id'],
            $fixture['account_id'],
            '200000000005',
            new DateTimeImmutable('2026-10-09T18:00:00+00:00'),
        ));
        $second = $this->enqueueOrder($fixture['work'], $fixture['company_id'], $fixture['account_id'], '200000000006');
        self::assertFalse($fixture['handler']->syncCurrentClaim(
            $second['claim']['id'],
            $second['claim']['claim_token'],
            $fixture['company_id'],
            $fixture['account_id'],
            '200000000006',
            new DateTimeImmutable('2026-10-09T18:00:00+00:00'),
        ));

        self::assertCount(1, $fixture['transport']->requests, 'The second Work must not repeat the one-use refresh POST.');
        self::assertStringEndsWith('/oauth/token', $fixture['transport']->requests[0]['url']);
        self::assertSame(
            'attention',
            $fixture['pdo']->query('SELECT status FROM meli_accounts WHERE id=' . $fixture['account_id'])->fetchColumn(),
        );
        self::assertSame(
            0,
            (int) $fixture['pdo']->query('SELECT refresh_version FROM meli_tokens WHERE account_id=' . $fixture['account_id'])->fetchColumn(),
        );
    }

    public function testAmbiguousRefreshTransportOutcomeStopsNextWorkBeforeHttp(): void
    {
        $fixture = $this->expiredConnectedFixture([
            new RuntimeException('simulated ambiguous refresh response loss'),
        ]);
        $first = $this->enqueueOrder($fixture['work'], $fixture['company_id'], $fixture['account_id'], '200000000009');

        self::assertFalse($fixture['handler']->syncCurrentClaim(
            $first['claim']['id'],
            $first['claim']['claim_token'],
            $fixture['company_id'],
            $fixture['account_id'],
            '200000000009',
            new DateTimeImmutable('2026-10-09T18:00:00+00:00'),
        ));
        $second = $this->enqueueOrder($fixture['work'], $fixture['company_id'], $fixture['account_id'], '200000000010');
        self::assertFalse($fixture['handler']->syncCurrentClaim(
            $second['claim']['id'],
            $second['claim']['claim_token'],
            $fixture['company_id'],
            $fixture['account_id'],
            '200000000010',
            new DateTimeImmutable('2026-10-09T18:00:00+00:00'),
        ));

        self::assertCount(1, $fixture['transport']->requests);
        self::assertStringEndsWith('/oauth/token', $fixture['transport']->requests[0]['url']);
        self::assertSame(
            'attention',
            $fixture['pdo']->query('SELECT status FROM meli_accounts WHERE id=' . $fixture['account_id'])->fetchColumn(),
        );
    }

    private function expiredConnectedFixture(array $responses): array
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sync-order-expired-fixture-key');
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Expired Token Company','expired-token-company')");
        $companyId = (int) $pdo->lastInsertId();
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (:company_id,'700000005','MCO','Expired Seller','connected')"
        );
        $account->execute(['company_id' => $companyId]);
        $accountId = (int) $pdo->lastInsertId();
        $token = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (:account_id,:access_token,:refresh_token,:expires_at,0)'
        );
        $token->execute([
            'account_id' => $accountId,
            'access_token' => $cipher->encrypt('expired-access'),
            'refresh_token' => $cipher->encrypt('single-use-refresh'),
            'expires_at' => '2026-10-09 17:00:00.000000',
        ]);

        $transport = new QueuedOAuthWorkTransport($responses);
        /** @var array<string,array<string,mixed>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            new ApiUsageRecorder($pdo),
            new MeliCooldownRepository($pdo),
            minRequestIntervalMs: 0,
        );
        $tokens = new OAuthRefreshService(
            $pdo,
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $cipher,
            '123456789',
            'client-secret',
            'erp_meli2.oauth.account',
        );
        $work = new WorkRepository($pdo);

        return [
            'pdo' => $pdo,
            'company_id' => $companyId,
            'account_id' => $accountId,
            'transport' => $transport,
            'work' => $work,
            'handler' => new SyncOrderHandler($work, $client, $tokens),
        ];
    }

    /** @return array{id:int,claim:array{id:int,claim_token:string}} */
    private function enqueueOrder(WorkRepository $work, int $companyId, int $accountId, string $orderId): array
    {
        $workId = $work->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            $orderId,
            'order.sync:' . $orderId,
            ['order_id' => $orderId],
            new DateTimeImmutable('2026-10-09T18:00:00+00:00'),
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        return ['id' => $workId, 'claim' => ['id' => $claim['id'], 'claim_token' => $claim['claim_token']]];
    }

    public function testBusyRefreshLockDefersWorkWithoutConsumingAttemptOrChangingAccount(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sync-order-busy-lock-key');
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Busy Lock Company','busy-lock-company')");
        $companyId = (int) $pdo->lastInsertId();
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (:company_id,'700000004','MCO','Busy Seller','connected')"
        );
        $account->execute(['company_id' => $companyId]);
        $accountId = (int) $pdo->lastInsertId();
        $token = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (:account_id,:access_token,:refresh_token,:expires_at,0)'
        );
        $token->execute([
            'account_id' => $accountId,
            'access_token' => $cipher->encrypt('expired-access'),
            'refresh_token' => $cipher->encrypt('unused-refresh'),
            'expires_at' => '2026-10-11 17:00:00.000000',
        ]);

        $work = new WorkRepository($pdo);
        $workId = $work->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            '200000000004',
            'order.sync:200000000004',
            ['order_id' => '200000000004'],
            new DateTimeImmutable('2026-10-11T18:00:00+00:00'),
        );
        $claim = $work->claimNext(new DateTimeImmutable('2026-10-11T18:00:00+00:00'));
        self::assertIsArray($claim);

        $lockOwner = Connection::fromConfig(TestDatabase::config());
        $lockName = TestDatabase::config()->dbName . '.erp_meli2.oauth.account.' . $accountId;
        $lock = $lockOwner->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $lock->execute(['lock_name' => $lockName]);
        self::assertSame(1, (int) $lock->fetchColumn());

        $transport = new NoOrderHttpTransport();
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
        $tokens = new OAuthRefreshService(
            $pdo,
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $cipher,
            '123456789',
            'client-secret',
            'erp_meli2.oauth.account',
        );
        $handler = new SyncOrderHandler($work, $client, $tokens);

        try {
            self::assertFalse($handler->syncCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                $companyId,
                $accountId,
                '200000000004',
                new DateTimeImmutable('2026-10-11T18:00:00+00:00'),
            ));
        } finally {
            $release = $lockOwner->prepare('SELECT RELEASE_LOCK(:lock_name)');
            $release->execute(['lock_name' => $lockName]);
        }

        self::assertCount(0, $transport->requests);
        $row = $pdo->query(
            'SELECT status, attempts, claim_token, last_error_code FROM work_items WHERE id=' . $workId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertSame('meli_oauth_busy', $row['last_error_code']);
        self::assertSame('connected', $pdo->query('SELECT status FROM meli_accounts WHERE id=' . $accountId)->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=' . $accountId)->fetchColumn());
    }

    public function testAttentionAccountFailsCurrentWorkWithoutThrowingOrCallingMeli(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sync-order-token-failure-key');
        [$companyId, $accountId] = $this->seedAttentionAccount($pdo, $cipher);
        $transport = new NoOrderHttpTransport();

        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
        $tokens = new OAuthRefreshService(
            $pdo,
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $cipher,
            '123456789',
            'client-secret',
            'erp_meli2.oauth.account',
        );
        $work = new WorkRepository($pdo);
        $handler = new SyncOrderHandler($work, $client, $tokens);

        $work->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            '200000000002',
            'order.sync:200000000002',
            ['order_id' => '200000000002'],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        try {
            $completed = $handler->syncCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                $companyId,
                $accountId,
                '200000000002',
                new DateTimeImmutable('2026-10-09T01:35:00+00:00'),
            );
        } catch (RuntimeException $exception) {
            self::fail('OAuth attention must become a terminal work outcome, not escape the handler: ' . $exception->getMessage());
        }

        self::assertFalse($completed);
        self::assertCount(0, $transport->requests, 'An attention account must not perform OAuth or order HTTP.');

        $row = $pdo->query(
            'SELECT status, claim_token, last_error_code FROM work_items WHERE id=' . (int) $claim['id']
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertNull($row['claim_token']);
        self::assertSame('meli_oauth_attention', $row['last_error_code']);
    }

    /** @return array{0:int,1:int} */
    private function seedAttentionAccount(PDO $pdo, TokenCipher $cipher): array
    {
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Order Token Guard','order-token-guard')");
        $companyId = (int) $pdo->lastInsertId();

        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (:company_id,'700000003','MCO','Attention Seller','attention')"
        );
        $account->execute(['company_id' => $companyId]);
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (:account_id,:access_token,:refresh_token,:expires_at,0)'
        );
        $tokens->execute([
            'account_id' => $accountId,
            'access_token' => $cipher->encrypt('expired-access'),
            'refresh_token' => $cipher->encrypt('must-not-reuse-refresh'),
            'expires_at' => '2026-10-09 01:00:00.000000',
        ]);

        return [$companyId, $accountId];
    }
}

final class NoOrderHttpTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        throw new RuntimeException('HTTP must not be reached for an attention account.');
    }
}

final class QueuedOAuthWorkTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse|RuntimeException> $responses */
    public function __construct(private array $responses)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $response = array_shift($this->responses);
        if ($response instanceof RuntimeException) {
            throw $response;
        }
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('No OAuth Work response queued.');
        }

        return $response;
    }
}
