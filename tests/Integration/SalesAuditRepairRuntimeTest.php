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

final class SalesAuditRepairRuntimeTest extends TestCase
{
    public function testRepairingSalesAuditEnqueuesOneChildAndDefersParentWithoutRemoteHttp(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);

        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedRepairingRun($audit);
        $work = new WorkRepository($pdo);
        $transport = new SalesAuditRepairRuntimeTransport();
        $processor = $this->processor($pdo, $work, $audit, $transport);

        $parentId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':repair',
            ['run_id' => $runId],
            new DateTimeImmutable('2026-10-10T00:00:00+00:00'),
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        self::assertSame($parentId, $claim['id']);
        self::assertSame(1, $claim['attempts']);

        $processor($claim);

        $parent = $pdo->query(
            'SELECT status,attempts,last_error_code FROM work_items WHERE id = ' . $parentId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($parent);
        self::assertSame('pending', $parent['status']);
        self::assertSame('0', (string) $parent['attempts']);
        self::assertSame('sales_audit_repair_wait', $parent['last_error_code']);

        $children = $pdo->query(
            "SELECT resource_key,status FROM work_items WHERE type='order.sync' ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $children);
        self::assertSame('100000000001', $children[0]['resource_key']);
        self::assertSame('pending', $children[0]['status']);
        self::assertSame(
            'repairing',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
        self::assertSame([], $transport->requests);
    }

    public function testTerminalRepairChildMovesRunToAttentionWithoutRecreation(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);

        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedRepairingRun($audit);
        $work = new WorkRepository($pdo);
        $transport = new SalesAuditRepairRuntimeTransport();
        $processor = $this->processor($pdo, $work, $audit, $transport);

        $scopeKey = 'company:1:account:1';
        $childId = $work->enqueue(
            1,
            1,
            $scopeKey,
            'order.sync',
            '100000000001',
            'order.sync:100000000001',
            ['order_id' => '100000000001'],
            new DateTimeImmutable('2026-10-10T00:00:00+00:00'),
        );
        $childClaim = $work->claimNext();
        self::assertIsArray($childClaim);
        self::assertSame($childId, $childClaim['id']);
        self::assertTrue($work->failCurrentClaim(
            $childClaim['id'],
            $childClaim['claim_token'],
            'meli_remote_permanent',
            'Mercado Libre rejected the order sync.',
        ));

        $parentId = $work->enqueue(
            1,
            1,
            $scopeKey,
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':repair',
            ['run_id' => $runId],
            new DateTimeImmutable('2026-10-10T00:00:00+00:00'),
        );
        $parentClaim = $work->claimNext();
        self::assertIsArray($parentClaim);
        self::assertSame($parentId, $parentClaim['id']);

        $processor($parentClaim);

        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
            'A terminal child with a persistent local gap must never be recreated automatically.',
        );
        self::assertSame(
            'attention',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
        self::assertSame(
            'done',
            $pdo->query('SELECT status FROM work_items WHERE id = ' . $parentId)->fetchColumn(),
            'The parent successfully records a durable business outcome; Work is not the business history.',
        );
        self::assertSame([], $transport->requests);
    }

    public function testRepairingRunWithNoMissingCanonicalOrdersTransitionsToConfirming(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);

        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedRepairingRun($audit);
        foreach ([
            ['100000000001', '2026-10-10 10:00:00.000000'],
            ['200000000002', '2026-10-11 10:00:00.000000'],
            ['300000000003', '2026-10-12 10:00:00.000000'],
        ] as [$id, $date]) {
            $this->insertLocalOrder($pdo, $id, $date);
        }

        $work = new WorkRepository($pdo);
        $transport = new SalesAuditRepairRuntimeTransport();
        $processor = $this->processor($pdo, $work, $audit, $transport);
        $parentId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':repair',
            ['run_id' => $runId],
            new DateTimeImmutable('2026-10-10T00:00:00+00:00'),
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        self::assertSame($parentId, $claim['id']);

        $processor($claim);

        self::assertSame(
            'confirming',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id = ' . $parentId)->fetchColumn());
        self::assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
        );
        self::assertSame([], $transport->requests);
    }

    private function processor(
        PDO $pdo,
        WorkRepository $work,
        SalesAuditRepository $audit,
        MeliTransport $transport,
    ): SalesWorkProcessor {
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
            new TokenCipher('sales-audit-repair-runtime-key'),
            'client-id',
            'client-secret',
            'erp_meli2.test.sales.audit.repair.runtime',
        );

        return new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new SalesAuditHandler($work, $audit, $client, $tokens),
            new SalesAuditRepairHandler($audit, $work),
            $audit,
            $work,
        );
    }

    private function seedRepairingRun(SalesAuditRepository $audit): int
    {
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 3));
        foreach ([
            ['300000000003', '2026-10-12T10:00:00+00:00'],
            ['100000000001', '2026-10-10T10:00:00+00:00'],
            ['200000000002', '2026-10-11T10:00:00+00:00'],
        ] as [$id, $date]) {
            self::assertTrue($audit->recordObservation($runId, $id, new DateTimeImmutable($date)));
        }
        $audit->persistCanonicalFingerprint(
            $runId,
            1,
            1,
            SalesAuditWindow::forSitePeriod('MCO', '2026-10-01'),
        );
        self::assertSame('repairing', $audit->advanceCapturedRun($runId, 1, 1));

        return $runId;
    }

    private function insertLocalOrder(PDO $pdo, string $externalOrderId, string $dateCreated): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO orders '
            . '(company_id,account_id,external_order_id,status,date_created,total_amount,currency_id) '
            . "VALUES (1,1,:external_order_id,'paid',:date_created,'1000.0000','COP')"
        );
        $statement->execute([
            'external_order_id' => $externalOrderId,
            'date_created' => $dateCreated,
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

final class SalesAuditRepairRuntimeTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;
        throw new RuntimeException('Repair parent must not call Mercado Libre directly.');
    }
}
