<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class WorkQueueMigrationTest extends TestCase
{
    public function testWorkItemsTableExistsWithActiveOnlyDedupe(): void
    {
        $pdo = TestDatabase::reset();

        $table = $pdo->query("SHOW TABLES LIKE 'work_items'")->fetchColumn();
        self::assertSame('work_items', $table, 'F2 requires exactly one durable work_items table.');

        $insert = $pdo->prepare(
            'INSERT INTO work_items '
            . '(company_id, account_id, scope_key, type, resource_key, dedupe_key, payload_json, status, available_at, created_at, updated_at) '
            . 'VALUES (1, 10, :scope_key, :type, :resource_key, :dedupe_key, :payload_json, :status, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))'
        );

        $base = [
            'scope_key' => 'company:1:account:10',
            'type' => 'order.sync',
            'resource_key' => 'order:123',
            'dedupe_key' => hash('sha256', 'order.sync|order:123'),
            'payload_json' => json_encode(['order_id' => '123'], JSON_THROW_ON_ERROR),
        ];

        $insert->execute($base + ['status' => 'pending']);

        try {
            $insert->execute($base + ['status' => 'pending']);
            self::fail('A duplicate active work item must be rejected by the database uniqueness invariant.');
        } catch (PDOException $exception) {
            self::assertSame('23000', $exception->getCode());
        }

        $pdo->exec("UPDATE work_items SET status = 'done', finished_at = UTC_TIMESTAMP(6) WHERE id = 1");
        $insert->execute($base + ['status' => 'pending']);

        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public function testFailedTerminalWorkDoesNotBlockFutureEquivalentWork(): void
    {
        $pdo = TestDatabase::reset();

        self::assertSame('work_items', $pdo->query("SHOW TABLES LIKE 'work_items'")->fetchColumn());

        $dedupeKey = hash('sha256', 'system.cleanup|daily');
        $sql = <<<'SQL'
            INSERT INTO work_items
                (company_id, account_id, scope_key, type, resource_key, dedupe_key, status, available_at, created_at, updated_at)
            VALUES
                (NULL, NULL, 'app:system', 'system.cleanup', 'daily', :dedupe_key, :status, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
            SQL;
        $insert = $pdo->prepare($sql);

        $insert->execute(['dedupe_key' => $dedupeKey, 'status' => 'failed']);
        $insert->execute(['dedupe_key' => $dedupeKey, 'status' => 'pending']);

        self::assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE scope_key = 'app:system'")->fetchColumn());
    }
}
