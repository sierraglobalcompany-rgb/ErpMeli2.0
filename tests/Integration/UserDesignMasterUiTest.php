<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class UserDesignMasterUiTest extends TestCase
{
    public function testUsersUseDesignMasterAdministrationSurfaceWithoutChangingUserOwner(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin('Main Company', 'main-company', 'users-ui@example.test', 'test-users-ui-password', 'Users UI', 'users-ui');
        self::assertTrue($auth->login('users-ui', 'test-users-ui-password'));

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/system/users')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="simple-page administration-users-workspace"', $html);
        self::assertStringContainsString('data-design-master-page="users"', $html);
        self::assertStringContainsString('class="page-header users-page-header"', $html);
        self::assertStringContainsString('class="panel panel-full users-create-panel"', $html);
        self::assertStringContainsString('class="users-list"', $html);
        self::assertSame(1, substr_count($html, 'data-admin-card="user"'));
        self::assertStringContainsString('action="/system/users"', $html);
        self::assertStringContainsString('action="/system/users/1/update"', $html);
        self::assertStringContainsString('action="/system/users/1/status"', $html);
        self::assertStringContainsString('action="/system/users/1/system-admin"', $html);
        self::assertStringContainsString('action="/system/users/1/password"', $html);
        self::assertStringContainsString('action="/system/users/1/membership"', $html);
        self::assertStringContainsString('action="/system/users/1/remove-membership"', $html);
        self::assertStringNotContainsString('action="/system/users/1/delete"', $html);
        self::assertStringNotContainsString('href="/system/roles"', $html);
    }
}
