<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Core\Database\Connection;
use App\Work\WorkRepository;
use App\Work\WorkRunner;
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

        $processEnv = getenv();
        self::assertIsArray($processEnv);

        $process = proc_open(
            [PHP_BINARY, $path],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            array_replace($processEnv, $env),
        );

        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        self::assertSame(
            0,
            $exitCode,
            trim((string) $stdout . PHP_EOL . (string) $stderr),
        );
        self::assertSame('processed=0', trim((string) $stdout));
    }

    public function testWorkEntrypointComposesUnifiedSalesProcessor(): void
    {
        $path = dirname(__DIR__, 2) . '/bin/work.php';
        $source = file_get_contents($path);

        self::assertIsString($source);
        self::assertStringContainsString('use App\\Modules\\Sales\\SalesWorkProcessor;', $source);
        self::assertStringContainsString('use App\\Modules\\Sales\\Audit\\SalesAuditHandler;', $source);
        self::assertStringContainsString('use App\\Modules\\Sales\\Audit\\SalesAuditRepairHandler;', $source);
        self::assertStringContainsString('use App\\Modules\\Sales\\Audit\\SalesAuditRepository;', $source);
        self::assertStringContainsString('$audit = new SalesAuditRepository($pdo);', $source);
        self::assertStringContainsString('$processor = new SalesWorkProcessor(', $source);
        self::assertStringContainsString('new OrderSyncWorkProcessor(', $source);
        self::assertStringContainsString('new SalesAuditHandler($work, $audit, $client, $tokens)', $source);
        self::assertStringContainsString('new SalesAuditRepairHandler($audit, $work)', $source);
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

    public function testManualOneWithAutomationOffProcessesOnlyOldestGlobalWork(): void
    {
        $pdo = TestDatabase::reset();
        $work = new WorkRepository($pdo);
        $oldest = $work->enqueue(2, 20, 'company:2:account:20', 'unsupported.test', 'one', 'one', [], new \DateTimeImmutable('2000-01-01Z'));
        $work->enqueue(1, 10, 'company:1:account:10', 'billing.period.sync', 'two', 'two', [], new \DateTimeImmutable('2001-01-01Z'));
        [$exit, $output, $error] = $this->runCli(['--manual-one']);
        self::assertSame(0, $exit, $error);
        self::assertSame('processed=1', trim($output));
        self::assertSame('unsupported_work_type', $pdo->query('SELECT last_error_code FROM work_items WHERE id=' . $oldest)->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status='pending'")->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
    }

    public function testManualOneRoutesBillingThroughExistingHandlerWithoutRealHttp(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Synthetic','cli-synthetic')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES(10,1,'100','MCO','connected')");
        (new WorkRepository($pdo))->enqueue(1, 10, 'company:1:account:10', 'billing.period.sync', '2026-09-01:BILL', '2026-09-01:BILL',
            ['period_key'=>'2026-09-01','document_type'=>'BILL','from_id'=>'0'], new \DateTimeImmutable('2000-01-01Z'));
        [$exit, $output, $error] = $this->runCli(['--manual-one']);
        self::assertSame(0, $exit, $error);
        self::assertSame('processed=1', trim($output));
        self::assertSame('meli_oauth_attention', $pdo->query('SELECT last_error_code FROM work_items')->fetchColumn());
        self::assertSame('failed', $pdo->query('SELECT status FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
    }

    public function testManualOneRespectsFutureWorkAndEmptyQueue(): void
    {
        $pdo = TestDatabase::reset();
        self::assertSame([0, 'processed=0', ''], $this->trimmedCli());
        (new WorkRepository($pdo))->enqueue(null, null, 'app', 'unsupported.test', 'future', 'future', [], new \DateTimeImmutable('+1 hour UTC'));
        self::assertSame([0, 'processed=0', ''], $this->trimmedCli());
        self::assertSame('pending', $pdo->query('SELECT status FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT attempts FROM work_items')->fetchColumn());
    }

    public function testManualOneSharesAutomaticRunnerLock(): void
    {
        $pdo = TestDatabase::reset();
        (new WorkRepository($pdo))->enqueue(null, null, 'app', 'unsupported.test', 'one', 'one', []);
        $config = TestDatabase::config();
        $runner = new WorkRunner(Connection::fromConfig($config), 'erp_meli2.runner.' . $config->dbName);
        self::assertTrue($runner->acquireLock());
        try {
            self::assertSame([0, 'processed=0', ''], $this->trimmedCli());
            self::assertSame('pending', $pdo->query('SELECT status FROM work_items')->fetchColumn());
        } finally {
            $runner->releaseLock();
        }
        self::assertSame([0, 'processed=1', ''], $this->trimmedCli());
    }

    #[DataProvider('unsupportedArguments')]
    public function testTargetingAndLoopArgumentsAreRejectedBeforeClaims(string $argument): void
    {
        $pdo = TestDatabase::reset();
        (new WorkRepository($pdo))->enqueue(null, null, 'app', 'unsupported.test', 'one', 'one', []);
        [$exit, $output, $error] = $this->runCli([$argument]);
        self::assertSame(2, $exit);
        self::assertSame('', $output);
        self::assertStringContainsString('Usage:', $error);
        self::assertSame('pending', $pdo->query('SELECT status FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT attempts FROM work_items')->fetchColumn());
    }

    public static function unsupportedArguments(): iterable
    {
        foreach (['--manual-one=1', '--work-id=1', '--type=billing', '--company=1', '--account=10', '--until-done'] as $argument) {
            yield $argument => [$argument];
        }
    }

    /** @return array{int,string,string} */
    private function trimmedCli(): array
    {
        [$exit, $output, $error] = $this->runCli(['--manual-one']);
        return [$exit, trim($output), trim($error)];
    }

    /** @param list<string> $arguments @return array{int,string,string} */
    private function runCli(array $arguments): array
    {
        $config = TestDatabase::config();
        $environment = array_replace(getenv(), [
            'APP_ENV'=>'test','APP_KEY'=>'work-cli-test-key','DB_HOST'=>$config->dbHost,'DB_PORT'=>(string)$config->dbPort,
            'DB_NAME'=>$config->dbName,'DB_USER'=>$config->dbUser,'DB_PASSWORD'=>$config->dbPassword,
            'MELI_CLIENT_ID'=>'987654321','MELI_CLIENT_SECRET'=>'test-client-secret','REAL_MELI_HTTP'=>'0',
        ]);
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/work.php', ...$arguments], [1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__, 2), $environment);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $output, $error];
    }
}
