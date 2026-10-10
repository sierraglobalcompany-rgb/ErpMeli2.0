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

final class SalesAuditCaptureHandlerTest extends TestCase
{
    public function testOnePagePersistsObservedEvidenceAndNeverEnqueuesOrderSync(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-capture-secret');
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
        self::assertSame($workId, $claim['id']);

        $transport = new SalesAuditCaptureTransport(new MeliTransportResponse(
            200,
            [],
            '{"paging":{"total":2,"offset":0,"limit":2},"results":['
            . '{"id":9007199254740993,"date_created":"2026-09-30T23:30:00-05:00"},'
            . '{"id":200000000002,"date_created":"2026-10-15T08:45:00-05:00"}'
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

        self::assertCount(1, $transport->requests);
        self::assertStringContainsString('/orders/search?seller=99887766', $transport->requests[0]['url']);
        self::assertStringContainsString('order.date_created.from=2026-10-01T04%3A00%3A00%2B00%3A00', $transport->requests[0]['url']);
        self::assertStringContainsString('order.date_created.to=2026-11-01T06%3A00%3A00%2B00%3A00', $transport->requests[0]['url']);
        self::assertStringContainsString('sort=date_asc&offset=0&limit=2', $transport->requests[0]['url']);

        $rows = $pdo->query(
            'SELECT external_order_id,remote_date_created FROM sales_audit_orders '
            . 'WHERE audit_run_id = ' . $runId . ' ORDER BY external_order_id'
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $rows, 'All guard-band observations must be durable evidence, not only canonical-month rows.');
        self::assertSame('200000000002', (string) $rows[0]['external_order_id']);
        self::assertSame('2026-10-15 13:45:00.000000', $rows[0]['remote_date_created']);
        self::assertSame('9007199254740993', (string) $rows[1]['external_order_id']);
        self::assertSame('2026-10-01 04:30:00.000000', $rows[1]['remote_date_created']);

        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id = ' . $workId)->fetchColumn());
        self::assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn(),
            'CAPTURE must never enqueue order.sync.',
        );
    }

    public function testMonthOutsideSellerSearchHorizonBecomesUnavailableWithoutOAuthOrRemoteHttp(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-horizon-secret');
        $this->seedAccount($pdo);

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2025-09-01',
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
            'sales.audit:' . $runId . ':0:50',
            ['run_id' => $runId, 'offset' => 0, 'limit' => 50],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $transport = new SalesAuditCaptureTransport(new MeliTransportResponse(
            500,
            [],
            '{"error":"must_not_be_called"}',
        ));
        $handler = $this->handler($pdo, $work, $audit, $transport, $cipher);

        self::assertTrue($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T14:00:00+00:00'),
        ));

        self::assertSame([], $transport->requests);
        self::assertSame(
            'unavailable',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id = ' . $workId)->fetchColumn());
        self::assertSame(
            0,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id = ' . $runId)->fetchColumn(),
        );
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM meli_tokens')->fetchColumn());
    }

    public function testOffsetlessRemoteDateFailsClosedWithoutPartialEvidence(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-bad-date-secret');
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

        $transport = new SalesAuditCaptureTransport(new MeliTransportResponse(
            200,
            [],
            '{"paging":{"total":2,"offset":0,"limit":2},"results":['
            . '{"id":200000000001,"date_created":"2026-10-10T10:00:00-05:00"},'
            . '{"id":200000000002,"date_created":"2026-10-10T11:00:00"}'
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
            0,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id = ' . $runId)->fetchColumn(),
            'A malformed page must not leave partial evidence.',
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
            'erp2.oauth.sales-audit',
        );

        return new SalesAuditHandler($work, $audit, $client, $tokens);
    }

    private function seedAccountAndToken(PDO $pdo, TokenCipher $cipher): void
    {
        $this->seedAccount($pdo);
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

    private function seedAccount(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (1,1,'99887766','MCO','Seller','connected')"
        );
    }
}

final class SalesAuditCaptureTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    public function __construct(private readonly MeliTransportResponse $response)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        return $this->response;
    }
}
