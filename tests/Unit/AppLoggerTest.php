<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Logging\AppLogger;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class AppLoggerTest extends TestCase
{
    public function testSensitiveFieldsAreRedactedRecursively(): void
    {
        $dir = sys_get_temp_dir() . '/erp-meli2-log-' . bin2hex(random_bytes(4));
        $logger = new AppLogger($dir);

        $logger->warning('oauth.failed', [
            'account_id' => 7,
            'authorization' => 'Bearer secret',
            'nested' => ['access_token' => 'very-secret'],
        ]);

        $files = glob($dir . '/app-*.log') ?: [];
        self::assertCount(1, $files);
        $content = (string) file_get_contents($files[0]);

        self::assertStringContainsString('[REDACTED]', $content);
        self::assertStringNotContainsString('Bearer secret', $content);
        self::assertStringNotContainsString('very-secret', $content);

        @unlink($files[0]);
        @rmdir($dir);
    }

    public function testRetentionCutoffUsesWholeDays(): void
    {
        $logger = new AppLogger(sys_get_temp_dir());
        $now = new DateTimeImmutable('2026-10-08T12:00:00+00:00');

        self::assertSame(
            '2026-09-24',
            $logger->retentionCutoff(14, $now)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d')
        );
    }
}
