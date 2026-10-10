<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use App\Core\Logging\DebugExportService;
use App\Core\Logging\DebugMaintenance;
use App\Core\Security\Csrf;
use DateTimeImmutable;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;
use ZipArchive;

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
        $debugUsage = $this->debugMaintenance?->usage() ?? ['total_bytes' => 0, 'days' => []];
        $debugCapReached = $debugUsage['total_bytes'] >= ($settings->debugMaxMb * 1024 * 1024);
        $csrfToken = $this->csrf->token();

        ob_start();
        require __DIR__ . '/views/system.php';
        $html = (string) ob_get_clean();
        $response->getBody()->write($html);

        return $response;
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        if (!is_array($data) || !$this->csrf->verify($data['csrf_token'] ?? null)) {
            return $response->withStatus(419);
        }

        $retention = filter_var($data['debug_retention_days'] ?? null, FILTER_VALIDATE_INT);
        $maxMb = filter_var($data['debug_max_mb'] ?? null, FILTER_VALIDATE_INT);
        if ($retention === false || $maxMb === false || $retention < 1 || $retention > 90 || $maxMb < 10 || $maxMb > 10240) {
            return $response->withStatus(422);
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
            return $response->withStatus(403);
        }

        $this->settings->updateOperationalToggles(
            array_key_exists('automation_enabled', $data),
            array_key_exists('debug_enabled', $data),
            (int) $retention,
            (int) $maxMb,
            (int) $userId,
        );

        return $response->withHeader('Location', '/settings/system')->withStatus(303);
    }

    public function exportDebug(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        if (!is_array($data) || !$this->csrf->verify($data['csrf_token'] ?? null)) {
            return $response->withStatus(419);
        }
        if (!$this->debugExport instanceof DebugExportService) {
            return $response->withStatus(503);
        }

        $start = $this->date($data['debug_start_date'] ?? null);
        $end = $this->date($data['debug_end_date'] ?? null);
        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable || $start > $end) {
            return $response->withStatus(422);
        }

        try {
            $path = $this->debugExport->exportRange($start, $end);
        } catch (RuntimeException) {
            return $response->withStatus(422);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            @unlink($path);
            return $response->withStatus(500);
        }

        $response->getBody()->write($contents);
        @unlink($path);

        return $response
            ->withHeader('Content-Type', 'application/zip')
            ->withHeader('Content-Disposition', 'attachment; filename="erp-meli2-debug.zip"')
            ->withStatus(200);
    }

    public function clearDebug(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin()) {
            return $response->withStatus(403);
        }

        $data = $request->getParsedBody();
        if (!is_array($data) || !$this->csrf->verify($data['csrf_token'] ?? null)) {
            return $response->withStatus(419);
        }
        if (!$this->debugMaintenance instanceof DebugMaintenance) {
            return $response->withStatus(503);
        }

        try {
            $this->debugMaintenance->clear();
        } catch (Throwable) {
            return $response->withStatus(500);
        }

        return $response->withHeader('Location', '/settings/system')->withStatus(303);
    }

    private function isAdmin(): bool
    {
        $userId = $_SESSION['user_id'] ?? null;
        $companyId = $_SESSION['company_id'] ?? null;
        if ((!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) ||
            (!is_int($companyId) && !(is_string($companyId) && ctype_digit($companyId)))) {
            return false;
        }

        $statement = $this->pdo->prepare(
            "SELECT role FROM company_users WHERE user_id = :user_id AND company_id = :company_id LIMIT 1"
        );
        $statement->execute([
            'user_id' => (int) $userId,
            'company_id' => (int) $companyId,
        ]);

        return $statement->fetchColumn() === 'admin';
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $date;
    }
}
