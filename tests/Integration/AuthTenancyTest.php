<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth\AuthService;
use App\Core\Auth\PasswordService;
use App\Core\Tenancy\CompanyContext;
use App\Bootstrap;
use App\Core\Security\Csrf;
use DomainException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class AuthTenancyTest extends TestCase
{
    #[DataProvider('protectedRequests')]
    public function testDisabledUserSessionCannotUseProtectedRoutes(string $method, string $path, array $body): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);
        self::assertTrue((new AuthService($pdo))->login('admin@example.test', 'secret'));
        $pdo->exec("INSERT INTO products(id,company_id,internal_sku,name) VALUES(1,1,'existing','Existing')");
        $pdo->exec("INSERT INTO product_inventory(product_id,quantity) VALUES(1,3)");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES(1,1,'100','MCO','connected')");
        $before = [];
        foreach (['products', 'product_inventory', 'work_items', 'meli_accounts', 'api_usage_daily'] as $table) {
            $before[$table] = $pdo->query('SELECT * FROM ' . $table)->fetchAll();
        }
        $csrf = (new Csrf())->token();
        $settings = $pdo->query('SELECT * FROM system_settings')->fetchAll();
        $pdo->exec("UPDATE users SET status='disabled' WHERE id=1");
        $body['csrf_token'] = $csrf;
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withParsedBody($body)->withHeader('X-CSRF-Token', $csrf);
        $previousClientId = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=local-test-client');
        try {
            $response = Bootstrap::create()->handle($request);
        } finally {
            putenv($previousClientId === false ? 'MELI_CLIENT_ID' : 'MELI_CLIENT_ID=' . $previousClientId);
        }
        self::assertSame(403, $response->getStatusCode());
        self::assertSame($settings, $pdo->query('SELECT * FROM system_settings')->fetchAll());
        foreach (['products', 'product_inventory', 'work_items', 'meli_accounts', 'api_usage_daily'] as $table) {
            self::assertSame($before[$table], $pdo->query('SELECT * FROM ' . $table)->fetchAll());
        }
        self::assertArrayNotHasKey('meli_oauth', $_SESSION);
        self::assertArrayNotHasKey('meli_oauth_company_id', $_SESSION);
    }

    /** @return iterable<string,array{string,string,array<string,int|string>}> */
    public static function protectedRequests(): iterable
    {
        yield 'Sales read' => ['GET', '/sales', []];
        yield 'Sales audit write' => ['POST', '/sales/audits', ['account_id'=>1,'period_key'=>'2026-09']];
        yield 'ERP product read' => ['GET', '/api/products/1', []];
        yield 'ERP product write' => ['POST', '/api/products', ['internal_sku'=>'disabled','name'=>'Denied']];
        yield 'ERP inventory write' => ['PUT', '/api/products/1/inventory', ['quantity'=>1]];
        yield 'Settings read' => ['GET', '/settings/system', []];
        yield 'Settings write' => ['POST', '/settings/system', ['automation_enabled'=>'1','debug_enabled'=>'1']];
        yield 'OAuth start' => ['GET', '/oauth/mercadolibre/connect', []];
        yield 'Company select' => ['POST', '/company/select', ['company_id'=>1]];
    }

    public function testDisabledUserSessionCannotUseCompanyContextDirectly(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);
        self::assertTrue((new AuthService($pdo))->login('admin@example.test', 'secret'));
        $pdo->exec("UPDATE users SET status='disabled' WHERE id=1");
        $this->expectException(DomainException::class);
        (new CompanyContext($pdo))->companyId();
    }

    public function testActiveUserCanSelectOwnCompanyButNotForeignCompanyThroughRoute(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Foreign','foreign')");
        self::assertTrue((new AuthService($pdo))->login('admin@example.test', 'secret'));
        $csrf = (new Csrf())->token();
        $app = Bootstrap::create();
        $factory = new ServerRequestFactory();
        self::assertSame(303, $app->handle($factory->createServerRequest('POST', '/company/select')
            ->withParsedBody(['company_id'=>1, 'csrf_token'=>$csrf]))->getStatusCode());
        self::assertSame(403, $app->handle($factory->createServerRequest('POST', '/company/select')
            ->withParsedBody(['company_id'=>2, 'csrf_token'=>$csrf]))->getStatusCode());
        self::assertSame(1, $_SESSION['company_id']);
    }

    public function testValidPasswordLogsInAndInvalidPasswordDoesNot(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);

        $auth = new AuthService($pdo);

        self::assertFalse($auth->login('admin@example.test', 'wrong'));
        self::assertTrue($auth->login('admin@example.test', 'secret'));
        self::assertSame(1, $auth->userId());
        self::assertSame('admin', $auth->role());
    }

    public function testUserCannotSelectCompanyWithoutMembership(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (2,'Other','other')");

        $auth = new AuthService($pdo);
        self::assertTrue($auth->login('admin@example.test', 'secret'));

        $this->expectException(DomainException::class);
        (new CompanyContext($pdo))->select(2);
    }

    public function testMultiMembershipLoginClearsStaleCompanyAndRoleBeforeSelection(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Second','second')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(2,1,'member')");
        $_SESSION = ['user_id'=>1,'company_id'=>1,'role'=>'admin'];

        self::assertTrue((new AuthService($pdo))->login('admin@example.test', 'secret'));
        self::assertSame(1, $_SESSION['user_id']);
        self::assertArrayNotHasKey('company_id', $_SESSION);
        self::assertArrayNotHasKey('role', $_SESSION);
    }

    public function testValidCredentialsWithoutMembershipCannotRetainPreviousOperatorIdentity(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);
        $_SESSION = ['user_id'=>1,'company_id'=>1,'role'=>'admin'];
        $pdo->exec('DELETE FROM company_users');

        self::assertFalse((new AuthService($pdo))->login('admin@example.test', 'secret'));
        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertArrayNotHasKey('company_id', $_SESSION);
        self::assertArrayNotHasKey('role', $_SESSION);
    }

    private function seedUser(\PDO $pdo): void
    {
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $stmt = $pdo->prepare(
            "INSERT INTO users(id,email,password_hash,status) VALUES (1,'admin@example.test',?,'active')"
        );
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");
    }
}
