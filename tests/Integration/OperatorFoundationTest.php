<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Security\Csrf;
use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class OperatorFoundationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        (new AuthService($this->pdo))->bootstrapAdmin('Main <Company>', 'main', 'admin@example.test', 'test-password-long');
        self::assertTrue((new AuthService($this->pdo))->login('admin@example.test', 'test-password-long'));
    }

    private function request(string $method, string $path, array $body = [], array $query = []): ResponseInterface
    {
        $before = $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll();
        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest($method, $path)
            ->withParsedBody($body)->withQueryParams($query));
        self::assertSame($before, $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll());
        return $response;
    }

    public function testOperatorRootAndNavigationRespectCurrentAuthority(): void
    {
        $root = $this->request('GET', '/');
        self::assertSame(303, $root->getStatusCode());
        self::assertSame('/dashboard', $root->getHeaderLine('Location'));
        $html = (string) $this->request('GET', '/sales')->getBody();
        self::assertStringContainsString('Main &lt;Company&gt;', $html);
        self::assertStringContainsString('href="/sales"', $html);
        self::assertStringContainsString('href="/settings/system"', $html);
        self::assertStringContainsString('action="/logout"', $html);
        $this->pdo->exec("UPDATE company_users SET role='member'");
        $_SESSION['role'] = 'admin'; // Company role does not control installation settings.
        self::assertStringContainsString('href="/settings/system"', (string) $this->request('GET', '/sales')->getBody());
        $this->pdo->exec("UPDATE users SET status='disabled'");
        self::assertSame(403, $this->request('GET', '/')->getStatusCode());
        self::assertSame(403, $this->request('GET', '/sales')->getStatusCode());
        $_SESSION = [];
        $guestRoot = $this->request('GET', '/');
        self::assertSame(303, $guestRoot->getStatusCode());
        self::assertSame('/login', $guestRoot->getHeaderLine('Location'));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public function testLoginLandsOnUsableOperatorRoute(): void
    {
        $_SESSION = [];
        $response = $this->request('POST', '/login', ['username'=>'admin@example.test', 'password'=>'test-password-long', 'csrf_token'=>(new Csrf())->token()]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/', $response->getHeaderLine('Location'));
        self::assertSame('/dashboard', $this->request('GET', '/')->getHeaderLine('Location'));
    }
    public function testTenantSelectorUsesMembershipsAndCsrfAndValidDestination(): void
    {
        self::assertSame('/dashboard', $this->request('GET', '/company/select')->getHeaderLine('Location'));
        self::assertStringNotContainsString('href="/company/select"', (string) $this->request('GET', '/sales')->getBody());
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Second','second'),(3,'Foreign','foreign')");
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(2,1,'member')");
        $selector = $this->request('GET', '/company/select');
        self::assertSame(200, $selector->getStatusCode());
        $html = (string) $selector->getBody();
        self::assertStringContainsString('Second', $html);
        self::assertStringNotContainsString('Foreign', $html);
        self::assertStringContainsString('href="/company/select"', (string) $this->request('GET', '/sales')->getBody());
        self::assertSame(419, $this->request('POST', '/company/select', ['company_id'=>2])->getStatusCode());
        $csrf = (new Csrf())->token();
        self::assertSame(403, $this->request('POST', '/company/select', ['company_id'=>3, 'csrf_token'=>$csrf])->getStatusCode());
        self::assertSame(1, $_SESSION['company_id']);
        $selected = $this->request('POST', '/company/select', ['company_id'=>2, 'csrf_token'=>$csrf]);
        self::assertSame(303, $selected->getStatusCode());
        self::assertSame('/', $selected->getHeaderLine('Location'));
        self::assertSame(2, $_SESSION['company_id']);
        self::assertStringContainsString('Second', (string) $this->request('GET', '/sales')->getBody());
        $this->pdo->exec("UPDATE users SET status='disabled'");
        self::assertSame(403, $this->request('GET', '/company/select')->getStatusCode());
        self::assertSame(403, $this->request('POST', '/company/select', ['company_id'=>1, 'csrf_token'=>$csrf])->getStatusCode());
        self::assertSame(2, $_SESSION['company_id']);
    }

    public function testMultiCompanyLoginReachesChooserWithoutClaimingAnActiveCompany(): void
    {
        $this->addSecondMembership();
        $login = $this->request('POST', '/login', ['username'=>'admin@example.test','password'=>'test-password-long','csrf_token'=>(new Csrf())->token()]);
        self::assertSame(303, $login->getStatusCode());
        self::assertSame('/', $login->getHeaderLine('Location'));
        self::assertSame('/company/select', $this->request('GET', '/')->getHeaderLine('Location'));
        self::assertSame(1, $_SESSION['user_id']);
        self::assertArrayNotHasKey('company_id', $_SESSION);
        self::assertArrayNotHasKey('role', $_SESSION);

        $selector = $this->request('GET', '/company/select');
        self::assertSame(200, $selector->getStatusCode());
        $html = (string) $selector->getBody();
        self::assertStringContainsString('Main &lt;Company&gt;', $html);
        self::assertStringContainsString('Second', $html);
        self::assertStringNotContainsString('<p>Empresa:', $html, 'An uncommitted option is not an active company.');
        foreach (['/sales','/catalog','/billing'] as $path) {
            self::assertSame(403, $this->request('GET', $path)->getStatusCode());
        }
        self::assertSame(200,$this->request('GET','/settings/system')->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    #[DataProvider('explicitCompanyChoices')]
    public function testExplicitCompanyChoiceActivatesExactRoleAndDownstreamContext(int $companyId, string $role, string $name, int $settingsStatus): void
    {
        $this->addSecondMembership();
        self::assertTrue((new AuthService($this->pdo))->login('admin@example.test', 'test-password-long'));
        self::assertArrayNotHasKey('company_id', $_SESSION);
        self::assertArrayNotHasKey('role', $_SESSION);
        $selected = $this->request('POST', '/company/select', ['company_id'=>$companyId,'csrf_token'=>(new Csrf())->token()]);
        self::assertSame(303, $selected->getStatusCode());
        self::assertSame($companyId, $_SESSION['company_id']);
        self::assertSame($role, $_SESSION['role']);
        self::assertSame('/dashboard', $this->request('GET', '/')->getHeaderLine('Location'));
        foreach (['/sales','/catalog','/billing'] as $path) {
            $response = $this->request('GET', $path);
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString($name, (string) $response->getBody());
        }
        self::assertSame($settingsStatus, $this->request('GET', '/settings/system')->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public static function explicitCompanyChoices(): iterable
    {
        yield 'admin' => [1,'admin','Main &lt;Company&gt;',200];
        yield 'member' => [2,'member','Second',200];
    }

    #[DataProvider('rejectedCompanyChoices')]
    public function testRejectedCompanyChoiceDoesNotActivateTenantOrRole(string $case, int $status): void
    {
        $this->addSecondMembership();
        self::assertTrue((new AuthService($this->pdo))->login('admin@example.test', 'test-password-long'));
        $body = ['company_id'=>2,'csrf_token'=>(new Csrf())->token()];
        if ($case === 'csrf') { $body['csrf_token'] = 'invalid'; }
        if ($case === 'invalid-id') { $body['company_id'] = 'invalid'; }
        if ($case === 'foreign') { $body['company_id'] = 999; }
        self::assertSame($status, $this->request('POST', '/company/select', $body)->getStatusCode());
        self::assertSame(1, $_SESSION['user_id']);
        self::assertArrayNotHasKey('company_id', $_SESSION);
        self::assertArrayNotHasKey('role', $_SESSION);
        self::assertSame('/company/select', $this->request('GET', '/')->getHeaderLine('Location'));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public static function rejectedCompanyChoices(): iterable
    {
        yield 'bad CSRF' => ['csrf',419];
        yield 'invalid ID' => ['invalid-id',422];
        yield 'foreign ID' => ['foreign',403];
    }

    private function addSecondMembership(): void
    {
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Second','second')");
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(2,1,'member')");
    }

    private function seedSales(): void
    {
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Other','other')");
        $this->pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES(1,1,'100','MCO','connected'),(2,2,'200','MCO','connected')");
    }

    public function testExactIdFindsOlderSaleWithoutWorkOrRemoteCalls(): void
    {
        $this->seedSales();
        $insert = $this->pdo->prepare("INSERT INTO orders(company_id,account_id,external_order_id,status,last_updated,date_created,total_amount,currency_id) VALUES(?,?,?,?,?,?,?,?)");
        $insert->execute([1,1,'9007199254740993','paid','2020-01-01','2020-01-01','1.2345','COP']);
        $insert->execute([2,2,'9007199254740995','paid','2020-01-01','2020-01-01','2.0000','COP']);
        for ($i = 1; $i <= 101; $i++) {
            $insert->execute([1,1,(string) $i,'paid','2026-09-01','2026-09-01','3.0000','COP']);
        }
        self::assertStringNotContainsString('9007199254740993', (string) $this->request('GET', '/sales')->getBody());
        $found = $this->request('GET', '/sales', [], ['order_id'=>'9007199254740993']);
        self::assertSame(200, $found->getStatusCode());
        self::assertStringContainsString('href="/sales/9007199254740993"', (string) $found->getBody());
        self::assertStringContainsString('name="order_id"', (string) $found->getBody());
        foreach (['9007199254740995','999999'] as $id) {
            $html = (string) $this->request('GET', '/sales', [], ['order_id'=>$id])->getBody();
            self::assertStringContainsString('No se encontró la venta', $html);
            self::assertStringNotContainsString('href="/sales/' . $id . '"', $html);
        }
        self::assertSame(422, $this->request('GET', '/sales', [], ['order_id'=>['bad']])->getStatusCode());
        self::assertSame(422, $this->request('GET', '/sales', [], ['order_id'=>'1 OR 1=1'])->getStatusCode());
        self::assertSame(404, $this->request('GET', '/sales/9007199254740995')->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public function testAuditStartAndStoredResultsAreVisibleWithoutProcessingWork(): void
    {
        $this->seedSales();
        self::assertStringContainsString('No hay auditorías', (string) $this->request('GET', '/sales')->getBody());
        $response = $this->request('POST', '/sales/audits', ['account_id'=>1, 'period_key'=>'2026-09', 'csrf_token'=>(new Csrf())->token()]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        $html = (string) $this->request('GET', '/sales')->getBody();
        self::assertStringContainsString('<td>CAPTURING</td>', $html);
        self::assertStringContainsString('2026-09-01', $html);
        $this->pdo->exec("INSERT INTO sales_audit_runs(company_id,account_id,period_key,contract_version,status) VALUES(2,2,'2019-02-01','seller-search-v1','attention')");
        foreach (['repairing', 'confirming', 'valid', 'attention', 'unavailable'] as $status) {
            $statement = $this->pdo->prepare('UPDATE sales_audit_runs SET status=?,canonical_count=7,remote_total=8,completed_at=? WHERE company_id=1');
            $statement->execute([$status, in_array($status, ['valid','attention','unavailable'], true) ? '2026-10-10 12:00:00' : null]);
            $html = (string) $this->request('GET', '/sales')->getBody();
            self::assertStringContainsString('<td>' . strtoupper($status) . '</td>', $html);
            self::assertStringNotContainsString('2019-02-01', $html);
            self::assertStringNotContainsString('claim_token', $html);
            self::assertStringNotContainsString('work_items', $html);
        }
        self::assertStringContainsString('2026-10-10 12:00:00', $html);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        self::assertSame('pending', $this->pdo->query('SELECT status FROM work_items')->fetchColumn());
        $this->pdo->exec("UPDATE company_users SET role='member' WHERE company_id=1");
        $html = (string) $this->request('GET', '/sales')->getBody();
        self::assertStringContainsString('<td>UNAVAILABLE</td>', $html);
        self::assertStringNotContainsString('action="/sales/audits"', $html);
    }

    public function testCrossSecurityAndReadOnlyOperatorComposition(): void
    {
        $this->seedSales();
        foreach (['/', '/sales', '/company/select', '/sales/123'] as $path) {
            $_SESSION['company_id'] = 2;
            self::assertSame(403, $this->request('GET', $path)->getStatusCode());
        }
        $_SESSION['company_id'] = 1;
        self::assertSame(419, $this->request('POST', '/sales/audits', ['account_id'=>1,'period_key'=>'2026-09'])->getStatusCode());
        self::assertSame(419, $this->request('POST', '/logout')->getStatusCode());
        self::assertSame(1, $_SESSION['user_id']);
        $csrf = (new Csrf())->token();
        self::assertSame(422, $this->request('POST', '/sales/audits', ['account_id'=>2,'period_key'=>'2026-09','csrf_token'=>$csrf])->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM sales_audit_runs')->fetchColumn());
        $response = $this->request('POST', '/logout', ['csrf_token'=>$csrf]);
        self::assertSame('/login', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('user_id', $_SESSION);
        self::assertSame(403, $this->request('GET', '/sales')->getStatusCode());
    }

    public function testOperatorCanReturnFromSettingsAndLoginInputsAreLabelled(): void
    {
        $html = (string) $this->request('GET', '/settings/system')->getBody();
        self::assertStringContainsString('href="/"', $html);
        self::assertStringContainsString('href="/system/companies"', $html);
        $html = (string) $this->request('GET', '/login')->getBody();
        self::assertStringContainsString('<label', $html);
        self::assertStringContainsString('Usuario', $html);
        self::assertStringContainsString('Contraseña', $html);
    }

    public function testAuditResultsAreBoundedAndScoped(): void
    {
        $this->seedSales();
        $insert = $this->pdo->prepare("INSERT INTO sales_audit_runs(company_id,account_id,period_key,contract_version,status) VALUES(1,1,?,'seller-search-v1','valid')");
        $insert->execute(['2000-01-01']);
        for ($i = 0; $i < 25; $i++) {
            $insert->execute(['2026-01-01']);
        }
        $html = (string) $this->request('GET', '/sales')->getBody();
        self::assertSame(25, substr_count($html, '<td>VALID</td>'));
        self::assertStringNotContainsString('2000-01-01', $html);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

}
