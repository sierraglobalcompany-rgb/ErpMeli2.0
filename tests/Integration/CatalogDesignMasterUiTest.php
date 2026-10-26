<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Modules\Catalog\ProductRepository;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class CatalogDesignMasterUiTest extends TestCase
{
    public function testCatalogUsesOneResponsiveProductSurfaceWithSelectedInventoryCard(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin('Local Company', 'local', 'catalog-ui@example.test', 'test-catalog-ui-password');
        self::assertTrue($auth->login('catalog-ui@example.test', 'test-catalog-ui-password'));
        $pdo->exec("UPDATE company_users SET role='member'");

        $productId = (new ProductRepository($pdo))->create(1, 'SKU-DM4', 'Producto UI DM4');
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/catalog')
            ->withQueryParams(['product_id' => (string) $productId]);
        $response = Bootstrap::create()->handle($request);

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('<title>Productos e inventario local</title>', $html);
        self::assertStringContainsString('<span>Productos e inventario local</span>', $html);
        self::assertStringContainsString('<small>Inventario local</small>', $html);
        self::assertStringContainsString('<h1>Productos e inventario local</h1>', $html);
        self::assertStringContainsString('<span>Productos</span>', $html);
        self::assertStringNotContainsString('<h1>Catálogo e inventario</h1>', $html);
        self::assertStringContainsString('class="page-header catalog-page-header"', $html);
        self::assertStringContainsString('class="catalog-workspace"', $html);
        self::assertStringContainsString('data-responsive-table="catalog-products"', $html);
        self::assertSame(1, substr_count($html, 'data-mobile-card="product-row"'));
        self::assertStringContainsString('data-label="SKU interno"', $html);
        self::assertStringContainsString('data-label="Nombre"', $html);
        self::assertStringContainsString('class="catalog-inventory-card"', $html);
        self::assertStringContainsString('<output id="local-quantity">Sin definir</output>', $html);
        self::assertStringNotContainsString('href="/inventory"', $html);
    }

    public function testExactIdLookupIsSecondaryProgressiveDisclosure(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin('Local Company', 'local', 'catalog-hierarchy@example.test', 'test-catalog-hierarchy-password');
        self::assertTrue($auth->login('catalog-hierarchy@example.test', 'test-catalog-hierarchy-password'));
        $pdo->exec("UPDATE company_users SET role='member'");

        (new ProductRepository($pdo))->create(1, 'SKU-H5', 'Producto H5');
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/catalog')
        );

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('href="/catalog?product_id=', $html);
        self::assertStringContainsString('<details class="catalog-advanced-selection">', $html);
        self::assertStringContainsString('<summary>Seleccionar producto por ID</summary>', $html);
        self::assertStringContainsString('name="product_id"', $html);
    }
}
