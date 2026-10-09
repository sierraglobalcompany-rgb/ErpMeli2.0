<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SalesSchemaTest extends TestCase
{
    public function testF4CreatesOnlyTheMinimumSalesSliceTables(): void
    {
        $pdo = TestDatabase::reset();

        foreach (['webhook_events', 'orders', 'order_items'] as $table) {
            self::assertSame(
                $table,
                $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($table))->fetchColumn(),
                'Missing F4 table: ' . $table,
            );
        }
    }

    public function testWebhookEventsStoreMetadataButNotRawPayloads(): void
    {
        $pdo = TestDatabase::reset();
        $columns = $pdo->query('SHOW COLUMNS FROM webhook_events')->fetchAll();
        $names = array_map(static fn (array $column): string => (string) $column['Field'], $columns);

        foreach (['event_id', 'topic', 'resource', 'external_user_id', 'application_id', 'attempts', 'sent_at', 'received_at'] as $name) {
            self::assertContains($name, $names);
        }

        foreach (['payload', 'payload_json', 'raw_payload', 'raw_body', 'body'] as $forbidden) {
            self::assertNotContains($forbidden, $names);
        }
    }

    public function testOrdersUseScopedNaturalIdentityAndDecimalMoney(): void
    {
        $pdo = TestDatabase::reset();

        $columns = $pdo->query('SHOW COLUMNS FROM orders')->fetchAll();
        $byName = [];
        foreach ($columns as $column) {
            $byName[(string) $column['Field']] = $column;
        }

        self::assertArrayHasKey('company_id', $byName);
        self::assertArrayHasKey('account_id', $byName);
        self::assertArrayHasKey('external_order_id', $byName);
        self::assertArrayHasKey('last_updated', $byName);
        self::assertArrayHasKey('total_amount', $byName);
        self::assertStringStartsWith('decimal(', strtolower((string) $byName['total_amount']['Type']));

        $indexes = $pdo->query('SHOW INDEX FROM orders')->fetchAll();
        $uniqueColumns = [];
        foreach ($indexes as $index) {
            if ((int) $index['Non_unique'] !== 0 || (string) $index['Key_name'] === 'PRIMARY') {
                continue;
            }
            $uniqueColumns[(string) $index['Key_name']][(int) $index['Seq_in_index']] = (string) $index['Column_name'];
        }

        $hasScopedNaturalKey = false;
        foreach ($uniqueColumns as $columnsForIndex) {
            ksort($columnsForIndex);
            if (array_values($columnsForIndex) === ['company_id', 'account_id', 'external_order_id']) {
                $hasScopedNaturalKey = true;
                break;
            }
        }

        self::assertTrue($hasScopedNaturalKey, 'Orders must be unique by company/account/external_order_id.');
    }

    public function testOrderItemsAreNormalizedAndDoNotStoreRemoteSnapshots(): void
    {
        $pdo = TestDatabase::reset();
        $columns = $pdo->query('SHOW COLUMNS FROM order_items')->fetchAll();
        $byName = [];
        foreach ($columns as $column) {
            $byName[(string) $column['Field']] = $column;
        }

        foreach (['order_id', 'external_item_id', 'variation_id', 'title', 'quantity', 'unit_price', 'currency_id'] as $name) {
            self::assertArrayHasKey($name, $byName);
        }
        self::assertStringStartsWith('decimal(', strtolower((string) $byName['quantity']['Type']));
        self::assertStringStartsWith('decimal(', strtolower((string) $byName['unit_price']['Type']));

        foreach (['payload', 'payload_json', 'raw_payload', 'raw_json', 'snapshot_json'] as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $byName);
        }
    }
}
