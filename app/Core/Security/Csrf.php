<?php

declare(strict_types=1);

namespace App\Core\Security;

use RuntimeException;

final class Csrf
{
    public function token(): string
    {
        $token = $_SESSION['csrf_token'] ?? null;
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['csrf_token'] = $token;
        }

        return $token;
    }

    public function assertValid(string $token): void
    {
        $expected = $_SESSION['csrf_token'] ?? null;
        if (!is_string($expected) || !hash_equals($expected, $token)) {
            throw new RuntimeException('Invalid CSRF token.');
        }
    }
}
