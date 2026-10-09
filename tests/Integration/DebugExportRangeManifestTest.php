<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Logging\DebugExportService;
use App\Core\Logging\DebugMaintenance;
use App\Core\Security\Csrf;
use App\Modules\Settings\SystemSettingsController;
use App\Modules\Settings\SystemSettingsRepository;
use InvalidArgumentException;
use PharData;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class DebugExportRangeManifestTest extends TestCase
{
    public function testSelectedUtcRangeExportsOnlyMatchingDaysWithManifestAndChecksums(): void
    {
        [$root, $debugDir, $exportDir] = $this->roots('range');
        file_put_contents($debugDir . '/debug-2026-10-07.jsonl', "day-07\n");
        file_put_contents($debugDir . '/debug-2026-10-08.jsonl.gz', 'day-08-gzip');
        file_put_contents($debugDir . '/debug-2026-10-09.jsonl', "day-09\n");

        $service = new DebugExportService(
            $debugDir,
            $exportDir,
            appVersion: '2.0-test',
            schemaVersion: '004_sales.sql',
            redactionSchemaVersion: '1',
        );
        $result = $service->create('2026-10-08', '2026-10-09');

        self::assertNotNull($result);
        $archive = new PharData($result['path']);
        self::assertFalse($archive->offsetExists('debug-2026-10-07.jsonl'));
        self::assertTrue($archive->offsetExists('debug-2026-10-08.jsonl.gz'));
        self::assertTrue($archive->offsetExists('debug-2026-10-09.jsonl'));
        self::assertTrue($archive->offsetExists('manifest.json'));

        $manifestJson = $archive['manifest.json']->getContent();
        $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertSame('2.0-test', $manifest['app_version'] ?? null);
        self::assertSame('004_sales.sql', $manifest['schema_version'] ?? null);
        self::assertSame('1', $manifest['redaction_schema_version'] ?? null);
        self::assertSame('2026-10-08', $manifest['range_utc']['start'] ?? null);
        self::assertSame('2026-10-09', $manifest['range_utc']['end'] ?? null);

        $files = $manifest['files'] ?? null;
        self::assertIsArray($files);
        self::assertSame([
            [
                'name' => 'debug-2026-10-08.jsonl.gz',
                'sha256' => hash('sha256', 'day-08-gzip'),
            ],
            [
                'name' => 'debug-2026-10-09.jsonl',
                'sha256' => hash('sha256', "day-09\n"),
            ],
        ], $files);

        $this->removeTree($root);
    }

    public function testInvalidOrExcessiveRangesAreRejected(): void
    {
        [$root, $debugDir, $exportDir] = $this->roots('invalid');
        $service = new DebugExportService($debugDir, $exportDir);

        foreach ([
            ['not-a-date', '2026-10-09'],
            ['2026-10-10', '2026-10-09'],
            ['2026-01-01', '2026-10-09'],
        ] as [$start, $end]) {
            try {
                $service->create($start, $end);
                self::fail('Expected invalid debug export range to be rejected.');
            } catch (InvalidArgumentException) {
                self::assertTrue(true);
            }
        }

        $this->removeTree($root);
    }

    public function testHttpExportUsesDateRangeAndSettingsUiExposesRangeInputs(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedAdmin($pdo);
        [$root, $debugDir, $exportDir] = $this->roots('http');
        file_put_contents($debugDir . '/debug-2026-10-08.jsonl', "safe-08\n");
        file_put_contents($debugDir . '/debug-2026-10-09.jsonl', "safe-09\n");

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

        $show = $controller->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/settings/system'),
            (new ResponseFactory())->createResponse(),
        );
        $html = (string) $show->getBody();
        self::assertStringContainsString('name="debug_start_date"', $html);
        self::assertStringContainsString('name="debug_end_date"', $html);

        $invalid = (new ServerRequestFactory())
            ->createServerRequest('POST', '/settings/system/debug/export')
            ->withParsedBody([
                'csrf_token' => $csrf->token(),
                'debug_start_date' => '2026-10-10',
                'debug_end_date' => '2026-10-09',
            ]);
        self::assertSame(
            422,
            $controller->exportDebug($invalid, (new ResponseFactory())->createResponse())->getStatusCode(),
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/settings/system/debug/export')
            ->withParsedBody([
                'csrf_token' => $csrf->token(),
                'debug_start_date' => '2026-10-08',
                'debug_end_date' => '2026-10-08',
            ]);
        $response = $controller->exportDebug($request, (new ResponseFactory())->createResponse());
        self::assertSame(200, $response->getStatusCode());

        $exports = glob($exportDir . '/debug-export-*.zip') ?: [];
        self::assertCount(1, $exports);
        $archive = new PharData($exports[0]);
        self::assertTrue($archive->offsetExists('debug-2026-10-08.jsonl'));
        self::assertFalse($archive->offsetExists('debug-2026-10-09.jsonl'));
        self::assertTrue($archive->offsetExists('manifest.json'));

        $this->removeTree($root);
    }

    private function seedAdmin(\PDO $pdo): void
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Debug Range','debug-range')");
        $pdo->exec(
            "INSERT INTO users(id,email,password_hash,status) VALUES (1,'debug-range@example.test','unused','active')"
        );
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");
    }

    /** @return array{0:string,1:string,2:string} */
    private function roots(string $suffix): array
    {
        $root = sys_get_temp_dir() . '/erp-meli2-debug-export-range-' . $suffix . '-' . bin2hex(random_bytes(4));
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
        @rmdir($root);
    }
}
