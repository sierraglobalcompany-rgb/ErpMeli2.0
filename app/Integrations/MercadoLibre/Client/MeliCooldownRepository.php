<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class MeliCooldownRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function activeUntil(string $cooldownKey): ?DateTimeImmutable
    {
        $statement = $this->pdo->prepare(
            'SELECT blocked_until FROM meli_cooldowns '
            . 'WHERE cooldown_key = :cooldown_key AND blocked_until > UTC_TIMESTAMP(6)'
        );
        $statement->execute(['cooldown_key' => $cooldownKey]);
        $value = $statement->fetchColumn();

        if (!is_string($value) || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    public function register429(string $cooldownKey, ?int $retryAfterSeconds): DateTimeImmutable
    {
        $cooldownKey = trim($cooldownKey);
        if ($cooldownKey === '') {
            throw new InvalidArgumentException('cooldown_key cannot be empty.');
        }

        $jitterSeconds = random_int(0, 2);
        $insertSeconds = ($retryAfterSeconds !== null && $retryAfterSeconds >= 0)
            ? $retryAfterSeconds + $jitterSeconds
            : 15 + $jitterSeconds;

        $retryAfterParam = ($retryAfterSeconds !== null && $retryAfterSeconds >= 0)
            ? $retryAfterSeconds
            : null;

        $statement = $this->pdo->prepare(
            'INSERT INTO meli_cooldowns (cooldown_key, blocked_until, consecutive_429) '
            . 'VALUES (:cooldown_key, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL :insert_seconds SECOND), 1) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'blocked_until = GREATEST('
            . '    blocked_until, '
            . '    DATE_ADD(UTC_TIMESTAMP(6), INTERVAL ('
            . '        CASE '
            . '            WHEN :retry_after_seconds >= 0 THEN :retry_after_seconds_val '
            . '            WHEN LEAST(consecutive_429 + 1, 65535) = 1 THEN 15 '
            . '            WHEN LEAST(consecutive_429 + 1, 65535) = 2 THEN 30 '
            . '            WHEN LEAST(consecutive_429 + 1, 65535) = 3 THEN 60 '
            . '            WHEN LEAST(consecutive_429 + 1, 65535) = 4 THEN 120 '
            . '            WHEN LEAST(consecutive_429 + 1, 65535) = 5 THEN 300 '
            . '            ELSE 900 '
            . '        END + :jitter_seconds'
            . '    ) SECOND)'
            . '), '
            . 'consecutive_429 = LEAST(consecutive_429 + 1, 65535), '
            . 'updated_at = UTC_TIMESTAMP(6)'
        );

        $statement->execute([
            'cooldown_key' => $cooldownKey,
            'insert_seconds' => $insertSeconds,
            'retry_after_seconds' => $retryAfterParam,
            'retry_after_seconds_val' => $retryAfterParam,
            'jitter_seconds' => $jitterSeconds,
        ]);

        $fetch = $this->pdo->prepare(
            'SELECT blocked_until FROM meli_cooldowns WHERE cooldown_key = :cooldown_key'
        );
        $fetch->execute(['cooldown_key' => $cooldownKey]);
        $value = $fetch->fetchColumn();

        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Registered cooldown deadline could not be reloaded.');
        }

        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    public function blockUntil(string $cooldownKey, DateTimeImmutable $blockedUntil, int $consecutive429): void
    {
        if ($consecutive429 < 0) {
            throw new RuntimeException('consecutive_429 cannot be negative.');
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO meli_cooldowns (cooldown_key, blocked_until, consecutive_429) '
            . 'VALUES (:cooldown_key, :blocked_until, :consecutive_429) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'blocked_until = GREATEST(blocked_until, VALUES(blocked_until)), '
            . 'consecutive_429 = VALUES(consecutive_429), '
            . 'updated_at = UTC_TIMESTAMP(6)'
        );
        $statement->execute([
            'cooldown_key' => $cooldownKey,
            'blocked_until' => $blockedUntil->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'consecutive_429' => min($consecutive429, 65535),
        ]);
    }

    public function clear(string $cooldownKey): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM meli_cooldowns WHERE cooldown_key = :cooldown_key AND blocked_until <= UTC_TIMESTAMP(6)'
        );
        $statement->execute(['cooldown_key' => $cooldownKey]);
    }
}
