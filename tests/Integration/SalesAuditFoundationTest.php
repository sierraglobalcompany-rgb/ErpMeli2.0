<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesAuditFoundationTest extends TestCase
{
    public function testMcoMonthBuildsCanonicalUtcWindowAndOneHourRemoteGuardBand(): void
    {
        $window = SalesAuditWindow::forSitePeriod('MCO', '2026-10-01');

        self::assertSame('2026-10-01', $window->periodKey);
        self::assertSame('2026-10-01T05:00:00+00:00', $window->canonicalStartUtc->format(DATE_ATOM));
        self::assertSame('2026-11-01T05:00:00+00:00', $window->canonicalEndUtc->format(DATE_ATOM));
        self::assertSame('2026-10-01T04:00:00+00:00', $window->remoteFromUtc->format(DATE_ATOM));
        self::assertSame('2026-11-01T06:00:00+00:00', $window->remoteToUtc->format(DATE_ATOM));
    }

    public function testUnsupportedSiteFailsClosed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Mercado Libre site for historical sales audit.');

        SalesAuditWindow::forSitePeriod('MLA', '2026-10-01');
    }

    public function testPeriodKeyMustBeTheFirstDayOfARealMonth(): void
    {
        foreach (['2026-10-15', '2026-13-01', '2026-02-30', '2026-10'] as $periodKey) {
            try {
                SalesAuditWindow::forSitePeriod('MCO', $periodKey);
                self::fail('Invalid period key accepted: ' . $periodKey);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid sales audit period key.', $exception->getMessage());
            }
        }
    }

    public function testRepositoryResolvesScopedSiteAndCreatesCapturingRun(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo, 1, 1, 'MCO');

        $repository = new SalesAuditRepository($pdo);
        self::assertSame('MCO', $repository->siteIdForScope(1, 1));

        $startedAt = new DateTimeImmutable('2026-10-10T01:20:00+00:00');
        $runId = $repository->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            $startedAt,
        );

        $statement = $pdo->prepare(
            'SELECT company_id,account_id,period_key,contract_version,status,remote_total,canonical_count,set_hash,started_at,completed_at '
            . 'FROM sales_audit_runs WHERE id = :id'
        );
        $statement->execute(['id' => $runId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        self::assertIsArray($row);
        self::assertSame('1', (string) $row['company_id']);
        self::assertSame('1', (string) $row['account_id']);
        self::assertSame('2026-10-01', $row['period_key']);
        self::assertSame('seller-search-v1', $row['contract_version']);
        self::assertSame('capturing', $row['status']);
        self::assertNull($row['remote_total']);
        self::assertNull($row['canonical_count']);
        self::assertNull($row['set_hash']);
        self::assertSame('2026-10-10 01:20:00.000000', $row['started_at']);
        self::assertNull($row['completed_at']);
    }

    public function testRepositoryFailsClosedWhenAccountDoesNotBelongToCompany(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo, 1, 1, 'MCO');
        $repository = new SalesAuditRepository($pdo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Mercado Libre account scope not found.');

        $repository->siteIdForScope(2, 1);
    }

    public function testObservationIsInsertedOnceAndDuplicateDoesNotOverwriteOriginalEvidence(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo, 1, 1, 'MCO');
        $repository = new SalesAuditRepository($pdo);
        $runId = $repository->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:20:00+00:00'),
        );

        self::assertTrue($repository->recordObservation(
            $runId,
            '9007199254740993',
            new DateTimeImmutable('2026-10-10T05:30:00-05:00'),
        ));
        self::assertFalse($repository->recordObservation(
            $runId,
            '9007199254740993',
            new DateTimeImmutable('2026-10-11T06:45:00-05:00'),
        ));

        $statement = $pdo->prepare(
            'SELECT external_order_id,remote_date_created FROM sales_audit_orders WHERE audit_run_id = :run_id'
        );
        $statement->execute(['run_id' => $runId]);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        self::assertCount(1, $rows);
        self::assertSame('9007199254740993', (string) $rows[0]['external_order_id']);
        self::assertSame('2026-10-10 10:30:00.000000', $rows[0]['remote_date_created']);
    }

    public function testRemoteTotalIsFixedOnceAndCannotDrift(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo, 1, 1, 'MCO');
        $repository = new SalesAuditRepository($pdo);
        $runId = $repository->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:20:00+00:00'),
        );

        self::assertTrue($repository->acceptRemoteTotal($runId, 1, 1, 127));
        self::assertSame('127', (string) $pdo->query(
            'SELECT remote_total FROM sales_audit_runs WHERE id=' . $runId
        )->fetchColumn());

        self::assertTrue($repository->acceptRemoteTotal($runId, 1, 1, 127));
        self::assertFalse($repository->acceptRemoteTotal($runId, 1, 1, 128));
        self::assertSame('127', (string) $pdo->query(
            'SELECT remote_total FROM sales_audit_runs WHERE id=' . $runId
        )->fetchColumn(), 'A drifting remote total must never overwrite the first observed total.');
    }

    public function testRemoteTotalRejectsInvalidValueAndWrongScopeOrState(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAccount($pdo, 1, 1, 'MCO');
        $repository = new SalesAuditRepository($pdo);
        $runId = $repository->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:20:00+00:00'),
        );

        try {
            $repository->acceptRemoteTotal($runId, 1, 1, -1);
            self::fail('Negative remote total was accepted.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Sales audit remote total is invalid.', $exception->getMessage());
        }

        try {
            $repository->acceptRemoteTotal($runId, 2, 1, 10);
            self::fail('Wrong company scope was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Sales audit capturing run is unavailable.', $exception->getMessage());
        }

        $pdo->exec("UPDATE sales_audit_runs SET status='attention' WHERE id={$runId}");
        try {
            $repository->acceptRemoteTotal($runId, 1, 1, 10);
            self::fail('Non-capturing run accepted remote total.');
        } catch (RuntimeException $exception) {
            self::assertSame('Sales audit capturing run is unavailable.', $exception->getMessage());
        }
    }

    private function seedAccount(PDO $pdo, int $companyId, int $accountId, string $siteId): void
    {
        $pdo->exec(
            "INSERT INTO companies(id,name,slug) VALUES ({$companyId},'Company {$companyId}','company-{$companyId}')"
        );
        $statement = $pdo->prepare(
            'INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) '
            . "VALUES (:id,:company_id,'99887766',:site_id,'Seller','connected')"
        );
        $statement->execute([
            'id' => $accountId,
            'company_id' => $companyId,
            'site_id' => $siteId,
        ]);
    }
}
