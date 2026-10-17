<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class CompanySelectorHomeContractTest extends TestCase
{
    public function testSingleActiveCompanySelectorReturnsToApprovedDashboardHome(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin(
            'Main Company',
            'main-company',
            'selector-home@example.test',
            'test-selector-home-password',
        );
        self::assertTrue($auth->login('selector-home@example.test', 'test-selector-home-password'));

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/company/select')
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/dashboard', $response->getHeaderLine('Location'));
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }
}
