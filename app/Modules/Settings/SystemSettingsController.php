<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Security\Csrf;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SystemSettingsController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SystemSettingsRepository $settings,
        private readonly Csrf $csrf,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $response->withStatus(403);
        }

        $settings = $this->settings->get();
        $csrfToken = $this->csrf->token();

        ob_start();
        require __DIR__ . '/views/system.php';
        $html = (string) ob_get_clean();

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $response->withStatus(403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        try {
            $this->csrf->assertValid((string) ($body['csrf_token'] ?? ''));
        } catch (\RuntimeException) {
            return $response->withStatus(419);
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) || $userId <= 0) {
            return $response->withStatus(403);
        }

        $this->settings->updateOperationalToggles(
            ($body['automation_enabled'] ?? null) === '1',
            ($body['meli_writes_enabled'] ?? null) === '1',
            ($body['debug_enabled'] ?? null) === '1',
            filter_var($body['debug_retention_days'] ?? 7, FILTER_VALIDATE_INT) ?: 7,
            filter_var($body['debug_max_mb'] ?? 100, FILTER_VALIDATE_INT) ?: 100,
            $userId,
        );

        return $response
            ->withHeader('Location', '/settings/system')
            ->withStatus(303);
    }

    private function isAdmin(): bool
    {
        $userId = $_SESSION['user_id'] ?? null;
        $companyId = $_SESSION['company_id'] ?? null;
        if (!is_int($userId) || !is_int($companyId)) {
            return false;
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM company_users
             WHERE user_id = ? AND company_id = ? AND role = 'admin'"
        );
        $stmt->execute([$userId, $companyId]);

        return (int) $stmt->fetchColumn() === 1;
    }
}
