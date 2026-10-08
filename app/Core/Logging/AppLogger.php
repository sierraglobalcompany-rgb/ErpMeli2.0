<?php

declare(strict_types=1);

namespace App\Core\Logging;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class AppLogger
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'authorization',
        'access_token',
        'refresh_token',
        'client_secret',
        'password',
        'api_key',
        'cookie',
    ];

    public function __construct(private readonly string $directory)
    {
    }

    /** @param array<string, mixed> $fields */
    public function error(string $event, array $fields = []): void
    {
        $this->write(new LogEvent('ERROR', $event, $fields, new DateTimeImmutable('now', new DateTimeZone('UTC'))));
    }

    /** @param array<string, mixed> $fields */
    public function warning(string $event, array $fields = []): void
    {
        $this->write(new LogEvent('WARNING', $event, $fields, new DateTimeImmutable('now', new DateTimeZone('UTC'))));
    }

    public function retentionCutoff(int $days, ?DateTimeImmutable $now = null): DateTimeImmutable
    {
        if ($days < 1) {
            throw new RuntimeException('Retention days must be positive.');
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $now->modify(sprintf('-%d days', $days));
    }

    private function write(LogEvent $event): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create log directory.');
        }

        $payload = [
            'ts' => $event->at->format(DATE_ATOM),
            'level' => $event->level,
            'event' => $event->event,
            'fields' => $this->sanitize($event->fields),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $path = rtrim($this->directory, '/') . '/app-' . $event->at->format('Y-m-d') . '.log';

        if (file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Cannot write application log.');
        }
        @chmod($path, 0600);
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
            return '[REDACTED]';
        }

        if (!is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $childKey => $childValue) {
            $stringKey = is_string($childKey) ? $childKey : null;
            $result[$childKey] = $this->sanitize($childValue, $stringKey);
        }

        return $result;
    }
}
