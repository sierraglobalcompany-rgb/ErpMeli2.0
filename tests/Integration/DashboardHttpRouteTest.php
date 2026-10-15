<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PDO;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class DashboardHttpRouteTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');

        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main'),(2,'Other','other')");
        $statement = $this->pdo->prepare(
            "INSERT INTO users(id,username,display_name,email,password_hash,status) "
            . "VALUES (1,'member@example.test','Dashboard User','member@example.test',?,'active')",
        );
        $statement->execute([$hash]);
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;
        $_SESSION['display_name'] = 'Dashboard User';
        $_SESSION['is_system_admin'] = false;
    }

    public function testRootWithActiveCompanyRedirectsToDashboard(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/'),
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/dashboard', $response->getHeaderLine('Location'));
    }

    public function testDashboardIsCompanyScopedReadOnlyAndKeepsCurrenciesSeparate(): void
    {
        $this->pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES "
            . "(10,1,'700000101','MCO','Principal','connected'),"
            . "(11,1,'700000102','MCO','Atención','attention'),"
            . "(20,2,'700000202','MCO','Foreign','connected')"
        );
        $this->pdo->exec(
            "INSERT INTO orders(company_id,account_id,external_order_id,status,date_created,total_amount,currency_id) VALUES "
            . "(1,10,'200000000101','paid','2026-10-05 10:00:00.000000',100000.0000,'COP'),"
            . "(1,10,'200000000102','paid','2026-10-06 10:00:00.000000',25.0000,'USD'),"
            . "(2,20,'200000000202','paid','2026-10-07 10:00:00.000000',999999.0000,'COP')"
        );
        $this->pdo->exec(
            "INSERT INTO products(id,company_id,internal_sku,name) VALUES "
            . "(100,1,'OWN','Own product'),(200,2,'FOREIGN','Foreign product')"
        );
        $this->pdo->exec("INSERT INTO product_inventory(product_id,quantity) VALUES (100,7),(200,999)");

        $beforeWork = $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll();
        $beforeUsage = $this->pdo->query('SELECT * FROM api_usage_daily ORDER BY usage_date,scope_key,operation_key')->fetchAll();

        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            '/dashboard?from=2026-10-01&to=2026-10-31',
        )->withQueryParams(['from' => '2026-10-01', 'to' => '2026-10-31']);
        $response = Bootstrap::create()->handle($request);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Inicio / Resumen', $html);
        self::assertStringContainsString('data-dashboard-kpi="orders">2<', $html);
        self::assertStringContainsString('data-dashboard-kpi="products">1<', $html);
        self::assertStringContainsString('data-dashboard-account-status="connected">1<', $html);
        self::assertStringContainsString('data-dashboard-account-status="attention">1<', $html);
        self::assertStringContainsString('data-dashboard-sales-currency="COP"', $html);
        self::assertStringContainsString('100000', $html);
        self::assertStringContainsString('data-dashboard-sales-currency="USD"', $html);
        self::assertStringContainsString('25', $html);
        self::assertStringContainsString('data-dashboard-profitability="UNKNOWN"', $html);
        self::assertStringContainsString('200000000101', $html);
        self::assertStringContainsString('200000000102', $html);
        self::assertStringNotContainsString('200000000202', $html);
        self::assertStringNotContainsString('999999', $html);
        self::assertSame($beforeWork, $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll());
        self::assertSame($beforeUsage, $this->pdo->query('SELECT * FROM api_usage_daily ORDER BY usage_date,scope_key,operation_key')->fetchAll());
    }

    public function testDashboardRejectsInvalidOrUnboundedDateRange(): void
    {
        foreach ([
            ['from' => 'bad', 'to' => '2026-10-31'],
            ['from' => '2026-11-01', 'to' => '2026-10-01'],
            ['from' => '2020-01-01', 'to' => '2026-10-31'],
        ] as $query) {
            $response = Bootstrap::create()->handle(
                (new ServerRequestFactory())->createServerRequest('GET', '/dashboard')->withQueryParams($query),
            );
            self::assertSame(422, $response->getStatusCode());
        }
    }
}
