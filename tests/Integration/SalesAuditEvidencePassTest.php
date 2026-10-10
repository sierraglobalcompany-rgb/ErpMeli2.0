<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SalesAuditEvidencePassTest extends TestCase
{
    public function testEvidencePrimitivesSeparateCapturePassesWithoutChangingDefaultA(): void
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
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 2));

        self::assertTrue($audit->recordObservation(
            $runId,
            '100000000001',
            new DateTimeImmutable('2026-10-10T10:00:00+00:00'),
        ));
        self::assertTrue($audit->recordObservation(
            $runId,
            '200000000002',
            new DateTimeImmutable('2026-10-11T10:00:00+00:00'),
        ));
        self::assertTrue($audit->recordObservation(
            $runId,
            '100000000001',
            new DateTimeImmutable('2026-10-10T10:00:00+00:00'),
            'B',
        ));
        self::assertTrue($audit->recordObservation(
            $runId,
            '300000000003',
            new DateTimeImmutable('2026-10-12T10:00:00+00:00'),
            'B',
        ));

        self::assertSame(2, $audit->observationCount($runId));
        self::assertSame(2, $audit->observationCount($runId, 'B'));

        $window = SalesAuditWindow::forSitePeriod('MCO', '2026-10-01');
        $a = $audit->canonicalFingerprint($runId, $window);
        $b = $audit->canonicalFingerprint($runId, $window, 'B');

        self::assertSame(2, $a['canonical_count']);
        self::assertSame(
            hash('sha256', "100000000001\n200000000002"),
            $a['set_hash'],
        );
        self::assertSame(2, $b['canonical_count']);
        self::assertSame(
            hash('sha256', "100000000001\n300000000003"),
            $b['set_hash'],
        );

        $persisted = $audit->persistCanonicalFingerprint($runId, 1, 1, $window);
        self::assertSame($a, $persisted);

        $row = $pdo->query(
            'SELECT canonical_count,set_hash FROM sales_audit_runs WHERE id = ' . $runId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('2', (string) $row['canonical_count']);
        self::assertSame($a['set_hash'], $row['set_hash']);
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
