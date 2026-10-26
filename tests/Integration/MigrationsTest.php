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
        $before = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();

        (new Migrator())->migrate($pdo, dirname(__DIR__, 2) . '/database/migrations');

        $after = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        self::assertGreaterThan(0, $before);
        self::assertSame($before, $after);
    }

    public function testMigratorAcceptsOnlyLineEndingChangesWithoutChangingStoredChecksum(): void
    {
        $pdo = TestDatabase::reset();
        $directory = sys_get_temp_dir() . '/erp2-migration-eol-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));

        $first = $directory . '/a_probe.sql';
        $second = $directory . '/b_probe.sql';
        $firstSql = "CREATE TABLE migration_eol_a (\n    id INT NOT NULL PRIMARY KEY\n) ENGINE=InnoDB;\n";
        $secondSql = "CREATE TABLE migration_eol_b (\n    id INT NOT NULL PRIMARY KEY\n) ENGINE=InnoDB;\n";
        $crlf = static fn(string $value): string => str_replace("\n", "\r\n", $value);
        $migrator = new Migrator();

        try {
            file_put_contents($first, $crlf($firstSql));
            file_put_contents($second, $secondSql);
            $migrator->migrate($pdo, $directory);
            $before = $pdo->query("SELECT version, checksum FROM schema_migrations WHERE version IN ('a_probe.sql', 'b_probe.sql') ORDER BY version")
                ->fetchAll(\PDO::FETCH_KEY_PAIR);
            self::assertSame(hash('sha256', $crlf($firstSql)), $before['a_probe.sql']);
            self::assertSame(hash('sha256', $secondSql), $before['b_probe.sql']);

            file_put_contents($first, $firstSql);
            file_put_contents($second, $crlf($secondSql));
            $migrator->migrate($pdo, $directory);
            $after = $pdo->query("SELECT version, checksum FROM schema_migrations WHERE version IN ('a_probe.sql', 'b_probe.sql') ORDER BY version")
                ->fetchAll(\PDO::FETCH_KEY_PAIR);
            self::assertSame($before, $after, 'Original stored checksums must never be rewritten.');

            file_put_contents($first, str_replace('id INT', 'id BIGINT', $firstSql));
            try {
                $migrator->migrate($pdo, $directory);
                self::fail('A real migration SQL change must fail.');
            } catch (\RuntimeException $error) {
                self::assertSame('Applied migration changed: a_probe.sql', $error->getMessage());
            }
        } finally {
            if (is_file($first)) unlink($first);
            if (is_file($second)) unlink($second);
            rmdir($directory);
        }
    }
}
