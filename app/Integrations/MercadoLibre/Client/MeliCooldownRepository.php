<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

final class MeliCooldownRepository
{
    /** @var list<int> */
    private const BACKOFF_SECONDS = [15, 30, 60, 120, 300, 900];

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
        $statement = $this->pdo->prepare(
            'SELECT consecutive_429 FROM meli_cooldowns WHERE cooldown_key = :cooldown_key'
        );
        $statement->execute(['cooldown_key' => $cooldownKey]);
        $previous = $statement->fetchColumn();
        $consecutive = is_numeric($previous) ? (int) $previous + 1 : 1;

        if ($retryAfterSeconds !== null && $retryAfterSeconds >= 0) {
            $baseSeconds = $retryAfterSeconds;
        } else {
            $index = min($consecutive - 1, count(self::BACKOFF_SECONDS) - 1);
            $baseSeconds = self::BACKOFF_SECONDS[$index];
        }

        $jitterSeconds = random_int(0, 2);
        $blockedUntil = new DateTimeImmutable(
            '+' . ($baseSeconds + $jitterSeconds) . ' seconds',
            new DateTimeZone('UTC'),
        );

        $this->blockUntil($cooldownKey, $blockedUntil, $consecutive);

        return $blockedUntil;
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
            . 'blocked_until = VALUES(blocked_until), '
            . 'consecutive_429 = VALUES(consecutive_429), '
            . 'updated_at = UTC_TIMESTAMP(6)'
        );
        $statement->execute([
            'cooldown_key' => $cooldownKey,
            'blocked_until' => $blockedUntil->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'consecutive_429' => $consecutive429,
        ]);
    }

    public function clear(string $cooldownKey): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM meli_cooldowns WHERE cooldown_key = :cooldown_key'
        );
        $statement->execute(['cooldown_key' => $cooldownKey]);
    }
}
