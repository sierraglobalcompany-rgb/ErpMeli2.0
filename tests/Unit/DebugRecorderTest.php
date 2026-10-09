<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Logging\AppLogger;
use App\Core\Logging\DebugRecorder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class DebugRecorderTest extends TestCase
{
    public function testDebugOffWritesNothing(): void
    {
        $dir = $this->tempDir('off');
        $recorder = new DebugRecorder($dir, false, 1024);

        $recorder->record(
            'work.claimed',
            ['correlation_id' => 'corr-off', 'work_id' => 12],
            new DateTimeImmutable('2026-10-09T12:00:00+00:00'),
        );

        self::assertSame([], glob($dir . '/*') ?: []);
        $this->removeDir($dir);
    }

    public function testDebugOnWritesUtcJsonlWithAllowlistedFieldsOnly(): void
    {
        $dir = $this->tempDir('on');
        $recorder = new DebugRecorder($dir, true, 1024 * 1024);

        $recorder->record(
            'meli.http.completed',
            [
                'correlation_id' => 'corr-123',
                'company_id' => 7,
                'account_id' => 11,
                'work_id' => 19,
                'work_type' => 'order.sync',
                'operation' => 'orders.get',
                'http_status' => 200,
                'duration_ms' => 41,
                'outcome' => 'success',
                'request_id' => 'req-safe-1',
                'resource_count' => 1,
                'authorization' => 'Bearer must-never-appear',
                'access_token' => 'token-must-never-appear',
                'password' => 'password-must-never-appear',
                'email' => 'buyer@example.test',
                'name' => 'Buyer Name',
                'address' => 'Buyer Address',
                'phone' => '+57-secret-phone',
                'payload' => ['buyer' => ['email' => 'nested@example.test']],
            ],
            new DateTimeImmutable('2026-10-09T23:30:00-05:00'),
        );

        $path = $dir . '/debug-2026-10-10.jsonl';
        self::assertFileExists($path);
        $content = (string) file_get_contents($path);
        $lines = array_values(array_filter(explode("\n", trim($content)), static fn (string $line): bool => $line !== ''));
        self::assertCount(1, $lines);

        $row = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($row);
        self::assertSame('2026-10-10T04:30:00+00:00', $row['ts'] ?? null);
        self::assertSame('meli.http.completed', $row['event'] ?? null);
        self::assertSame([
            'correlation_id' => 'corr-123',
            'company_id' => 7,
            'account_id' => 11,
            'work_id' => 19,
            'work_type' => 'order.sync',
            'operation' => 'orders.get',
            'http_status' => 200,
            'duration_ms' => 41,
            'outcome' => 'success',
            'request_id' => 'req-safe-1',
            'resource_count' => 1,
        ], $row['fields'] ?? null);

        foreach ([
            'must-never-appear',
            'buyer@example.test',
            'Buyer Name',
            'Buyer Address',
            '+57-secret-phone',
            'nested@example.test',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $content);
        }

        $this->removeDir($dir);
    }

    public function testVerboseCapStopsDebugButNormalWarningStillWrites(): void
    {
        $debugDir = $this->tempDir('cap-debug');
        $logsDir = $this->tempDir('cap-logs');
        file_put_contents($debugDir . '/existing.jsonl', str_repeat('x', 64));

        $recorder = new DebugRecorder($debugDir, true, 64);
        $recorder->record(
            'work.claimed',
            ['correlation_id' => 'corr-cap', 'work_id' => 99],
            new DateTimeImmutable('2026-10-09T12:00:00+00:00'),
        );

        self::assertFileDoesNotExist($debugDir . '/debug-2026-10-09.jsonl');

        (new AppLogger($logsDir))->warning('debug.cap.reached', ['account_id' => 7]);
        self::assertCount(1, glob($logsDir . '/app-*.log') ?: []);

        $this->removeDir($debugDir);
        $this->removeDir($logsDir);
    }

    private function tempDir(string $suffix): string
    {
        $dir = sys_get_temp_dir() . '/erp-meli2-debug-' . $suffix . '-' . bin2hex(random_bytes(4));
        self::assertTrue(mkdir($dir, 0700, true));
        return $dir;
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($dir);
    }
}
