<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Logging\DebugExportService;
use App\Core\Logging\DebugMaintenance;
use App\Core\Security\Csrf;
use App\Modules\Settings\SystemSettingsController;
use App\Modules\Settings\SystemSettingsRepository;
use PharData;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class DebugExportTest extends TestCase
{
    public function testServiceExportsOnlyRecognizedDvrFiles(): void
    {
        [$root, $debugDir, $exportDir] = $this->roots('service');
        file_put_contents($debugDir . '/debug-2026-10-09.jsonl', "today\n");
        file_put_contents($debugDir . '/debug-2026-10-08.jsonl.gz', 'gzip');
        file_put_contents($debugDir . '/notes.txt', 'do-not-export');

        $outside = $root . '/outside.txt';
        file_put_contents($outside, 'secret-outside');
        @symlink($outside, $debugDir . '/debug-2026-10-07.jsonl');

        $result = (new DebugExportService($debugDir, $exportDir))->create();

        self::assertNotNull($result);
        self::assertMatchesRegularExpression('/^debug-export-[A-Za-z0-9_-]{8,128}\.zip$/D', $result['filename']);
        self::assertSame($exportDir . '/' . $result['filename'], $result['path']);
        self::assertFileExists($result['path']);

        $archive = new PharData($result['path']);
        self::assertTrue($archive->offsetExists('debug-2026-10-09.jsonl'));
        self::assertTrue($archive->offsetExists('debug-2026-10-08.jsonl.gz'));
        self::assertFalse($archive->offsetExists('notes.txt'));
        self::assertFalse($archive->offsetExists('debug-2026-10-07.jsonl'));

        $this->removeTree($root);
    }

    public function testEmptyDebugHistoryCreatesNoExport(): void
    {
        [$root, $debugDir, $exportDir] = $this->roots('empty');
        file_put_contents($debugDir . '/notes.txt', 'ignore');

        self::assertNull((new DebugExportService($debugDir, $exportDir))->create());
        self::assertSame([], glob($exportDir . '/debug-export-*.zip') ?: []);

        $this->removeTree($root);
    }

    public function testAdminExportRequiresCsrfAndMemberIsForbidden(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAdmin($pdo);
        [$root, $debugDir, $exportDir] = $this->roots('http');
        file_put_contents($debugDir . '/debug-2026-10-09.jsonl', "safe\n");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;
        $csrf = new Csrf();
        $controller = new SystemSettingsController(
            $pdo,
            new SystemSettingsRepository($pdo),
            $csrf,
            new DebugMaintenance($debugDir, $exportDir),
            new DebugExportService($debugDir, $exportDir),
        );

        $badRequest = (new ServerRequestFactory())
            ->createServerRequest('POST', '/settings/system/debug/export')
            ->withParsedBody(['csrf_token' => 'invalid']);
        $bad = $controller->exportDebug($badRequest, (new ResponseFactory())->createResponse());
        self::assertSame(419, $bad->getStatusCode());

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/settings/system/debug/export')
            ->withParsedBody(['csrf_token' => $csrf->token()]);
        $response = $controller->exportDebug($request, (new ResponseFactory())->createResponse());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/zip', $response->getHeaderLine('Content-Type'));
        self::assertMatchesRegularExpression(
            '/^attachment; filename="debug-export-[A-Za-z0-9_-]{8,128}\.zip"$/D',
            $response->getHeaderLine('Content-Disposition'),
        );
        self::assertNotSame('', (string) $response->getBody());

        $pdo->exec("UPDATE company_users SET role='member' WHERE company_id=1 AND user_id=1");
        $forbidden = $controller->exportDebug($request, (new ResponseFactory())->createResponse());
        self::assertSame(403, $forbidden->getStatusCode());

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
        $root = sys_get_temp_dir() . '/erp-meli2-debug-export-' . $suffix . '-' . bin2hex(random_bytes(4));
        $debugDir = $root . '/debug';
        $exportDir = $root . '/exports';
        self::assertTrue(mkdir($debugDir, 0700, true));
        self::assertTrue(mkdir($exportDir, 0700, true));
        return [$root, $debugDir, $exportDir];
    }

    private function removeTree(string $root): void
    {
        foreach (['debug', 'exports'] as $name) {
            $dir = $root . '/' . $name;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob($dir . '/*') ?: [] as $path) {
                if (is_link($path) || is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        }
        @unlink($root . '/outside.txt');
        @rmdir($root);
    }
}
