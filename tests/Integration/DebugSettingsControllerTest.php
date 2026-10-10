<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Logging\DebugMaintenance;
use App\Core\Security\Csrf;
use App\Modules\Settings\SystemSettingsController;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class DebugSettingsControllerTest extends TestCase
{
    public function testAdminScreenShowsUsageHistoryAndClearActionWithoutRemoteWritesControl(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAdmin($pdo);
        [$root, $debugDir, $exportDir] = $this->roots('show');
        file_put_contents($debugDir . '/debug-2026-10-09.jsonl', str_repeat('x', 25));
        file_put_contents($debugDir . '/debug-2026-10-08.jsonl.gz', str_repeat('y', 10));

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $csrf = new Csrf();
        $controller = new SystemSettingsController(
            $pdo,
            new SystemSettingsRepository($pdo),
            $csrf,
            new DebugMaintenance($debugDir, $exportDir),
        );
        $response = $controller->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/settings/system'),
            (new ResponseFactory())->createResponse(),
        );
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Uso debug', $html);
        self::assertStringContainsString('35 bytes', $html);
        self::assertStringContainsString('2026-10-09', $html);
        self::assertStringContainsString('2026-10-08', $html);
        self::assertStringContainsString('/settings/system/debug/clear', $html);
        self::assertStringContainsString($csrf->token(), $html);
        self::assertStringNotContainsString('name="meli_writes_enabled"', $html);
        self::assertStringNotContainsString('Escrituras Mercado Libre', $html);

        $this->removeTree($root);
    }

    public function testSettingsPostCannotEnableRemoteWritesBeforeF16(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAdmin($pdo);
        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $csrf = new Csrf();
        $settings = new SystemSettingsRepository($pdo);
        $controller = new SystemSettingsController($pdo, $settings, $csrf);
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/settings/system')
            ->withParsedBody([
                'csrf_token' => $csrf->token(),
                'automation_enabled' => '1',
                'meli_writes_enabled' => '1',
                'debug_enabled' => '1',
                'debug_retention_days' => '14',
                'debug_max_mb' => '200',
            ]);

        $response = $controller->update($request, (new ResponseFactory())->createResponse());
        $updated = $settings->get();

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/settings/system', $response->getHeaderLine('Location'));
        self::assertTrue($updated->automationEnabled);
        self::assertFalse($updated->meliWritesEnabled);
        self::assertTrue($updated->debugEnabled);
        self::assertSame(14, $updated->debugRetentionDays);
        self::assertSame(200, $updated->debugMaxMb);
    }

    public function testAdminClearRequiresCsrfAndDeletesOnlyRecognizedDebugFiles(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAdmin($pdo);
        [$root, $debugDir, $exportDir] = $this->roots('clear');
        $logsDir = $root . '/logs';
        self::assertTrue(mkdir($logsDir, 0700, true));

        $debugRaw = $debugDir . '/debug-2026-10-09.jsonl';
        $debugGzip = $debugDir . '/debug-2026-10-08.jsonl.gz';
        $unrelated = $debugDir . '/notes.txt';
        $log = $logsDir . '/app-2026-10-09.log';
        $export = $exportDir . '/debug-export-aaaaaaaa.zip';
        file_put_contents($debugRaw, 'raw');
        file_put_contents($debugGzip, 'gzip');
        file_put_contents($unrelated, 'keep');
        file_put_contents($log, 'keep-log');
        file_put_contents($export, 'keep-export');

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $csrf = new Csrf();
        $controller = new SystemSettingsController(
            $pdo,
            new SystemSettingsRepository($pdo),
            $csrf,
            new DebugMaintenance($debugDir, $exportDir),
        );

        $badRequest = (new ServerRequestFactory())
            ->createServerRequest('POST', '/settings/system/debug/clear')
            ->withParsedBody(['csrf_token' => 'invalid']);
        $badResponse = $controller->clearDebug($badRequest, (new ResponseFactory())->createResponse());
        self::assertSame(419, $badResponse->getStatusCode());
        self::assertFileExists($debugRaw);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/settings/system/debug/clear')
            ->withParsedBody(['csrf_token' => $csrf->token()]);
        $response = $controller->clearDebug($request, (new ResponseFactory())->createResponse());

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/settings/system', $response->getHeaderLine('Location'));
        self::assertFileDoesNotExist($debugRaw);
        self::assertFileDoesNotExist($debugGzip);
        self::assertFileExists($unrelated);
        self::assertFileExists($log);
        self::assertFileExists($export);

        $this->removeTree($root);
    }

    public function testNonAdminCannotViewOrClearDebugStorage(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAdmin($pdo);
        $pdo->exec("UPDATE company_users SET role='member' WHERE company_id=1 AND user_id=1");
        [$root, $debugDir, $exportDir] = $this->roots('member');
        $debugRaw = $debugDir . '/debug-2026-10-09.jsonl';
        file_put_contents($debugRaw, 'keep');

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $csrf = new Csrf();
        $controller = new SystemSettingsController(
            $pdo,
            new SystemSettingsRepository($pdo),
            $csrf,
            new DebugMaintenance($debugDir, $exportDir),
        );

        $show = $controller->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/settings/system'),
            (new ResponseFactory())->createResponse(),
        );
        self::assertSame(403, $show->getStatusCode());

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/settings/system/debug/clear')
            ->withParsedBody(['csrf_token' => $csrf->token()]);
        $clear = $controller->clearDebug($request, (new ResponseFactory())->createResponse());
        self::assertSame(403, $clear->getStatusCode());
        self::assertFileExists($debugRaw);

        $this->removeTree($root);
    }

    private function seedAdmin(\PDO $pdo): void
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Debug Admin','debug-admin')");
        $pdo->exec(
            "INSERT INTO users(id,email,password_hash,status) VALUES (1,'debug-admin@example.test','unused','active')"
        );
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");
    }

    /** @return array{0:string,1:string,2:string} */
    private function roots(string $suffix): array
    {
        $root = sys_get_temp_dir() . '/erp-meli2-debug-settings-' . $suffix . '-' . bin2hex(random_bytes(4));
        $debugDir = $root . '/debug';
        $exportDir = $root . '/exports';
        self::assertTrue(mkdir($debugDir, 0700, true));
        self::assertTrue(mkdir($exportDir, 0700, true));
        return [$root, $debugDir, $exportDir];
    }

    private function removeTree(string $root): void
    {
        foreach (['debug', 'exports', 'logs'] as $name) {
            $dir = $root . '/' . $name;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($dir);
        }
        @rmdir($root);
    }
}
