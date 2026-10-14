<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Tenancy\CompanyContext;
use App\Modules\Catalog\ProductRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Tests\Support\TestDatabase;

final class CompanyRoleAuthorizationTest extends TestCase
{
    private const string CSRF = 'company-role-test-csrf-1234567890';

    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Main','main')");
        $this->pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES
            (1,'operator','Operator','operator@example.test','x','active'),
            (2,'viewer','Viewer','viewer@example.test','x','active'),
            (3,'invalid-role','Invalid','invalid@example.test','x','active')");
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES
            (1,1,'operator'),(1,2,'viewer'),(1,3,'owner')");
        $_SESSION['csrf_token'] = self::CSRF;
    }

    public function testMigrationMakesOperatorTheDefaultCompanyRole(): void
    {
        $column = $this->pdo->query("SHOW COLUMNS FROM company_users LIKE 'role'")->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($column);
        self::assertSame('operator', $column['Default']);
    }

    public function testLegacyMemberRetainsVisibleCompatibilityAndGetsOperatorAuthority(): void
    {
        $this->pdo->exec("UPDATE company_users SET role='member' WHERE user_id=1");
        $this->asUser(1);
        $context = new CompanyContext($this->pdo);
        self::assertSame('member', $context->company()['role']);
        self::assertSame('operator', $context->companyForRoles('admin', 'operator')['role']);

        $response = $this->formRequest('POST', '/catalog', [
            'internal_sku' => 'LEGACY-OP',
            'name' => 'Legacy operator',
            'csrf_token' => self::CSRF,
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM products WHERE internal_sku='LEGACY-OP'")->fetchColumn());
    }

    public function testViewerCanReadButCannotMutateCatalogOrErpApi(): void
    {
        $productId = (new ProductRepository($this->pdo))->create(1, 'READ-ONLY', 'Visible');
        $this->asUser(2);

        $catalog = $this->formRequest('GET', '/catalog', [], ['product_id' => (string) $productId]);
        self::assertSame(200, $catalog->getStatusCode());
        $html = (string) $catalog->getBody();
        self::assertStringContainsString('READ-ONLY', $html);
        self::assertStringNotContainsString('Crear producto', $html);
        self::assertStringNotContainsString('Establecer cantidad local', $html);

        $create = $this->formRequest('POST', '/catalog', [
            'internal_sku' => 'DENIED',
            'name' => 'Denied',
            'csrf_token' => self::CSRF,
        ]);
        self::assertSame(403, $create->getStatusCode());

        $apiRead = $this->jsonRequest('GET', '/api/products/' . $productId);
        self::assertSame(200, $apiRead->getStatusCode());
        $apiWrite = $this->jsonRequest('POST', '/api/products', [
            'internal_sku' => 'API-DENIED',
            'name' => 'Denied',
        ]);
        self::assertSame(403, $apiWrite->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
    }

    public function testOperatorKeepsOperationalWritesButNotAdminOnlyActions(): void
    {
        $this->asUser(1);
        $productId = (new ProductRepository($this->pdo))->create(1, 'OPERATE', 'Operator item');

        self::assertSame(200, $this->jsonRequest('PUT', '/api/products/' . $productId . '/inventory', ['quantity' => 7])->getStatusCode());
        self::assertSame(7, (int) $this->pdo->query('SELECT quantity FROM product_inventory WHERE product_id=' . $productId)->fetchColumn());

        self::assertSame(403, $this->formRequest('POST', '/billing/syncs', [])->getStatusCode());
        self::assertSame(403, $this->formRequest('POST', '/sales/audits', [])->getStatusCode());
        self::assertSame(403, $this->formRequest('GET', '/oauth/mercadolibre/connect')->getStatusCode());
    }

    public function testUnknownRoleFailsClosedAndClearsStaleSelection(): void
    {
        $this->asUser(3);
        self::assertSame(403, $this->formRequest('GET', '/catalog')->getStatusCode());
        self::assertArrayNotHasKey('company_id', $_SESSION);
        self::assertArrayNotHasKey('role', $_SESSION);
    }

    private function asUser(int $userId): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['company_id'] = 1;
        $_SESSION['role'] = 'stale-hint';
        $_SESSION['csrf_token'] = self::CSRF;
    }

    /** @param array<string,mixed> $body @param array<string,mixed> $query */
    private function formRequest(string $method, string $path, array $body = [], array $query = []): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withParsedBody($body)
            ->withQueryParams($query);
        return Bootstrap::create()->handle($request);
    }

    /** @param array<string,mixed>|null $payload */
    private function jsonRequest(string $method, string $path, ?array $payload = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withHeader('X-CSRF-Token', self::CSRF);
        if ($payload !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($payload, JSON_THROW_ON_ERROR)));
        }
        return Bootstrap::create()->handle($request);
    }
}
