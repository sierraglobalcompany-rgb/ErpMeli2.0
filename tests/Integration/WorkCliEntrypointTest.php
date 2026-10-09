<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class WorkCliEntrypointTest extends TestCase
{
    public function testWorkEntrypointExitsCleanlyWhenAutomationIsDisabled(): void
    {
        TestDatabase::reset();
        $path = dirname(__DIR__, 2) . '/bin/work.php';

        self::assertFileExists($path);

        $config = TestDatabase::config();
        $env = [
            'APP_ENV' => 'test',
            'APP_URL' => $config->appUrl,
            'APP_KEY' => 'work-cli-test-key',
            'DB_HOST' => $config->dbHost,
            'DB_PORT' => (string) $config->dbPort,
            'DB_NAME' => $config->dbName,
            'DB_USER' => $config->dbUser,
            'DB_PASSWORD' => $config->dbPassword,
            'MELI_CLIENT_ID' => '987654321',
            'MELI_CLIENT_SECRET' => 'test-client-secret',
            'REAL_MELI_HTTP' => '0',
        ];

        $prefix = '';
        foreach ($env as $name => $value) {
            $prefix .= $name . '=' . escapeshellarg($value) . ' ';
        }

        $output = [];
        $exitCode = 1;
        exec(
            $prefix . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1',
            $output,
            $exitCode,
        );

        self::assertSame(0, $exitCode, implode("\n", $output));
        self::assertSame('processed=0', trim(implode("\n", $output)));
    }

    public function testWorkEntrypointComposesUnifiedSalesProcessor(): void
    {
        $path = dirname(__DIR__, 2) . '/bin/work.php';
        $source = file_get_contents($path);

        self::assertIsString($source);
        self::assertStringContainsString('use App\\Modules\\Sales\\SalesWorkProcessor;', $source);
        self::assertStringContainsString('use App\\Modules\\Sales\\ReconcileOrders\\ReconcileOrdersHandler;', $source);
        self::assertStringContainsString('$processor = new SalesWorkProcessor(', $source);
        self::assertStringContainsString('new OrderSyncWorkProcessor(', $source);
        self::assertStringContainsString('new ReconcileOrdersHandler($pdo, $work, $client, $tokens)', $source);
        self::assertStringNotContainsString('$processor = new OrderSyncWorkProcessor(', $source);
    }

    public function testWorkRunnerLockIsScopedByConfiguredDatabaseName(): void
    {
        $path = dirname(__DIR__, 2) . '/bin/work.php';
        $source = file_get_contents($path);

        self::assertIsString($source);
        self::assertStringContainsString("'erp_meli2.runner.' . \$config->dbName", $source);
        self::assertStringNotContainsString("new WorkRunner(\$runnerLockConnection, 'erp_meli2.runner')", $source);
    }
}
