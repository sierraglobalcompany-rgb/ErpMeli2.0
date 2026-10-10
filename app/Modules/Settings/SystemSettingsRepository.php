<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class SystemSettingsRepository
{
    private const RETENTION_DAYS = [1, 3, 7, 14, 30, 90];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): SystemSettings
    {
        $row = $this->pdo->query(
            'SELECT automation_enabled, meli_writes_enabled, debug_enabled,
                    debug_retention_days, debug_max_mb
             FROM system_settings WHERE id = 1'
        )->fetch();

        if (!is_array($row)) {
            throw new RuntimeException('System settings are missing.');
        }

        return new SystemSettings(
            $this->boolFailClosed($row['automation_enabled'] ?? null),
            $this->boolFailClosed($row['meli_writes_enabled'] ?? null),
            $this->boolFailClosed($row['debug_enabled'] ?? null),
            (int) ($row['debug_retention_days'] ?? 7),
            (int) ($row['debug_max_mb'] ?? 100),
        );
    }

    public function updateOperationalToggles(
        bool $automation,
        bool $debug,
        int $retentionDays,
        int $debugMaxMb,
        int $userId,
    ): void {
        if (!in_array($retentionDays, self::RETENTION_DAYS, true)) {
            throw new InvalidArgumentException('Unsupported debug retention.');
        }
        if ($debugMaxMb < 10 || $debugMaxMb > 10240) {
            throw new InvalidArgumentException('Debug storage limit out of range.');
        }

        $stmt = $this->pdo->prepare(
            'UPDATE system_settings
             SET automation_enabled = ?, debug_enabled = ?,
                 debug_retention_days = ?, debug_max_mb = ?, updated_by = ?
             WHERE id = 1'
        );
        $stmt->execute([
            $automation ? 1 : 0,
            $debug ? 1 : 0,
            $retentionDays,
            $debugMaxMb,
            $userId,
        ]);

        if ($stmt->rowCount() > 1) {
            throw new RuntimeException('Unexpected settings update count.');
        }
    }

    private function boolFailClosed(mixed $value): bool
    {
        return $value === 1 || $value === '1';
    }
}
