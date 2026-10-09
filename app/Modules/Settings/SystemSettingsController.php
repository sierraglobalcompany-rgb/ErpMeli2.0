<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Logging\DebugExportService;
use App\Core\Logging\DebugMaintenance;
use App\Core\Security\Csrf;
use InvalidArgumentException;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SystemSettingsController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly SystemSettingsRepository $settings,
        private readonly Csrf $csrf,
        private readonly ?DebugMaintenance $debugMaintenance = null,
        private readonly ?DebugExportService $debugExport = null,
    ) {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $response->withStatus(403);
        }

        $settings = $this->settings->get();
        $csrfToken = $this->csrf->token();
        $debugUsage = $this->debugMaintenance?->usage() ?? [
            'total_bytes' => 0,
            'days' => [],
        ];
        $debugCapReached = $settings->debugEnabled
            && $debugUsage['total_bytes'] >= ($settings->debugMaxMb * 1024 * 1024);

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

    public function clearDebug(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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

        if ($this->debugMaintenance === null) {
            return $response->withStatus(503);
        }

        $this->debugMaintenance->clearDebug();

        return $response
            ->withHeader('Location', '/settings/system')
            ->withStatus(303);
    }

    public function exportDebug(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
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

        if ($this->debugExport === null) {
            return $response->withStatus(503);
        }

        try {
            $startDay = $this->optionalStringField($body, 'debug_start_date');
            $endDay = $this->optionalStringField($body, 'debug_end_date');
            $export = $this->debugExport->create($startDay, $endDay, $this->schemaVersion());
        } catch (InvalidArgumentException) {
            return $response->withStatus(422);
        }

        if ($export === null) {
            return $response->withStatus(204);
        }

        $size = filesize($export['path']);
        $handle = fopen($export['path'], 'rb');
        if ($size === false || $handle === false) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            return $response->withStatus(500);
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) {
                    return $response->withStatus(500);
                }
                if ($chunk !== '') {
                    $response->getBody()->write($chunk);
                }
            }
        } finally {
            fclose($handle);
        }

        return $response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $export['filename'] . '"')
            ->withHeader('Content-Length', (string) $size);
    }

    /** @param array<string,mixed> $body */
    private function optionalStringField(array $body, string $key): ?string
    {
        if (!array_key_exists($key, $body)) {
            return null;
        }

        $value = $body[$key];
        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid debug export field.');
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }

    private function schemaVersion(): string
    {
        $version = $this->pdo->query(
            'SELECT version FROM schema_migrations ORDER BY applied_at DESC, version DESC LIMIT 1'
        )->fetchColumn();

        return is_string($version) && $version !== '' ? $version : 'unknown';
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
