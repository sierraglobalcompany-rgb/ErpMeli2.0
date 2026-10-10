<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Sales\Audit\SalesAuditRepairHandler;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesAuditRepairFanoutTest extends TestCase
{
    public function testRepairActionEnqueuesExactlyOneDeterministicMissingOrder(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);
        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedRepairingRun($audit);
        $work = new WorkRepository($pdo);
        $handler = new SalesAuditRepairHandler($audit, $work);

        $workId = $handler->enqueueNextMissingOrder(
            $runId,
            1,
            1,
            new DateTimeImmutable('2026-10-10T03:10:00+00:00'),
        );

        self::assertIsInt($workId);
        $rows = $pdo->query(
            "SELECT id,type,resource_key,payload_json,status FROM work_items WHERE type='order.sync' ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $rows);
        self::assertSame('100000000001', $rows[0]['resource_key']);
        self::assertSame('pending', $rows[0]['status']);
        self::assertSame(
            ['order_id' => '100000000001'],
            json_decode((string) $rows[0]['payload_json'], true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testRepairActionReusesActiveOrderSyncDedupe(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);
        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedRepairingRun($audit);
        $work = new WorkRepository($pdo);
        $existingId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'order.sync',
            '100000000001',
            'order.sync:100000000001',
            ['order_id' => '100000000001'],
            new DateTimeImmutable('2026-10-10T03:09:00+00:00'),
        );
        $handler = new SalesAuditRepairHandler($audit, $work);

        self::assertSame(
            $existingId,
            $handler->enqueueNextMissingOrder(
                $runId,
                1,
                1,
                new DateTimeImmutable('2026-10-10T03:10:00+00:00'),
            ),
        );
        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
        );
    }

    public function testRepairActionDoesNotRecreateFailedOrderSyncForSameMissingCandidate(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);
        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedRepairingRun($audit);
        $work = new WorkRepository($pdo);
        $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'order.sync',
            '100000000001',
            'order.sync:100000000001',
            ['order_id' => '100000000001'],
            new DateTimeImmutable('2026-10-10T03:09:00+00:00'),
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        self::assertTrue($work->failCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            'meli_remote_permanent',
            'Permanent repair sync failure.',
        ));

        $handler = new SalesAuditRepairHandler($audit, $work);

        try {
            $handler->enqueueNextMissingOrder(
                $runId,
                1,
                1,
                new DateTimeImmutable('2026-10-10T03:10:00+00:00'),
            );
            self::fail('Expected failed repair sync to block automatic recreation.');
        } catch (RuntimeException) {
            self::assertSame(
                1,
                (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
            );
        }
    }

    public function testRepairActionReturnsNullWhenNothingRemainsMissing(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);
        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedRepairingRun($audit);
        $this->insertLocalOrder($pdo, '100000000001', '2026-10-10 10:00:00.000000');
        $this->insertLocalOrder($pdo, '200000000002', '2026-10-11 10:00:00.000000');
        $this->insertLocalOrder($pdo, '300000000003', '2026-10-12 10:00:00.000000');
        $work = new WorkRepository($pdo);
        $handler = new SalesAuditRepairHandler($audit, $work);

        self::assertNull($handler->enqueueNextMissingOrder(
            $runId,
            1,
            1,
            new DateTimeImmutable('2026-10-10T03:10:00+00:00'),
        ));
        self::assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
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
