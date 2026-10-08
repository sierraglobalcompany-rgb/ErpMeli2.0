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

    public function companyId(): int
    {
        $userId = $_SESSION['user_id'] ?? null;
        $companyId = $_SESSION['company_id'] ?? null;

        if (!is_int($userId) || !is_int($companyId)) {
            throw new DomainException('No active company.');
        }

        $this->membership($userId, $companyId);

        return $companyId;
    }

    public function select(int $companyId): void
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) || $userId <= 0) {
            throw new DomainException('Authentication required.');
        }

        $role = $this->membership($userId, $companyId);
        $_SESSION['company_id'] = $companyId;
        $_SESSION['role'] = $role;
    }

    private function membership(int $userId, int $companyId): string
    {
        $stmt = $this->pdo->prepare(
            'SELECT role FROM company_users WHERE user_id = ? AND company_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $companyId]);
        $role = $stmt->fetchColumn();

        if (!is_string($role) || $role === '') {
            throw new DomainException('Company access denied.');
        }

        return $role;
    }
}
