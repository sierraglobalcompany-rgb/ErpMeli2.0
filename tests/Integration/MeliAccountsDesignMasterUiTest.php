<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliAccountsDesignMasterUiTest extends TestCase
{
    private string|false $clientBefore;
    private string|false $secretBefore;
    private mixed $envClientBefore;
    private mixed $envSecretBefore;

    protected function setUp(): void
    {
        $this->clientBefore = getenv('MELI_CLIENT_ID');
        $this->secretBefore = getenv('MELI_CLIENT_SECRET');
        $this->envClientBefore = $_ENV['MELI_CLIENT_ID'] ?? null;
        $this->envSecretBefore = $_ENV['MELI_CLIENT_SECRET'] ?? null;
        putenv('MELI_CLIENT_ID=test-client');
        putenv('MELI_CLIENT_SECRET=test-secret');
        $_ENV['MELI_CLIENT_ID'] = 'test-client';
        $_ENV['MELI_CLIENT_SECRET'] = 'test-secret';

        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'meli-ui','MELI Admin','meli-ui@example.test','unused','active')");
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(10,'Empresa MELI','empresa-meli')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(10,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES
            (101,10,'1001','MCO','Principal','connected'),
            (102,10,'1002','MCO','Atención','attention'),
            (103,10,'1003','MCO','Reautorizar','reauth_required'),
            (104,10,'1004','MCO','Desconectada','disabled')");

        $_SESSION = [
            'user_id' => 1,
            'company_id' => 10,
            'role' => 'admin',
            'display_name' => 'MELI Admin',
            'csrf_token' => str_repeat('c', 64),
        ];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $this->restoreEnv('MELI_CLIENT_ID', $this->clientBefore, $this->envClientBefore);
        $this->restoreEnv('MELI_CLIENT_SECRET', $this->secretBefore, $this->envSecretBefore);
        parent::tearDown();
    }

    public function testCompanyAccountsUseOneResponsiveConnectionCenterWithoutFakeHealthRoutes(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/mercadolibre/accounts')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="page-header meli-accounts-page-header"', $html);
        self::assertStringContainsString('class="meli-accounts-workspace"', $html);
        self::assertStringContainsString('class="panel meli-connection-panel"', $html);
        self::assertStringContainsString('class="panel panel-full meli-linked-accounts-panel"', $html);
        self::assertStringContainsString('data-responsive-table="meli-accounts"', $html);
        self::assertSame(4, substr_count($html, 'data-mobile-card="meli-account-row"'));
        self::assertStringContainsString('data-label="Cuenta"', $html);
        self::assertStringContainsString('data-label="Seller ID"', $html);
        self::assertStringContainsString('data-label="Sitio"', $html);
        self::assertStringContainsString('data-label="Estado"', $html);
        self::assertStringContainsString('data-label="Actualizada"', $html);
        self::assertStringContainsString('data-label="Acciones"', $html);

        foreach (['Conectada', 'Requiere revisión', 'Debes volver a autorizar', 'Desconectada'] as $label) {
            self::assertStringContainsString($label, $html);
        }

        self::assertStringContainsString('href="/oauth/mercadolibre/connect"', $html);
        self::assertStringNotContainsString('href="/mercadolibre/health"', $html);
        self::assertStringNotContainsString('href="/mercadolibre/sync"', $html);
        self::assertStringNotContainsString('href="/mercadolibre/webhooks"', $html);
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
