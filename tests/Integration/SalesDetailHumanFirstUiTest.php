<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesDetailHumanFirstUiTest extends TestCase
{
    public function testMobileSaleDetailPrioritizesFinancialMeaningWithoutDuplicateSummary(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.css'),
        );
        self::assertSame(200, $response->getStatusCode());

        preg_match('/@media\s*\(\s*max-width\s*:\s*760px\s*\)\s*\{((?:[^{}]|\{[^{}]*\})*)\}/s', (string) $response->getBody(), $mobile);
        self::assertArrayHasKey(1, $mobile, 'The mobile breakpoint must be served by the application.');
        self::assertMatchesRegularExpression('/\.sales-summary\s*\{\s*display\s*:\s*none\s*;?\s*\}/', $mobile[1]);
        self::assertMatchesRegularExpression('/\.financial-human-summary\s*\{\s*order\s*:\s*-1\s*;?\s*\}/', $mobile[1]);
    }

    public function testConflictSaleDetailIsHumanFirstAndKeepsRawFactsInTechnicalDisclosure(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $user = $pdo->prepare("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'human@example.test','Human','human@example.test',?,'active')");
        $user->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES (10,1,'human-account','MCO','connected')");

        $pdo->exec(
            "INSERT INTO orders(id,company_id,account_id,external_order_id,status,total_amount,currency_id,pack_id) VALUES "
            . "(101,1,10,'1001','paid','10.0000','COP','PACK-A'),"
            . "(102,1,10,'1002','paid','10.0000','COP','PACK-A')"
        );
        $pdo->exec(
            "INSERT INTO order_items(order_id,external_item_id,title,quantity,unit_price,currency_id,sale_fee,seller_sku) VALUES "
            . "(101,'ITEM-1001','Producto COP',1.0000,10.0000,'COP',0.0000,'SKU-COP'),"
            . "(102,'ITEM-1002','Producto USD',1.0000,10.0000,'USD',0.0000,'SKU-USD')"
        );

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales/1001'),
        );
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());

        // Human summary first: business language, not raw machine state as the primary message.
        self::assertStringContainsString('Pagada', $html);
        self::assertStringContainsString('Requiere revisión', $html);
        self::assertStringContainsString('monedas', mb_strtolower($html));
        self::assertStringContainsString('Sin información suficiente', $html);

        // Progressive disclosure: raw reconciliation remains available for audit/support.
        self::assertStringContainsString('class="technical-disclosure"', $html);
        self::assertStringContainsString('Ver conciliación técnica', $html);
        self::assertStringContainsString('CONFLICT', $html);
        self::assertStringContainsString('UNKNOWN', $html);

        // Empty Billing evidence should not render spreadsheet/table chrome.
        self::assertStringContainsString('Sin detalles de facturación', $html);
        self::assertStringNotContainsString('data-responsive-table="billing-details"', $html);

        // A normal Sale Detail product set should read as compact records, not an internal horizontal table.
        self::assertStringContainsString('class="sale-product-list"', $html);
        self::assertStringContainsString('data-sale-product="product-row"', $html);
        self::assertStringNotContainsString('data-responsive-table="sale-products"', $html);

        // Raw post-sale/readiness codes should not be the primary presentation.
        self::assertStringContainsString('Sin postventa registrada', $html);
        self::assertStringContainsString('Membresía del paquete sin verificar', $html);
    }
}
