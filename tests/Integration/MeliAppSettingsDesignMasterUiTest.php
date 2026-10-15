<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliAppSettingsDesignMasterUiTest extends TestCase
{
    private string $runtimePath;
    private string $lockPath;
    private string|false $runtimeBefore;
    private string|false $lockBefore;
    private string|false $clientBefore;
    private string|false $secretBefore;
    private mixed $envClientBefore;
    private mixed $envSecretBefore;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->runtimePath = $root . '/storage/private/runtime.json';
        $this->lockPath = $root . '/storage/private/installed.lock';
        $this->runtimeBefore = is_file($this->runtimePath) ? file_get_contents($this->runtimePath) : false;
        $this->lockBefore = is_file($this->lockPath) ? file_get_contents($this->lockPath) : false;
        $this->clientBefore = getenv('MELI_CLIENT_ID');
        $this->secretBefore = getenv('MELI_CLIENT_SECRET');
        $this->envClientBefore = $_ENV['MELI_CLIENT_ID'] ?? null;
        $this->envSecretBefore = $_ENV['MELI_CLIENT_SECRET'] ?? null;
        putenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_SECRET');
        unset($_ENV['MELI_CLIENT_ID'], $_ENV['MELI_CLIENT_SECRET']);

        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status,is_system_admin) VALUES(1,'root-ui','Root UI','root-ui@example.test','unused','active',1)");
        $_SESSION = ['user_id' => 1, 'display_name' => 'Root UI'];

        $config = TestDatabase::config();
        file_put_contents($this->runtimePath, json_encode([
            'APP_ENV' => 'local',
            'APP_NAME' => 'ERP Meli 2.0',
            'APP_URL' => 'http://localhost',
            'APP_KEY' => base64_encode(str_repeat('k', 32)),
            'DB_HOST' => $config->dbHost,
            'DB_PORT' => $config->dbPort,
            'DB_NAME' => $config->dbName,
            'DB_USER' => $config->dbUser,
            'DB_PASSWORD' => $config->dbPassword,
            'MELI_CLIENT_ID' => 'client-design-master',
            'MELI_CLIENT_SECRET' => 'secret-must-never-render',
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->lockPath, 'installed');
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if ($this->runtimeBefore === false) {
            @unlink($this->runtimePath);
        } else {
            file_put_contents($this->runtimePath, $this->runtimeBefore);
        }
        if ($this->lockBefore === false) {
            @unlink($this->lockPath);
        } else {
            file_put_contents($this->lockPath, $this->lockBefore);
        }
        $this->restoreEnv('MELI_CLIENT_ID', $this->clientBefore, $this->envClientBefore);
        $this->restoreEnv('MELI_CLIENT_SECRET', $this->secretBefore, $this->envSecretBefore);
        parent::tearDown();
    }

    public function testGlobalMeliSettingsUseDesignMasterPanelsWithoutChangingGlobalOwner(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/settings/mercadolibre')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="simple-page meli-app-settings-workspace"', $html);
        self::assertStringContainsString('data-design-master-page="meli-app-settings"', $html);
        self::assertStringContainsString('class="panel panel-full meli-app-status-panel"', $html);
        self::assertStringContainsString('class="panel meli-app-credentials-panel"', $html);
        self::assertStringContainsString('class="panel meli-app-clear-panel"', $html);
        self::assertStringContainsString('client-design-master', $html);
        self::assertStringContainsString('Secret configurado', $html);
        self::assertStringNotContainsString('secret-must-never-render', $html);
        self::assertStringNotContainsString('href="/mercadolibre/health"', $html);
        self::assertStringNotContainsString('href="/mercadolibre/sync"', $html);
    }

    private function restoreEnv(string $key, string|false $getenvValue, mixed $envValue): void
    {
        putenv($getenvValue === false ? $key : $key . '=' . $getenvValue);
        if ($envValue === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $envValue;
        }
    }
}
