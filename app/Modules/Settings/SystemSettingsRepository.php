<?php

declare(strict_types=1);

namespace App\Modules\Settings;

use PDO;

final class SystemSettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): SystemSettings
    {
        $statement = $this->pdo->query(
            'SELECT automation_enabled, meli_writes_enabled, debug_enabled, debug_retention_days, debug_max_mb '
            . 'FROM system_settings WHERE id = 1'
        );
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return new SystemSettings(false, false, false, 7, 100);
        }

        return new SystemSettings(
            (bool) $row['automation_enabled'],
            (bool) $row['meli_writes_enabled'],
            (bool) $row['debug_enabled'],
            (int) $row['debug_retention_days'],
            (int) $row['debug_max_mb'],
        );
    }

    public function updateOperationalToggles(
        bool $automationEnabled,
        bool $debugEnabled,
        int $debugRetentionDays,
        int $debugMaxMb,
        int $updatedBy,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE system_settings SET automation_enabled = :automation_enabled, '
            . 'debug_enabled = :debug_enabled, debug_retention_days = :debug_retention_days, '
            . 'debug_max_mb = :debug_max_mb, updated_by = :updated_by WHERE id = 1'
        );
        $statement->execute([
            'automation_enabled' => (int) $automationEnabled,
            'debug_enabled' => (int) $debugEnabled,
            'debug_retention_days' => $debugRetentionDays,
            'debug_max_mb' => $debugMaxMb,
            'updated_by' => $updatedBy,
        ]);
    }
}
