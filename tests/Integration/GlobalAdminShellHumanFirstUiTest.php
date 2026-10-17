<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class GlobalAdminShellHumanFirstUiTest extends TestCase
{
    public function testSystemAdminWithoutActiveCompanyUsesSharedShellWithoutActionableCompanyDestinations(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status,is_system_admin) VALUES(1,'root-ui','Root UI','root-ui@example.test','unused','active',1)");
        $_SESSION = [
            'user_id' => 1,
            'display_name' => 'Root UI',
            'is_system_admin' => true,
        ];

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/system/companies')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="app-shell-chrome"', $html);
        self::assertStringContainsString('id="main-content"', $html);
        self::assertStringNotContainsString('class="simple-nav"', $html);
        self::assertStringContainsString('Administrador del sistema', $html);
        self::assertStringContainsString('Sin empresa activa', $html);
        self::assertStringNotContainsString('href="/dashboard"', $html);
        self::assertStringNotContainsString('href="/sales"', $html);
        self::assertStringNotContainsString('href="/catalog"', $html);
        self::assertStringNotContainsString('href="/billing"', $html);
        self::assertStringContainsString('href="/system/companies"', $html);
        self::assertStringContainsString('href="/system/users"', $html);
        self::assertStringContainsString('href="/settings/system"', $html);
        self::assertStringContainsString('href="/settings/mercadolibre"', $html);
        self::assertSame(5, substr_count($html, 'data-mobile-nav-item='));
        self::assertMatchesRegularExpression('/<span data-mobile-nav-item="inicio" class="is-disabled" aria-disabled="true">/', $html);
        self::assertMatchesRegularExpression('/<span data-mobile-nav-item="operacion" class="is-disabled" aria-disabled="true">/', $html);
        self::assertMatchesRegularExpression('/<span data-mobile-nav-item="productos" class="is-disabled" aria-disabled="true">/', $html);
    }

    public function testSystemAdminWithActiveCompanyKeepsCompanyDestinationsInsideSharedGlobalShell(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin(
            'Main Company',
            'main-company',
            'global-shell@example.test',
            'test-global-shell-password',
            'Global Shell',
            'global-shell',
        );
        self::assertTrue($auth->login('global-shell', 'test-global-shell-password'));

        foreach (['/system/companies', '/system/users', '/settings/system'] as $path) {
            $response = Bootstrap::create()->handle(
                (new ServerRequestFactory())->createServerRequest('GET', $path)
            );

            self::assertSame(200, $response->getStatusCode(), $path);
            $html = (string) $response->getBody();

            self::assertStringContainsString('class="app-shell-chrome"', $html, $path);
            self::assertStringContainsString('id="main-content"', $html, $path);
            self::assertStringNotContainsString('class="simple-nav"', $html, $path);
            self::assertStringNotContainsString('Sin empresa activa', $html, $path);
            self::assertStringContainsString('href="/dashboard"', $html, $path);
            self::assertStringContainsString('href="/sales"', $html, $path);
            self::assertStringContainsString('href="/catalog"', $html, $path);
            self::assertStringContainsString('href="/billing"', $html, $path);
            self::assertStringContainsString('href="/system/companies"', $html, $path);
            self::assertStringContainsString('href="/system/users"', $html, $path);
            self::assertStringContainsString('href="/settings/system"', $html, $path);
            self::assertStringContainsString('href="/settings/mercadolibre"', $html, $path);
            self::assertSame(5, substr_count($html, 'data-mobile-nav-item='), $path);
        }
    }
}
