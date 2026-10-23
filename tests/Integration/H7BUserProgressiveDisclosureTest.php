<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class H7BUserProgressiveDisclosureTest extends TestCase
{
    public function testUserAdministrationUsesThreeProgressiveDisclosureGroupsWithoutChangingOwners(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin(
            'Main Company',
            'main-company',
            'h7b-users@example.test',
            'test-h7b-users-password',
            'H7B Users',
            'h7b-users'
        );
        self::assertTrue($auth->login('h7b-users', 'test-h7b-users-password'));

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/system/users')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertSame(1, substr_count($html, 'data-admin-card="user"'));
        self::assertSame(1, substr_count($html, 'data-user-section="identity"'));
        self::assertSame(1, substr_count($html, 'data-user-section="security"'));
        self::assertSame(1, substr_count($html, 'data-user-section="memberships"'));
        self::assertSame(3, substr_count($html, 'data-user-section='));

        self::assertStringContainsString('<summary>Identidad</summary>', $html);
        self::assertStringContainsString('<summary>Seguridad y acceso global</summary>', $html);
        self::assertStringContainsString('<summary>Acceso por empresa</summary>', $html);

        $identity = self::section($html, 'identity');
        self::assertStringContainsString('action="/system/users/1/update"', $identity);
        self::assertStringNotContainsString('action="/system/users/1/password"', $identity);
        self::assertStringNotContainsString('action="/system/users/1/membership"', $identity);

        $security = self::section($html, 'security');
        self::assertStringContainsString('action="/system/users/1/status"', $security);
        self::assertStringContainsString('action="/system/users/1/system-admin"', $security);
        self::assertStringContainsString('action="/system/users/1/password"', $security);
        self::assertStringNotContainsString('action="/system/users/1/membership"', $security);

        $memberships = self::section($html, 'memberships');
        self::assertStringContainsString('action="/system/users/1/membership"', $memberships);
        self::assertStringContainsString('action="/system/users/1/remove-membership"', $memberships);
        self::assertStringNotContainsString('action="/system/users/1/password"', $memberships);

        self::assertStringContainsString('action="/system/users"', $html);
        self::assertStringNotContainsString('action="/system/users/1/delete"', $html);
        self::assertStringNotContainsString('href="/system/roles"', $html);
    }

    private static function section(string $html, string $name): string
    {
        $marker = 'data-user-section="' . $name . '"';
        $markerOffset = strpos($html, $marker);
        self::assertNotFalse($markerOffset, 'Missing disclosure section ' . $name);

        $start = strrpos(substr($html, 0, $markerOffset), '<details');
        self::assertNotFalse($start, 'Missing details start for ' . $name);

        $end = strpos($html, '</details>', $markerOffset);
        self::assertNotFalse($end, 'Missing details end for ' . $name);

        return substr($html, $start, $end + strlen('</details>') - $start);
    }
}
