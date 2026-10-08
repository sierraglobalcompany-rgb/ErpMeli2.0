<?php

declare(strict_types=1);

namespace App\Core\Auth;

use PDO;

final class AuthService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PasswordService $passwords = new PasswordService(),
    ) {
    }

    public function login(string $email, string $password): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, password_hash FROM users
             WHERE email = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch();

        if (!is_array($user) || !$this->passwords->verify($password, (string) $user['password_hash'])) {
            return false;
        }

        $membership = $this->pdo->prepare(
            'SELECT company_id, role FROM company_users WHERE user_id = ? ORDER BY company_id LIMIT 1'
        );
        $membership->execute([(int) $user['id']]);
        $row = $membership->fetch();

        if (!is_array($row)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['company_id'] = (int) $row['company_id'];
        $_SESSION['role'] = (string) $row['role'];

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['company_id'], $_SESSION['role'], $_SESSION['csrf_token']);
        session_regenerate_id(true);
    }

    public function userId(): ?int
    {
        $id = $_SESSION['user_id'] ?? null;
        return is_int($id) && $id > 0 ? $id : null;
    }

    public function role(): ?string
    {
        $role = $_SESSION['role'] ?? null;
        return is_string($role) ? $role : null;
    }
}
