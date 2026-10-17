<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class DesignMasterResponsiveContractTest extends TestCase
{
    public function testSharedOperationalTypographyUsesHumanFirstSixteenPixelBaseline(): void
    {
        $css = $this->servedStylesheet();
        self::assertMatchesRegularExpression('/\bfont:\s*16px\/1\.55\s/', $this->singleStyleRule($css, 'body'));
    }

    public function testSharedFormAndLauncherControlsUsePreferredTouchFloor(): void
    {
        $css = $this->servedStylesheet();
        foreach (['input, select, textarea', 'button, .button', '.button-secondary', '.apps-launcher-close'] as $selector) {
            self::assertMatchesRegularExpression('/\bmin-height:\s*44px\s*;/', $this->singleStyleRule($css, $selector), $selector);
        }
        $close = $this->singleStyleRule($css, '.apps-launcher-close');
        self::assertMatchesRegularExpression('/(?:^|;)\s*width:\s*44px\s*;/', $close);
        self::assertMatchesRegularExpression('/(?:^|;)\s*height:\s*44px\s*;/', $close);
    }

    public function testCriticalMobileNavigationLabelsUseReadableTwelvePixelBaseline(): void
    {
        $css = $this->servedStylesheet();
        $rule = $this->singleStyleRule($css, '.mobile-bottom-nav > a, .mobile-bottom-nav > span, .mobile-bottom-nav > button');
        self::assertMatchesRegularExpression('/\bfont-size:\s*12px\s*;/', $rule);
    }

    private function servedStylesheet(): string
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.css'),
        );
        self::assertSame(200, $response->getStatusCode());
        return (string) $response->getBody();
    }

    private function singleStyleRule(string $css, string $selector): string
    {
        $css = (string) preg_replace('/\s+/', ' ', $css);
        preg_match_all('~(?:^|[{}])\s*' . preg_quote($selector, '~') . '\s*\{([^{}]*)\}~', $css, $matches);
        self::assertCount(1, $matches[1], 'One authoritative rule: ' . $selector);
        return $matches[1][0];
    }

    public function testSharedNavigationExposesOneSkipLinkBeforePersistentChrome(): void
    {
        $html = $this->renderNavigation('admin', true);
        self::assertSame(1, substr_count($html, 'class="skip-link"'));
        self::assertStringContainsString('href="#main-content">Saltar al contenido principal</a>', $html);
        self::assertLessThan(strpos($html, 'class="app-shell-chrome"'), strpos($html, 'class="skip-link"'));
    }

    public function testEverySharedShellViewHasOneFocusableMainTarget(): void
    {
        $views = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/app'));
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (!str_contains($source, 'navigation.php')) {
                continue;
            }
            ++$views;
            self::assertSame(1, preg_match_all('/<main\b/i', $source), $file->getPathname());
            self::assertSame(1, substr_count($source, 'id="main-content"'), $file->getPathname());
            self::assertMatchesRegularExpression('/<main\b(?=[^>]*\bid="main-content")(?=[^>]*\btabindex="-1")[^>]*>/i', $source, $file->getPathname());
        }
        self::assertGreaterThan(0, $views);
    }

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
