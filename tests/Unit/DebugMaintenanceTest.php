<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Logging\DebugMaintenance;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DebugMaintenanceTest extends TestCase
{
    public function testClosedUtcDayIsGzippedWhileCurrentDayStaysWritable(): void
    {
        [$root, $debugDir, $exportDir] = $this->roots('gzip');
        file_put_contents($debugDir . '/debug-2026-10-09.jsonl', "{\"event\":\"closed\"}\n");
        file_put_contents($debugDir . '/debug-2026-10-10.jsonl', "{\"event\":\"current\"}\n");

        $maintenance = new DebugMaintenance($debugDir, $exportDir);
        $result = $maintenance->run(7, new DateTimeImmutable('2026-10-10T12:00:00+00:00'));

        self::assertSame(1, $result['compressed']);
        self::assertFileDoesNotExist($debugDir . '/debug-2026-10-09.jsonl');
        self::assertFileExists($debugDir . '/debug-2026-10-09.jsonl.gz');
        self::assertSame(
            "{\"event\":\"closed\"}\n",
            gzdecode((string) file_get_contents($debugDir . '/debug-2026-10-09.jsonl.gz')),
        );
        self::assertFileExists($debugDir . '/debug-2026-10-10.jsonl');
        self::assertFileDoesNotExist($debugDir . '/debug-2026-10-10.jsonl.gz');

        $second = $maintenance->run(7, new DateTimeImmutable('2026-10-10T12:05:00+00:00'));
        self::assertSame(0, $second['compressed']);

        $this->removeTree($root);
    }

    public function testRetentionDeletesOnlyExpiredDebugFilesAndExportTtlIs24Hours(): void
    {
        [$root, $debugDir, $exportDir] = $this->roots('retention');
        file_put_contents($debugDir . '/debug-2026-09-30.jsonl', "old\n");
        file_put_contents($debugDir . '/debug-2026-10-05.jsonl', "recent\n");
        file_put_contents($debugDir . '/notes.txt', "leave-me\n");

        $oldExport = $exportDir . '/debug-export-aaaaaaaa.zip';
        $recentExport = $exportDir . '/debug-export-bbbbbbbb.zip';
        $foreignExport = $exportDir . '/manual.zip';
        file_put_contents($oldExport, 'old-export');
        file_put_contents($recentExport, 'recent-export');
        file_put_contents($foreignExport, 'foreign-export');

        $now = new DateTimeImmutable('2026-10-10T12:00:00+00:00');
        touch($oldExport, $now->getTimestamp() - 90000);
        touch($recentExport, $now->getTimestamp() - 82800);
        touch($foreignExport, $now->getTimestamp() - 200000);

        $result = (new DebugMaintenance($debugDir, $exportDir))->run(7, $now);

        self::assertSame(1, $result['deleted_debug']);
        self::assertFileDoesNotExist($debugDir . '/debug-2026-09-30.jsonl');
        self::assertFileExists($debugDir . '/debug-2026-10-05.jsonl.gz');
        self::assertFileExists($debugDir . '/notes.txt');

        self::assertSame(1, $result['deleted_exports']);
        self::assertFileDoesNotExist($oldExport);
        self::assertFileExists($recentExport);
        self::assertFileExists($foreignExport);

        $this->removeTree($root);
    }

    public function testMaintenanceNeverFollowsSymlinkOutsideManagedRoots(): void
    {
        [$root, $debugDir, $exportDir] = $this->roots('symlink');
        $outside = sys_get_temp_dir() . '/erp-meli2-debug-outside-' . bin2hex(random_bytes(4));
        file_put_contents($outside, 'outside-safe');

        $link = $debugDir . '/debug-2026-09-01.jsonl';
        if (!@symlink($outside, $link)) {
            @unlink($outside);
            $this->removeTree($root);
            self::markTestSkipped('Symlink creation is unavailable on this platform.');
        }

        (new DebugMaintenance($debugDir, $exportDir))->run(
            7,
            new DateTimeImmutable('2026-10-10T12:00:00+00:00'),
        );

        self::assertFileExists($outside);
        self::assertSame('outside-safe', file_get_contents($outside));
        self::assertTrue(is_link($link));

        @unlink($link);
        @unlink($outside);
        $this->removeTree($root);
    }

    /** @return array{0:string,1:string,2:string} */
    private function roots(string $suffix): array
    {
        $root = sys_get_temp_dir() . '/erp-meli2-maint-' . $suffix . '-' . bin2hex(random_bytes(4));
        $debugDir = $root . '/debug';
        $exportDir = $root . '/exports';
        self::assertTrue(mkdir($debugDir, 0700, true));
        self::assertTrue(mkdir($exportDir, 0700, true));
        return [$root, $debugDir, $exportDir];
    }

    private function removeTree(string $root): void
    {
        foreach ([$root . '/debug', $root . '/exports'] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $path) {
                if (is_link($path) || is_file($path)) {
                    @unlink($path);
                }
            }
            @rmdir($dir);
        }
        @rmdir($root);
    }
}
