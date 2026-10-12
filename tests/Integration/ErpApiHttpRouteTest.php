<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use App\Modules\Catalog\ProductRepository;
use App\Modules\Inventory\InventoryRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\TestDatabase;

final class ErpApiHttpRouteTest extends TestCase
{
    private const string CSRF_TOKEN = 'erp-api-test-csrf-token-1234567890';

    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $passwordHash = (new PasswordService())->hash('secret');

        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main'),(2,'Other','other')");
        $user = $this->pdo->prepare(
            "INSERT INTO users(id,email,password_hash,status) VALUES (1,'api@example.test',?,'active')",
        );
        $user->execute([$passwordHash]);
        $this->pdo->exec(
            "INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')",
        );

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;
        $_SESSION['csrf_token'] = self::CSRF_TOKEN;
    }

    public function testCompanyCanCreateAndReadProductWithoutClientCompanyOverride(): void
    {
        $created = $this->request('POST', '/api/products', [
            'company_id' => 2,
            'internal_sku' => 'API-001',
            'name' => 'Producto API',
        ]);
        $createdBody = $this->json($created);

        self::assertSame(201, $created->getStatusCode());
        self::assertSame('application/json; charset=utf-8', $created->getHeaderLine('Content-Type'));
        self::assertSame('/api/products/' . $createdBody['id'], $created->getHeaderLine('Location'));
        self::assertSame(1, (int) $this->pdo->query('SELECT company_id FROM products')->fetchColumn());

        $read = $this->request('GET', '/api/products/' . $createdBody['id']);

        self::assertSame(200, $read->getStatusCode());
        self::assertSame([
            'id' => $createdBody['id'],
            'internal_sku' => 'API-001',
            'name' => 'Producto API',
        ], $this->json($read));
    }

    public function testDuplicateSkuReturnsStableConflictWithoutDatabaseDetails(): void
    {
        $payload = ['internal_sku' => 'API-DUP', 'name' => 'Producto'];
        self::assertSame(201, $this->request('POST', '/api/products', $payload)->getStatusCode());

        $response = $this->request('POST', '/api/products', $payload);
        $body = (string) $response->getBody();

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(['error' => 'duplicate_sku'], $this->json($response));
        self::assertStringNotContainsString('SQLSTATE', $body);
        self::assertStringNotContainsString('Duplicate entry', $body);
    }

    public function testOtherCompanyProductIsReturnedAsNotFound(): void
    {
        $productId = (new ProductRepository($this->pdo))->create(2, 'FOREIGN-001', 'Producto privado');

        $response = $this->request('GET', '/api/products/' . $productId);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'not_found'], $this->json($response));
        self::assertStringNotContainsString('Producto privado', (string) $response->getBody());
    }

    public function testCompanyCanReadStockForItsProduct(): void
    {
        $productId = (new ProductRepository($this->pdo))->create(1, 'STOCK-READ', 'Producto con stock');
        (new InventoryRepository($this->pdo))->setForProduct(1, $productId, 12);

        $response = $this->request('GET', '/api/products/' . $productId . '/inventory');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['product_id' => $productId, 'quantity' => 12], $this->json($response));
    }

    public function testCompanyCanSetStockDeterministically(): void
    {
        $productId = (new ProductRepository($this->pdo))->create(1, 'STOCK-SET', 'Producto editable');

        $first = $this->request('PUT', '/api/products/' . $productId . '/inventory', ['quantity' => 14]);
        $second = $this->request('PUT', '/api/products/' . $productId . '/inventory', ['quantity' => 9]);

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(['product_id' => $productId, 'quantity' => 9], $this->json($second));
        self::assertSame(9, (int) $this->pdo->query('SELECT quantity FROM product_inventory')->fetchColumn());
    }

    public function testNegativeStockIsRejectedWithoutPersistence(): void
    {
        $productId = (new ProductRepository($this->pdo))->create(1, 'STOCK-NEG', 'Producto local');

        $response = $this->request('PUT', '/api/products/' . $productId . '/inventory', ['quantity' => -1]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(['error' => 'invalid_quantity'], $this->json($response));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM product_inventory')->fetchColumn());
    }

    public function testOtherCompanyStockCannotBeReadOrChanged(): void
    {
        $productId = (new ProductRepository($this->pdo))->create(2, 'STOCK-FOREIGN', 'Producto ajeno');
        (new InventoryRepository($this->pdo))->setForProduct(2, $productId, 7);

        $read = $this->request('GET', '/api/products/' . $productId . '/inventory');
        $write = $this->request('PUT', '/api/products/' . $productId . '/inventory', ['quantity' => 0]);

        self::assertSame(404, $read->getStatusCode());
        self::assertSame(['error' => 'not_found'], $this->json($read));
        self::assertSame(404, $write->getStatusCode());
        self::assertSame(['error' => 'not_found'], $this->json($write));
        self::assertSame(7, (int) $this->pdo->query('SELECT quantity FROM product_inventory')->fetchColumn());
    }

    public function testMissingStockIsRepresentedAsNullRatherThanZero(): void
    {
        $productId = (new ProductRepository($this->pdo))->create(1, 'STOCK-MISSING', 'Producto sin fila');

        $response = $this->request('GET', '/api/products/' . $productId . '/inventory');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['product_id' => $productId, 'quantity' => null], $this->json($response));
    }

    public function testUnauthenticatedRequestUsesExistingForbiddenBehavior(): void
    {
        $_SESSION = [];

        $response = $this->request('GET', '/api/products/1');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['error' => 'forbidden'], $this->json($response));
    }

    public function testWriteRequiresTheExistingSessionCsrfToken(): void
    {
        $response = $this->request('POST', '/api/products', [
            'internal_sku' => 'NO-CSRF',
            'name' => 'Sin token',
        ], includeCsrf: false);

        self::assertSame(419, $response->getStatusCode());
        self::assertSame(['error' => 'invalid_csrf'], $this->json($response));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
    }

    public function testUnexpectedDatabaseExceptionDoesNotLeakSqlDetails(): void
    {
        $this->pdo->exec('DROP TABLE product_inventory');
        $this->pdo->exec('DROP TABLE products');

        $response = $this->request('GET', '/api/products/1');
        $body = (string) $response->getBody();

        self::assertSame(500, $response->getStatusCode());
        self::assertStringNotContainsString('SQLSTATE', $body);
        self::assertStringNotContainsString('products', $body);
        self::assertStringNotContainsString('SELECT', $body);
    }

    /** @param array<string,mixed>|null $payload */
    private function request(
        string $method,
        string $uri,
        ?array $payload = null,
        bool $includeCsrf = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        if ($payload !== null) {
            $body = (new StreamFactory())->createStream(json_encode($payload, JSON_THROW_ON_ERROR));
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($body);
        }
        if ($includeCsrf) {
            $request = $request->withHeader('X-CSRF-Token', self::CSRF_TOKEN);
        }

        return Bootstrap::create()->handle($request);
    }

    /** @return array<string,mixed> */
    private function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
