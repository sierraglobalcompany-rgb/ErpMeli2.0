<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliAccountManagementTest extends TestCase
{
    private PDO $pdo;
    private string|false $clientIdBefore;
    private string|false $clientSecretBefore;
    private mixed $envClientIdBefore;
    private mixed $envClientSecretBefore;

    protected function setUp(): void
    {
        $this->clientIdBefore = getenv('MELI_CLIENT_ID');
        $this->clientSecretBefore = getenv('MELI_CLIENT_SECRET');
        $this->envClientIdBefore = $_ENV['MELI_CLIENT_ID'] ?? null;
        $this->envClientSecretBefore = $_ENV['MELI_CLIENT_SECRET'] ?? null;
        putenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_SECRET');
        unset($_ENV['MELI_CLIENT_ID'], $_ENV['MELI_CLIENT_SECRET']);

        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Sierra Global','sierra-global'),(2,'Foreign Company','foreign')");
        $this->pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status,is_system_admin) VALUES(1,'company-admin','Company Admin','admin@example.test','unused','active',0)");
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;
        $_SESSION['role'] = 'admin';
        $_SESSION['display_name'] = 'Company Admin';

        $insert = $this->pdo->prepare(
            'INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(?,?,?,?,?,?)'
        );
        $insert->execute([1,1,'10001','MCO','MOM PURITY','connected']);
        $insert->execute([2,1,'10002','MCO','Q GAME PC','attention']);
        $insert->execute([3,1,'10003','MCO','X MOD','reauth_required']);
        $insert->execute([4,1,'10004','MCO','OLD SHOP','disabled']);
        $insert->execute([5,2,'20001','MCO','FOREIGN SECRET','connected']);

        $this->pdo->exec(
            "INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at) "
            . "VALUES(1,'ACCESS-TOKEN-MUST-NOT-RENDER','REFRESH-TOKEN-MUST-NOT-RENDER','2026-10-15 00:00:00')"
        );
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('MELI_CLIENT_ID', $this->clientIdBefore, $this->envClientIdBefore);
        $this->restoreEnv('MELI_CLIENT_SECRET', $this->clientSecretBefore, $this->envClientSecretBefore);
        parent::tearDown();
    }

    public function testCompanyAdminSeesOnlyOwnAccountsWithHumanStatusesAndNoSecrets(): void
    {
        $response = $this->request();
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Cuentas de Mercado Libre', $html);
        self::assertStringContainsString('Sierra Global', $html);
        foreach (['MOM PURITY','Q GAME PC','X MOD','OLD SHOP','10001','10002','10003','10004'] as $visible) {
            self::assertStringContainsString($visible, $html);
        }
        foreach (['Conectada','Requiere revisión','Debes volver a autorizar','Desconectada / inactiva'] as $label) {
            self::assertStringContainsString($label, $html);
        }
        self::assertStringNotContainsString('FOREIGN SECRET', $html);
        self::assertStringNotContainsString('20001', $html);
        self::assertStringNotContainsString('ACCESS-TOKEN-MUST-NOT-RENDER', $html);
        self::assertStringNotContainsString('REFRESH-TOKEN-MUST-NOT-RENDER', $html);
        self::assertStringContainsString('Una empresa puede tener varias cuentas de Mercado Libre', $html);
        self::assertStringContainsString('sólo puede estar vinculada a una empresa a la vez', $html);
    }

    public function testOperatorViewerUnauthenticatedAndForeignSelectionAreDenied(): void
    {
        foreach (['operator','viewer'] as $role) {
            $this->pdo->exec("UPDATE company_users SET role='" . $role . "' WHERE company_id=1 AND user_id=1");
            self::assertSame(403, $this->request()->getStatusCode());
        }

        $this->pdo->exec("UPDATE company_users SET role='admin' WHERE company_id=1 AND user_id=1");
        $_SESSION['company_id'] = 2;
        self::assertSame(403, $this->request()->getStatusCode());

        $_SESSION = [];
        self::assertSame(403, $this->request()->getStatusCode());
    }

    public function testConfiguredAppShowsExistingOAuthConnectLinkWithoutCallingProvider(): void
    {
        putenv('MELI_CLIENT_ID=local-app-id');
        putenv('MELI_CLIENT_SECRET=local-app-secret');

        $response = $this->request();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('href="/oauth/mercadolibre/connect"', (string) $response->getBody());
        self::assertStringContainsString('Conectar otra cuenta Mercado Libre', (string) $response->getBody());
    }

    public function testMissingAppConfigurationShowsGuidanceWithoutConnectLink(): void
    {
        $response = $this->request();
        $html = (string) $response->getBody();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('La aplicación Mercado Libre aún no está configurada', $html);
        self::assertStringNotContainsString('href="/oauth/mercadolibre/connect"', $html);
    }

    public function testCompanyAdminNavigationMakesAccountPageDiscoverable(): void
    {
        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales'));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('href="/mercadolibre/accounts"', (string) $response->getBody());
    }

    private function request(): ResponseInterface
    {
        $workBefore = $this->pdo->query('SELECT * FROM work_items')->fetchAll();
        $usageBefore = $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll();
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/mercadolibre/accounts')
        );
        self::assertSame($workBefore, $this->pdo->query('SELECT * FROM work_items')->fetchAll());
        self::assertSame($usageBefore, $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll());
        return $response;
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
