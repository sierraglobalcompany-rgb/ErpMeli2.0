<?php

declare(strict_types=1);

namespace App\Core\Config;

final class Environment
{
    /** @return array<string, scalar|null> */
    public static function all(?string $privateFile = null): array
    {
        $privateFile ??= dirname(__DIR__, 3) . '/storage/private/runtime.json';
        $private = [];
        if (is_file($privateFile)) {
            $private = json_decode((string) file_get_contents($privateFile), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($private)) { throw new \RuntimeException('Invalid private configuration.'); }
        }
        return self::resolve($private);
    }

    /** @param array<mixed> $private
     *  @return array<string, scalar|null>
     */
    public static function resolve(array $private): array
    {
        $keys = [
            'APP_ENV', 'APP_NAME', 'APP_URL', 'APP_KEY',
            'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
            'MELI_CLIENT_ID', 'MELI_CLIENT_SECRET',
        ];

        $result = [];
        foreach ($keys as $key) {
            $value = getenv($key);
            $fallback = $private[$key] ?? null;
            $result[$key] = $value === false ? ($_ENV[$key] ?? (is_scalar($fallback) ? $fallback : null)) : $value;
        }

        return $result;
    }

    public static function isExternallyManaged(string $key): bool
    {
        return getenv($key) !== false || array_key_exists($key, $_ENV);
    }
}
