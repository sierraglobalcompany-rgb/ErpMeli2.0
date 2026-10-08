<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config\AppConfig;
use App\Core\Database\Connection;
use App\Core\Database\Migrator;
use PDO;

final class TestDatabase
{
    public static function config(): AppConfig
    {
        return AppConfig::fromEnvironment([
            'APP_ENV' => 'test',
            'APP_URL' => 'http://localhost',
            'APP_KEY' => 'test-only-key',
            'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
            'DB_PORT' => getenv('DB_PORT') ?: 3306,
            'DB_NAME' => getenv('DB_NAME') ?: 'erp_meli2_test',
            'DB_USER' => getenv('DB_USER') ?: 'root',
            'DB_PASSWORD' => getenv('DB_PASSWORD') ?: 'root',
        ]);
    }

    public static function reset(): PDO
    {
        $config = self::config();
        $server = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $config->dbHost, $config->dbPort),
            $config->dbUser,
            $config->dbPassword,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $db = str_replace('`', '', $config->dbName);
        $server->exec('DROP DATABASE IF EXISTS `' . $db . '`');
        $server->exec(
            'CREATE DATABASE `' . $db . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );

        $pdo = Connection::fromConfig($config);
        (new Migrator())->migrate($pdo, dirname(__DIR__, 2) . '/database/migrations');

        self::resetSession();

        return $pdo;
    }

    public static function resetSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];
    }
}
