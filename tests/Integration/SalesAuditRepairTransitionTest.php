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

final class SalesAuditRepairTransitionTest extends TestCase
{
    public function testFingerprintRunWithMissingCanonicalOrderAdvancesToRepairing(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);
        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedFingerprintedRun($audit);

        self::assertSame('repairing', $audit->advanceCapturedRun($runId, 1, 1));
        self::assertSame(
            'repairing',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
    }

    public function testFingerprintRunWithoutMissingCanonicalOrderAdvancesToConfirming(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);
        $audit = new SalesAuditRepository($pdo);
        $runId = $this->seedFingerprintedRun($audit);
        $this->insertLocalOrder($pdo, '100000000001', '2026-10-10 10:00:00.000000');

        self::assertSame('confirming', $audit->advanceCapturedRun($runId, 1, 1));
        self::assertSame(
            'confirming',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
    }

    public function testRunWithoutFingerprintCannotAdvance(): void
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
        $audit->advanceCapturedRun($runId, 1, 1);
    }

    private function seedFingerprintedRun(SalesAuditRepository $audit): int
    {
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
