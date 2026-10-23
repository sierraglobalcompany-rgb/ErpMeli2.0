<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class BillingDesignMasterUiTest extends TestCase
{
    public function testBillingUsesOneResponsiveFinancialSurfaceWithoutFakeFinanceRoutes(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing UI','billing-ui')");
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'billing-ui@example.test','Billing UI','billing-ui@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(10,1,'100','MCO','Cuenta principal','connected')");
        $pdo->exec("INSERT INTO billing_periods(id,company_id,account_id,period_key,document_type,cursor_last_id,sync_state,last_synced_at) VALUES(1,1,10,'2026-09-01','BILL','101','pending','2026-10-15 20:00:00')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'admin'];

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/billing')
            ->withQueryParams([
                'account_id' => '10',
                'period_key' => '2026-09-01',
                'document_type' => 'BILL',
            ]);
        $response = Bootstrap::create()->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="page-header billing-page-header"', $html);
        self::assertStringContainsString('class="billing-workspace"', $html);
        self::assertStringContainsString('class="panel billing-sync-panel"', $html);
        self::assertStringContainsString('class="panel billing-selection-panel"', $html);
        self::assertStringContainsString('data-responsive-table="billing-periods"', $html);
        self::assertSame(1, substr_count($html, 'data-mobile-card="billing-period-row"'));
        self::assertStringContainsString('data-label="Cuenta"', $html);
        self::assertStringContainsString('data-label="Período"', $html);
        self::assertStringContainsString('data-label="Tipo"', $html);
        self::assertStringContainsString('data-label="Progreso persistido"', $html);
        self::assertStringContainsString('data-label="Última lectura (UTC)"', $html);
        self::assertStringContainsString('La solicitud registra trabajo pendiente. No consulta Mercado Libre desde esta página.', $html);
        self::assertStringNotContainsString('href="/financial"', $html);
        self::assertStringNotContainsString('href="/reconciliation"', $html);
        self::assertStringNotContainsString('href="/profitability"', $html);
    }

    public function testBillingShowsHumanDocumentLabelsWithoutChangingMachineValues(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing UI','billing-ui')");
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'billing-ui@example.test','Billing UI','billing-ui@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(10,1,'100','MCO','Cuenta principal','connected')");
        $pdo->exec("INSERT INTO billing_periods(id,company_id,account_id,period_key,document_type,cursor_last_id,sync_state,last_synced_at) VALUES(1,1,10,'2026-09-01','BILL','101','pending','2026-10-15 20:00:00')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'admin'];

        $response = Bootstrap::create()->handle((new ServerRequestFactory())
            ->createServerRequest('GET', '/billing')
            ->withQueryParams([
                'account_id' => '10',
                'period_key' => '2026-09-01',
                'document_type' => 'BILL',
            ]));

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertMatchesRegularExpression('/<option value="BILL"[^>]*>Factura<\/option>/', $html);
        self::assertMatchesRegularExpression('/<option value="CREDIT_NOTE"[^>]*>Nota crédito<\/option>/', $html);
        self::assertStringContainsString('· Factura</p>', $html);
        self::assertStringContainsString('data-label="Tipo">Factura</td>', $html);
        self::assertStringContainsString('document_type=BILL', $html);
        self::assertStringNotContainsString('>BILL<', $html);
        self::assertStringNotContainsString('>CREDIT_NOTE<', $html);
    }

    public function testBillingKeepsTechnicalCursorBehindProgressiveDisclosure(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing UI','billing-ui')");
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'billing-ui@example.test','Billing UI','billing-ui@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(10,1,'100','MCO','Cuenta principal','connected')");
        $pdo->exec("INSERT INTO billing_periods(id,company_id,account_id,period_key,document_type,cursor_last_id,sync_state,last_synced_at) VALUES(1,1,10,'2026-09-01','BILL','101','pending','2026-10-15 20:00:00')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'admin'];

        $response = Bootstrap::create()->handle((new ServerRequestFactory())
            ->createServerRequest('GET', '/billing')
            ->withQueryParams([
                'account_id' => '10',
                'period_key' => '2026-09-01',
                'document_type' => 'BILL',
            ]));

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('<details class="billing-technical-details">', $html);
        self::assertStringContainsString('<summary>Detalles técnicos</summary>', $html);
        self::assertMatchesRegularExpression('/<details class="billing-technical-details">.*?<dt>Cursor confirmado<\/dt><dd>101<\/dd>.*?<\/details>/s', $html);
    }

    public function testBillingUsesHumanAccountLabelInSelectedSummaryWithoutChangingMachineValue(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing UI','billing-ui')");
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'billing-ui@example.test','Billing UI','billing-ui@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(10,1,'100','MCO','Cuenta principal','connected')");
        $pdo->exec("INSERT INTO billing_periods(id,company_id,account_id,period_key,document_type,cursor_last_id,sync_state,last_synced_at) VALUES(1,1,10,'2026-09-01','BILL','101','pending','2026-10-15 20:00:00')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'admin'];

        $response = Bootstrap::create()->handle((new ServerRequestFactory())
            ->createServerRequest('GET', '/billing')
            ->withQueryParams([
                'account_id' => '10',
                'period_key' => '2026-09-01',
                'document_type' => 'BILL',
            ]));

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('Cuenta principal · 2026-09-01 · Factura</p>', $html);
        self::assertStringNotContainsString('Cuenta 10 · 2026-09-01 · Factura</p>', $html);
        self::assertMatchesRegularExpression('/<option value="10"[^>]*>100 — Cuenta principal<\/option>/', $html);
        self::assertStringContainsString('account_id=10', $html);
    }

    public function testBillingPeriodHistoryUsesHumanAccountLabelWithoutChangingMachineQuery(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing UI','billing-ui')");
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'billing-ui@example.test','Billing UI','billing-ui@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(10,1,'100','MCO','Cuenta principal','connected')");
        $pdo->exec("INSERT INTO billing_periods(id,company_id,account_id,period_key,document_type,cursor_last_id,sync_state,last_synced_at) VALUES(1,1,10,'2026-09-01','BILL','101','pending','2026-10-15 20:00:00')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'admin'];

        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/billing'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('data-label="Cuenta">Cuenta principal</td>', $html);
        self::assertStringNotContainsString('data-label="Cuenta">100</td>', $html);
        self::assertStringContainsString('account_id=10', $html);
    }

    public function testLimitedOperatorBillingHidesUnreachableMeliManagementLink(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing Operator','billing-operator')");
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status)
            VALUES(1,'billing-operator@example.test','Billing Operator','billing-operator@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'operator')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'operator'];

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/billing')
        );
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('No hay períodos registrados para esta empresa.', $html);
        self::assertStringNotContainsString('Administrar cuentas Mercado Libre', $html);
        self::assertStringNotContainsString('href="/mercadolibre/accounts"', $html);
        self::assertStringNotContainsString('action="/billing/syncs"', $html);
        self::assertStringNotContainsString('Solicitar período', $html);
        self::assertStringContainsString('data-mobile-nav-item="mercado-libre" class="is-disabled"', $html);
    }

    public function testBillingUsesActionableEmptyStateInsteadOfImpossibleSyncFormWithoutConnectedAccounts(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing UI','billing-ui')");
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'billing-ui@example.test','Billing UI','billing-ui@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(10,1,'100','MCO','Cuenta histórica','disabled')");
        $pdo->exec("INSERT INTO billing_periods(id,company_id,account_id,period_key,document_type,cursor_last_id,sync_state,last_synced_at) VALUES(1,1,10,'2026-09-01','BILL','101','caught_up','2026-10-15 20:00:00')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'admin'];

        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/billing'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('No hay cuentas Mercado Libre conectadas disponibles para solicitar una sincronización.', $html);
        self::assertStringContainsString('href="/mercadolibre/accounts"', $html);
        self::assertStringContainsString('Administrar cuentas Mercado Libre', $html);
        self::assertStringNotContainsString('action="/billing/syncs"', $html);
        self::assertStringNotContainsString('<select name="account_id" required>', $html);
        self::assertStringContainsString('data-label="Cuenta">Cuenta histórica</td>', $html);
        self::assertStringContainsString('account_id=10', $html);
    }
}
