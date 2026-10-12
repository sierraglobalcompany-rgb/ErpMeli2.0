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


    public function testRehearsalDefaultsToRollbackAndIsIdempotentWithinTheMappedCompany(): void
    {
        $repo = new ProductRepository($this->pdo);
        $rows = [['id' => '10', 'company_id' => '3', 'internal_sku' => 'KEEP', 'name' => 'Local']];
        $foreign = $repo->create($this->companyTwoId, 'KEEP', 'Foreign');
        self::assertSame(['source_rows' => 1, 'would_migrate' => 1, 'already_present' => 0, 'migrated' => 0, 'dry_run' => true],
            $repo->rehearseImport($rows, 3, $this->companyOneId, 'catalog-one'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
        self::assertSame(1, $repo->rehearseImport($rows, 3, $this->companyOneId, 'catalog-one', false)['migrated']);
        $repeat = $repo->rehearseImport($rows, 3, $this->companyOneId, 'catalog-one', false);
        self::assertSame(0, $repeat['migrated']);
        self::assertSame(1, $repeat['already_present']);
        self::assertSame('Foreign', $repo->findForCompany($this->companyTwoId, $foreign)['name']);
    }

    public function testRehearsalRejectsForeignSourceOwnerAndRollsBackEarlierRows(): void
    {
        $this->assertRehearsalRejected([
            ['id' => '1', 'company_id' => '3', 'internal_sku' => 'FIRST', 'name' => 'First'],
            ['id' => '2', 'company_id' => '4', 'internal_sku' => 'SECOND', 'name' => 'Wrong owner'],
        ]);
    }

    public function testRehearsalRejectsSourceKeysThatCollideUnderDatabaseCollation(): void
    {
        $this->assertRehearsalRejected([
            ['id' => '1', 'company_id' => '3', 'internal_sku' => 'SKU', 'name' => 'Same'],
            ['id' => '2', 'company_id' => '3', 'internal_sku' => 'sku', 'name' => 'Same'],
        ]);
    }

    public function testRehearsalRejectsDuplicateSourceIdsAndMissingFields(): void
    {
        $this->assertRehearsalRejected([
            ['id' => '1', 'company_id' => '3', 'internal_sku' => 'A', 'name' => 'First'],
            ['id' => '1', 'company_id' => '3', 'internal_sku' => 'B', 'name' => 'Second'],
        ]);
        $this->assertRehearsalRejected([['id' => '2', 'company_id' => '3', 'internal_sku' => 'A', 'name' => '']]);
    }

    public function testRehearsalRejectsConflictingExistingProductWithoutOverwritingIt(): void
    {
        $repo = new ProductRepository($this->pdo);
        $id = $repo->create($this->companyOneId, 'SKU', 'Original');
        try {
            $repo->rehearseImport([['id' => '1', 'company_id' => '3', 'internal_sku' => 'SKU', 'name' => 'Different']], 3, $this->companyOneId, 'catalog-one', false);
            self::fail('A conflicting existing product must be rejected.');
        } catch (\RuntimeException $e) {
            self::assertSame('CATALOG_TARGET_CONFLICT', $e->getMessage());
        }
        self::assertSame('Original', $repo->findForCompany($this->companyOneId, $id)['name']);
    }

    public function testRehearsalRejectsAnUnmappedTargetCompany(): void
    {
        $repo = new ProductRepository($this->pdo);
        $this->expectException(\RuntimeException::class);
        $repo->rehearseImport([], 3, $this->companyOneId, 'other-company', false);
    }

    public function testRehearsalRejectsANonDisposableDatabase(): void
    {
        $this->pdo->exec('USE mysql');
        $this->expectException(\RuntimeException::class);
        (new ProductRepository($this->pdo))->rehearseImport([], 3, $this->companyOneId, 'catalog-one', false);
    }

    /** @param list<array{id:string,company_id:string,internal_sku:string,name:string}> $rows */
    private function assertRehearsalRejected(array $rows): void
    {
        try {
            (new ProductRepository($this->pdo))->rehearseImport($rows, 3, $this->companyOneId, 'catalog-one', false);
            self::fail('Invalid source must be rejected.');
        } catch (\RuntimeException|InvalidArgumentException $e) {
            self::assertNotSame('', $e->getMessage());
        }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn());
        self::assertFalse($this->pdo->inTransaction());
    }

    private function createCompany(string $name, string $slug): int
    {
        $statement = $this->pdo->prepare('INSERT INTO companies (name, slug) VALUES (:name, :slug)');
        $statement->execute(['name' => $name, 'slug' => $slug]);

        return (int) $this->pdo->lastInsertId();
    }
}
