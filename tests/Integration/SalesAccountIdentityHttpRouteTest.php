<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesAccountIdentityHttpRouteTest extends TestCase
{
    public function testSalesShowsNicknameAndSellerIdForEachAccountWithoutLeakingOtherCompany(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('synthetic-secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Empresa A','empresa-a'),(2,'Empresa B','empresa-b')");
        $stmt = $pdo->prepare("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'sales@example.test','Ventas','sales@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES "
            . "(10,1,'700000101','MCO','Tienda Principal','connected'),"
            . "(20,1,'700000102','MCO','<b>Tienda Alterna</b>','connected'),"
            . "(30,1,'700000103','MCO',NULL,'connected'),"
            . "(40,2,'700000104','MCO','Tienda Ajena','connected')");
        $pdo->exec("INSERT INTO orders(company_id,account_id,external_order_id,status,last_updated,total_amount,currency_id) VALUES "
            . "(1,10,'200000000101','paid','2026-10-09 01:00:00.000000',1000.0000,'COP'),"
            . "(1,20,'200000000102','paid','2026-10-09 01:01:00.000000',2000.0000,'COP'),"
            . "(1,30,'200000000103','paid','2026-10-09 01:02:00.000000',3000.0000,'COP'),"
            . "(2,40,'200000000104','paid','2026-10-09 01:03:00.000000',4000.0000,'COP')");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;
        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales'));
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Tienda Principal', $html);
        self::assertStringContainsString('ID vendedor: 700000101', $html);
        self::assertStringContainsString('&lt;b&gt;Tienda Alterna&lt;/b&gt;', $html);
        self::assertStringNotContainsString('<b>Tienda Alterna</b>', $html);
        self::assertStringContainsString('ID vendedor: 700000102', $html);
        self::assertStringContainsString('ID vendedor: 700000103', $html);
        self::assertStringNotContainsString('Tienda Ajena', $html);
        self::assertStringNotContainsString('200000000104', $html);
    }
}
