<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Modules\Catalog\ProductRepository;
use App\Modules\Inventory\InventoryRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class CatalogInternalSearchHttpRouteTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $auth = new AuthService($this->pdo);
        $auth->bootstrapAdmin('Local Company', 'local', 'search-catalog@example.test', 'test-search-catalog-password');
        self::assertTrue($auth->login('search-catalog@example.test', 'test-search-catalog-password'));
    }

    /** @param array<string,mixed> $query */
    private function search(array $query): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/catalog')->withQueryParams($query);
        return Bootstrap::create()->handle($request);
    }

    public function testSearchFindsOlderSkuAndNameBeyondTheRecentHundred(): void
    {
        $products = new ProductRepository($this->pdo);
        $id = $products->create(1, 'LOCAL-SPECIAL-001', 'Producto de bodega histórico');
        for ($n = 1; $n <= 105; $n++) {
            $products->create(1, 'OTHER-' . $n, 'Otro producto ' . $n);
        }
        $initial = (string) $this->search([])->getBody();
        self::assertStringNotContainsString('href="/catalog?product_id=' . $id . '"', $initial);
        $skuSearch = $this->search(['q' => 'special-001']);
        self::assertSame(200, $skuSearch->getStatusCode());
        $skuHtml = (string) $skuSearch->getBody();
        self::assertStringContainsString('href="/catalog?product_id=' . $id . '"', $skuHtml);
        self::assertStringContainsString('<td data-label="SKU interno"><a class="primary-record-link" href="/catalog?product_id=' . $id . '">LOCAL-SPECIAL-001</a></td>', $skuHtml);
        self::assertSame(1, substr_count($skuHtml, 'data-mobile-card="product-row"'));
        self::assertStringContainsString('name="q"', $skuHtml);
        self::assertStringContainsString('Resultados de búsqueda (máximo 100)', $skuHtml);
        self::assertStringContainsString('LOCAL-SPECIAL-001', (string) $this->search(['q' => 'bodega histórico'])->getBody());
    }

    public function testSearchIsTenantScopedLiteralAndEscaped(): void
    {
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Foreign','foreign')");
        $repo = new ProductRepository($this->pdo);
        $ours = $repo->create(1, 'P%_!X', '<script>Producto & local</script>');
        $repo->create(1, 'PAAAX', 'No coincide literal');
        $repo->create(2, 'P%_!X-FOREIGN', 'Privado de otra empresa');
        $html = (string) $this->search(['q' => '%_!'])->getBody();
        self::assertStringContainsString('href="/catalog?product_id=' . $ours . '"', $html);
        self::assertSame(1, substr_count($html, 'data-mobile-card="product-row"'));
        self::assertStringNotContainsString('Privado de otra empresa', $html);
        self::assertStringNotContainsString('PAAAX', $html);
        self::assertStringContainsString('P%_!X', $html);
        $escaped = (string) $this->search(['q' => 'Producto & local'])->getBody();
        self::assertStringContainsString('&lt;script&gt;Producto &amp; local&lt;/script&gt;', $escaped);
        self::assertStringNotContainsString('<script>Producto & local</script>', $escaped);
        self::assertStringContainsString('value="Producto &amp; local"', $escaped);
    }

    public function testSearchRejectsMalformedInputsWithoutChangingProductOrInventory(): void
    {
        $repo = new ProductRepository($this->pdo);
        $id = $repo->create(1, 'STOCK-1', 'Stock local');
        (new InventoryRepository($this->pdo))->setForProduct(1, $id, 0);
        $before = $this->pdo->query('SELECT * FROM products')->fetchAll();
        $inventory = $this->pdo->query('SELECT * FROM product_inventory')->fetchAll();
        foreach ([['q'=>['nested']], ['q'=>str_repeat('x', 121)]] as $query) {
            $response = $this->search($query);
            self::assertSame(422, $response->getStatusCode());
            self::assertStringContainsString('Corrige la búsqueda', (string) $response->getBody());
            self::assertStringNotContainsString('data-mobile-card="product-row"', (string) $response->getBody());
        }
        self::assertSame($before, $this->pdo->query('SELECT * FROM products')->fetchAll());
        self::assertSame($inventory, $this->pdo->query('SELECT * FROM product_inventory')->fetchAll());
        $detail = (string) $this->search(['product_id' => (string) $id])->getBody();
        self::assertStringContainsString('<output id="local-quantity">0</output>', $detail);
        $notFound = (string) $this->search(['q' => 'no-existe'])->getBody();
        self::assertStringContainsString('No se encontraron productos internos', $notFound);
        self::assertStringNotContainsString('Sin definir</output>', $detail);
    }

    public function testReadOnlyRoleCanSearchButCannotCreateAndNoRemoteCallIsNeeded(): void
    {
        (new ProductRepository($this->pdo))->create(1, 'VISIBLE', 'Producto visible');
        $this->pdo->exec("UPDATE company_users SET role='viewer' WHERE company_id=1");
        $response = $this->search(['q' => 'VISIBLE']);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('VISIBLE', $html);
        self::assertStringNotContainsString('name="internal_sku"', $html);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
    }
}
