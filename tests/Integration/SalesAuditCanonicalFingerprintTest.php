<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SalesAuditCanonicalFingerprintTest extends TestCase
{
    public function testCanonicalFingerprintExcludesGuardBandAndPersistsSortedDeterministicSet(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);

        $repository = new SalesAuditRepository($pdo);
        $runId = $repository->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($repository->acceptRemoteTotal($runId, 1, 1, 4));

        self::assertTrue($repository->recordObservation(
            $runId,
            '900000000009',
            new DateTimeImmutable('2026-10-01T04:59:59+00:00'),
        ));
        self::assertTrue($repository->recordObservation(
            $runId,
            '300000000003',
            new DateTimeImmutable('2026-10-01T05:00:00+00:00'),
        ));
        self::assertTrue($repository->recordObservation(
            $runId,
            '100000000001',
            new DateTimeImmutable('2026-10-15T12:30:00+00:00'),
        ));
        self::assertTrue($repository->recordObservation(
            $runId,
            '800000000008',
            new DateTimeImmutable('2026-11-01T05:00:00+00:00'),
        ));

        $fingerprint = $repository->persistCanonicalFingerprint(
            $runId,
            1,
            1,
            SalesAuditWindow::forSitePeriod('MCO', '2026-10-01'),
        );

        self::assertSame(2, $fingerprint['canonical_count']);
        self::assertSame(
            'c8660ac33865d9fd368fd3fd9979219bd533ce8155caf12bb26359e0d30b4e53',
            $fingerprint['set_hash'],
        );

        $statement = $pdo->prepare(
            'SELECT canonical_count,set_hash,status FROM sales_audit_runs WHERE id = :run_id'
        );
        $statement->execute(['run_id' => $runId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('2', (string) $row['canonical_count']);
        self::assertSame($fingerprint['set_hash'], $row['set_hash']);
        self::assertSame('capturing', $row['status']);
    }

    public function testEmptyCanonicalSetUsesSha256OfEmptyCanonicalSerialization(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo);

        $repository = new SalesAuditRepository($pdo);
        $runId = $repository->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($repository->acceptRemoteTotal($runId, 1, 1, 2));

        self::assertTrue($repository->recordObservation(
            $runId,
            '900000000009',
            new DateTimeImmutable('2026-10-01T04:30:00+00:00'),
        ));
        self::assertTrue($repository->recordObservation(
            $runId,
            '800000000008',
            new DateTimeImmutable('2026-11-01T05:30:00+00:00'),
        ));

        $fingerprint = $repository->persistCanonicalFingerprint(
            $runId,
            1,
            1,
            SalesAuditWindow::forSitePeriod('MCO', '2026-10-01'),
        );

        self::assertSame(0, $fingerprint['canonical_count']);
        self::assertSame(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $fingerprint['set_hash'],
        );
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
