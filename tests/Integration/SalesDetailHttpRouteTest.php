<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesDetailHttpRouteTest extends TestCase
{
    public function testOrderDetailIsVisibleOnlyInsideSelectedCompany(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main'),(2,'Other','other')");
        $user = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'detail@example.test',?,'active')");
        $user->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES "
            . "(10,1,'700000401','MCO','connected'),(20,2,'700000402','MCO','connected')"
        );
        $pdo->exec(
            "INSERT INTO orders(id,company_id,account_id,external_order_id,status,last_updated,total_amount,currency_id) VALUES "
            . "(101,1,10,'200000000401','paid','2026-10-09 02:00:00.000000',111111.0000,'COP'),"
            . "(202,2,20,'200000000402','paid','2026-10-09 02:00:00.000000',222222.0000,'COP')"
        );
        $pdo->exec(
            "INSERT INTO order_items(order_id,external_item_id,title,quantity,unit_price,currency_id,seller_sku) VALUES "
            . "(101,'MCO401401401','Producto visible',1.0000,111111.0000,'COP','VISIBLE-401'),"
            . "(202,'MCO402402402','Producto ajeno',1.0000,222222.0000,'COP','AJENO-402')"
        );

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $visible = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales/200000000401'),
        );
        $visibleHtml = (string) $visible->getBody();

        self::assertSame(200, $visible->getStatusCode());
        self::assertStringContainsString('200000000401', $visibleHtml);
        self::assertStringContainsString('Producto visible', $visibleHtml);
        self::assertStringContainsString('VISIBLE-401', $visibleHtml);
        self::assertStringNotContainsString('Producto ajeno', $visibleHtml);

        $foreign = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales/200000000402'),
        );

        self::assertSame(404, $foreign->getStatusCode());
        self::assertStringNotContainsString('Producto ajeno', (string) $foreign->getBody());
    }
}
