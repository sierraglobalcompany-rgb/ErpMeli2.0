<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesListHttpRouteTest extends TestCase
{
    public function testSalesRouteListsOnlyOrdersFromSelectedCompany(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main'),(2,'Other','other')");
        $stmt = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'member@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES "
            . "(10,1,'700000101','MCO','connected'),(20,2,'700000202','MCO','connected')"
        );
        $pdo->exec(
            "INSERT INTO orders(company_id,account_id,external_order_id,status,last_updated,total_amount,currency_id) VALUES "
            . "(1,10,'200000000101','paid','2026-10-09 01:00:00.000000',123456.0000,'COP'),"
            . "(2,20,'200000000202','paid','2026-10-09 01:00:00.000000',999999.0000,'COP')"
        );

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/sales');
        $response = Bootstrap::create()->handle($request);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('200000000101', $html);
        self::assertStringContainsString('123456', $html);
        self::assertStringNotContainsString('200000000202', $html);
        self::assertStringNotContainsString('999999', $html);
    }
}
