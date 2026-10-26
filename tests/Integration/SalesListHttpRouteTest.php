<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use App\Modules\Sales\Audit\SalesAuditRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesListHttpRouteTest extends TestCase
{
    public function testSalesRouteListsOnlyOrdersFromSelectedCompany(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main'),(2,'Other','other')");
        $stmt = $pdo->prepare("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'member@example.test','member@example.test','member@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES "
            . "(10,1,'700000101','MCO','connected'),(20,2,'700000202','MCO','connected')"
        );
        $pdo->exec(
            "INSERT INTO orders(company_id,account_id,external_order_id,status,last_updated,total_amount,currency_id) VALUES "
            . "(1,10,'200000000101','paid','2026-10-09 01:00:00.000000',123456.0000,'COP'),"
            . "(2,20,'200000000202','paid','2026-10-09 01:00:00.000000',999999.0000,'COP')"
        );

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/sales');
        $response = Bootstrap::create()->handle($request);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('200000000101', $html);
        self::assertStringContainsString('123456', $html);
        self::assertStringNotContainsString('200000000202', $html);
        self::assertStringNotContainsString('999999', $html);
    }

    public function testSalesListUsesOneResponsiveRowPerSale(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $stmt = $pdo->prepare("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'member@example.test','Member','member@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES (10,1,'700000101','MCO','connected')");
        $pdo->exec("INSERT INTO orders(company_id,account_id,external_order_id,status,last_updated,date_created,total_amount,currency_id) VALUES (1,10,'200000000101','paid','2026-10-09 01:00:00.000000','2026-10-09 01:00:00.000000',123456.0000,'COP')");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales'));
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('class="page-header sales-page-header"', $html);
        self::assertStringContainsString('data-responsive-table="sales-orders"', $html);
        self::assertStringContainsString('data-mobile-card="sale-row"', $html);
        self::assertStringContainsString('data-label="Venta"', $html);
        self::assertStringContainsString('class="status-chip', $html);
        self::assertSame(1, substr_count($html, 'href="/sales/200000000101?account_id=10"'), 'Responsive mobile presentation must reuse the same sale row instead of duplicating sale markup.');
    }

    public function testSalesListKeepsNormalSalesPrimaryAndAuditSecondaryWithHumanStatuses(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $stmt = $pdo->prepare("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'admin@example.test','Admin','admin@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES (10,1,'700000101','MCO','Principal','connected')");
        $pdo->exec(
            "INSERT INTO orders(company_id,account_id,external_order_id,status,last_updated,date_created,total_amount,currency_id) VALUES "
            . "(1,10,'200000000101','paid','2026-10-09 01:00:00.000000','2026-10-09 01:00:00.000000',123456.0000,'COP'),"
            . "(1,10,'200000000102','cancelled','2026-10-08 01:00:00.000000','2026-10-08 01:00:00.000000',654321.0000,'COP')"
        );

        $audit = new SalesAuditRepository($pdo);
        $validRunId = $audit->createCapturingRun(
            1,
            10,
            '2026-08-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-09-10T01:20:00+00:00'),
        );
        $pdo->exec("UPDATE sales_audit_runs SET status='valid', completed_at='2026-09-10 01:30:00.000000' WHERE id={$validRunId}");
        $attentionRunId = $audit->createCapturingRun(
            1,
            10,
            '2026-09-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:20:00+00:00'),
        );
        $pdo->exec("UPDATE sales_audit_runs SET status='attention', completed_at='2026-10-10 01:30:00.000000' WHERE id={$attentionRunId}");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales'));
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Consulta y revisa las ventas de esta empresa.', $html);
        self::assertStringNotContainsString('Consulta las ventas almacenadas localmente y controla las auditorías históricas de cada cuenta.', $html);
        self::assertStringContainsString('<details class="panel sales-audit-disclosure">', $html);
        self::assertStringContainsString('class="sales-audit-summary"', $html);
        self::assertStringContainsString('Auditoría histórica', $html);
        self::assertStringContainsString('data-sale-status="paid">Pagada</span>', $html);
        self::assertStringContainsString('data-sale-status="cancelled">Cancelada</span>', $html);
        self::assertStringContainsString('data-audit-status="valid">Sin diferencias</span>', $html);
        self::assertStringContainsString('data-audit-status="attention">Requiere revisión</span>', $html);
        self::assertStringNotContainsString('>paid</span>', $html);
        self::assertStringNotContainsString('>cancelled</span>', $html);
        self::assertStringNotContainsString('CAPTURING, REPAIRING y CONFIRMING', $html);
    }
}
