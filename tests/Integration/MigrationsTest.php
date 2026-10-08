<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Migrator;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class MigrationsTest extends TestCase
{
    public function testFreshDatabaseContainsCoreTablesAndSettings(): void
    {
        $pdo = TestDatabase::reset();

        foreach (['schema_migrations', 'companies', 'users', 'company_users', 'system_settings'] as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
            self::assertSame($table, $stmt->fetchColumn());
        }

        $settings = $pdo->query('SELECT * FROM system_settings WHERE id = 1')->fetch();
        self::assertIsArray($settings);
        self::assertSame(0, (int) $settings['automation_enabled']);
        self::assertSame(0, (int) $settings['meli_writes_enabled']);
        self::assertSame(0, (int) $settings['debug_enabled']);
        self::assertSame(7, (int) $settings['debug_retention_days']);
        self::assertSame(100, (int) $settings['debug_max_mb']);
    }

    public function testMigratorIsIdempotent(): void
    {
        $pdo = TestDatabase::reset();
        $migrator = new Migrator();
        $migrator->migrate($pdo, dirname(__DIR__, 2) . '/database/migrations');

        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn());
    }
}
