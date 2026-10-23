<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Auth\PasswordService;
use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use DomainException;
use InvalidArgumentException;
use OutOfBoundsException;
use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class UserManagementController
{
    /** @var list<string> */
    private const array ROLES = ['admin', 'operator', 'viewer'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly PasswordService $passwords = new PasswordService(),
    ) {
    }

    public function handle(
        ServerRequestInterface $request,
        ResponseInterface $response,
        string $action = '',
        int $id = 0,
    ): ResponseInterface {
        $context = new CompanyContext($this->pdo);
        try {
            $context->assertSystemAdmin();
        } catch (DomainException) {
            return $response->withStatus(403);
        }

        $response = $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        if ($request->getMethod() === 'GET') {
            return $this->render($response);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        try {
            (new Csrf())->assertValid(is_string($body['csrf_token'] ?? null) ? $body['csrf_token'] : '');
        } catch (\RuntimeException) {
            return $response->withStatus(419);
        }

        $actorId = $_SESSION['user_id'] ?? null;
        if (!is_int($actorId) || $actorId < 1) {
            return $response->withStatus(403);
        }

        $this->pdo->beginTransaction();
        try {
            $this->change($action, $id, $body);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof InvalidArgumentException) {
                $response->getBody()->write('Revisa los datos del usuario, contraseña, empresa y rol.');
                return $response->withStatus(422);
            }
            if ($exception instanceof OutOfBoundsException) {
                return $response->withStatus(404);
            }
            if ($exception instanceof DomainException || ($exception instanceof PDOException && $exception->getCode() === '23000')) {
                $response->getBody()->write('No se pudo completar porque dejaría una autoridad requerida sin responsable o porque el usuario ya existe.');
                return $response->withStatus(409);
            }
            throw $exception;
        }

        if ($id === $actorId && $action === 'status' && ($body['status'] ?? null) === 'disabled') {
            $_SESSION = [];
            session_regenerate_id(true);
            return $response->withHeader('Location', '/login')->withStatus(303);
        }
        if ($id === $actorId && $action === 'system-admin' && ($body['enabled'] ?? null) !== '1') {
            unset($_SESSION['is_system_admin']);
            return $response->withHeader('Location', '/')->withStatus(303);
        }
        if ($id === $actorId && in_array($action, ['membership', 'remove-membership'], true)) {
            $companyId = $this->positiveId($body['company_id'] ?? null);
            if ($companyId !== null && ($_SESSION['company_id'] ?? null) === $companyId) {
                if ($action === 'remove-membership') {
                    unset($_SESSION['company_id'], $_SESSION['role']);
                } else {
                    $_SESSION['role'] = (string) $body['role'];
                }
            }
        }

        return $response->withHeader('Location', '/system/users')->withStatus(303);
    }

    private function render(ResponseInterface $response): ResponseInterface
    {
        $users = $this->pdo->query(
            'SELECT id,username,display_name,email,status,is_system_admin FROM users ORDER BY display_name,id LIMIT 200'
        )->fetchAll(PDO::FETCH_ASSOC);
        $companies = $this->pdo->query(
            'SELECT id,name,archived_at FROM companies ORDER BY name,id LIMIT 500'
        )->fetchAll(PDO::FETCH_ASSOC);
        $rows = $this->pdo->query(
            'SELECT cu.user_id,cu.company_id,cu.role,c.name,c.archived_at '
            . 'FROM company_users cu INNER JOIN companies c ON c.id=cu.company_id '
            . 'ORDER BY cu.user_id,c.name,c.id'
        )->fetchAll(PDO::FETCH_ASSOC);
        $memberships = [];
        foreach ($rows as $row) {
            $memberships[(int) $row['user_id']][] = $row;
        }
        $csrfToken = (new Csrf())->token();
        ob_start();
        require __DIR__ . '/views/users.php';
        $response->getBody()->write((string) ob_get_clean());
        return $response;
    }

    /** @param array<mixed> $body */
    private function change(string $action, int $id, array $body): void
    {
        if ($action === '') {
            [$username, $displayName, $email] = $this->identity($body);
            $password = $this->password($body['password'] ?? null);
            $isSystemAdmin = ($body['is_system_admin'] ?? null) === '1' ? 1 : 0;
            $companyId = $this->optionalPositiveId($body['company_id'] ?? null);
            $role = $companyId === null ? null : $this->role($body['role'] ?? null);
            if ($companyId !== null) {
                $this->assertCompanyExists($companyId);
            }

            $insert = $this->pdo->prepare(
                "INSERT INTO users(username,display_name,email,password_hash,status,is_system_admin) VALUES(?,?,?,?,'active',?)"
            );
            $insert->execute([$username, $displayName, $email, $this->passwords->hash($password), $isSystemAdmin]);
            $userId = (int) $this->pdo->lastInsertId();
            if ($companyId !== null && $role !== null) {
                $membership = $this->pdo->prepare('INSERT INTO company_users(company_id,user_id,role) VALUES(?,?,?)');
                $membership->execute([$companyId, $userId, $role]);
            }
            return;
        }

        $user = $this->lockUser($id);
        if ($action === 'update') {
            [$username, $displayName, $email] = $this->identity($body);
            $statement = $this->pdo->prepare('UPDATE users SET username=?,display_name=?,email=? WHERE id=?');
            $statement->execute([$username, $displayName, $email, $id]);
            return;
        }
        if ($action === 'password') {
            $password = $this->password($body['password'] ?? null);
            $statement = $this->pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
            $statement->execute([$this->passwords->hash($password), $id]);
            return;
        }
        if ($action === 'status') {
            $status = $body['status'] ?? null;
            if (!is_string($status) || !in_array($status, ['active', 'disabled'], true)) {
                throw new InvalidArgumentException();
            }
            if ($status === 'disabled' && $user['status'] === 'active') {
                if ((int) $user['is_system_admin'] === 1) {
                    $this->assertAnotherActiveSystemAdmin($id);
                }
                $admins = $this->pdo->prepare(
                    "SELECT company_id FROM company_users WHERE user_id=? AND role='admin' ORDER BY company_id"
                );
                $admins->execute([$id]);
                foreach ($admins->fetchAll(PDO::FETCH_COLUMN) as $companyId) {
                    $this->assertAnotherActiveCompanyAdmin((int) $companyId, $id);
                }
            }
            $statement = $this->pdo->prepare('UPDATE users SET status=? WHERE id=?');
            $statement->execute([$status, $id]);
            return;
        }
        if ($action === 'system-admin') {
            $enabled = $body['enabled'] ?? null;
            if (!is_string($enabled) || !in_array($enabled, ['0', '1'], true)) {
                throw new InvalidArgumentException();
            }
            if ($enabled === '0' && (int) $user['is_system_admin'] === 1 && $user['status'] === 'active') {
                $this->assertAnotherActiveSystemAdmin($id);
            }
            if ($enabled === '0' && (int) $user['is_system_admin'] === 1
                && ($body['confirm_remove_system_admin'] ?? null) !== '1') {
                throw new InvalidArgumentException('Explicit System Admin revocation acknowledgement required.');
            }
            $statement = $this->pdo->prepare('UPDATE users SET is_system_admin=? WHERE id=?');
            $statement->execute([(int) $enabled, $id]);
            return;
        }
        if ($action === 'membership') {
            $companyId = $this->requiredPositiveId($body['company_id'] ?? null);
            $role = $this->role($body['role'] ?? null);
            $this->assertCompanyExists($companyId);
            $existing = $this->pdo->prepare('SELECT role FROM company_users WHERE company_id=? AND user_id=? FOR UPDATE');
            $existing->execute([$companyId, $id]);
            $existingRole = $existing->fetchColumn();
            if ($existingRole === 'admin' && $role !== 'admin' && $user['status'] === 'active') {
                $this->assertAnotherActiveCompanyAdmin($companyId, $id);
            }
            $statement = $this->pdo->prepare(
                'INSERT INTO company_users(company_id,user_id,role) VALUES(?,?,?) '
                . 'ON DUPLICATE KEY UPDATE role=VALUES(role)'
            );
            $statement->execute([$companyId, $id, $role]);
            return;
        }
        if ($action === 'remove-membership') {
            $companyId = $this->requiredPositiveId($body['company_id'] ?? null);
            $existing = $this->pdo->prepare('SELECT role FROM company_users WHERE company_id=? AND user_id=? FOR UPDATE');
            $existing->execute([$companyId, $id]);
            $existingRole = $existing->fetchColumn();
            if (!is_string($existingRole)) {
                throw new OutOfBoundsException();
            }
            if ($existingRole === 'admin' && $user['status'] === 'active') {
                $this->assertAnotherActiveCompanyAdmin($companyId, $id);
            }
            if (($body['confirm_remove_membership'] ?? null) !== '1') {
                throw new InvalidArgumentException('Explicit company membership removal acknowledgement required.');
            }
            $statement = $this->pdo->prepare('DELETE FROM company_users WHERE company_id=? AND user_id=?');
            $statement->execute([$companyId, $id]);
            return;
        }

        throw new InvalidArgumentException();
    }

    /** @return array<string,mixed> */
    private function lockUser(int $id): array
    {
        if ($id < 1) {
            throw new OutOfBoundsException();
        }
        $statement = $this->pdo->prepare('SELECT id,status,is_system_admin FROM users WHERE id=? FOR UPDATE');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new OutOfBoundsException();
        }
        return $row;
    }

    private function assertAnotherActiveSystemAdmin(int $excludingUserId): void
    {
        $statement = $this->pdo->query("SELECT id FROM users WHERE status='active' AND is_system_admin=1 ORDER BY id FOR UPDATE");
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (array_values(array_filter($ids, static fn (int $id): bool => $id !== $excludingUserId)) === []) {
            throw new DomainException('At least one active System Admin is required.');
        }
    }

    private function assertAnotherActiveCompanyAdmin(int $companyId, int $excludingUserId): void
    {
        $statement = $this->pdo->prepare(
            "SELECT cu.user_id FROM company_users cu INNER JOIN users u ON u.id=cu.user_id "
            . "WHERE cu.company_id=? AND cu.role='admin' AND u.status='active' ORDER BY cu.user_id FOR UPDATE"
        );
        $statement->execute([$companyId]);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        if (array_values(array_filter($ids, static fn (int $id): bool => $id !== $excludingUserId)) === []) {
            throw new DomainException('At least one active Company admin is required.');
        }
    }

    private function assertCompanyExists(int $companyId): void
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM companies WHERE id=? FOR UPDATE');
        $statement->execute([$companyId]);
        if ($statement->fetchColumn() === false) {
            throw new OutOfBoundsException();
        }
    }

    /**
     * @param array<mixed> $body
     * @return array{string,string,string}
     */
    private function identity(array $body): array
    {
        $username = $body['username'] ?? null;
        $displayName = $body['display_name'] ?? null;
        $email = $body['email'] ?? null;
        if (!is_string($username) || !is_string($displayName) || !is_string($email)) {
            throw new InvalidArgumentException();
        }
        $username = mb_strtolower(trim($username));
        $displayName = trim($displayName);
        $email = mb_strtolower(trim($email));
        if (preg_match('/^[a-z0-9._-]{3,80}$/D', $username) !== 1
            || $displayName === '' || mb_strlen($displayName) > 191
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 191) {
            throw new InvalidArgumentException();
        }
        return [$username, $displayName, $email];
    }

    private function password(mixed $password): string
    {
        if (!is_string($password) || strlen($password) < PasswordService::MIN_LENGTH || strlen($password) > 72) {
            throw new InvalidArgumentException();
        }
        return $password;
    }

    private function role(mixed $role): string
    {
        if (!is_string($role) || !in_array($role, self::ROLES, true)) {
            throw new InvalidArgumentException();
        }
        return $role;
    }

    private function optionalPositiveId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = $this->positiveId($value);
        if ($id === null) {
            throw new InvalidArgumentException();
        }
        return $id;
    }

    private function positiveId(mixed $value): ?int
    {
        if ((!is_int($value) && !is_string($value)) || preg_match('/^[1-9][0-9]*$/D', (string) $value) !== 1) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($id) ? $id : null;
    }

    private function requiredPositiveId(mixed $value): int
    {
        $id = $this->positiveId($value);
        if ($id === null) {
            throw new InvalidArgumentException();
        }
        return $id;
    }
}
