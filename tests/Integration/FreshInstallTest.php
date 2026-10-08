<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Auth\PasswordService;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class FreshInstallTest extends TestCase
{
    public function testFreshInstallCanBootHealthLoginAndReadSettings(): void
    {
        $pdo = TestDatabase::reset();

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $hash = (new PasswordService())->hash('secret');
        $stmt = $pdo->prepare(
            "INSERT INTO users(id,email,password_hash,status) VALUES (1,'admin@example.test',?,'active')"
        );
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");

        $app = Bootstrap::create();
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/health'));
        self::assertSame(200, $response->getStatusCode());

        self::assertTrue((new AuthService($pdo))->login('admin@example.test', 'secret'));
        self::assertFalse((new SystemSettingsRepository($pdo))->get()->meliWritesEnabled);
    }
}
