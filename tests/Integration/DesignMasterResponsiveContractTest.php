<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class DesignMasterResponsiveContractTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_URI']);
    }

    public function testApprovedShellRendersOneDesktopNavigationAndExactMobileNavigation(): void
    {
        $html = $this->renderNavigation('admin', true);

        self::assertSame(1, substr_count($html, 'class="app-sidebar"'));
        self::assertStringContainsString('Inicio / Resumen', $html);
        self::assertSame(1, substr_count($html, 'class="mobile-bottom-nav"'));
        self::assertSame(5, substr_count($html, 'data-mobile-nav-item='));
        foreach (['inicio', 'operacion', 'apps', 'productos', 'mercado-libre'] as $item) {
            self::assertStringContainsString('data-mobile-nav-item="' . $item . '"', $html);
        }
        self::assertSame(1, substr_count($html, 'id="apps-launcher"'));
        self::assertSame(1, substr_count($html, 'src="/assets/app.js"'));
        self::assertSame(1, substr_count($html, 'href="/assets/app.css"'));
    }

    public function testDashboardIsDirectHomeDestinationAndOwnsActiveHomeState(): void
    {
        $html = $this->renderNavigation('admin', true, '/dashboard');

        self::assertSame(2, substr_count($html, 'href="/dashboard"'));
        self::assertStringContainsString('class="is-active" href="/dashboard"', $html);
        self::assertStringContainsString('data-mobile-nav-item="inicio" class="is-active" href="/dashboard"', $html);
    }

    public function testShellDoesNotFabricateDeferredDestinations(): void
    {
        $html = $this->renderNavigation('admin', true);

        foreach (['/logistics', '/post-sale', '/linking', '/reconciliation', '/profitability', '/api-health'] as $deferredPath) {
            self::assertStringNotContainsString('href="' . $deferredPath, $html);
        }
    }

    public function testOperatorKeepsCoreNavigationWithoutAdminOnlyDestinations(): void
    {
        $html = $this->renderNavigation('operator', false);

        foreach (['href="/sales"', 'href="/catalog"', 'href="/billing"'] as $expected) {
            self::assertStringContainsString($expected, $html);
        }
        foreach (['href="/system/companies"', 'href="/system/users"', 'href="/settings/system"', 'href="/settings/mercadolibre"', 'href="/mercadolibre/accounts"'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $html);
        }
        self::assertStringContainsString('data-mobile-nav-item="mercado-libre"', $html);
        self::assertStringContainsString('aria-disabled="true"', $html);
    }

    public function testJavascriptAssetIsDeliveredAsJavascript(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.js'),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/javascript; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('apps-launcher', (string) $response->getBody());
    }

    public function testStylesheetTransformsResponsiveTablesIntoLabelledMobileCards(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.css'),
        );
        $css = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('table[data-responsive-table]', $css);
        self::assertStringContainsString('content: attr(data-label)', $css);
        self::assertStringContainsString('[data-mobile-card]', $css);
    }

    public function testLauncherBackdropIsOutsideKeyboardTabOrderAndKeyboardFocusIsVisible(): void
    {
        $html = $this->renderNavigation('admin', true);
        self::assertStringContainsString(
            'class="apps-launcher-backdrop" type="button" tabindex="-1" data-apps-launcher-close',
            $html,
        );

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.css'),
        );
        $css = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(':focus-visible', $css);
        self::assertStringContainsString('outline:', $css);
    }

    public function testLauncherTrapHandlesTabFromInitiallyFocusedSheet(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.js'),
        );
        $js = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('document.activeElement === sheet', $js);
        self::assertStringContainsString('(event.shiftKey ? last : first).focus()', $js);
    }

    public function testMobileShellAccountsForBottomSafeArea(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.css'),
        );
        $css = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('--safe-bottom: env(safe-area-inset-bottom, 0px);', $css);
        self::assertStringContainsString('bottom: calc(12px + var(--safe-bottom));', $css);
        self::assertStringContainsString('padding: calc(var(--topbar) + 22px) 0 calc(106px + var(--safe-bottom));', $css);
        self::assertStringContainsString('margin-bottom: calc(88px + var(--safe-bottom));', $css);
    }

    private function renderNavigation(string $companyRole, bool $systemAdmin, string $requestUri = '/sales'): string
    {
        $_SESSION = [
            'user_id' => 1,
            'display_name' => 'UI Tester',
            'is_system_admin' => $systemAdmin,
        ];
        $_SERVER['REQUEST_URI'] = $requestUri;
        $company = ['id' => 1, 'name' => 'Empresa Demo', 'role' => $companyRole];
        $companies = [$company];
        $csrfToken = 'test-token';

        ob_start();
        require dirname(__DIR__, 2) . '/app/Core/Http/views/navigation.php';
        return (string) ob_get_clean();
    }
}
