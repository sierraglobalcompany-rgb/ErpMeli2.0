<?php

declare(strict_types=1);

namespace Tests\Integration;

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
use Tests\Support\TestDatabase;

final class SalesAuditContinuationTest extends TestCase
{
    public function testNonTerminalPageStoresStableTotalAndEnqueuesExactlyOneContinuation(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-continuation-secret');
        $this->seedAccountAndToken($pdo, $cipher);

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        $work = new WorkRepository($pdo);
        $workId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':0:2',
            ['run_id' => $runId, 'offset' => 0, 'limit' => 2],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $transport = new SalesAuditContinuationTransport(new MeliTransportResponse(
            200,
            [],
            '{"paging":{"total":3,"offset":0,"limit":2},"results":['
            . '{"id":200000000001,"date_created":"2026-10-10T10:00:00-05:00"},'
            . '{"id":200000000002,"date_created":"2026-10-10T11:00:00-05:00"}'
            . ']}',
        ));
        $handler = $this->handler($pdo, $work, $audit, $transport, $cipher);

        self::assertTrue($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        self::assertSame(
            '3',
            (string) $pdo->query('SELECT remote_total FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
        self::assertSame(
            'done',
            $pdo->query('SELECT status FROM work_items WHERE id = ' . $workId)->fetchColumn(),
        );

        $statement = $pdo->prepare(
            "SELECT id,status,resource_key,payload_json FROM work_items WHERE type='sales.audit' ORDER BY id"
        );
        $statement->execute();
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $rows);
        self::assertSame((string) $runId, (string) $rows[1]['resource_key']);
        self::assertSame('pending', $rows[1]['status']);

        $payload = json_decode((string) $rows[1]['payload_json'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['run_id' => $runId, 'offset' => 2, 'limit' => 2], $payload);
        self::assertSame(
            2,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id = ' . $runId)->fetchColumn(),
        );
        self::assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
        );
    }

    public function testRemoteTotalDriftFailsCurrentPageWithoutPartialEvidenceOrContinuation(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-drift-secret');
        $this->seedAccountAndToken($pdo, $cipher);

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 3));

        $work = new WorkRepository($pdo);
        $workId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':2:2',
            ['run_id' => $runId, 'offset' => 2, 'limit' => 2],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $transport = new SalesAuditContinuationTransport(new MeliTransportResponse(
            200,
            [],
            '{"paging":{"total":4,"offset":2,"limit":2},"results":['
            . '{"id":200000000003,"date_created":"2026-10-20T10:00:00-05:00"},'
            . '{"id":200000000004,"date_created":"2026-10-21T11:00:00-05:00"}'
            . ']}',
        ));
        $handler = $this->handler($pdo, $work, $audit, $transport, $cipher);

        self::assertFalse($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        $row = $pdo->query(
            'SELECT status,last_error_code FROM work_items WHERE id = ' . $workId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertSame('meli_sales_audit_contract', $row['last_error_code']);
        self::assertSame(
            '3',
            (string) $pdo->query('SELECT remote_total FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
        self::assertSame(
            0,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id = ' . $runId)->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn(),
        );
    }

    public function testTerminalPageWithCompleteDurableEvidenceEnqueuesRepairContinuationWhenLocalOrdersAreMissing(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-terminal-complete-secret');
        $this->seedAccountAndToken($pdo, $cipher);

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 3));
        self::assertTrue($audit->recordObservation(
            $runId,
            '200000000001',
            new DateTimeImmutable('2026-10-10T10:00:00-05:00'),
        ));
        self::assertTrue($audit->recordObservation(
            $runId,
            '200000000002',
            new DateTimeImmutable('2026-10-10T11:00:00-05:00'),
        ));

        $work = new WorkRepository($pdo);
        $workId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':2:2',
            ['run_id' => $runId, 'offset' => 2, 'limit' => 2],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $transport = new SalesAuditContinuationTransport(new MeliTransportResponse(
            200,
            [],
            '{"paging":{"total":3,"offset":2,"limit":2},"results":['
            . '{"id":200000000003,"date_created":"2026-10-20T10:00:00-05:00"}'
            . ']}',
        ));
        $handler = $this->handler($pdo, $work, $audit, $transport, $cipher);

        self::assertTrue($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id = ' . $workId)->fetchColumn());
        self::assertSame(
            3,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id = ' . $runId)->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit' AND status='pending'")->fetchColumn(),
        );
        self::assertSame(
            'repairing',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
    }

    public function testTerminalPageWithIncompleteDurableEvidenceRollsBackAndFailsClosed(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-terminal-incomplete-secret');
        $this->seedAccountAndToken($pdo, $cipher);

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 3));
        self::assertTrue($audit->recordObservation(
            $runId,
            '200000000001',
            new DateTimeImmutable('2026-10-10T10:00:00-05:00'),
        ));

        $work = new WorkRepository($pdo);
        $workId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':2:2',
            ['run_id' => $runId, 'offset' => 2, 'limit' => 2],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $transport = new SalesAuditContinuationTransport(new MeliTransportResponse(
            200,
            [],
            '{"paging":{"total":3,"offset":2,"limit":2},"results":['
            . '{"id":200000000003,"date_created":"2026-10-20T10:00:00-05:00"}'
            . ']}',
        ));
        $handler = $this->handler($pdo, $work, $audit, $transport, $cipher);

        self::assertFalse($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        $row = $pdo->query(
            'SELECT status,last_error_code FROM work_items WHERE id = ' . $workId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertSame('meli_sales_audit_contract', $row['last_error_code']);
        self::assertSame(
            1,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id = ' . $runId)->fetchColumn(),
            'The terminal page observation must roll back when durable evidence is incomplete.',
        );
        self::assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit' AND status='pending'")->fetchColumn(),
        );
        self::assertSame(
            'capturing',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
    }

    private function handler(
        PDO $pdo,
        WorkRepository $work,
        SalesAuditRepository $audit,
        MeliTransport $transport,
        TokenCipher $cipher,
    ): SalesAuditHandler {
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string,preserve_numbers?:bool}> $operations */
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
            $pdo,
            $client,
            $cipher,
            'client-id',
            'client-secret',
            'erp2.oauth.sales-audit-continuation',
        );

        return new SalesAuditHandler($work, $audit, $client, $tokens);
    }

    private function seedAccountAndToken(PDO $pdo, TokenCipher $cipher): void
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (1,1,'99887766','MCO','Seller','connected')"
        );
        $statement = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (1,:access_token,:refresh_token,:expires_at,0)'
        );
        $statement->execute([
            'access_token' => $cipher->encrypt('valid-access-token'),
            'refresh_token' => $cipher->encrypt('unused-refresh-token'),
            'expires_at' => '2030-01-01 00:00:00.000000',
        ]);
    }
}

final class SalesAuditContinuationTransport implements MeliTransport
{
    public function __construct(private readonly MeliTransportResponse $response)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        return $this->response;
    }
}
