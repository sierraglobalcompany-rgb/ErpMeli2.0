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
use App\Modules\Sales\Audit\SalesAuditRepairHandler;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use App\Modules\Sales\SalesWorkProcessor;
use App\Modules\Sales\SyncOrder\OrderSyncWorkProcessor;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesWorkProcessorTest extends TestCase
{
    public function testProcessorDispatchesSalesAudit(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-processor-test-key');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Sales','sales')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) "
            . "VALUES (1,1,'99887766','MCO','connected')"
        );
        $token = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (1,?,?,?,0)'
        );
        $token->execute([
            $cipher->encrypt('valid-access'),
            $cipher->encrypt('unused-refresh'),
            '2030-01-01 00:00:00.000000',
        ]);

        $transport = new SalesAuditProcessorTransport();
        /** @var array<string,array<string,mixed>> $operations */
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
            'client-id',
            'client-secret',
            'erp_meli2.test.sales.processor.oauth',
        );
        $work = new WorkRepository($pdo);
        $audit = new SalesAuditRepository($pdo);
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new SalesAuditHandler($work, $audit, $client, $tokens),
            new SalesAuditRepairHandler($audit, $work),
            $audit,
            $work,
        );

        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        $auditWorkId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':0:50',
            ['run_id' => $runId, 'offset' => 0, 'limit' => 50],
        );
        $auditClaim = $work->claimNext();
        self::assertIsArray($auditClaim);
        $processor($auditClaim);

        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id=' . $auditWorkId)->fetchColumn());
        self::assertSame(
            1,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id=' . $runId)->fetchColumn(),
        );
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn());
        self::assertCount(1, $transport->requests);
    }

    public function testConfirmingRunDispatchesOneIndependentCaptureBPageAndPreservesCaptureA(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-confirm-processor-test-key');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Sales','sales')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) "
            . "VALUES (1,1,'99887766','MCO','connected')"
        );
        $token = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (1,?,?,?,0)'
        );
        $token->execute([
            $cipher->encrypt('valid-access'),
            $cipher->encrypt('unused-refresh'),
            '2030-01-01 00:00:00.000000',
        ]);

        $transport = new SalesAuditProcessorTransport(
            '{"paging":{"total":2,"offset":0,"limit":1},"results":['
            . '{"id":200000000100,"date_created":"2026-10-09T15:00:00-05:00"}'
            . ']}',
        );
        /** @var array<string,array<string,mixed>> $operations */
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
            'client-id',
            'client-secret',
            'erp_meli2.test.sales.confirm.processor.oauth',
        );
        $work = new WorkRepository($pdo);
        $audit = new SalesAuditRepository($pdo);
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new SalesAuditHandler($work, $audit, $client, $tokens),
            new SalesAuditRepairHandler($audit, $work),
            $audit,
            $work,
        );

        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 1));
        self::assertTrue($audit->recordObservation(
            $runId,
            '200000000099',
            new DateTimeImmutable('2026-10-08T15:00:00-05:00'),
        ));
        $audit->persistCanonicalFingerprint(
            $runId,
            1,
            1,
            SalesAuditWindow::forSitePeriod('MCO', '2026-10-01'),
        );
        $pdo->exec(
            "INSERT INTO orders(company_id,account_id,external_order_id,status,date_created,total_amount,currency_id) "
            . "VALUES (1,1,'200000000099','paid','2026-10-08 20:00:00.000000','1000.0000','COP')"
        );
        self::assertSame('confirming', $audit->advanceCapturedRun($runId, 1, 1));

        $confirmWorkId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':confirm:0:1',
            ['run_id' => $runId, 'offset' => 0, 'limit' => 1],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        $processor($claim);

        self::assertSame(
            'done',
            $pdo->query('SELECT status FROM work_items WHERE id=' . $confirmWorkId)->fetchColumn(),
        );
        self::assertCount(1, $transport->requests);
        self::assertSame(
            1,
            (int) $pdo->query(
                "SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id={$runId} AND capture_pass='A'"
            )->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $pdo->query(
                "SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id={$runId} AND capture_pass='B'"
            )->fetchColumn(),
        );
        self::assertSame(
            'confirming',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id=' . $runId)->fetchColumn(),
        );

        $continuations = $pdo->query(
            "SELECT payload_json FROM work_items WHERE type='sales.audit' AND status='pending' ORDER BY id"
        )->fetchAll(PDO::FETCH_COLUMN);
        self::assertCount(1, $continuations);
        self::assertSame(
            ['run_id' => $runId, 'offset' => 1, 'limit' => 1, 'remote_total' => 2],
            json_decode((string) $continuations[0], true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
        );
    }
}

final class SalesAuditProcessorTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    public function __construct(private readonly ?string $ordersSearchBody = null)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;

        if (str_contains($url, '/orders/search?')) {
            return new MeliTransportResponse(
                200,
                [],
                $this->ordersSearchBody
                    ?? '{"paging":{"total":1,"offset":0,"limit":50},"results":['
                    . '{"id":200000000099,"date_created":"2026-10-08T15:00:00-05:00"}'
                    . ']}',
            );
        }

        throw new RuntimeException('Unexpected Sales work processor request: ' . $url);
    }
}
