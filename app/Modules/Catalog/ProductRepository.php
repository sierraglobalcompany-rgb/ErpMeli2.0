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

    /** @return list<array{id:int,internal_sku:string,name:string}> */
    public function listRecentForCompany(int $companyId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id,internal_sku,name FROM products WHERE company_id = ? ORDER BY id DESC LIMIT 100'
        );
        $statement->execute([$companyId]);
        return array_map(static fn (array $row): array => [
            'id'=>(int) $row['id'], 'internal_sku'=>(string) $row['internal_sku'], 'name'=>(string) $row['name'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Search only this company's internal products. Read-only; no inventory or provider queries.
     * Wildcards typed by the user are treated as literal SKU/name characters.
     * @return list<array{id:int,internal_sku:string,name:string}>
     */
    public function searchForCompany(int $companyId, string $term): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
        $pattern = '%' . $escaped . '%';
        $statement = $this->pdo->prepare(
            "SELECT id,internal_sku,name FROM products WHERE company_id = :company_id "
            . "AND (internal_sku LIKE :sku ESCAPE '!' OR name LIKE :name ESCAPE '!') "
            . 'ORDER BY id DESC LIMIT 100'
        );
        $statement->execute(['company_id' => $companyId, 'sku' => $pattern, 'name' => $pattern]);
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'internal_sku' => (string) $row['internal_sku'], 'name' => (string) $row['name'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
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


    /**
     * Local F13 rehearsal only. Dry run rolls back rows; disposable AUTO_INCREMENT may advance.
     * @param list<array{id:string,company_id:string,internal_sku:string,name:string}> $rows
     * @return array{source_rows:int,would_migrate:int,already_present:int,migrated:int,dry_run:bool}
     */
    public function rehearseImport(array $rows, int $sourceCompanyId, int $targetCompanyId, string $targetSlug, bool $dryRun = true): array
    {
        $state = $this->pdo->query('SELECT DATABASE() AS database_name, @@in_transaction AS active_transaction')->fetch(PDO::FETCH_ASSOC);
        if (!is_array($state) || !in_array($state['database_name'], ['erp_meli2_test', 'erp_meli2_rehearsal'], true)
            || (int) $state['active_transaction'] !== 0) {
            throw new \RuntimeException('CATALOG_DISPOSABLE_DATABASE_REQUIRED');
        }
        $target = $this->pdo->prepare('SELECT slug FROM companies WHERE id = :id');
        $target->execute(['id' => $targetCompanyId]);
        if ($sourceCompanyId <= 0 || $targetSlug === '' || $target->fetchColumn() !== $targetSlug) {
            throw new \RuntimeException('CATALOG_TARGET_MAPPING_UNPROVEN');
        }
        $report = ['source_rows' => count($rows), 'would_migrate' => 0, 'already_present' => 0, 'migrated' => 0, 'dry_run' => $dryRun];
        $sourceIds = [];
        $targetIds = [];
        $find = $this->pdo->prepare('SELECT id, internal_sku, name FROM products WHERE company_id = :company AND internal_sku = :sku FOR UPDATE');
        $this->pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                if (!ctype_digit($row['id']) || (int) $row['id'] <= 0 || isset($sourceIds[$row['id']])
                    || $row['company_id'] !== (string) $sourceCompanyId || trim($row['internal_sku']) === ''
                    || trim($row['name']) === '' || mb_strlen($row['internal_sku']) > 120 || mb_strlen($row['name']) > 255) {
                    throw new InvalidArgumentException('CATALOG_SOURCE_INVALID');
                }
                $sourceIds[$row['id']] = true;
                $find->execute(['company' => $targetCompanyId, 'sku' => $row['internal_sku']]);
                $existing = $find->fetch(PDO::FETCH_ASSOC);
                if (is_array($existing)) {
                    $id = (int) $existing['id'];
                    if (isset($targetIds[$id])) {
                        throw new InvalidArgumentException('CATALOG_SOURCE_KEY_COLLISION');
                    }
                    if ($existing['internal_sku'] !== $row['internal_sku'] || $existing['name'] !== $row['name']) {
                        throw new \RuntimeException('CATALOG_TARGET_CONFLICT');
                    }
                    $report['already_present']++;
                } else {
                    $id = $this->create($targetCompanyId, $row['internal_sku'], $row['name']);
                    $report['would_migrate']++;
                }
                $targetIds[$id] = true;
            }
            if ($dryRun) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->commit();
                $report['migrated'] = $report['would_migrate'];
            }
            return $report;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
