<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Billing\BillingDetailNormalizer;
use App\Modules\Billing\BillingPeriodSyncHandler;
use App\Modules\Sales\Audit\SalesAuditHandler;
use App\Modules\Sales\Audit\SalesAuditRepairHandler;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use App\Modules\Sales\SalesWorkProcessor;
use App\Modules\Sales\SyncOrder\OrderSyncWorkProcessor;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use App\Work\WorkRunner;
use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesWorkProcessorTest extends TestCase
{
    public function testProcessorDoesNotTurnRunStatusDatabaseFailureIntoAuditScopeOutcome(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-processor-sql-failure-key');
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
        $work = new WorkRepository($pdo);
        $audit = new SalesAuditRepository($pdo);
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(
                new SyncOrderHandler(
                    $work,
                    $client,
                    new OAuthRefreshService(
                        $pdo,
                        Connection::fromConfig(TestDatabase::config()),
                        $client,
                        $cipher,
                        'client-id',
                        'client-secret',
                        'erp_meli2.test.sales.processor.oauth',
                    ),
                ),
                $work,
            ),
            new SalesAuditHandler(
                $work,
                $audit,
                $client,
                new OAuthRefreshService(
                    $pdo,
                    Connection::fromConfig(TestDatabase::config()),
                    $client,
                    $cipher,
                    'client-id',
                    'client-secret',
                    'erp_meli2.test.sales.processor.audit.oauth',
                ),
            ),
            new SalesAuditRepairHandler($audit, $work),
            $audit,
            $work,
            new BillingPeriodSyncHandler(
                $work,
                $client,
                new OAuthRefreshService(
                    $pdo,
                    Connection::fromConfig(TestDatabase::config()),
                    $client,
                    $cipher,
                    'client-id',
                    'client-secret',
                    'erp_meli2.test.sales.processor.billing.oauth',
                ),
                new BillingDetailNormalizer(),
            ),
        );

        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
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
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('DROP TABLE sales_audit_runs');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $failure = null;
        try {
            $processor($claim);
        } catch (PDOException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(PDOException::class, $failure);
        self::assertCount(0, $transport->requests);
        $row = $pdo->query(
            'SELECT status,attempts,claim_token,last_error_code FROM work_items WHERE id=' . $workId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('running', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNotNull($row['claim_token']);
        self::assertNull($row['last_error_code']);
    }

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
            new BillingPeriodSyncHandler($work, $client, $tokens, new BillingDetailNormalizer()),
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
            new BillingPeriodSyncHandler($work, $client, $tokens, new BillingDetailNormalizer()),
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

    public function testWorkRunnerRoutesBillingPageAndQueuedContinuationThroughSameProcessor(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-billing-routing-test-key');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Billing Routing','billing-routing')");
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

        $transport = new BillingRoutingProcessorTransport();
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
            'erp_meli2.test.sales.processor.billing.oauth',
        );
        $work = new WorkRepository($pdo);
        $audit = new SalesAuditRepository($pdo);
        $billing = new BillingPeriodSyncHandler($work, $client, $tokens, new BillingDetailNormalizer());
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new SalesAuditHandler($work, $audit, $client, $tokens),
            new SalesAuditRepairHandler($audit, $work),
            $audit,
            $work,
            $billing,
        );

        $initialWorkId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'billing.period.sync',
            '2026-09-01:BILL',
            'billing.period.sync:2026-09-01:BILL:0',
            ['period_key' => '2026-09-01', 'document_type' => 'BILL', 'from_id' => '0'],
        );
        $runner = new WorkRunner(
            Connection::fromConfig(TestDatabase::config()),
            'erp_meli2.test.sales.processor.billing-routing',
        );

        self::assertSame(1, $runner->run($work, $processor, maxItems: 1, maxSeconds: 5));
        self::assertSame(
            'done',
            $pdo->query('SELECT status FROM work_items WHERE id=' . $initialWorkId)->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $pdo->query(
                "SELECT COUNT(*) FROM work_items WHERE type='billing.period.sync' AND status='pending'"
            )->fetchColumn(),
        );
        self::assertCount(1, $transport->requests);

        self::assertSame(1, $runner->run($work, $processor, maxItems: 1, maxSeconds: 5));
        self::assertSame(
            2,
            (int) $pdo->query(
                "SELECT COUNT(*) FROM work_items WHERE type='billing.period.sync' AND status='done'"
            )->fetchColumn(),
        );
        self::assertCount(2, $transport->requests);
        self::assertSame(
            'caught_up',
            $pdo->query(
                "SELECT sync_state FROM billing_periods WHERE company_id=1 AND account_id=1 "
                . "AND period_key='2026-09-01' AND document_type='BILL'",
            )->fetchColumn(),
        );

        $orderWorkId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'order.sync',
            '200000000101',
            'order.sync:200000000101',
            ['order_id' => '200000000101'],
        );
        self::assertSame(1, $runner->run($work, $processor, maxItems: 1, maxSeconds: 5));
        self::assertSame(
            'done',
            $pdo->query('SELECT status FROM work_items WHERE id=' . $orderWorkId)->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE external_order_id='200000000101'")->fetchColumn(),
        );
        self::assertSame(
            1,
            count(array_filter($transport->requests, static fn (string $url): bool => str_contains($url, '/orders/'))),
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

final class BillingRoutingProcessorTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    private int $page = 0;

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;

        if (str_ends_with($url, '/orders/200000000101')) {
            return new MeliTransportResponse(200, [], '{"id":200000000101,"status":"paid",'
                . '"date_created":"2026-10-09T01:00:00.000Z","last_updated":"2026-10-09T01:30:00.000Z",'
                . '"total_amount":50000,"currency_id":"COP","buyer":{"id":800000101},'
                . '"order_items":[{"item":{"id":"MCO101010101","title":"Producto"},"quantity":1,'
                . '"unit_price":50000,"currency_id":"COP"}]}');
        }

        $this->page++;

        if ($this->page === 1) {
            return new MeliTransportResponse(
                200,
                [],
                '{"results":[{"charge_info":{"detail_id":101,"detail_associated_id":90,'
                . '"detail_amount":28265.86,"detail_type":"CHARGE","detail_sub_type":"CV",'
                . '"creation_date_time":"2026-09-12T15:12:02.123-05:00"},'
                . '"currency_info":{"currency_id":"COP"},"document_info":{"document_id":3454540850},'
                . '"marketplace_info":{"marketplace":"MCO"}}],"last_id":101}',
            );
        }

        if ($this->page === 2) {
            return new MeliTransportResponse(200, [], '{"results":[],"last_id":null}');
        }

        throw new RuntimeException('Unexpected extra billing routing request: ' . $url);
    }
}
