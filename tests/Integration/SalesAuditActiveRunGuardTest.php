<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Sales\Audit\SalesAuditRepository;
use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SalesAuditActiveRunGuardTest extends TestCase
{
    public function testEveryActiveStatusBlocksAnotherRunForSameScope(): void
    {
        foreach (['capturing', 'repairing', 'confirming'] as $status) {
            $pdo = TestDatabase::reset();
            $this->seedAccount($pdo);
            $repository = new SalesAuditRepository($pdo);
            $startedAt = new DateTimeImmutable('2026-10-10T01:20:00+00:00');

            $runId = $repository->createCapturingRun(
                1,
                1,
                '2026-10-01',
                SalesAuditRepository::CONTRACT_VERSION,
                $startedAt,
            );
            $pdo->prepare('UPDATE sales_audit_runs SET status = :status WHERE id = :id')
                ->execute(['status' => $status, 'id' => $runId]);

            try {
                $repository->createCapturingRun(
                    1,
                    1,
                    '2026-10-01',
                    SalesAuditRepository::CONTRACT_VERSION,
                    $startedAt->modify('+1 second'),
                );
                self::fail('Second active sales audit run was created for the same scope.');
            } catch (PDOException $exception) {
                self::assertSame('23000', $exception->getCode());
                self::assertSame(1062, (int) ($exception->errorInfo[1] ?? 0));
            }
        }
    }

    public function testTerminalRunAllowsAReplacementRunForSameScope(): void
    {
        foreach (['valid', 'attention', 'unavailable'] as $status) {
            $pdo = TestDatabase::reset();
            $this->seedAccount($pdo);
            $repository = new SalesAuditRepository($pdo);
            $startedAt = new DateTimeImmutable('2026-10-10T01:20:00+00:00');

            $runId = $repository->createCapturingRun(
                1,
                1,
                '2026-10-01',
                SalesAuditRepository::CONTRACT_VERSION,
                $startedAt,
            );
            $pdo->prepare('UPDATE sales_audit_runs SET status = :status WHERE id = :id')
                ->execute(['status' => $status, 'id' => $runId]);

            $replacementId = $repository->createCapturingRun(
                1,
                1,
                '2026-10-01',
                SalesAuditRepository::CONTRACT_VERSION,
                $startedAt->modify('+1 second'),
            );

            self::assertGreaterThan($runId, $replacementId);
        }
    }

    public function testSchemaEnforcesOneActiveRunPerAuditScope(): void
    {
        $pdo = TestDatabase::reset();
        $indexes = $pdo->query("SHOW INDEX FROM sales_audit_runs WHERE Key_name = 'uq_sales_audit_runs_active'")
            ->fetchAll(PDO::FETCH_ASSOC);

        $columns = [];
        foreach ($indexes as $index) {
            self::assertSame('0', (string) $index['Non_unique']);
            $columns[(int) $index['Seq_in_index']] = (string) $index['Column_name'];
        }
        ksort($columns);

        self::assertSame(
            ['company_id', 'account_id', 'period_key', 'active_contract_version'],
            array_values($columns),
        );
    }

    private function seedAccount(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Company 1','company-1')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (1,1,'99887766','MCO','Seller','connected')"
        );
    }
}
