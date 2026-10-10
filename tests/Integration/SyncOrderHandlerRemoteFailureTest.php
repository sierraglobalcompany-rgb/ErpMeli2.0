<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SyncOrderHandlerRemoteFailureTest extends TestCase
{
    public function testUnauthorizedOrderRefreshesTokenAndRetriesSafeGetExactlyOnce(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-failure-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new RemoteOutcomeTransport([
            new MeliTransportResponse(401, ['x-request-id' => 'order-401'], '{"error":"unauthorized"}'),
            new MeliTransportResponse(200, [], '{"access_token":"new-access","refresh_token":"new-refresh","expires_in":21600}'),
            $this->validOrderResponse(),
        ]);
        [$handler, $work] = $this->handler($pdo, $cipher, $transport);
        $claim = $this->claim($work, $companyId, $accountId);

        $completed = $handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T01:20:00+00:00'),
        );

        self::assertTrue($completed);
        self::assertCount(3, $transport->requests);
        self::assertSame('https://api.mercadolibre.com/orders/200000000001', $transport->requests[0]['url']);
        self::assertSame('Bearer old-access', $transport->requests[0]['headers']['Authorization']);
        self::assertSame('https://api.mercadolibre.com/oauth/token', $transport->requests[1]['url']);
        self::assertSame('https://api.mercadolibre.com/orders/200000000001', $transport->requests[2]['url']);
        self::assertSame('Bearer new-access', $transport->requests[2]['headers']['Authorization']);
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=' . $accountId)->fetchColumn());
    }

    public function testRateLimitReturnsCurrentClaimToPendingWithoutConsumingAttempt(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-failure-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new RemoteOutcomeTransport([
            new MeliTransportResponse(429, ['retry-after' => '5'], '{"error":"too_many_requests"}'),
        ]);
        [$handler, $work] = $this->handler($pdo, $cipher, $transport, true);
        $claim = $this->claim($work, $companyId, $accountId);
        $before = new DateTimeImmutable('now');

        self::assertSame(1, $claim['attempts']);
        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T01:20:00+00:00'),
        ));

        $row = $pdo->query('SELECT status, attempts, available_at, claim_token, last_error_code FROM work_items WHERE id=' . $claim['id'])->fetch();
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertSame('meli_rate_limited', $row['last_error_code']);
        self::assertGreaterThan($before, new DateTimeImmutable((string) $row['available_at']));
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
    }

    public function testServerErrorSchedulesBoundedRetryAndConsumesAttempt(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-failure-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new RemoteOutcomeTransport([
            new MeliTransportResponse(503, [], '{"error":"service_unavailable"}'),
        ]);
        [$handler, $work] = $this->handler($pdo, $cipher, $transport);
        $claim = $this->claim($work, $companyId, $accountId);
        $now = new DateTimeImmutable('2026-10-09T01:20:00+00:00');

        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            $now,
        ));

        $row = $pdo->query('SELECT status, attempts, available_at, last_error_code FROM work_items WHERE id=' . $claim['id'])->fetch();
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertSame('meli_remote_retry', $row['last_error_code']);
        self::assertSame('2026-10-09 01:20:30.000000', $row['available_at']);
    }

    public function testTransportFailureSchedulesSameBoundedRetry(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-failure-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new RemoteOutcomeTransport([
            new RuntimeException('simulated transport timeout'),
        ]);
        [$handler, $work] = $this->handler($pdo, $cipher, $transport);
        $claim = $this->claim($work, $companyId, $accountId);

        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T01:20:00+00:00'),
        ));

        $row = $pdo->query('SELECT status, attempts, available_at, last_error_code FROM work_items WHERE id=' . $claim['id'])->fetch();
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertSame('meli_remote_retry', $row['last_error_code']);
        self::assertSame('2026-10-09 01:20:30.000000', $row['available_at']);
    }

    public function testSuccessfulHttpWithMalformedOrderContractFailsTerminally(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-failure-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new RemoteOutcomeTransport([
            new MeliTransportResponse(200, [], '{"id":200000000001,"status":"paid"}'),
        ]);
        [$handler, $work] = $this->handler($pdo, $cipher, $transport);
        $claim = $this->claim($work, $companyId, $accountId);

        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T01:20:00+00:00'),
        ));

        $row = $pdo->query('SELECT status, last_error_code, claim_token FROM work_items WHERE id=' . $claim['id'])->fetch();
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertSame('meli_order_contract', $row['last_error_code']);
        self::assertNull($row['claim_token']);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
    }

    /** @return array{0:SyncOrderHandler,1:WorkRepository} */
    private function handler(PDO $pdo, TokenCipher $cipher, RemoteOutcomeTransport $transport, bool $withCooldown = false): array
    {
        /** @var array<string,array<string,mixed>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $settings = new SystemSettingsRepository($pdo);
        $client = new MeliClient(
            $transport,
            $settings,
            $operations,
            'https://api.mercadolibre.com',
            cooldowns: $withCooldown ? new MeliCooldownRepository($pdo) : null,
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

        return [new SyncOrderHandler($work, $client, $tokens), $work];
    }

    /** @return array{id:int,claim_token:string,attempts:int} */
    private function claim(WorkRepository $work, int $companyId, int $accountId): array
    {
        $work->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            '200000000001',
            'order.sync:200000000001',
            ['order_id' => '200000000001'],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        return [
            'id' => $claim['id'],
            'claim_token' => $claim['claim_token'],
            'attempts' => $claim['attempts'],
        ];
    }

    /** @return array{0:int,1:int} */
    private function seedAccountAndToken(PDO $pdo, TokenCipher $cipher): array
    {
        $slug = 'sync-failure-' . bin2hex(random_bytes(4));
        $company = $pdo->prepare('INSERT INTO companies (name, slug) VALUES (?, ?)');
        $company->execute(['Sync Failure Test Co', $slug]);
        $companyId = (int) $pdo->lastInsertId();

        $account = $pdo->prepare("INSERT INTO meli_accounts (company_id, external_user_id, site_id, status) VALUES (?, '700000002', 'MCO', 'connected')");
        $account->execute([$companyId]);
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens (account_id, access_token_cipher, refresh_token_cipher, expires_at) VALUES (?, ?, ?, ?)'
        );
        $tokens->execute([
            $accountId,
            $cipher->encrypt('old-access'),
            $cipher->encrypt('old-refresh'),
            '2026-10-09 06:00:00.000000',
        ]);

        return [$companyId, $accountId];
    }

    private function validOrderResponse(): MeliTransportResponse
    {
        return new MeliTransportResponse(200, [], json_encode([
            'id' => 200000000001,
            'status' => 'paid',
            'status_detail' => null,
            'date_created' => '2026-10-09T01:00:00.000Z',
            'date_closed' => null,
            'last_updated' => '2026-10-09T01:15:00.000Z',
            'total_amount' => 100000,
            'currency_id' => 'COP',
            'buyer' => ['id' => 800000002],
            'order_items' => [[
                'item' => ['id' => 'MCO999999999', 'title' => 'Producto'],
                'quantity' => 1,
                'unit_price' => 100000,
                'currency_id' => 'COP',
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}

final class RemoteOutcomeTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse|RuntimeException> $outcomes */
    public function __construct(private array $outcomes)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $outcome = array_shift($this->outcomes);
        if ($outcome instanceof RuntimeException) {
            throw $outcome;
        }
        if (!$outcome instanceof MeliTransportResponse) {
            throw new RuntimeException('No queued remote outcome.');
        }
        return $outcome;
    }
}
