<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesAuditMissingLocalSetTest extends TestCase
{
    public function testMissingCanonicalOrderIdsUsesRemoteCanonicalMembershipAndLocalMonthScope(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);
        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedFingerprint($audit);

        $this->insertLocalOrder($pdo, '100000000001', '2026-10-10 10:00:00.000000');
        $this->insertLocalOrder($pdo, '200000000002', '2026-11-01 05:00:00.000000');
        $this->insertLocalOrder($pdo, '400000000004', '2026-10-13 10:00:00.000000');

        self::assertSame(
            ['200000000002', '300000000003'],
            $audit->missingCanonicalOrderIds($runId, 1, 1),
        );
        self::assertSame(
            'capturing',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
    }

    public function testMissingCanonicalOrderIdsFailsClosedBeforeFingerprintExists(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);
        $audit = new SalesAuditRepository($pdo);
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
            '100000000001',
            new DateTimeImmutable('2026-10-10T10:00:00+00:00'),
        ));

        $this->expectException(RuntimeException::class);
        $audit->missingCanonicalOrderIds($runId, 1, 1);
    }

    private function seedFingerprint(SalesAuditRepository $audit): int
    {
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 4));

        foreach ([
            ['100000000001', '2026-10-10T10:00:00+00:00'],
            ['200000000002', '2026-10-11T10:00:00+00:00'],
            ['300000000003', '2026-10-12T10:00:00+00:00'],
            ['900000000009', '2026-10-01T04:30:00+00:00'],
        ] as [$id, $date]) {
            self::assertTrue($audit->recordObservation($runId, $id, new DateTimeImmutable($date)));
        }

        $audit->persistCanonicalFingerprint(
            $runId,
            1,
            1,
            SalesAuditWindow::forSitePeriod('MCO', '2026-10-01'),
        );

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
