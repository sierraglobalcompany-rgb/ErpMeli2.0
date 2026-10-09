<?php

declare(strict_types=1);

namespace App\Core\Logging;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class DebugRecorder
{
    /** @var list<string> */
    private const INTEGER_FIELDS = [
        'company_id',
        'account_id',
        'work_id',
        'http_status',
        'duration_ms',
        'resource_count',
        'count',
    ];

    /** @var list<string> */
    private const IDENTIFIER_FIELDS = [
        'correlation_id',
        'work_type',
        'operation',
        'outcome',
        'request_id',
        'event_id',
        'topic',
        'resource_id',
        'error_code',
    ];

    public function __construct(
        private readonly string $directory,
        private readonly bool $enabled,
        private readonly int $maxBytes,
    ) {
        if ($this->maxBytes < 1) {
            throw new RuntimeException('Debug storage cap must be positive.');
        }
    }

    /** @param array<string,mixed> $fields */
    public function record(
        string $event,
        array $fields = [],
        ?DateTimeImmutable $at = null,
    ): void {
        if (!$this->enabled) {
            return;
        }

        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $event) !== 1) {
            throw new RuntimeException('Invalid debug event name.');
        }

        if ($this->usageBytes() >= $this->maxBytes) {
            return;
        }

        $at ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $at = $at->setTimezone(new DateTimeZone('UTC'));

        $payload = [
            'ts' => $at->format(DATE_ATOM),
            'event' => $event,
            'fields' => $this->allowlistedFields($fields),
        ];
        $line = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

        if ($this->usageBytes() + strlen($line) > $this->maxBytes) {
            return;
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Cannot create debug directory.');
        }

        $path = rtrim($this->directory, '/') . '/debug-' . $at->format('Y-m-d') . '.jsonl';
        if (file_put_contents($path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('Cannot write debug record.');
        }
        @chmod($path, 0600);
    }

    /**
     * @param array<string,mixed> $fields
     * @return array<string,int|string>
     */
    private function allowlistedFields(array $fields): array
    {
        $safe = [];

        foreach ($fields as $key => $value) {
            if (in_array($key, self::INTEGER_FIELDS, true)) {
                if (is_int($value) && $value >= 0) {
                    $safe[$key] = $value;
                }
                continue;
            }

            if (in_array($key, self::IDENTIFIER_FIELDS, true)) {
                if (!is_string($value)) {
                    continue;
                }

                $trimmed = trim($value);
                if (
                    $trimmed !== ''
                    && strlen($trimmed) <= 160
                    && preg_match('/^[A-Za-z0-9._:-]+$/D', $trimmed) === 1
                ) {
                    $safe[$key] = $trimmed;
                }
                continue;
            }

            if ($key === 'retry_at' && is_string($value)) {
                $trimmed = trim($value);
                if (
                    strlen($trimmed) <= 40
                    && preg_match('/^[0-9T:+.Z-]+$/D', $trimmed) === 1
                ) {
                    $safe[$key] = $trimmed;
                }
            }
        }

        return $safe;
    }

    private function usageBytes(): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }

        $entries = scandir($this->directory);
        if ($entries === false) {
            throw new RuntimeException('Cannot inspect debug directory.');
        }

        $total = 0;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = rtrim($this->directory, '/') . '/' . $entry;
            if (!is_file($path)) {
                continue;
            }

            $size = filesize($path);
            if ($size !== false) {
                $total += $size;
            }
        }

        return $total;
    }
}
