<?php

declare(strict_types=1);

namespace App\Core\Logging;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class DebugMaintenance
{
    private const EXPORT_TTL_SECONDS = 86400;

    public function __construct(
        private readonly string $debugDirectory,
        private readonly string $exportDirectory,
    ) {
    }

    /** @return array{compressed:int,deleted_debug:int,deleted_exports:int} */
    public function run(int $retentionDays, ?DateTimeImmutable $now = null): array
    {
        if ($retentionDays < 1) {
            throw new RuntimeException('Debug retention days must be positive.');
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $now = $now->setTimezone(new DateTimeZone('UTC'));
        $today = $now->format('Y-m-d');
        $cutoff = $now->modify('-' . $retentionDays . ' days')->format('Y-m-d');

        $deletedDebug = $this->deleteExpiredDebug($cutoff);
        $compressed = $this->compressClosedDays($today, $cutoff);
        $deletedExports = $this->deleteExpiredExports($now->getTimestamp() - self::EXPORT_TTL_SECONDS);

        return [
            'compressed' => $compressed,
            'deleted_debug' => $deletedDebug,
            'deleted_exports' => $deletedExports,
        ];
    }

    /** @return array{total_bytes:int,days:list<array{day:string,bytes:int,compressed:bool}>} */
    public function usage(): array
    {
        /** @var array<string,array{bytes:int,compressed:bool}> $days */
        $days = [];
        $totalBytes = 0;

        foreach ($this->entries($this->debugDirectory) as $entry) {
            $path = $this->debugDirectory . '/' . $entry;
            if (is_link($path) || !is_file($path)) {
                continue;
            }

            $metadata = $this->debugFileMetadata($entry);
            if ($metadata === null) {
                continue;
            }

            $bytes = filesize($path);
            if ($bytes === false) {
                throw new RuntimeException('Cannot inspect debug file size.');
            }

            $totalBytes += $bytes;
            $day = $metadata['day'];
            if (!isset($days[$day])) {
                $days[$day] = ['bytes' => 0, 'compressed' => true];
            }
            $days[$day]['bytes'] += $bytes;
            $days[$day]['compressed'] = $days[$day]['compressed'] && $metadata['compressed'];
        }

        krsort($days, SORT_STRING);
        $history = [];
        foreach ($days as $day => $metadata) {
            $history[] = [
                'day' => $day,
                'bytes' => $metadata['bytes'],
                'compressed' => $metadata['compressed'],
            ];
        }

        return ['total_bytes' => $totalBytes, 'days' => $history];
    }

    public function clearDebug(): int
    {
        $deleted = 0;
        foreach ($this->entries($this->debugDirectory) as $entry) {
            $path = $this->debugDirectory . '/' . $entry;
            if (is_link($path) || !is_file($path) || $this->debugFileMetadata($entry) === null) {
                continue;
            }

            if (!unlink($path)) {
                throw new RuntimeException('Cannot clear debug file.');
            }
            ++$deleted;
        }

        return $deleted;
    }

    private function deleteExpiredDebug(string $cutoff): int
    {
        $deleted = 0;
        foreach ($this->entries($this->debugDirectory) as $entry) {
            $path = $this->debugDirectory . '/' . $entry;
            if (is_link($path) || !is_file($path)) {
                continue;
            }

            $metadata = $this->debugFileMetadata($entry);
            if ($metadata === null || $metadata['day'] >= $cutoff) {
                continue;
            }

            if (!unlink($path)) {
                throw new RuntimeException('Cannot remove expired debug file.');
            }
            ++$deleted;
        }

        return $deleted;
    }

    private function compressClosedDays(string $today, string $cutoff): int
    {
        $compressed = 0;
        foreach ($this->entries($this->debugDirectory) as $entry) {
            $source = $this->debugDirectory . '/' . $entry;
            if (is_link($source) || !is_file($source)) {
                continue;
            }

            if (preg_match('/^debug-(\d{4}-\d{2}-\d{2})\.jsonl$/D', $entry, $matches) !== 1) {
                continue;
            }

            $day = $matches[1];
            if ($day >= $today || $day < $cutoff) {
                continue;
            }

            $target = $source . '.gz';
            if (is_link($target) || file_exists($target)) {
                continue;
            }

            $contents = file_get_contents($source);
            if ($contents === false) {
                throw new RuntimeException('Cannot read closed debug file.');
            }
            $gzip = gzencode($contents, 6);
            if ($gzip === false) {
                throw new RuntimeException('Cannot gzip closed debug file.');
            }

            $temporary = $this->debugDirectory . '/.gzip-' . bin2hex(random_bytes(8));
            if (file_put_contents($temporary, $gzip, LOCK_EX) === false) {
                throw new RuntimeException('Cannot write compressed debug file.');
            }
            @chmod($temporary, 0600);

            if (!rename($temporary, $target)) {
                @unlink($temporary);
                throw new RuntimeException('Cannot finalize compressed debug file.');
            }
            if (!unlink($source)) {
                @unlink($target);
                throw new RuntimeException('Cannot remove uncompressed debug file.');
            }

            ++$compressed;
        }

        return $compressed;
    }

    private function deleteExpiredExports(int $cutoffTimestamp): int
    {
        $deleted = 0;
        foreach ($this->entries($this->exportDirectory) as $entry) {
            $path = $this->exportDirectory . '/' . $entry;
            if (is_link($path) || !is_file($path)) {
                continue;
            }

            if (preg_match('/^debug-export-[A-Za-z0-9_-]{8,128}\.zip$/D', $entry) !== 1) {
                continue;
            }

            $modifiedAt = filemtime($path);
            if ($modifiedAt === false || $modifiedAt >= $cutoffTimestamp) {
                continue;
            }

            if (!unlink($path)) {
                throw new RuntimeException('Cannot remove expired debug export.');
            }
            ++$deleted;
        }

        return $deleted;
    }

    /** @return array{day:string,compressed:bool}|null */
    private function debugFileMetadata(string $entry): ?array
    {
        if (preg_match('/^debug-(\d{4}-\d{2}-\d{2})\.jsonl(\.gz)?$/D', $entry, $matches) !== 1) {
            return null;
        }

        return [
            'day' => $matches[1],
            'compressed' => ($matches[2] ?? '') === '.gz',
        ];
    }

    /** @return list<string> */
    private function entries(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $entries = scandir($directory);
        if ($entries === false) {
            throw new RuntimeException('Cannot inspect debug storage directory.');
        }

        return array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
        ));
    }
}
