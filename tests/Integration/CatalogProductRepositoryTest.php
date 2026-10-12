<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Catalog\ProductRepository;
use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class CatalogProductRepositoryTest extends TestCase
{
    private PDO $pdo;

    private int $companyOneId;

    private int $companyTwoId;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->companyOneId = $this->createCompany('Catalog One', 'catalog-one');
        $this->companyTwoId = $this->createCompany('Catalog Two', 'catalog-two');
    }

    public function testProductsTableUsesOnlyTheCompanyScopedIdentityFields(): void
    {
        $table = $this->pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn();
        self::assertSame('products', $table, 'The Catalog migration must create products.');

        $columns = $this->pdo->query('SHOW COLUMNS FROM products')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame(
            ['id', 'company_id', 'internal_sku', 'name', 'created_at', 'updated_at'],
            array_map(static fn (array $column): string => (string) $column['Field'], $columns),
        );
    }

    public function testCreateAndReadReturnsTheProductWithinItsCompany(): void
    {
        $repository = new ProductRepository($this->pdo);

        $productId = $repository->create($this->companyOneId, 'SKU-001', 'Producto local');

        self::assertGreaterThan(0, $productId);
        self::assertSame([
            'id' => $productId,
            'company_id' => $this->companyOneId,
            'internal_sku' => 'SKU-001',
            'name' => 'Producto local',
        ], $repository->findForCompany($this->companyOneId, $productId));
    }

    public function testDuplicateInternalSkuInOneCompanyIsRejected(): void
    {
        $repository = new ProductRepository($this->pdo);
        $repository->create($this->companyOneId, 'SKU-001', 'Primer producto');

        $this->expectException(PDOException::class);
        $repository->create($this->companyOneId, 'SKU-001', 'Producto duplicado');
    }

    public function testSameInternalSkuIsAllowedForDifferentCompanies(): void
    {
        $repository = new ProductRepository($this->pdo);

        $companyOneProductId = $repository->create($this->companyOneId, 'SKU-001', 'Producto uno');
        $companyTwoProductId = $repository->create($this->companyTwoId, 'SKU-001', 'Producto dos');

        self::assertNotSame($companyOneProductId, $companyTwoProductId);
        self::assertSame('Producto uno', $repository->findForCompany($this->companyOneId, $companyOneProductId)['name']);
        self::assertSame('Producto dos', $repository->findForCompany($this->companyTwoId, $companyTwoProductId)['name']);
    }

    public function testProductFromAnotherCompanyIsNotFound(): void
    {
        $repository = new ProductRepository($this->pdo);
        $productId = $repository->create($this->companyOneId, 'SKU-001', 'Producto privado');

        self::assertNull($repository->findForCompany($this->companyTwoId, $productId));
    }

    public function testCreateRejectsAnEmptyInternalSku(): void
    {
        $repository = new ProductRepository($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        $repository->create($this->companyOneId, '', 'Producto local');
    }

    public function testCreateRejectsAnEmptyName(): void
    {
        $repository = new ProductRepository($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        $repository->create($this->companyOneId, 'SKU-001', '');
    }

    private function createCompany(string $name, string $slug): int
    {
        $statement = $this->pdo->prepare('INSERT INTO companies (name, slug) VALUES (:name, :slug)');
        $statement->execute(['name' => $name, 'slug' => $slug]);

        return (int) $this->pdo->lastInsertId();
    }
}
