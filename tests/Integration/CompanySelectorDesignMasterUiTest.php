<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class CompanySelectorDesignMasterUiTest extends TestCase
{
    public function testMultiCompanySelectorUsesSharedDesignMasterComponentsWithoutSideEffects(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin(
            'Main Company',
            'main-company',
            'selector-ui@example.test',
            'test-selector-ui-password',
        );
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Second Company','second-company')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(2,1,'member')");

        self::assertTrue($auth->login('selector-ui@example.test', 'test-selector-ui-password'));
        self::assertArrayNotHasKey('company_id', $_SESSION);

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/company/select')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('class="simple-page company-selector-workspace"', $html);
        self::assertStringContainsString('class="page-header company-select-page-header"', $html);
        self::assertStringContainsString('class="panel company-select-panel"', $html);
        self::assertStringContainsString('class="settings-form company-select-form"', $html);
        self::assertStringContainsString('action="/company/select"', $html);
        self::assertStringContainsString('Main Company', $html);
        self::assertStringContainsString('Second Company', $html);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
    }
}
