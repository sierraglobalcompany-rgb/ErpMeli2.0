<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use RuntimeException;

final class Migrator
{
    public function migrate(PDO $pdo, string $directory): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(191) NOT NULL PRIMARY KEY,
                checksum CHAR(64) NOT NULL,
                applied_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $files = glob(rtrim($directory, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        foreach ($files as $file) {
            $version = basename($file);
            $sql = file_get_contents($file);
            if ($sql === false) {
                throw new RuntimeException('Cannot read migration: ' . $version);
            }

            $checksum = hash('sha256', $sql);
            $stmt = $pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
            $stmt->execute([$version]);
            $applied = $stmt->fetchColumn();

            if (is_string($applied)) {
                if (!hash_equals($applied, $checksum)) {
                    throw new RuntimeException('Applied migration changed: ' . $version);
                }
                continue;
            }

            $pdo->exec($sql);
            $insert = $pdo->prepare('INSERT INTO schema_migrations(version, checksum) VALUES (?, ?)');
            $insert->execute([$version, $checksum]);
        }
    }
}
