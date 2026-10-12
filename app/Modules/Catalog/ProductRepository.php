<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use InvalidArgumentException;
use PDO;

final class ProductRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $companyId, string $internalSku, string $name): int
    {
        if (trim($internalSku) === '') {
            throw new InvalidArgumentException('Internal SKU must not be empty.');
        }
        if (trim($name) === '') {
            throw new InvalidArgumentException('Product name must not be empty.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO products (company_id, internal_sku, name) '
            . 'VALUES (:company_id, :internal_sku, :name)'
        );
        $statement->execute([
            'company_id' => $companyId,
            'internal_sku' => $internalSku,
            'name' => $name,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array{id:int,company_id:int,internal_sku:string,name:string}|null */
    public function findForCompany(int $companyId, int $productId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, company_id, internal_sku, name FROM products '
            . 'WHERE company_id = :company_id AND id = :product_id LIMIT 1'
        );
        $statement->execute([
            'company_id' => $companyId,
            'product_id' => $productId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'company_id' => (int) $row['company_id'],
            'internal_sku' => (string) $row['internal_sku'],
            'name' => (string) $row['name'],
        ];
    }
}
