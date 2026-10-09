<?php

declare(strict_types=1);

namespace App\Core\Logging;

use FilesystemIterator;
use Phar;
use PharData;
use RuntimeException;
use Throwable;

final class DebugExportService
{
    public function __construct(
        private readonly string $debugDirectory,
        private readonly string $exportDirectory,
    ) {
    }

    /** @return array{path:string,filename:string,count:int}|null */
    public function create(): ?array
    {
        $files = $this->debugFiles();
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
                    continue;
                }

                $contents = file_get_contents($source);
                if ($contents === false) {
                    throw new RuntimeException('Cannot read debug file for export.');
                }
                $archive->addFromString($entry, $contents);
            }
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
            'count' => count($files),
        ];
    }

    /** @return list<string> */
    private function debugFiles(): array
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

            $path = rtrim($this->debugDirectory, '/') . '/' . $entry;
            if (is_link($path) || !is_file($path)) {
                continue;
            }

            $files[] = $entry;
        }

        sort($files, SORT_STRING);
        return $files;
    }
}
