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
            'context_json',
            'remote_created_at',
            'created_at',
        ] as $name) {
            self::assertArrayHasKey($name, $byName);
        }
        self::assertStringStartsWith('decimal(', strtolower((string) $byName['detail_amount']['Type']));
        self::assertSame('YES', strtoupper((string) $byName['context_json']['Null']));

        $createSql = strtolower((string) $pdo->query('SHOW CREATE TABLE billing_details')->fetchColumn(1));
        self::assertStringContainsString('context_json', $createSql);
        self::assertStringContainsString('json_valid', $createSql);

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

    public function testFinancialReferencesAreByteExactIndexedLinksAndFreshSchemaIsReady(): void
    {
        $pdo = TestDatabase::reset();
        $this->assertTableExists($pdo, 'billing_detail_references');

        $columns = $pdo->query('SHOW COLUMNS FROM billing_detail_references')->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($columns as $column) {
            $byName[(string) $column['Field']] = $column;
        }
        self::assertSame('bigint(20) unsigned', strtolower((string) $byName['billing_detail_id']['Type']));
        self::assertSame('varbinary(255)', strtolower((string) $byName['reference_value']['Type']));
        self::assertSame('NO', strtoupper((string) $byName['reference_value']['Null']));

        $indexes = $pdo->query('SHOW INDEX FROM billing_detail_references')->fetchAll(PDO::FETCH_ASSOC);
        $byIndex = [];
        foreach ($indexes as $index) {
            $byIndex[(string) $index['Key_name']][(int) $index['Seq_in_index']] = (string) $index['Column_name'];
        }
        foreach ($byIndex as &$indexColumns) {
            ksort($indexColumns);
            $indexColumns = array_values($indexColumns);
        }
        unset($indexColumns);
        self::assertSame(
            ['billing_detail_id', 'reference_kind', 'reference_value'],
            $byIndex['PRIMARY'] ?? null,
        );
        self::assertSame(
            ['reference_kind', 'reference_value', 'billing_detail_id'],
            $byIndex['idx_billing_detail_references_lookup'] ?? null,
        );

        $marker = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
        $marker->execute(['013_billing_detail_references_backfill']);
        self::assertSame(hash('sha256', 'billing-detail-references-backfill-v1'), $marker->fetchColumn());
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
