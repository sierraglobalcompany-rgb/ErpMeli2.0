<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SystemSettingsDesignMasterUiTest extends TestCase
{
    public function testSystemSettingsUseDesignMasterAdministrationSurfaceWithoutChangingOwners(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin('Main Company', 'main-company', 'system-ui@example.test', 'test-system-ui-password', 'System UI', 'system-ui');
        self::assertTrue($auth->login('system-ui', 'test-system-ui-password'));

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/settings/system')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="simple-page administration-system-workspace"', $html);
        self::assertStringContainsString('data-design-master-page="system-settings"', $html);
        self::assertStringContainsString('class="page-header system-settings-page-header"', $html);
        self::assertStringContainsString('class="panel panel-full system-operation-panel"', $html);
        self::assertStringContainsString('class="panel panel-full system-debug-panel"', $html);
        self::assertStringContainsString('action="/settings/system"', $html);
        self::assertStringContainsString('action="/settings/system/debug/export"', $html);
        self::assertStringContainsString('action="/settings/system/debug/clear"', $html);
        self::assertStringContainsString('name="automation_enabled"', $html);
        self::assertStringContainsString('name="debug_enabled"', $html);
        self::assertStringNotContainsString('name="meli_writes_enabled"', $html);
        self::assertStringNotContainsString('Escrituras Mercado Libre', $html);
    }
}
