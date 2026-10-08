<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class MeliCoreSchemaTest extends TestCase
{
    public function testF3CreatesOnlyTheRequiredMercadoLibreCoreTables(): void
    {
        $pdo = TestDatabase::reset();

        foreach (['meli_accounts', 'meli_tokens', 'meli_cooldowns', 'api_usage_daily'] as $table) {
            self::assertSame(
                $table,
                $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn(),
                'Missing F3 table: ' . $table,
            );
        }
    }

    public function testTokenTableHasCiphertextColumnsAndNoPlaintextTokenColumns(): void
    {
        $pdo = TestDatabase::reset();

        $columns = $pdo->query('SHOW COLUMNS FROM meli_tokens')->fetchAll();
        $names = array_map(
            static fn (array $column): string => (string) $column['Field'],
            $columns,
        );

        self::assertContains('access_token_cipher', $names);
        self::assertContains('refresh_token_cipher', $names);
        self::assertNotContains('access_token', $names);
        self::assertNotContains('refresh_token', $names);
        self::assertContains('expires_at', $names);
        self::assertContains('refresh_version', $names);
    }
}
