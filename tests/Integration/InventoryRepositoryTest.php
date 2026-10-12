<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Catalog\ProductRepository;
use App\Modules\Inventory\InventoryRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class InventoryRepositoryTest extends TestCase
{
    private PDO $pdo;

    private int $companyOneId;

    private int $companyTwoId;

    private int $productOneId;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->companyOneId = $this->createCompany('Inventory One', 'inventory-one');
        $this->companyTwoId = $this->createCompany('Inventory Two', 'inventory-two');
        $this->productOneId = (new ProductRepository($this->pdo))->create(
            $this->companyOneId,
            'SKU-INV-001',
            'Producto de inventario',
        );
    }

    public function testInventoryStoresOneExactIntegerRowPerProduct(): void
    {
        $table = $this->pdo->query("SHOW TABLES LIKE 'product_inventory'")->fetchColumn();
        self::assertSame('product_inventory', $table, 'F8 must create its local stock table.');

        $columns = $this->pdo->query('SHOW COLUMNS FROM product_inventory')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame(
            ['product_id', 'quantity', 'created_at', 'updated_at'],
            array_map(static fn (array $column): string => (string) $column['Field'], $columns),
        );
        self::assertStringStartsWith('bigint', strtolower((string) $columns[1]['Type']));
        self::assertStringContainsString('unsigned', strtolower((string) $columns[1]['Type']));

        $primary = $this->pdo->query("SHOW INDEX FROM product_inventory WHERE Key_name = 'PRIMARY'")
            ->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame(['product_id'], array_map(static fn (array $index): string => (string) $index['Column_name'], $primary));
    }

    public function testSetAndReadStockForACompanyProduct(): void
    {
        $inventory = new InventoryRepository($this->pdo);

        $inventory->setForProduct($this->companyOneId, $this->productOneId, 12);

        self::assertSame(12, $inventory->getForProduct($this->companyOneId, $this->productOneId));
    }

    public function testStockIsIsolatedByTheProductCompany(): void
    {
        $inventory = new InventoryRepository($this->pdo);
        $inventory->setForProduct($this->companyOneId, $this->productOneId, 12);

        self::assertNull($inventory->getForProduct($this->companyTwoId, $this->productOneId));

        $this->expectException(InvalidArgumentException::class);
        $inventory->setForProduct($this->companyTwoId, $this->productOneId, 20);
    }

    public function testNegativeStockQuantityIsRejected(): void
    {
        $inventory = new InventoryRepository($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        $inventory->setForProduct($this->companyOneId, $this->productOneId, -1);
    }

    public function testSettingStockTwiceKeepsTheLastExplicitQuantity(): void
    {
        $inventory = new InventoryRepository($this->pdo);
        $inventory->setForProduct($this->companyOneId, $this->productOneId, 4);
        $inventory->setForProduct($this->companyOneId, $this->productOneId, 9);

        self::assertSame(9, $inventory->getForProduct($this->companyOneId, $this->productOneId));
    }

    public function testSettingStockForANonexistentProductIsRejected(): void
    {
        $inventory = new InventoryRepository($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        $inventory->setForProduct($this->companyOneId, 999999, 1);
    }

    private function createCompany(string $name, string $slug): int
    {
        $statement = $this->pdo->prepare('INSERT INTO companies (name, slug) VALUES (:name, :slug)');
        $statement->execute(['name' => $name, 'slug' => $slug]);

        return (int) $this->pdo->lastInsertId();
    }
}
