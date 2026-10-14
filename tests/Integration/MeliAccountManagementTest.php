<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliAccountManagementTest extends TestCase
{
    private \PDO $pdo;
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
        putenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_SECRET');
        unset($_ENV['MELI_CLIENT_ID'], $_ENV['MELI_CLIENT_SECRET']);

        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status,is_system_admin) VALUES(1,'company-admin','Company Admin','admin@example.test','not-used','active',0)");
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(10,'Empresa A','empresa-a'),(20,'Empresa B','empresa-b')");
        $this->pdo->exec("INSERT INTO company_users(user_id,company_id,role) VALUES(1,10,'admin')");
        $this->pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES
            (101,10,'1001','MCO','TIENDA UNO','connected'),
            (102,10,'1002','MCO','TIENDA DOS','attention'),
            (103,10,'1003','MCO',NULL,'reauth_required'),
            (104,10,'1004','MCO','TIENDA CUATRO','disabled'),
            (201,20,'2001','MCO','OTRA EMPRESA','connected')");
        $this->pdo->exec("INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at) VALUES(101,'ACCESS_SECRET_BYTES','REFRESH_SECRET_BYTES',DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR))");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 10;
        $_SESSION['role'] = 'admin';
        $_SESSION['display_name'] = 'Company Admin';
    }

    protected function tearDown(): void
    {
        $this->restoreEnv('MELI_CLIENT_ID', $this->clientBefore, $this->envClientBefore);
        $this->restoreEnv('MELI_CLIENT_SECRET', $this->secretBefore, $this->envSecretBefore);
        parent::tearDown();
    }

    public function testCompanyAdminSeesOnlySelectedCompanyAccountsAndNeverTokens(): void
    {
        $response = $this->request();
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Cuentas Mercado Libre', $html);
        self::assertStringContainsString('TIENDA UNO', $html);
        self::assertStringContainsString('1001', $html);
        self::assertStringContainsString('TIENDA DOS', $html);
        self::assertStringContainsString('1003', $html);
        self::assertStringContainsString('TIENDA CUATRO', $html);
        self::assertStringNotContainsString('OTRA EMPRESA', $html);
        self::assertStringNotContainsString('2001', $html);
        self::assertStringNotContainsString('ACCESS_SECRET_BYTES', $html);
        self::assertStringNotContainsString('REFRESH_SECRET_BYTES', $html);
    }

    public function testHumanStatusLabelsAndRelationshipHelpAreVisible(): void
    {
        $html = (string) $this->request()->getBody();

        self::assertStringContainsString('Conectada', $html);
        self::assertStringContainsString('Requiere revisión', $html);
        self::assertStringContainsString('Debes volver a autorizar', $html);
        self::assertStringContainsString('Desconectada', $html);
        self::assertStringContainsString('Una empresa puede tener varias cuentas Mercado Libre', $html);
        self::assertStringContainsString('sólo puede estar vinculada a una empresa a la vez', $html);
    }

    public function testOperatorAndViewerAreDenied(): void
    {
        foreach (['operator', 'viewer'] as $role) {
            $statement = $this->pdo->prepare('UPDATE company_users SET role=? WHERE user_id=1 AND company_id=10');
            $statement->execute([$role]);
            $_SESSION['role'] = $role;
            self::assertSame(403, $this->request()->getStatusCode(), $role);
        }
    }

    public function testConfiguredApplicationShowsExistingOAuthConnectLinkWithoutProviderCall(): void
    {
        putenv('MELI_CLIENT_ID=test-client');
        putenv('MELI_CLIENT_SECRET=test-secret');

        $response = $this->request();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('href="/oauth/mercadolibre/connect"', (string) $response->getBody());
    }

    public function testMissingApplicationShowsGuidanceAndNoConnectLink(): void
    {
        $html = (string) $this->request()->getBody();

        self::assertStringNotContainsString('href="/oauth/mercadolibre/connect"', $html);
        self::assertStringContainsString('La aplicación Mercado Libre aún no está configurada', $html);
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
