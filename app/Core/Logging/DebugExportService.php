<?php

declare(strict_types=1);

namespace App\Core\Logging;

use DateTimeImmutable;
use DateTimeZone;
use FilesystemIterator;
use InvalidArgumentException;
use Phar;
use PharData;
use RuntimeException;
use Throwable;

final class DebugExportService
{
    private const MAX_RANGE_DAYS = 90;

    public function __construct(
        private readonly string $debugDirectory,
        private readonly string $exportDirectory,
        private readonly string $appVersion = '2.0',
        private readonly string $schemaVersion = 'unknown',
        private readonly string $redactionSchemaVersion = '1',
    ) {
    }

    /** @return array{path:string,filename:string,count:int}|null */
    public function create(
        ?string $startDay = null,
        ?string $endDay = null,
        ?string $schemaVersion = null,
    ): ?array {
        [$start, $end] = $this->normalizeRange($startDay, $endDay);
        $files = $this->debugFiles($start, $end);
        if ($files === []) {
            return null;
        }

        if (!is_dir($this->exportDirectory)
            && !mkdir($this->exportDirectory, 0700, true)
            && !is_dir($this->exportDirectory)
        ) {
            throw new RuntimeException('Cannot create debug export directory.');
        }

        $filename = 'debug-export-' . bin2hex(random_bytes(8)) . '.zip';
        $path = rtrim($this->exportDirectory, '/') . '/' . $filename;
        $manifestFiles = [];

        try {
            $archive = new PharData(
                $path,
                FilesystemIterator::SKIP_DOTS,
                null,
                Phar::ZIP,
            );

            foreach ($files as $entry) {
                $source = rtrim($this->debugDirectory, '/') . '/' . $entry;
                if (is_link($source) || !is_file($source)) {
                    throw new RuntimeException('Debug export source changed during export.');
                }

                $contents = file_get_contents($source);
                if ($contents === false) {
                    throw new RuntimeException('Cannot read debug file for export.');
                }

                $archive->addFromString($entry, $contents);
                $manifestFiles[] = [
                    'name' => $entry,
                    'sha256' => hash('sha256', $contents),
                ];
            }

            $manifest = [
                'app_version' => $this->appVersion,
                'schema_version' => $schemaVersion ?? $this->schemaVersion,
                'redaction_schema_version' => $this->redactionSchemaVersion,
                'range_utc' => [
                    'start' => $start ?? $this->entryDay($files[0]),
                    'end' => $end ?? $this->entryDay($files[count($files) - 1]),
                ],
                'files' => $manifestFiles,
            ];
            $archive->addFromString(
                'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            );
        } catch (Throwable $exception) {
            @unlink($path);
            throw new RuntimeException('Cannot create debug export.', 0, $exception);
        }

        if (!is_file($path)) {
            throw new RuntimeException('Debug export file was not created.');
        }
        @chmod($path, 0600);

        return [
            'path' => $path,
            'filename' => $filename,
            'count' => count($manifestFiles),
        ];
    }

    /** @return array{0:?string,1:?string} */
    private function normalizeRange(?string $startDay, ?string $endDay): array
    {
        $start = $this->normalizeDay($startDay);
        $end = $this->normalizeDay($endDay);

        if ($start === null && $end === null) {
            return [null, null];
        }
        if ($start === null) {
            $start = $end;
        }
        if ($end === null) {
            $end = $start;
        }

        if ($start > $end) {
            throw new InvalidArgumentException('Invalid debug export range.');
        }

        $startDate = new DateTimeImmutable($start, new DateTimeZone('UTC'));
        $endDate = new DateTimeImmutable($end, new DateTimeZone('UTC'));
        $rangeDays = (int) $startDate->diff($endDate)->format('%a') + 1;
        if ($rangeDays > self::MAX_RANGE_DAYS) {
            throw new InvalidArgumentException('Debug export range is too large.');
        }

        return [$start, $end];
    }

    private function normalizeDay(?string $day): ?string
    {
        if ($day === null || trim($day) === '') {
            return null;
        }

        $day = trim($day);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day) !== 1) {
            throw new InvalidArgumentException('Invalid debug export day.');
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $day) {
            throw new InvalidArgumentException('Invalid debug export day.');
        }

        return $day;
    }

    /** @return list<string> */
    private function debugFiles(?string $startDay, ?string $endDay): array
    {
        if (!is_dir($this->debugDirectory)) {
            return [];
        }

        $entries = scandir($this->debugDirectory);
        if ($entries === false) {
            throw new RuntimeException('Cannot inspect debug storage directory.');
        }

        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (preg_match('/^debug-\d{4}-\d{2}-\d{2}\.jsonl(?:\.gz)?$/D', $entry) !== 1) {
                continue;
            }

            $day = $this->entryDay($entry);
            if (($startDay !== null && $day < $startDay) || ($endDay !== null && $day > $endDay)) {
                continue;
            }

            $path = rtrim($this->debugDirectory, '/') . '/' . $entry;
            if (is_link($path) || !is_file($path)) {
                continue;
            }

            $files[] = $entry;
        }

        sort($files, SORT_STRING);
        return $files;
    }

    private function entryDay(string $entry): string
    {
        return substr($entry, 6, 10);
    }
}
