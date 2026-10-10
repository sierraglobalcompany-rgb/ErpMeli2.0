<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\Audit\SalesAuditHandler;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesAuditUnauthorizedTest extends TestCase
{
    public function testUnauthorizedSearchRefreshesTokenAndRetriesExactlyOnce(): void
    {
        [$pdo, $handler, $claim, $runId, $transport] = $this->fixture([
            new MeliTransportResponse(401, [], '{"error":"unauthorized"}'),
            new MeliTransportResponse(200, [], '{"access_token":"new-access","refresh_token":"new-refresh","expires_in":21600}'),
            new MeliTransportResponse(
                200,
                [],
                '{"paging":{"total":1,"offset":0,"limit":50},"results":['
                . '{"id":200000000001,"date_created":"2026-10-10T10:00:00-05:00"}'
                . ']}',
            ),
        ]);

        self::assertTrue($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        self::assertCount(3, $transport->requests);
        self::assertStringContainsString('/orders/search?', $transport->requests[0]['url']);
        self::assertSame('Bearer old-access', $transport->requests[0]['headers']['Authorization'] ?? null);
        self::assertSame('https://api.mercadolibre.com/oauth/token', $transport->requests[1]['url']);
        self::assertStringContainsString('/orders/search?', $transport->requests[2]['url']);
        self::assertSame('Bearer new-access', $transport->requests[2]['headers']['Authorization'] ?? null);
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame(1, $this->evidenceCount($pdo, $runId));
        self::assertSame(1, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=1')->fetchColumn());
        self::assertSame(2, $this->auditWorkCount($pdo));
    }

    public function testSecondUnauthorizedAfterRefreshFailsTerminallyWithoutEvidence(): void
    {
        [$pdo, $handler, $claim, $runId, $transport] = $this->fixture([
            new MeliTransportResponse(401, [], '{"error":"unauthorized"}'),
            new MeliTransportResponse(200, [], '{"access_token":"new-access","refresh_token":"new-refresh","expires_in":21600}'),
            new MeliTransportResponse(401, [], '{"error":"unauthorized"}'),
        ]);

        self::assertFalse($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        $row = $pdo->query(
            'SELECT status,attempts,last_error_code,claim_token FROM work_items WHERE id=' . $claim['id']
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertSame('meli_unauthorized', $row['last_error_code']);
        self::assertNull($row['claim_token']);
        self::assertCount(3, $transport->requests);
        self::assertSame(0, $this->evidenceCount($pdo, $runId));
        self::assertSame(1, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=1')->fetchColumn());
        self::assertSame(1, $this->auditWorkCount($pdo));
    }

    public function testRateLimitDuringOAuthRefreshDefersWithoutAttemptBurn(): void
    {
        [$pdo, $handler, $claim, $runId, $transport] = $this->fixture([
            new MeliTransportResponse(401, [], '{"error":"unauthorized"}'),
            new MeliTransportResponse(429, ['retry-after' => '5'], '{"error":"too_many_requests"}'),
        ]);
        $before = new DateTimeImmutable('now');

        self::assertFalse($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        $row = $pdo->query(
            'SELECT status,attempts,available_at,last_error_code FROM work_items WHERE id=' . $claim['id']
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertSame('meli_rate_limited', $row['last_error_code']);
        self::assertGreaterThan($before, new DateTimeImmutable((string) $row['available_at']));
        self::assertCount(2, $transport->requests);
        self::assertSame(0, $this->evidenceCount($pdo, $runId));
        self::assertSame(0, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=1')->fetchColumn());
        self::assertSame(1, $this->auditWorkCount($pdo));
    }

    public function testServerErrorAfterRefreshUsesSameBoundedRetry(): void
    {
        [$pdo, $handler, $claim, $runId, $transport] = $this->fixture([
            new MeliTransportResponse(401, [], '{"error":"unauthorized"}'),
            new MeliTransportResponse(200, [], '{"access_token":"new-access","refresh_token":"new-refresh","expires_in":21600}'),
            new MeliTransportResponse(503, [], '{"error":"service_unavailable"}'),
        ]);

        self::assertFalse($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        $row = $pdo->query(
            'SELECT status,attempts,available_at,last_error_code FROM work_items WHERE id=' . $claim['id']
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertSame('meli_remote_retry', $row['last_error_code']);
        self::assertSame('2026-10-10 02:00:30.000000', $row['available_at']);
        self::assertCount(3, $transport->requests);
        self::assertSame(0, $this->evidenceCount($pdo, $runId));
        self::assertSame(1, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=1')->fetchColumn());
        self::assertSame(1, $this->auditWorkCount($pdo));
    }

    public function testPermanentRemoteRejectionFailsTerminallyWithoutRefreshOrEvidence(): void
    {
        [$pdo, $handler, $claim, $runId, $transport] = $this->fixture([
            new MeliTransportResponse(403, [], '{"error":"forbidden"}'),
        ]);

        self::assertFalse($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        $row = $pdo->query(
            'SELECT status,attempts,last_error_code,claim_token FROM work_items WHERE id=' . $claim['id']
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertSame('meli_remote_permanent', $row['last_error_code']);
        self::assertNull($row['claim_token']);
        self::assertCount(1, $transport->requests);
        self::assertSame(0, $this->evidenceCount($pdo, $runId));
        self::assertSame(0, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=1')->fetchColumn());
        self::assertSame(1, $this->auditWorkCount($pdo));
    }

    /**
     * @param list<MeliTransportResponse|RuntimeException> $outcomes
     * @return array{0:PDO,1:SalesAuditHandler,2:array{id:int,claim_token:string,payload:array<string,mixed>},3:int,4:SalesAuditUnauthorizedTransport}
     */
    private function fixture(array $outcomes): array
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-unauthorized-secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (1,1,'99887766','MCO','Seller','connected')"
        );
        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (1,:access_token,:refresh_token,:expires_at,0)'
        );
        $tokens->execute([
            'access_token' => $cipher->encrypt('old-access'),
            'refresh_token' => $cipher->encrypt('old-refresh'),
            'expires_at' => '2030-01-01 00:00:00.000000',
        ]);

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        $work = new WorkRepository($pdo);
        $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':0:50',
            ['run_id' => $runId, 'offset' => 0, 'limit' => 50],
        );
        $claimed = $work->claimNext();
        self::assertIsArray($claimed);
        $claim = [
            'id' => $claimed['id'],
            'claim_token' => $claimed['claim_token'],
            'payload' => $claimed['payload'],
        ];

        $transport = new SalesAuditUnauthorizedTransport($outcomes);
        /** @var array<string,array<string,mixed>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
        $oauth = new OAuthRefreshService(
            $pdo,
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $cipher,
            'client-id',
            'client-secret',
            'erp2.oauth.sales-audit-unauthorized',
        );

        return [$pdo, new SalesAuditHandler($work, $audit, $client, $oauth), $claim, $runId, $transport];
    }

    private function evidenceCount(PDO $pdo, int $runId): int
    {
        return (int) $pdo->query(
            'SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id=' . $runId
        )->fetchColumn();
    }

    private function auditWorkCount(PDO $pdo): int
    {
        return (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn();
    }
}

final class SalesAuditUnauthorizedTransport implements MeliTransport
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
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $outcome = array_shift($this->outcomes);
        if ($outcome instanceof RuntimeException) {
            throw $outcome;
        }
        if (!$outcome instanceof MeliTransportResponse) {
            throw new RuntimeException('No queued transport outcome.');
        }
        return $outcome;
    }
}
