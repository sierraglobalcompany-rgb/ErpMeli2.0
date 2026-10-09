<?php

declare(strict_types=1);

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class BillingSchemaTest extends TestCase
{
    public function testF6ACreatesOnlyThePeriodFirstBillingTables(): void
    {
        $pdo = TestDatabase::reset();

        foreach (['billing_periods', 'billing_details'] as $table) {
            self::assertSame(
                $table,
                $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetchColumn(),
                'Missing F6A table: ' . $table,
            );
        }
    }

    public function testBillingPeriodsUseScopedPeriodDocumentIdentityAndCursorState(): void
    {
        $pdo = TestDatabase::reset();
        $this->assertTableExists($pdo, 'billing_periods');

        $columns = $pdo->query('SHOW COLUMNS FROM billing_periods')->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($columns as $column) {
            $byName[(string) $column['Field']] = $column;
        }

        foreach ([
            'company_id',
            'account_id',
            'period_key',
            'document_type',
            'cursor_last_id',
            'sync_state',
            'partial_flag',
            'last_synced_at',
            'created_at',
            'updated_at',
        ] as $name) {
            self::assertArrayHasKey($name, $byName);
        }

        $indexes = $pdo->query('SHOW INDEX FROM billing_periods')->fetchAll(PDO::FETCH_ASSOC);
        $uniqueColumns = [];
        foreach ($indexes as $index) {
            if ((int) $index['Non_unique'] !== 0 || (string) $index['Key_name'] === 'PRIMARY') {
                continue;
            }
            $uniqueColumns[(string) $index['Key_name']][(int) $index['Seq_in_index']] = (string) $index['Column_name'];
        }

        $hasScopedPeriodDocumentKey = false;
        foreach ($uniqueColumns as $columnsForIndex) {
            ksort($columnsForIndex);
            if (array_values($columnsForIndex) === ['company_id', 'account_id', 'period_key', 'document_type']) {
                $hasScopedPeriodDocumentKey = true;
                break;
            }
        }

        self::assertTrue(
            $hasScopedPeriodDocumentKey,
            'Billing periods must be unique by company/account/period/document_type.',
        );
    }

    public function testBillingDetailsUseExactMoneyScopedRemoteIdentityAndNoRawPayload(): void
    {
        $pdo = TestDatabase::reset();
        $this->assertTableExists($pdo, 'billing_details');

        $columns = $pdo->query('SHOW COLUMNS FROM billing_details')->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($columns as $column) {
            $byName[(string) $column['Field']] = $column;
        }

        foreach ([
            'billing_period_id',
            'external_detail_id',
            'associated_detail_id',
            'detail_type',
            'detail_sub_type',
            'detail_amount',
            'currency_id',
            'document_id',
            'marketplace',
            'remote_created_at',
            'created_at',
        ] as $name) {
            self::assertArrayHasKey($name, $byName);
        }
        self::assertStringStartsWith('decimal(', strtolower((string) $byName['detail_amount']['Type']));

        foreach ([
            'payload',
            'payload_json',
            'raw_payload',
            'raw_json',
            'snapshot_json',
            'payer_nickname',
            'buyer_nickname',
            'billing_info',
        ] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $byName);
        }

        $indexes = $pdo->query('SHOW INDEX FROM billing_details')->fetchAll(PDO::FETCH_ASSOC);
        $uniqueColumns = [];
        foreach ($indexes as $index) {
            if ((int) $index['Non_unique'] !== 0 || (string) $index['Key_name'] === 'PRIMARY') {
                continue;
            }
            $uniqueColumns[(string) $index['Key_name']][(int) $index['Seq_in_index']] = (string) $index['Column_name'];
        }

        $hasRemoteDetailKey = false;
        foreach ($uniqueColumns as $columnsForIndex) {
            ksort($columnsForIndex);
            if (array_values($columnsForIndex) === ['billing_period_id', 'external_detail_id']) {
                $hasRemoteDetailKey = true;
                break;
            }
        }

        self::assertTrue($hasRemoteDetailKey, 'Billing details must dedupe by period/detail identity.');
    }

    private function assertTableExists(PDO $pdo, string $table): void
    {
        self::assertSame(
            $table,
            $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetchColumn(),
            'Missing F6A table: ' . $table,
        );
    }
}
