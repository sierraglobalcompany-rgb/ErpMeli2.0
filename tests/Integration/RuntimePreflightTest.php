<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Runtime\RuntimePreflight;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class RuntimePreflightTest extends TestCase
{
    public function testReportsVerifiedAndManualRuntimeFactsWithoutInventingPasses(): void
    {
        $pdo = TestDatabase::reset();
        $report = (new RuntimePreflight())->inspect(
            TestDatabase::config(),
            $pdo,
            dirname(__DIR__, 2)
        );

        self::assertSame('PASS', $report['php']['status']);
        self::assertSame('PASS', $report['extensions']['status']);
        self::assertSame('PASS', $report['database']['status']);
        self::assertSame('PASS', $report['storage']['status']);

        self::assertSame('UNKNOWN', $report['host_limits']['status']);
        self::assertSame('UNKNOWN', $report['cron_capacity']['status']);
        self::assertSame('PARTIAL', $report['overall_status']);

        self::assertIsString($report['database']['version']);
        self::assertNotSame('', $report['database']['version']);
        self::assertIsInt($report['disk']['free_bytes']);
        self::assertGreaterThan(0, $report['disk']['free_bytes']);
    }
}
