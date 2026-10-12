<?php

declare(strict_types=1);

namespace App\Core\Tenancy;

use DomainException;
use PDO;

final class CompanyContext
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function assertActiveUser(): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) || $userId < 1) {
            throw new DomainException('Authentication required.');
        }
        $statement = $this->pdo->prepare("SELECT 1 FROM users WHERE id = ? AND status = 'active'");
        $statement->execute([$userId]);
        if ($statement->fetchColumn() === false) {
            throw new DomainException('Active user required.');
        }
    }

    public function companyId(): int
    {
        return $this->company()['id'];
    }

    /** @return array{id:int,name:string,role:string} */
    public function company(): array
    {
        $userId = $_SESSION['user_id'] ?? null;
        $companyId = $_SESSION['company_id'] ?? null;
        if (!is_int($userId) || !is_int($companyId)) {
            throw new DomainException('No active company.');
        }
        return $this->membership($userId, $companyId);
    }

    /** @return list<array{id:int,name:string,role:string}> */
    public function memberships(): array
    {
        $this->assertActiveUser();
        $statement = $this->pdo->prepare(
            'SELECT c.id,c.name,cu.role FROM company_users cu INNER JOIN companies c ON c.id = cu.company_id '
            . 'WHERE cu.user_id = ? ORDER BY c.name,c.id'
        );
        $statement->execute([$_SESSION['user_id']]);
        return array_map(static fn (array $row): array => [
            'id'=>(int) $row['id'], 'name'=>(string) $row['name'], 'role'=>(string) $row['role'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function select(int $companyId): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) || $userId <= 0) {
            throw new DomainException('Authentication required.');
        }

        $company = $this->membership($userId, $companyId);
        $_SESSION['company_id'] = $companyId;
        $_SESSION['role'] = $company['role'];
    }

    /** @return array{id:int,name:string,role:string} */
    private function membership(int $userId, int $companyId): array
    {
        $this->assertActiveUser();
        $stmt = $this->pdo->prepare(
            'SELECT c.id,c.name,cu.role FROM company_users cu INNER JOIN companies c ON c.id = cu.company_id '
            . 'WHERE cu.user_id = ? AND cu.company_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $companyId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row) || !is_string($row['role']) || $row['role'] === '') {
            throw new DomainException('Company access denied.');
        }

        return ['id'=>(int) $row['id'], 'name'=>(string) $row['name'], 'role'=>$row['role']];
    }
}
