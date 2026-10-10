<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class OperationalRetentionTest extends TestCase
{
    public function testWorkRetentionDeletesOnlyTerminalRowsStrictlyOlderThanCutoff(): void
    {
        $pdo = TestDatabase::reset();
        $cutoff = new DateTimeImmutable('2026-09-10T12:00:00.000000+00:00');

        $this->insertWork($pdo, 'old-done', 'done', '2026-09-10 11:59:59.999999');
        $this->insertWork($pdo, 'old-failed', 'failed', '2026-09-01 00:00:00.000000');
        $this->insertWork($pdo, 'edge-done', 'done', '2026-09-10 12:00:00.000000');
        $this->insertWork($pdo, 'recent-failed', 'failed', '2026-09-11 00:00:00.000000');
        $this->insertWork($pdo, 'old-pending', 'pending', null, '2026-08-01 00:00:00.000000');
        $this->insertWork($pdo, 'old-running', 'running', null, '2026-08-01 00:00:00.000000');
        $this->insertWork($pdo, 'terminal-without-finished-at', 'failed', null, '2026-08-01 00:00:00.000000');

        $deleted = (new WorkRepository($pdo))->purgeTerminalBefore($cutoff);

        self::assertSame(2, $deleted);
        self::assertSame(
            [
                'edge-done',
                'old-pending',
                'old-running',
                'recent-failed',
                'terminal-without-finished-at',
            ],
            $pdo->query('SELECT resource_key FROM work_items ORDER BY resource_key')->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function testApiUsageRetentionDeletesOnlyDaysStrictlyOlderThanCutoff(): void
    {
        $pdo = TestDatabase::reset();
        $this->insertUsage($pdo, '2026-07-11', 'old');
        $this->insertUsage($pdo, '2026-07-12', 'edge');
        $this->insertUsage($pdo, '2026-07-13', 'recent');

        $deleted = (new ApiUsageRecorder($pdo))->purgeBefore(
            new DateTimeImmutable('2026-07-12T00:00:00+00:00'),
        );

        self::assertSame(1, $deleted);
        self::assertSame(
            ['2026-07-12', '2026-07-13'],
            $pdo->query('SELECT usage_date FROM api_usage_daily ORDER BY usage_date')->fetchAll(PDO::FETCH_COLUMN),
        );
    }

    public function testCleanupEntrypointOwnsOnlyRetentionCutoffsAndUsesExistingOwners(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/bin/cleanup.php');

        self::assertIsString($source);
        self::assertStringContainsString('new WorkRepository($pdo)', $source);
        self::assertStringContainsString('purgeTerminalBefore(', $source);
        self::assertStringContainsString('new ApiUsageRecorder($pdo)', $source);
        self::assertStringContainsString('purgeBefore(', $source);
        self::assertStringContainsString("modify('-30 days')", $source);
        self::assertStringContainsString("modify('-90 days')", $source);
        self::assertStringContainsString('deleted_work=', $source);
        self::assertStringContainsString('deleted_api_usage=', $source);
        self::assertStringNotContainsString('MaintenanceEngine', $source);
    }

    private function insertWork(
        PDO $pdo,
        string $resourceKey,
        string $status,
        ?string $finishedAt,
        ?string $updatedAt = null,
    ): void {
        $updatedAt ??= $finishedAt ?? '2026-10-10 00:00:00.000000';
        $statement = $pdo->prepare(
            'INSERT INTO work_items '
            . '(scope_key, type, resource_key, dedupe_key, status, available_at, created_at, updated_at, finished_at) '
            . 'VALUES (:scope_key, :type, :resource_key, :dedupe_key, :status, :available_at, :created_at, :updated_at, :finished_at)'
        );
        $statement->execute([
            'scope_key' => 'retention:' . $resourceKey,
            'type' => 'retention.test',
            'resource_key' => $resourceKey,
            'dedupe_key' => hash('sha256', 'retention|' . $resourceKey),
            'status' => $status,
            'available_at' => '2026-08-01 00:00:00.000000',
            'created_at' => '2026-08-01 00:00:00.000000',
            'updated_at' => $updatedAt,
            'finished_at' => $finishedAt,
        ]);
    }

    private function insertUsage(PDO $pdo, string $usageDate, string $operationKey): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO api_usage_daily '
            . '(usage_date, scope_key, operation_key, requests, resources, successes, client_errors, '
            . 'server_errors, rate_limited, duration_ms_sum, duration_ms_max) '
            . "VALUES (:usage_date, 'retention:test', :operation_key, 1, 1, 1, 0, 0, 0, 10, 10)"
        );
        $statement->execute([
            'usage_date' => $usageDate,
            'operation_key' => $operationKey,
        ]);
    }
}
