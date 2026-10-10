<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class BillingC0CliGuardTest extends TestCase
{
    public function testCliRejectsNonProductionBeforeDatabaseOrHttp(): void
    {
        $result = $this->runCli([
            'APP_ENV' => 'test',
            'BILLING_C0_REAL_HTTP' => '1',
        ]);

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString('Billing C0 requires APP_ENV=production.', $result['stderr']);
        self::assertSame('', $result['stdout']);
    }

    public function testCliRequiresExplicitRealHttpOptInBeforeDatabaseOrHttp(): void
    {
        $result = $this->runCli([
            'APP_ENV' => 'production',
            'BILLING_C0_REAL_HTTP' => '0',
        ]);

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString('Billing C0 requires BILLING_C0_REAL_HTTP=1.', $result['stderr']);
        self::assertSame('', $result['stdout']);
    }

    /**
     * @param array<string,string> $environment
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    private function runCli(array $environment): array
    {
        $root = dirname(__DIR__, 2);
        $command = [
            PHP_BINARY,
            $root . '/bin/billing-c0-smoke.php',
            '--account-id=1',
            '--period=2026-09-01',
            '--document-type=BILL',
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $root, $environment);
        self::assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
