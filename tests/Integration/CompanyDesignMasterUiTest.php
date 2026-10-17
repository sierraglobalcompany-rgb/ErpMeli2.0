<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class CompanyDesignMasterUiTest extends TestCase
{
    public function testCompaniesUseSharedDesignMasterAdministrationShellWithoutChangingOwner(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin('Main Company', 'main-company', 'companies-ui@example.test', 'test-companies-ui-password');
        self::assertTrue($auth->login('companies-ui@example.test', 'test-companies-ui-password'));

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/system/companies')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="app-shell-chrome"', $html);
        self::assertStringContainsString('id="main-content"', $html);
        self::assertStringContainsString('class="administration-companies-workspace"', $html);
        self::assertStringNotContainsString('class="simple-nav"', $html);
        self::assertStringContainsString('data-design-master-page="companies"', $html);
        self::assertStringContainsString('class="page-header companies-page-header"', $html);
        self::assertStringContainsString('class="panel panel-full companies-create-panel"', $html);
        self::assertStringContainsString('class="companies-list"', $html);
        self::assertSame(1, substr_count($html, 'data-admin-card="company"'));
        self::assertStringContainsString('action="/system/companies"', $html);
        self::assertStringContainsString('action="/system/companies/1/update"', $html);
        self::assertStringContainsString('action="/system/companies/1/archive"', $html);
        self::assertStringNotContainsString('href="/system/companies/new"', $html);
        self::assertStringNotContainsString('href="/system/roles"', $html);
    }
}
