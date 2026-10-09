<?php

declare(strict_types=1);

namespace App\Core\Config;

use RuntimeException;

final readonly class AppConfig
{
    public function __construct(
        public string $appEnv,
        public string $appUrl,
        public string $appKey,
        public string $dbHost,
        public int $dbPort,
        public string $dbName,
        public string $dbUser,
        public string $dbPassword,
        public string $meliClientId,
        public string $meliClientSecret,
    ) {
    }

    /** @param array<string, scalar|null> $env */
    public static function fromEnvironment(array $env): self
    {
        $appEnv = self::string($env, 'APP_ENV', 'local');
        if (!in_array($appEnv, ['local', 'test', 'production'], true)) {
            throw new RuntimeException('Unsupported APP_ENV.');
        }

        return new self(
            $appEnv,
            self::string($env, 'APP_URL', 'http://localhost'),
            self::string($env, 'APP_KEY', ''),
            self::string($env, 'DB_HOST', '127.0.0.1'),
            self::integer($env, 'DB_PORT', 3306),
            self::string($env, 'DB_NAME', 'erp_meli2'),
            self::string($env, 'DB_USER', 'root'),
            self::string($env, 'DB_PASSWORD', ''),
            self::string($env, 'MELI_CLIENT_ID', ''),
            self::string($env, 'MELI_CLIENT_SECRET', ''),
        );
    }

    public function isProduction(): bool
    {
        return $this->appEnv === 'production';
    }

    /** @param array<string, scalar|null> $env */
    private static function string(array $env, string $key, string $default): string
    {
        return trim((string) ($env[$key] ?? $default));
    }

    /** @param array<string, scalar|null> $env */
    private static function integer(array $env, string $key, int $default): int
    {
        $value = filter_var($env[$key] ?? null, FILTER_VALIDATE_INT);
        return $value === false ? $default : $value;
    }
}
