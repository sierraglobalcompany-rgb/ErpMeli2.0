<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Auth\PasswordService;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class FreshInstallTest extends TestCase
{
    public function testSupportedBootstrapCreatesFirstAuthorityAndRejectsRepeat(): void
    {
        $pdo = TestDatabase::reset();
        self::assertFileExists(dirname(__DIR__, 2) . '/bin/bootstrap-admin.php');
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin('First Company', 'first-company', 'ADMIN@example.test', 'test-secret-long');
        self::assertTrue($auth->login('admin@example.test', 'test-secret-long'));
        self::assertSame('admin', $_SESSION['role']);
        foreach (['companies', 'users', 'company_users'] as $table) {
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn());
        }
        try {
            $auth->bootstrapAdmin('First Company', 'first-company', 'admin@example.test', 'test-secret-long');
            self::fail('Repeat must fail clearly.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('already', $e->getMessage());
        }
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());
        self::assertFalse((new SystemSettingsRepository($pdo))->get()->meliWritesEnabled);
    }

    public function testInvalidBootstrapCreatesNoPartialAuthority(): void
    {
        $pdo = TestDatabase::reset();
        self::assertTrue(method_exists(AuthService::class, 'bootstrapAdmin'));
        foreach ([['', 'ok', 'admin@example.test', 'long-test-password'],
            ['Company', '../bad', 'admin@example.test', 'long-test-password'],
            ['Company', 'ok', 'bad', 'long-test-password'],
            ['Company', 'ok', 'admin@example.test', '']] as $input) {
            try {
                (new AuthService($pdo))->bootstrapAdmin(...$input);
                self::fail('Invalid bootstrap accepted.');
            } catch (\InvalidArgumentException) {
                foreach (['companies', 'users', 'company_users'] as $table) {
                    self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn());
                }
            }
        }
    }

    public function testBootstrapCliRunsMigrationsAndKeepsPasswordOutOfOutput(): void
    {
        $pdo = TestDatabase::reset();
        // Remove all migrated tables to prove the supported executable migrates a fresh DB.
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN) as $table) {
            $pdo->exec('DROP TABLE `' . str_replace('`', '', (string) $table) . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $run = static function (): array {
            $previous = getenv('ERP_BOOTSTRAP_PASSWORD');
            putenv('ERP_BOOTSTRAP_PASSWORD=test-cli-password-long');
            try {
                $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/bootstrap-admin.php', 'CLI Company', 'cli-company', 'cli@example.test'],
                    [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(__DIR__, 2), null, ['bypass_shell'=>true]);
                self::assertIsResource($process);
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                return [proc_close($process), $output];
            } finally {
                putenv($previous === false ? 'ERP_BOOTSTRAP_PASSWORD' : 'ERP_BOOTSTRAP_PASSWORD=' . $previous);
            }
        };
        [$exit, $output] = $run();
        self::assertSame(0, $exit, $output);
        self::assertStringNotContainsString('test-cli-password-long', $output);
        $app = Bootstrap::create();
        $factory = new ServerRequestFactory();
        $login = $app->handle($factory->createServerRequest('POST', '/login')->withParsedBody([
            'username'=>'cli@example.test', 'password'=>'test-cli-password-long',
            'csrf_token'=>(new \App\Core\Security\Csrf())->token(),
        ]));
        self::assertSame(303, $login->getStatusCode());
        self::assertSame('/', $login->getHeaderLine('Location'));
        self::assertSame('admin', $_SESSION['role']);
        $root = $app->handle($factory->createServerRequest('GET', '/'));
        self::assertSame('/dashboard', $root->getHeaderLine('Location'));
        $sales = $app->handle($factory->createServerRequest('GET', '/sales'));
        self::assertSame(200, $sales->getStatusCode());
        self::assertStringContainsString('CLI Company', (string) $sales->getBody());
        [$exit, $output] = $run();
        self::assertSame(1, $exit);
        self::assertStringContainsString('already', $output);
        foreach (['companies','users','company_users'] as $table) {
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn());
        }
    }

    public function testFreshInstallCanBootHealthLoginAndReadSettings(): void
    {
        $pdo = TestDatabase::reset();

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $hash = (new PasswordService())->hash('secret');
        $stmt = $pdo->prepare(
            "INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'admin@example.test','admin@example.test','admin@example.test',?,'active')"
        );
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");

        $app = Bootstrap::create();
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/health'));
        self::assertSame(200, $response->getStatusCode());

        self::assertTrue((new AuthService($pdo))->login('admin@example.test', 'secret'));
        self::assertFalse((new SystemSettingsRepository($pdo))->get()->meliWritesEnabled);
    }
}
