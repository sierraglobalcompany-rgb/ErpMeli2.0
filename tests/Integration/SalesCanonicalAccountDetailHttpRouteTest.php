<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesCanonicalAccountDetailHttpRouteTest extends TestCase
{
    public function testAccountScopedLinksResolveCollisionsWithoutCrossCompanyAccess(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('synthetic-only');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Own','own'),(2,'Other','other')");
        $user = $pdo->prepare("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'scope@example.test','Sales member','scope@example.test',?,'active')");
        $user->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES "
            . "(10,1,'700000010','MCO','Tienda principal','connected'),"
            . "(20,1,'700000020','MCO','Tienda alternativa','connected'),"
            . "(30,2,'700000030','MCO','Cuenta ajena','connected')"
        );
        $pdo->exec(
            "INSERT INTO orders(id,company_id,account_id,external_order_id,status,last_updated,date_created,total_amount,currency_id) VALUES "
            . "(101,1,10,'200000000111','paid','2026-10-09 01:00:00.000000','2026-10-09 01:00:00.000000',1000.0000,'COP'),"
            . "(102,1,20,'200000000111','paid','2026-10-09 02:00:00.000000','2026-10-09 02:00:00.000000',2000.0000,'COP'),"
            . "(103,2,30,'200000000111','paid','2026-10-09 03:00:00.000000','2026-10-09 03:00:00.000000',3000.0000,'COP')"
        );
        $pdo->exec(
            "INSERT INTO order_items(order_id,external_item_id,title,quantity,unit_price,currency_id,seller_sku) VALUES "
            . "(101,'MCO101','Producto uno',1,1000,'COP','SKU-UNO'),"
            . "(102,'MCO102','Producto dos',1,2000,'COP','SKU-DOS'),"
            . "(103,'MCO103','Producto ajeno',1,3000,'COP','SKU-AJENO')"
        );

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $sales = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales'));
        $html = (string) $sales->getBody();
        self::assertSame(200, $sales->getStatusCode());
        self::assertSame(1, substr_count($html, 'href="/sales/200000000111?account_id=10"'));
        self::assertSame(1, substr_count($html, 'href="/sales/200000000111?account_id=20"'));
        self::assertStringContainsString('Tienda principal', $html);
        self::assertStringContainsString('Tienda alternativa', $html);
        self::assertStringContainsString('ID vendedor: 700000010', $html);
        self::assertStringNotContainsString('Cuenta ajena', $html);

        $dashboard = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/dashboard')
                ->withQueryParams(['from' => '2026-10-01', 'to' => '2026-10-31']),
        );
        self::assertSame(200, $dashboard->getStatusCode());
        self::assertStringContainsString('href="/sales/200000000111?account_id=10"', (string) $dashboard->getBody());
        self::assertStringContainsString('href="/sales/200000000111?account_id=20"', (string) $dashboard->getBody());

        // Legacy route fails closed when account ambiguity exists.
        self::assertSame(404, $this->detail('200000000111')->getStatusCode());

        $one = $this->detail('200000000111', ['account_id' => '10']);
        self::assertSame(200, $one->getStatusCode());
        self::assertStringContainsString('Producto uno', (string) $one->getBody());
        self::assertStringNotContainsString('Producto dos', (string) $one->getBody());
        self::assertStringNotContainsString('Producto ajeno', (string) $one->getBody());

        $two = $this->detail('200000000111', ['account_id' => '20']);
        self::assertSame(200, $two->getStatusCode());
        self::assertStringContainsString('Producto dos', (string) $two->getBody());
        self::assertStringNotContainsString('Producto uno', (string) $two->getBody());
        self::assertStringNotContainsString('Producto ajeno', (string) $two->getBody());

        foreach ([['account_id' => '30'], ['account_id' => '0'], ['account_id' => '-1'], ['account_id' => 'abc'], ['account_id' => ['10']]] as $scope) {
            self::assertSame(404, $this->detail('200000000111', $scope)->getStatusCode());
        }
    }

    private function detail(string $orderId, array $query = []): \Psr\Http\Message\ResponseInterface
    {
        return Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales/' . $orderId)
                ->withQueryParams($query),
        );
    }
}
