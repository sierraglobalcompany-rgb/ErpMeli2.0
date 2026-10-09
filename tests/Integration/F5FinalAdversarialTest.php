<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Logging\AppLogger;
use App\Core\Logging\DebugMaintenance;
use App\Core\Logging\DebugRecorder;
use App\Core\Security\Csrf;
use App\Modules\Settings\SystemSettingsController;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class F5FinalAdversarialTest extends TestCase
{
    public function testCapEmitsOneNormalWarningAndSettingsShowsCapAlert(): void
    {
        [$root, $debugDir, $exportDir, $logsDir] = $this->roots('cap');
        file_put_contents($debugDir . '/existing.bin', str_repeat('x', 64));

        $recorder = new DebugRecorder(
            $debugDir,
            true,
            64,
            normalLogger: new AppLogger($logsDir),
        );
        $recorder->record('work.started', ['work_id' => 1]);
        $recorder->record('work.started', ['work_id' => 2]);

        $logFiles = glob($logsDir . '/app-*.log') ?: [];
        self::assertCount(1, $logFiles);
        $logContent = (string) file_get_contents($logFiles[0]);
        self::assertSame(1, substr_count($logContent, 'debug.cap.reached'));
        self::assertSame([], glob($debugDir . '/debug-*.jsonl') ?: []);

        $pdo = TestDatabase::reset();
        $this->seedAdmin($pdo);
        $pdo->exec('UPDATE system_settings SET debug_enabled=1, debug_max_mb=10 WHERE id=1');
        $capFile = $debugDir . '/debug-2026-10-09.jsonl';
        $handle = fopen($capFile, 'wb');
        self::assertIsResource($handle);
        self::assertTrue(ftruncate($handle, 10 * 1024 * 1024));
        fclose($handle);

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;
        $controller = new SystemSettingsController(
            $pdo,
            new SystemSettingsRepository($pdo),
            new Csrf(),
            new DebugMaintenance($debugDir, $exportDir),
        );
        $response = $controller->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/settings/system'),
            (new ResponseFactory())->createResponse(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Límite de almacenamiento debug alcanzado', (string) $response->getBody());

        $this->removeTree($root);
    }

    public function testRealWebhookHttpRouteUsesDebugRecorderWhenEnabled(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('F5 Webhook Runtime','f5-webhook-runtime')");
        $companyId = (int) $pdo->lastInsertId();
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES (?, '700000099', 'MCO', 'connected')"
        );
        $account->execute([$companyId]);
        $pdo->exec('UPDATE system_settings SET debug_enabled=1, debug_max_mb=100 WHERE id=1');

        $projectRoot = dirname(__DIR__, 2);
        $debugDir = $projectRoot . '/storage/debug';
        if (!is_dir($debugDir)) {
            self::assertTrue(mkdir($debugDir, 0700, true));
        }
        $debugFile = $debugDir . '/debug-' . gmdate('Y-m-d') . '.jsonl';
        $previousDebug = is_file($debugFile) ? file_get_contents($debugFile) : null;
        $before = is_string($previousDebug) ? $previousDebug : '';
        $previousClientId = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=987654321');
        $_ENV['MELI_CLIENT_ID'] = '987654321';

        try {
            $payload = json_encode([
                '_id' => 'evt-f5-runtime-debug-1',
                'resource' => '/orders/200000000099',
                'user_id' => 700000099,
                'topic' => 'orders_v2',
                'application_id' => 987654321,
                'attempts' => 1,
                'sent' => '2026-10-09T04:00:00.000Z',
                'received' => '2026-10-09T04:00:00.100Z',
            ], JSON_THROW_ON_ERROR);

            $request = (new ServerRequestFactory())
                ->createServerRequest('POST', '/webhooks/mercadolibre')
                ->withHeader('Content-Type', 'application/json');
            $request->getBody()->write($payload);
            $request->getBody()->rewind();

            $response = Bootstrap::create()->handle($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertFileExists($debugFile);
            $content = (string) file_get_contents($debugFile);
            self::assertGreaterThan(strlen($before), strlen($content));
            $appended = substr($content, strlen($before));
            self::assertStringContainsString('webhook.accepted', $appended);
            self::assertStringContainsString('evt-f5-runtime-debug-1', $appended);
            self::assertStringContainsString('work:', $appended);
        } finally {
            $this->restoreClientId($previousClientId);
            if ($previousDebug === null) {
                @unlink($debugFile);
            } else {
                file_put_contents($debugFile, $previousDebug);
            }
        }
    }

    private function seedAdmin(\PDO $pdo): void
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'F5 Admin','f5-admin')");
        $pdo->exec("INSERT INTO users(id,email,password_hash,status) VALUES (1,'f5-admin@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");
    }

    /** @return array{0:string,1:string,2:string,3:string} */
    private function roots(string $suffix): array
    {
        $root = sys_get_temp_dir() . '/erp-meli2-f5-final-' . $suffix . '-' . bin2hex(random_bytes(4));
        $debugDir = $root . '/debug';
        $exportDir = $root . '/exports';
        $logsDir = $root . '/logs';
        self::assertTrue(mkdir($debugDir, 0700, true));
        self::assertTrue(mkdir($exportDir, 0700, true));
        self::assertTrue(mkdir($logsDir, 0700, true));
        return [$root, $debugDir, $exportDir, $logsDir];
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

    private function restoreClientId(string|false $previousClientId): void
    {
        if ($previousClientId === false) {
            putenv('MELI_CLIENT_ID');
            unset($_ENV['MELI_CLIENT_ID']);
            return;
        }

        putenv('MELI_CLIENT_ID=' . $previousClientId);
        $_ENV['MELI_CLIENT_ID'] = $previousClientId;
    }
}
