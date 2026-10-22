<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Security\Csrf;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliAppSettingsTest extends TestCase
{
    private \PDO $pdo;
    private string $runtimePath;
    private string $lockPath;
    private string|false $runtimeBefore;
    private string|false $lockBefore;
    private string|false $envClientBefore;
    private string|false $envSecretBefore;
    private mixed $envArrayClientBefore;
    private mixed $envArraySecretBefore;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);
        $this->runtimePath = $root . '/storage/private/runtime.json';
        $this->lockPath = $root . '/storage/private/installed.lock';
        $this->runtimeBefore = is_file($this->runtimePath) ? file_get_contents($this->runtimePath) : false;
        $this->lockBefore = is_file($this->lockPath) ? file_get_contents($this->lockPath) : false;
        $this->envClientBefore = getenv('MELI_CLIENT_ID');
        $this->envSecretBefore = getenv('MELI_CLIENT_SECRET');
        $this->envArrayClientBefore = $_ENV['MELI_CLIENT_ID'] ?? null;
        $this->envArraySecretBefore = $_ENV['MELI_CLIENT_SECRET'] ?? null;
        putenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_SECRET');
        unset($_ENV['MELI_CLIENT_ID'], $_ENV['MELI_CLIENT_SECRET']);

        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status,is_system_admin) VALUES(1,'root-admin','Root Admin','root@example.test','not-used','active',1)");
        $_SESSION['user_id'] = 1;

        $config = TestDatabase::config();
        $runtime = [
            'APP_ENV' => 'local',
            'APP_NAME' => 'ERP Meli 2.0',
            'APP_URL' => 'http://localhost',
            'APP_KEY' => base64_encode(str_repeat('k', 32)),
            'DB_HOST' => $config->dbHost,
            'DB_PORT' => $config->dbPort,
            'DB_NAME' => $config->dbName,
            'DB_USER' => $config->dbUser,
            'DB_PASSWORD' => $config->dbPassword,
            'MELI_CLIENT_ID' => 'client-old',
            'MELI_CLIENT_SECRET' => 'secret-old-value',
        ];
        file_put_contents($this->runtimePath, json_encode($runtime, JSON_THROW_ON_ERROR));
        file_put_contents($this->lockPath, 'installed');
    }

    protected function tearDown(): void
    {
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

        $this->restoreEnv('MELI_CLIENT_ID', $this->envClientBefore, $this->envArrayClientBefore);
        $this->restoreEnv('MELI_CLIENT_SECRET', $this->envSecretBefore, $this->envArraySecretBefore);
        parent::tearDown();
    }

    public function testSystemAdminCanSeeEffectiveSettingsButNeverStoredSecret(): void
    {
        $response = $this->request('GET');
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Aplicación Mercado Libre', $html);
        self::assertStringContainsString('client-old', $html);
        self::assertStringContainsString('Secret configurado', $html);
        self::assertStringNotContainsString('secret-old-value', $html);
        self::assertStringContainsString('http://localhost/oauth/mercadolibre/callback', $html);
        self::assertStringContainsString('http://localhost/webhooks/mercadolibre', $html);
    }

    public function testMeliSettingsUsesSharedGlobalVisualStructure(): void
    {
        $html = (string) $this->request('GET')->getBody();

        self::assertStringContainsString('href="/assets/app.css"', $html);
        self::assertStringContainsString('class="app-shell-chrome"', $html);
        self::assertStringContainsString('id="main-content"', $html);
        self::assertStringContainsString('class="meli-app-settings-workspace"', $html);
        self::assertStringNotContainsString('class="simple-nav"', $html);
        self::assertStringContainsString('class="page-header"', $html);
        self::assertStringContainsString('class="settings-grid"', $html);
        self::assertStringContainsString('class="panel', $html);
    }

    public function testGlobalAdminNavigationMakesMeliSettingsDiscoverable(): void
    {
        $factory = new ServerRequestFactory();
        foreach (['/system/companies', '/system/users', '/settings/system'] as $path) {
            $response = Bootstrap::create()->handle($factory->createServerRequest('GET', $path));
            self::assertSame(200, $response->getStatusCode(), $path);
            self::assertStringContainsString('href="/settings/mercadolibre"', (string) $response->getBody(), $path);
        }
    }

    public function testCompanyAdminWithoutSystemAuthorityIsDenied(): void
    {
        $this->pdo->exec('UPDATE users SET is_system_admin=0 WHERE id=1');
        self::assertSame(403, $this->request('GET')->getStatusCode());
    }

    public function testSavePreservesNonMeliRuntimeAndBlankSecretKeepsExistingSecret(): void
    {
        $before = $this->runtime();
        $csrf = (new Csrf())->token();

        $saved = $this->request('POST', [
            'action' => 'save',
            'csrf_token' => $csrf,
            'client_id' => 'client-new',
            'client_secret' => 'secret-new-value',
        ]);
        self::assertSame(303, $saved->getStatusCode());
        self::assertSame('/settings/mercadolibre', $saved->getHeaderLine('Location'));

        $after = $this->runtime();
        self::assertSame('client-new', $after['MELI_CLIENT_ID']);
        self::assertSame('secret-new-value', $after['MELI_CLIENT_SECRET']);
        foreach (array_diff(array_keys($before), ['MELI_CLIENT_ID', 'MELI_CLIENT_SECRET']) as $key) {
            self::assertSame($before[$key], $after[$key], 'Non-Meli runtime key changed: ' . $key);
        }

        $kept = $this->request('POST', [
            'action' => 'save',
            'csrf_token' => $csrf,
            'client_id' => 'client-new',
            'client_secret' => '',
        ]);
        self::assertSame(303, $kept->getStatusCode());
        self::assertSame('secret-new-value', $this->runtime()['MELI_CLIENT_SECRET']);
    }

    public function testChangingClientWithoutNewSecretFailsClosedAndClearIsExplicit(): void
    {
        $csrf = (new Csrf())->token();
        $before = $this->runtime();

        $invalid = $this->request('POST', [
            'action' => 'save',
            'csrf_token' => $csrf,
            'client_id' => 'different-client',
            'client_secret' => '',
        ]);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame($before, $this->runtime());

        $cleared = $this->request('POST', [
            'action' => 'clear',
            'csrf_token' => $csrf,
        ]);
        self::assertSame(303, $cleared->getStatusCode());
        self::assertSame('', $this->runtime()['MELI_CLIENT_ID']);
        self::assertSame('', $this->runtime()['MELI_CLIENT_SECRET']);
    }

    public function testClearRequiresExplicitHumanConfirmationAndPreservesPrivateRuntime(): void
    {
        $csrf = (new Csrf())->token();
        $before = (string) file_get_contents($this->runtimePath);

        foreach ([
            [],
            ['confirm_clear_meli_credentials' => '0'],
            ['confirm_clear_meli_credentials' => 'true'],
            ['confirm_clear_meli_credentials' => ' 1 '],
        ] as $confirmation) {
            $response = $this->request('POST', [
                'action' => 'clear',
                'csrf_token' => $csrf,
            ] + $confirmation);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame('', $response->getHeaderLine('Location'));
            self::assertSame($before, file_get_contents($this->runtimePath));
        }
    }

    public function testInvalidCsrfDoesNotMutatePrivateRuntime(): void
    {
        $before = file_get_contents($this->runtimePath);
        $response = $this->request('POST', [
            'action' => 'clear',
            'csrf_token' => 'invalid',
        ]);
        self::assertSame(419, $response->getStatusCode());
        self::assertSame($before, file_get_contents($this->runtimePath));
    }

    public function testServerManagedCredentialsAreReadOnlyAndSecretStillNeverRendered(): void
    {
        putenv('MELI_CLIENT_ID=server-client');
        putenv('MELI_CLIENT_SECRET=server-secret-value');
        $before = file_get_contents($this->runtimePath);

        $get = $this->request('GET');
        $html = (string) $get->getBody();
        self::assertSame(200, $get->getStatusCode());
        self::assertStringContainsString('Administrada por servidor', $html);
        self::assertStringContainsString('server-client', $html);
        self::assertStringNotContainsString('server-secret-value', $html);

        $post = $this->request('POST', [
            'action' => 'save',
            'csrf_token' => (new Csrf())->token(),
            'client_id' => 'ignored-client',
            'client_secret' => 'ignored-secret',
        ]);
        self::assertSame(409, $post->getStatusCode());
        self::assertSame($before, file_get_contents($this->runtimePath));
    }

    /** @param array<string,string> $body */
    private function request(string $method, array $body = []): ResponseInterface
    {
        $workBefore = $this->pdo->query('SELECT * FROM work_items')->fetchAll();
        $usageBefore = $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll();
        $request = (new ServerRequestFactory())->createServerRequest($method, '/settings/mercadolibre');
        if ($body !== []) {
            $request = $request->withParsedBody($body);
        }
        $response = Bootstrap::create()->handle($request);
        self::assertSame($workBefore, $this->pdo->query('SELECT * FROM work_items')->fetchAll());
        self::assertSame($usageBefore, $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll());
        return $response;
    }

    /** @return array<string,mixed> */
    private function runtime(): array
    {
        $decoded = json_decode((string) file_get_contents($this->runtimePath), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        return $decoded;
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
