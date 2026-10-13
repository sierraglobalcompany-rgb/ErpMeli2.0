<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Security\Csrf;
use App\Modules\Catalog\ProductRepository;
use App\Modules\Catalog\CatalogController;
use App\Modules\Inventory\InventoryRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class CatalogOperatorHttpRouteTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        (new AuthService($this->pdo))->bootstrapAdmin('Local <Company>', 'local', 'operator@example.test', 'test-operator-password');
        self::assertTrue((new AuthService($this->pdo))->login('operator@example.test', 'test-operator-password'));
        $this->pdo->exec("UPDATE company_users SET role='member'");
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Foreign','foreign')");
    }

    /** @param array<string,mixed> $body @param array<string,mixed> $query */
    private function request(string $method, string $path, array $body = [], array $query = []): ResponseInterface
    {
        $work = $this->pdo->query('SELECT * FROM work_items')->fetchAll();
        $usage = $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll();
        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest($method, $path)
            ->withParsedBody($body)->withQueryParams($query));
        self::assertSame($work, $this->pdo->query('SELECT * FROM work_items')->fetchAll(), 'Work delta must be zero.');
        self::assertSame($usage, $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll(), 'HTTP usage delta must be zero.');
        return $response;
    }

    public function testCatalogListsOnlyLatestHundredOwnProductsAndOperatorNavigation(): void
    {
        $products = new ProductRepository($this->pdo);
        $products->create(1, 'OLD', 'Old local');
        for ($i = 1; $i <= 105; $i++) {
            $products->create(1, 'LOCAL-' . $i, 'Recent ' . $i);
        }
        $products->create(2, 'FOREIGN-PRIVATE', 'Foreign private');
        $response = $this->request('GET', '/catalog');
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Local &lt;Company&gt;', $html);
        self::assertStringContainsString('LOCAL-105', $html);
        self::assertStringNotContainsString('Old local', $html);
        self::assertStringNotContainsString('FOREIGN-PRIVATE', $html);
        self::assertSame(100, substr_count($html, 'href="/catalog?product_id='));
        self::assertStringContainsString('href="/catalog"', (string) $this->request('GET', '/sales')->getBody());
    }

    public function testCatalogEmptyStateAndBoundedRepositoryContract(): void
    {
        self::assertTrue(method_exists(ProductRepository::class, 'listRecentForCompany'));
        $repo = new ProductRepository($this->pdo);
        self::assertSame([], $repo->listRecentForCompany(1));
        self::assertStringContainsString('No hay productos', (string) $this->request('GET', '/catalog')->getBody());
        $repo->create(2, 'ONLY-FOREIGN', 'Foreign');
        $first = $repo->create(1, 'ONE', 'One');
        $second = $repo->create(1, 'TWO', 'Two');
        self::assertSame([$second,$first], array_column($repo->listRecentForCompany(1), 'id'));
        self::assertSame(['id','internal_sku','name'], array_keys($repo->listRecentForCompany(1)[0]));
    }
    public function testCreateProductUsesSelectedCompanyAndRedirectsToSelection(): void
    {
        $response = $this->request('POST', '/catalog', ['internal_sku'=>'LOCAL-CREATE','name'=>'Created product','company_id'=>2,'csrf_token'=>(new Csrf())->token()]);
        self::assertSame(303, $response->getStatusCode());
        $id = (int) $this->pdo->query('SELECT id FROM products')->fetchColumn();
        self::assertSame('/catalog?product_id=' . $id, $response->getHeaderLine('Location'));
        self::assertSame(1, (int) $this->pdo->query('SELECT company_id FROM products')->fetchColumn());
        self::assertStringContainsString('action="/catalog"', (string) $this->request('GET', '/catalog')->getBody());
    }

    public function testInvalidCreateAndDuplicateRemainRecoverableWithoutSqlLeak(): void
    {
        $csrf = (new Csrf())->token();
        foreach ([['','Name'],['SKU',' '],[['bad'],'Name'],['SKU',['bad']],[str_repeat('x',121),'Name'],['SKU',str_repeat('x',256)]] as [$sku,$name]) {
            $response = $this->request('POST', '/catalog', ['internal_sku'=>$sku,'name'=>$name,'csrf_token'=>$csrf]);
            self::assertSame(422, $response->getStatusCode());
            self::assertStringContainsString('Crear producto', (string) $response->getBody());
            self::assertStringNotContainsString('SQLSTATE', (string) $response->getBody());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
        }
        (new ProductRepository($this->pdo))->create(1, 'DUP', 'Existing');
        (new ProductRepository($this->pdo))->create(2, 'OTHER', 'Foreign SKU');
        $response = $this->request('POST', '/catalog', ['internal_sku'=>'DUP','name'=>'Different','csrf_token'=>$csrf]);
        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('Ya existe un producto con este SKU', (string) $response->getBody());
        self::assertStringNotContainsString('SQLSTATE', (string) $response->getBody());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
        self::assertSame(303, $this->request('POST', '/catalog', ['internal_sku'=>'OTHER','name'=>'Own same SKU','csrf_token'=>$csrf])->getStatusCode());
        self::assertSame(419, $this->request('POST', '/catalog', ['internal_sku'=>'NO-CSRF','name'=>'Denied'])->getStatusCode());
        self::assertSame(3, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
    }

    public function testSelectionPreservesUnsetZeroAndExactPositiveInventory(): void
    {
        $id = (new ProductRepository($this->pdo))->create(1, 'SELECTED', '<Selected>');
        $response = $this->request('GET', '/catalog', [], ['product_id'=>(string) $id]);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('name="product_id"', $html);
        self::assertStringContainsString('Producto seleccionado', $html);
        self::assertStringContainsString('&lt;Selected&gt;', $html);
        self::assertStringContainsString('<output id="local-quantity">Sin definir</output>', $html);
        $inventory = new InventoryRepository($this->pdo);
        foreach ([0,9007199254740993] as $quantity) {
            $inventory->setForProduct(1, $id, $quantity);
            $html = (string) $this->request('GET', '/catalog', [], ['product_id'=>(string) $id])->getBody();
            self::assertStringContainsString('<output id="local-quantity">' . $quantity . '</output>', $html);
        }
    }

    public function testForeignAndMissingSelectionAreNotFoundAndInvalidIdsRecover(): void
    {
        $foreign = (new ProductRepository($this->pdo))->create(2, 'SECRET-FOREIGN', 'Private product');
        foreach ([(string) $foreign, '999999'] as $id) {
            $response = $this->request('GET', '/catalog', [], ['product_id'=>$id]);
            self::assertSame(404, $response->getStatusCode());
            self::assertStringContainsString('Producto no encontrado en esta empresa.', (string) $response->getBody());
            self::assertStringNotContainsString('SECRET-FOREIGN', (string) $response->getBody());
            self::assertStringContainsString('Crear producto', (string) $response->getBody());
        }
        foreach ([['bad'], '0','-1','1.0','1e2','999999999999999999999'] as $id) {
            $response = $this->request('GET', '/catalog', [], ['product_id'=>$id]);
            self::assertSame(422, $response->getStatusCode());
            self::assertStringContainsString('ID de producto inválido.', (string) $response->getBody());
        }
    }

    public function testSetInventoryAcceptsExactIntegersAndReplacesOneLocalRow(): void
    {
        $id = (new ProductRepository($this->pdo))->create(1, 'SET', 'Set quantity');
        $csrf = (new Csrf())->token();
        foreach (['0','7','0002','9007199254740993',(string) PHP_INT_MAX] as $quantity) {
            $response = $this->request('POST', '/catalog/' . $id . '/inventory', ['quantity'=>$quantity,'company_id'=>2,'csrf_token'=>$csrf]);
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/catalog?product_id=' . $id, $response->getHeaderLine('Location'));
            self::assertSame((int) $quantity, (new InventoryRepository($this->pdo))->getForProduct(1, $id));
            self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM product_inventory')->fetchColumn());
        }
        $html = (string) $this->request('GET', '/catalog', [], ['product_id'=>(string) $id])->getBody();
        self::assertStringContainsString('action="/catalog/' . $id . '/inventory"', $html);
        self::assertStringContainsString('name="quantity"', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public function testInvalidInventoryForeignMissingAndCsrfFailWithoutMutation(): void
    {
        $products = new ProductRepository($this->pdo);
        $id = $products->create(1, 'SET', 'Own');
        $foreign = $products->create(2, 'PRIVATE', 'Foreign');
        $inventory = new InventoryRepository($this->pdo);
        $inventory->setForProduct(1, $id, 5);
        $inventory->setForProduct(2, $foreign, 11);
        $before = $this->pdo->query('SELECT * FROM product_inventory ORDER BY product_id')->fetchAll();
        $csrf = (new Csrf())->token();
        foreach (['-1','1.5','1e3','abc','', ' ', '9223372036854775808',1.0,true,['bad']] as $quantity) {
            $response = $this->request('POST', '/catalog/' . $id . '/inventory', ['quantity'=>$quantity,'csrf_token'=>$csrf]);
            self::assertSame(422, $response->getStatusCode());
            self::assertStringContainsString('Cantidad', (string) $response->getBody());
            self::assertStringContainsString('Crear producto', (string) $response->getBody());
            self::assertStringNotContainsString('SQLSTATE', (string) $response->getBody());
            self::assertSame($before, $this->pdo->query('SELECT * FROM product_inventory ORDER BY product_id')->fetchAll());
        }
        foreach ([$foreign,999999,0] as $productId) {
            self::assertSame(404, $this->request('POST', '/catalog/' . $productId . '/inventory', ['quantity'=>'3','csrf_token'=>$csrf])->getStatusCode());
        }
        self::assertSame(419, $this->request('POST', '/catalog/' . $id . '/inventory', ['quantity'=>'0'])->getStatusCode());
        self::assertSame($before, $this->pdo->query('SELECT * FROM product_inventory ORDER BY product_id')->fetchAll());
    }

    public function testDisabledUnauthenticatedAndForeignSessionLoseAllCatalogAuthority(): void
    {
        $id = (new ProductRepository($this->pdo))->create(1, 'OWN', 'Own product');
        (new InventoryRepository($this->pdo))->setForProduct(1, $id, 6);
        $csrf = (new Csrf())->token();
        $products = $this->pdo->query('SELECT * FROM products')->fetchAll();
        $inventory = $this->pdo->query('SELECT * FROM product_inventory')->fetchAll();
        $session = $_SESSION;
        foreach (['foreign','disabled','unauthenticated'] as $condition) {
            $_SESSION = $session;
            $this->pdo->exec("UPDATE users SET status='active'");
            if ($condition === 'foreign') { $_SESSION['company_id'] = 2; }
            if ($condition === 'disabled') { $this->pdo->exec("UPDATE users SET status='disabled'"); }
            if ($condition === 'unauthenticated') { $_SESSION = []; }
            foreach ([['GET','/catalog',[]],['POST','/catalog',['internal_sku'=>'DENIED','name'=>'Denied','csrf_token'=>$csrf]],
                ['POST','/catalog/' . $id . '/inventory',['quantity'=>'99','csrf_token'=>$csrf]]] as [$method,$path,$body]) {
                self::assertSame(403, $this->request($method, $path, $body)->getStatusCode());
            }
            self::assertSame($products, $this->pdo->query('SELECT * FROM products')->fetchAll());
            self::assertSame($inventory, $this->pdo->query('SELECT * FROM product_inventory')->fetchAll());
        }
    }

    public function testListReadsNoInventoryAndSelectionReadsInventoryExactlyOnce(): void
    {
        $repo = new ProductRepository($this->pdo);
        for ($i = 1; $i <= 100; $i++) { $repo->create(1, 'ROW-' . $i, 'Row ' . $i); }
        $config = TestDatabase::config();
        $tracedPdo = new class($config) extends PDO {
            /** @var list<string> */
            public array $queries = [];
            public function __construct(\App\Core\Config\AppConfig $config)
            {
                parent::__construct(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->dbHost, $config->dbPort, $config->dbName),
                    $config->dbUser, $config->dbPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            }
            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $this->queries[] = $query;
                return parent::prepare($query, $options);
            }
        };
        $controller = new CatalogController($tracedPdo);
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/catalog');
        $factory = new \Slim\Psr7\Factory\ResponseFactory();
        self::assertSame(200, $controller->index($request, $factory->createResponse())->getStatusCode());
        self::assertCount(0, array_filter($tracedPdo->queries, static fn(string $sql): bool => str_contains($sql, 'product_inventory')));
        self::assertCount(1, array_filter($tracedPdo->queries, static fn(string $sql): bool => str_contains($sql, 'ORDER BY id DESC LIMIT 100')));
        $tracedPdo->queries = [];
        self::assertSame(200, $controller->index($request->withQueryParams(['product_id'=>'1']), $factory->createResponse())->getStatusCode());
        self::assertCount(1, array_filter($tracedPdo->queries, static fn(string $sql): bool => str_contains($sql, 'product_inventory')));
        self::assertCount(1, array_filter($tracedPdo->queries, static fn(string $sql): bool => str_contains($sql, 'FROM products WHERE company_id = :company_id AND id = :product_id')));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
    }

}
