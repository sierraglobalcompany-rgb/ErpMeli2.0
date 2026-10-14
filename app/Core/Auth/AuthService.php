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

    public function bootstrapAdmin(string $companyName, string $slug, string $email, string $password, ?string $displayName = null, ?string $username = null): void
    {
        $companyName = trim($companyName);
        $slug = trim($slug);
        $email = mb_strtolower(trim($email));
        $displayName = trim($displayName ?? $email);
        $legacyUsername = $username === null;
        $username = mb_strtolower(trim($username ?? $email));
        if ($companyName === '' || mb_strlen($companyName) > 160
            || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1 || strlen($slug) > 120
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 191
            || strlen($password) < 12 || strlen($password) > 72
            || $displayName === '' || mb_strlen($displayName) > 191
            || (!$legacyUsername && preg_match('/^[a-z0-9._-]{3,80}$/D', $username) !== 1)) {
            throw new \InvalidArgumentException('Invalid bootstrap input: company, slug, email and password (12-72 bytes) required.');
        }
        $hash = $this->passwords->hash($password);
        $this->pdo->beginTransaction();
        try {
            // Existing singleton serializes first-install authority without adding state.
            if ($this->pdo->query('SELECT id FROM system_settings WHERE id = 1 FOR UPDATE')->fetchColumn() === false) {
                throw new \DomainException('Run migrations before bootstrap.');
            }
            foreach (['companies', 'users', 'company_users'] as $table) {
                if ((int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn() !== 0) {
                    throw new \DomainException('Installation already provisioned; bootstrap requires empty authority tables.');
                }
            }
            $company = $this->pdo->prepare('INSERT INTO companies(name,slug) VALUES (?,?)');
            $company->execute([$companyName, $slug]);
            $companyId = (int) $this->pdo->lastInsertId();
            $user = $this->pdo->prepare("INSERT INTO users(email,password_hash,status,username,display_name,is_system_admin) VALUES (?,?,'active',?,?,1)");
            $user->execute([$email, $hash, $username, $displayName]);
            $userId = (int) $this->pdo->lastInsertId();
            $membership = $this->pdo->prepare("INSERT INTO company_users(company_id,user_id,role) VALUES (?,?,'admin')");
            $membership->execute([$companyId, $userId]);
            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function login(string $username, string $password): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, password_hash, display_name, is_system_admin FROM users
             WHERE username = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([mb_strtolower(trim($username))]);
        $user = $stmt->fetch();

        if (!is_array($user) || !$this->passwords->verify($password, (string) $user['password_hash'])) {
            return false;
        }

        unset($_SESSION['user_id'], $_SESSION['company_id'], $_SESSION['role'], $_SESSION['display_name'], $_SESSION['is_system_admin']);
        $_SESSION['user_id'] = (int) $user['id'];
        $memberships = (new \App\Core\Tenancy\CompanyContext($this->pdo))->memberships();

        if ($memberships === [] && (int)$user['is_system_admin'] !== 1) {
            unset($_SESSION['user_id']);
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['display_name'] = (string) $user['display_name'];
        $_SESSION['is_system_admin'] = (int)$user['is_system_admin'] === 1;
        if (count($memberships) === 1) {
            $_SESSION['company_id'] = $memberships[0]['id'];
            $_SESSION['role'] = (string) $memberships[0]['role'];
        }

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user_id'], $_SESSION['company_id'], $_SESSION['role'], $_SESSION['csrf_token'], $_SESSION['display_name'], $_SESSION['is_system_admin']);
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
