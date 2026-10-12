<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use InvalidArgumentException;
use PDO;

final class InventoryRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function setForProduct(int $companyId, int $productId, int $quantity): void
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Stock quantity must not be negative.');
        }

        $product = $this->pdo->prepare(
            'SELECT id FROM products WHERE company_id = :company_id AND id = :product_id LIMIT 1'
        );
        $product->execute(['company_id' => $companyId, 'product_id' => $productId]);
        if ($product->fetchColumn() === false) {
            throw new InvalidArgumentException('Product was not found in this company.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO product_inventory (product_id, quantity) VALUES (:product_id, :quantity) '
            . 'ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        );
        $statement->execute(['product_id' => $productId, 'quantity' => $quantity]);
    }

    public function getForProduct(int $companyId, int $productId): ?int
    {
        $statement = $this->pdo->prepare(
            'SELECT inventory.quantity FROM product_inventory AS inventory '
            . 'INNER JOIN products AS product ON product.id = inventory.product_id '
            . 'WHERE product.company_id = :company_id AND product.id = :product_id LIMIT 1'
        );
        $statement->execute(['company_id' => $companyId, 'product_id' => $productId]);
        $quantity = $statement->fetchColumn();

        return $quantity === false ? null : (int) $quantity;
    }
}
