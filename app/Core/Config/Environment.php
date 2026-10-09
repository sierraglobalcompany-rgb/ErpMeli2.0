<?php

declare(strict_types=1);

namespace App\Core\Config;

final class Environment
{
    /** @return array<string, scalar|null> */
    public static function all(): array
    {
        $keys = [
            'APP_ENV', 'APP_URL', 'APP_KEY',
            'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD',
            'MELI_CLIENT_ID', 'MELI_CLIENT_SECRET',
        ];

        $result = [];
        foreach ($keys as $key) {
            $value = getenv($key);
            $result[$key] = $value === false ? ($_ENV[$key] ?? null) : $value;
        }

        return $result;
    }
}
