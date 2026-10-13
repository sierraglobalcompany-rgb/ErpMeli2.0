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

    public function testRealCliUsesTestOnlyLoopbackOverrideAndIgnoresItOutsideTest(): void
    {
        $pdo = $this->connectedCliFixture();
        $this->withSimulator('ORDER_HAPPY_EXACT', function (string $baseUrl, string $directory) use ($pdo): void {
            $work = new WorkRepository($pdo);
            $id = $work->enqueue(1, 10, 'company:1:account:10', 'order.sync', '9007199254740993', 'loopback-order',
                ['order_id'=>'9007199254740993'], new \DateTimeImmutable('2000-01-01Z'));
            [$exit, $output, $error] = $this->runCli(['--manual-one'], ['MELI_TEST_BASE_URL'=>$baseUrl]);
            self::assertSame(0, $exit, $error);
            self::assertSame('processed=1', trim($output));
            $trace = file($directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            self::assertIsArray($trace);
            self::assertCount(1, $trace, 'Real CLI must reach existing loopback MLSIM.');
            $request = json_decode($trace[0], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('orders.get', $request['operation']);
            self::assertSame('GET', $request['method']);
            self::assertTrue($request['matched']);
            self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id=' . $id)->fetchColumn());
            self::assertSame('90071992547409.1234', $pdo->query('SELECT total_amount FROM orders')->fetchColumn());
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
            self::assertSame(0, (int) $pdo->query('SELECT meli_writes_enabled FROM system_settings')->fetchColumn());

            // Local root must ignore even a valid loopback override and keep the official host blocked.
            $other = $work->enqueue(1, 10, 'company:1:account:10', 'order.sync', '9007199254740994', 'non-test-order',
                ['order_id'=>'9007199254740994'], new \DateTimeImmutable('2000-01-01Z'));
            [$exit, $output, $error] = $this->runCli(['--manual-one'], ['APP_ENV'=>'local','MELI_TEST_BASE_URL'=>$baseUrl]);
            self::assertSame(0, $exit, $error);
            self::assertSame('processed=1', trim($output));
            self::assertSame('pending', $pdo->query('SELECT status FROM work_items WHERE id=' . $other)->fetchColumn());
            self::assertSame($trace, file($directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
            $default = $work->enqueue(1, 10, 'company:1:account:10', 'order.sync', '9007199254740995', 'default-test-order',
                ['order_id'=>'9007199254740995'], new \DateTimeImmutable('2000-01-01Z'));
            [$exit, $output, $error] = $this->runCli(['--manual-one']);
            self::assertSame(0, $exit, $error);
            self::assertSame('processed=1', trim($output));
            self::assertSame('pending', $pdo->query('SELECT status FROM work_items WHERE id=' . $default)->fetchColumn());
            self::assertSame($trace, file($directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
            // Production ignores invalid overrides too; empty automatic run performs no network.
            [$exit, $output, $error] = $this->runCli([], ['APP_ENV'=>'production','MELI_TEST_BASE_URL'=>'invalid']);
            self::assertSame(0, $exit, $error);
            self::assertSame('processed=0', trim($output));
            self::assertSame($trace, file($directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        });
    }

    public function testWebSignalThroughRealCliPersistsSaleAndPureReadDetail(): void
    {
        $pdo = $this->connectedCliFixture();
        $previous = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=987654321');
        try {
            $this->withSimulator('ORDER_HAPPY_EXACT', function (string $baseUrl, string $directory) use ($pdo): void {
                $factory = new \Slim\Psr7\Factory\ServerRequestFactory();
                $webhook = \App\Bootstrap::create()->handle($factory->createServerRequest('POST', '/webhooks/mercadolibre')->withParsedBody([
                    '_id'=>'lc3-sales-signal', 'resource'=>'/orders/9007199254740993', 'user_id'=>100,
                    'topic'=>'orders_v2', 'application_id'=>987654321, 'attempts'=>1,
                    'sent'=>'2026-09-10T12:00:00Z', 'received'=>'2026-09-10T12:00:00Z',
                ]));
                self::assertSame(200, $webhook->getStatusCode());
                self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn());
                [$exit, $output, $error] = $this->runCli(['--manual-one'], ['MELI_TEST_BASE_URL'=>$baseUrl]);
                self::assertSame(0, $exit, $error);
                self::assertSame('processed=1', trim($output));
                self::assertSame('done', $pdo->query('SELECT status FROM work_items')->fetchColumn());
                self::assertSame('90071992547409.1234', $pdo->query('SELECT total_amount FROM orders')->fetchColumn());
                $this->assertSimulatorCalls($directory, ['orders.get']);

                $before = [];
                foreach (['orders','order_items','billing_periods','billing_details','invoicing_artifacts','invoicing_service_keys','work_items','api_usage_daily'] as $table) {
                    $before[$table] = $pdo->query('SELECT * FROM ' . $table)->fetchAll(\PDO::FETCH_ASSOC);
                }
                // Bootstrap constructs distinct connections: global counters observe writes across them.
                $counterSql = "SHOW GLOBAL STATUS WHERE Variable_name IN ('Com_insert','Com_update','Com_delete','Com_replace','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Com_rename_table')";
                $writes = $pdo->query($counterSql)->fetchAll(\PDO::FETCH_ASSOC);
                $app = \App\Bootstrap::create();
                $list = $app->handle($factory->createServerRequest('GET', '/sales'));
                self::assertSame(200, $list->getStatusCode());
                self::assertStringContainsString('9007199254740993', (string) $list->getBody());
                $detail = $app->handle($factory->createServerRequest('GET', '/sales/9007199254740993'));
                self::assertSame(200, $detail->getStatusCode());
                $html = (string) $detail->getBody();
                self::assertStringContainsString('Reconciliación', $html);
                self::assertStringContainsString('Factura / Postventa', $html);
                self::assertStringContainsString('ORDER_READY', $html);
                self::assertStringContainsString('<dt>Postventa</dt><dd>NONE</dd>', $html);
                self::assertSame($writes, $pdo->query($counterSql)->fetchAll(\PDO::FETCH_ASSOC));
                foreach ($before as $table => $rows) {
                    self::assertSame($rows, $pdo->query('SELECT * FROM ' . $table)->fetchAll(\PDO::FETCH_ASSOC), $table . ' must remain unchanged.');
                }
                $this->assertSimulatorCalls($directory, ['orders.get']);
                self::assertSame(0, (int) $pdo->query('SELECT meli_writes_enabled FROM system_settings')->fetchColumn());
            });
        } finally {
            putenv($previous === false ? 'MELI_CLIENT_ID' : 'MELI_CLIENT_ID=' . $previous);
        }
    }

    public function testOperatorAuditThroughRealCliPersistsAndDisplaysResult(): void
    {
        $pdo = $this->connectedCliFixture();
        $this->withSimulator('AUDIT_REPAIR_1', function (string $baseUrl, string $directory) use ($pdo): void {
            $factory = new \Slim\Psr7\Factory\ServerRequestFactory();
            $start = \App\Bootstrap::create()->handle($factory->createServerRequest('POST', '/sales/audits')->withParsedBody([
                'account_id'=>'10', 'period_key'=>'2026-09', 'csrf_token'=>(new \App\Core\Security\Csrf())->token(),
            ]));
            self::assertSame(303, $start->getStatusCode());
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_runs')->fetchColumn());
            for ($step = 0; $step < 5; $step++) {
                $this->runNextPendingCli($pdo, $baseUrl);
            }
            self::assertSame('valid', $pdo->query('SELECT status FROM sales_audit_runs')->fetchColumn());
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
            self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status <> 'done'")->fetchColumn());
            $this->assertSimulatorCalls($directory, ['orders.search','orders.get','orders.search']);
            $visible = \App\Bootstrap::create()->handle($factory->createServerRequest('GET', '/sales'));
            self::assertSame(200, $visible->getStatusCode());
            self::assertStringContainsString('<td>VALID</td>', (string) $visible->getBody());
            $this->assertSimulatorCalls($directory, ['orders.search','orders.get','orders.search']);
            self::assertSame(0, (int) $pdo->query('SELECT meli_writes_enabled FROM system_settings')->fetchColumn());
        });
    }

    public function testOperatorBillingThroughRealCliUsesSameWorkAndDisplaysFacts(): void
    {
        $pdo = $this->connectedCliFixture();
        $this->withSimulator('BILLING_TWO_PAGES', function (string $baseUrl, string $directory) use ($pdo): void {
            $factory = new \Slim\Psr7\Factory\ServerRequestFactory();
            $start = \App\Bootstrap::create()->handle($factory->createServerRequest('POST', '/billing/syncs')->withParsedBody([
                'account_id'=>'10', 'period_key'=>'2026-09-01', 'document_type'=>'BILL',
                'csrf_token'=>(new \App\Core\Security\Csrf())->token(),
            ]));
            self::assertSame(303, $start->getStatusCode());
            $ids = $pdo->query('SELECT id FROM work_items')->fetchAll(\PDO::FETCH_COLUMN);
            self::assertCount(1, $ids);
            for ($step = 0; $step < 3; $step++) {
                $this->runNextPendingCli($pdo, $baseUrl);
                self::assertSame($ids, $pdo->query('SELECT id FROM work_items')->fetchAll(\PDO::FETCH_COLUMN));
            }
            self::assertSame('caught_up', $pdo->query('SELECT sync_state FROM billing_periods')->fetchColumn());
            self::assertSame('done', $pdo->query('SELECT status FROM work_items')->fetchColumn());
            self::assertSame(['90071992547409.1234','1.2345'], $pdo->query('SELECT detail_amount FROM billing_details ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
            $this->assertSimulatorCalls($directory, ['billing.period.details','billing.period.details','billing.period.details']);
            $visible = \App\Bootstrap::create()->handle($factory->createServerRequest('GET', '/billing')->withQueryParams([
                'account_id'=>'10', 'period_key'=>'2026-09-01', 'document_type'=>'BILL',
            ]));
            self::assertSame(200, $visible->getStatusCode());
            self::assertStringContainsString('Al día con la fuente consultada', (string) $visible->getBody());
            self::assertStringContainsString('102', (string) $visible->getBody());
            $this->assertSimulatorCalls($directory, ['billing.period.details','billing.period.details','billing.period.details']);
            self::assertSame(0, (int) $pdo->query('SELECT meli_writes_enabled FROM system_settings')->fetchColumn());
        });
    }

    private function runNextPendingCli(\PDO $pdo, string $baseUrl): void
    {
        $id = $pdo->query("SELECT id FROM work_items WHERE status='pending' ORDER BY available_at,id LIMIT 1")->fetchColumn();
        self::assertNotFalse($id);
        // Advance only the disposable fixture's due time, as the existing MLSIM test does.
        $pdo->exec("UPDATE work_items SET available_at='2000-01-01' WHERE id=" . (int) $id);
        [$exit, $output, $error] = $this->runCli(['--manual-one'], ['MELI_TEST_BASE_URL'=>$baseUrl]);
        self::assertSame(0, $exit, $error);
        self::assertSame('processed=1', trim($output));
    }

    /** @param list<string> $operations */
    private function assertSimulatorCalls(string $directory, array $operations): void
    {
        $lines = file($directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        $trace = array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
        self::assertSame($operations, array_column($trace, 'operation'));
        self::assertSame(array_fill(0, count($operations), 'GET'), array_column($trace, 'method'));
        self::assertSame(array_fill(0, count($operations), true), array_column($trace, 'matched'));
    }

    private function connectedCliFixture(): \PDO
    {
        $pdo = TestDatabase::reset();
        $cipher = new \App\Integrations\MercadoLibre\Auth\TokenCipher('work-cli-test-key');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Synthetic','cli-loopback')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES(10,1,'100','MCO','connected')");
        $tokens = $pdo->prepare('INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) VALUES(10,?,?,?,0)');
        $tokens->execute([$cipher->encrypt('synthetic-access'), $cipher->encrypt('synthetic-refresh'), '2030-01-01 00:00:00']);
        $pdo->exec("INSERT INTO users(id,email,password_hash,status) VALUES(1,'cli-cert@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $_SESSION = ['user_id'=>1,'company_id'=>1];
        return $pdo;
    }

    /** @param callable(string,string):void $test */
    private function withSimulator(string $scenario, callable $test): void
    {
        $directory = sys_get_temp_dir() . '/erp-meli2-mlsim-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($directory, 0700));
        file_put_contents($directory . '/requests.jsonl', '');
        $server = null;
        try {
            $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
            self::assertIsResource($reservation);
            $address = stream_socket_get_name($reservation, false);
            self::assertIsString($address);
            fclose($reservation);
            $baseUrl = 'http://' . $address;
            $server = proc_open([PHP_BINARY, '-S', $address, dirname(__DIR__) . '/Support/MeliSim/router.php'],
                [0=>['pipe','r'],1=>['file',$directory.'/stdout.txt','w'],2=>['file',$directory.'/stderr.txt','w']],
                $pipes, dirname(__DIR__, 2), array_replace(getenv(), ['APP_ENV'=>'test','MLSIM_SCENARIO'=>$scenario,'MLSIM_ROOT'=>$directory]));
            self::assertIsResource($server);
            fclose($pipes[0]);
            $ready = false;
            $deadline = hrtime(true) + 3_000_000_000;
            do {
                $socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
                if (is_resource($socket)) {
                    fclose($socket);
                    if (proc_get_status($server)['running'] && str_contains((string) file_get_contents($directory . '/stderr.txt'), 'started')) {
                        $ready = true;
                        break;
                    }
                }
                usleep(10_000);
            } while (hrtime(true) < $deadline);
            self::assertTrue($ready, 'MLSIM startup failed.');
            $test($baseUrl, $directory);
        } finally {
            if (is_resource($server)) { proc_terminate($server); proc_close($server); }
            if (realpath(dirname($directory)) !== realpath(sys_get_temp_dir()) || is_link($directory)
                || preg_match('/^erp-meli2-mlsim-[a-f0-9]{16}$/D', basename($directory)) !== 1) {
                throw new \RuntimeException('MLSIM_UNSAFE_CLEANUP');
            }
            foreach (['requests.jsonl','stdout.txt','stderr.txt'] as $name) {
                if (is_file($directory . '/' . $name)) { unlink($directory . '/' . $name); }
            }
            rmdir($directory);
        }
    }

    #[DataProvider('invalidTestBaseUrls')]
    public function testInvalidTestBaseUrlFailsBeforeClaimOrHttp(string $url): void
    {
        $pdo = TestDatabase::reset();
        (new WorkRepository($pdo))->enqueue(null, null, 'app', 'unsupported.test', 'one', 'one', []);
        [$exit, $output, $error] = $this->runCli(['--manual-one'], ['MELI_TEST_BASE_URL'=>$url]);
        self::assertNotSame(0, $exit);
        self::assertStringContainsString('MELI_TEST_BASE_URL must be an HTTP literal loopback URL with a valid port.', $output . $error);
        self::assertSame('pending', $pdo->query('SELECT status FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT attempts FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
    }

    public static function invalidTestBaseUrls(): iterable
    {
        foreach (['http://example.invalid:8080','http://localhost:8080','http://127.0.0.1.evil.invalid:8080',
            'http://user@127.0.0.1:8080','http://127.0.0.1:8080/path','http://127.0.0.1:8080?query=1',
            'https://127.0.0.1:8080','http://127.0.0.1:0','http://127.0.0.1:65536'] as $url) {
            yield $url => [$url];
        }
    }

    /** @param list<string> $arguments @param array<string,string> $overrides @return array{int,string,string} */
    private function runCli(array $arguments, array $overrides = []): array
    {
        $config = TestDatabase::config();
        $environment = array_replace(getenv(), [
            'APP_ENV'=>'test','APP_KEY'=>'work-cli-test-key','DB_HOST'=>$config->dbHost,'DB_PORT'=>(string)$config->dbPort,
            'DB_NAME'=>$config->dbName,'DB_USER'=>$config->dbUser,'DB_PASSWORD'=>$config->dbPassword,
            'MELI_CLIENT_ID'=>'987654321','MELI_CLIENT_SECRET'=>'test-client-secret','REAL_MELI_HTTP'=>'0','MELI_TEST_BASE_URL'=>'',
        ], $overrides);
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/work.php', ...$arguments], [1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__, 2), $environment);
        self::assertIsResource($process);
        $output = (string) stream_get_contents($pipes[1]);
        $error = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $output, $error];
    }
}
