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

    public function testCliRequiresPositiveAccountIdBeforeDatabaseOrHttp(): void
    {
        $result = $this->runCli($this->realHttpEnvironment(), [
            '--account-id=0',
            '--period=2026-09-01',
            '--document-type=BILL',
            '--max-pages=5',
        ]);

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString('Billing C0 requires --account-id as a positive integer.', $result['stderr']);
        self::assertSame('', $result['stdout']);
    }

    public function testCliRequiresCanonicalPeriodBeforeDatabaseOrHttp(): void
    {
        $result = $this->runCli($this->realHttpEnvironment(), [
            '--account-id=1',
            '--period=2026-09',
            '--document-type=BILL',
            '--max-pages=5',
        ]);

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString('Billing C0 requires --period=YYYY-MM-01.', $result['stderr']);
        self::assertSame('', $result['stdout']);
    }

    public function testCliRequiresSupportedDocumentTypeBeforeDatabaseOrHttp(): void
    {
        $result = $this->runCli($this->realHttpEnvironment(), [
            '--account-id=1',
            '--period=2026-09-01',
            '--document-type=INVOICE',
            '--max-pages=5',
        ]);

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString('Billing C0 requires --document-type=BILL|CREDIT_NOTE.', $result['stderr']);
        self::assertSame('', $result['stdout']);
    }

    public function testCliRequiresBoundedMaxPagesBeforeDatabaseOrHttp(): void
    {
        $result = $this->runCli($this->realHttpEnvironment(), [
            '--account-id=1',
            '--period=2026-09-01',
            '--document-type=BILL',
            '--max-pages=21',
        ]);

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString('Billing C0 requires --max-pages between 1 and 20.', $result['stderr']);
        self::assertSame('', $result['stdout']);
    }

    /** @return array<string,string> */
    private function realHttpEnvironment(): array
    {
        return [
            'APP_ENV' => 'production',
            'BILLING_C0_REAL_HTTP' => '1',
        ];
    }

    /**
     * @param array<string,string> $environment
     * @param list<string>|null $arguments
     * @return array{exit_code:int,stdout:string,stderr:string}
     */
    private function runCli(array $environment, ?array $arguments = null): array
    {
        $root = dirname(__DIR__, 2);
        $command = [
            PHP_BINARY,
            $root . '/bin/billing-c0-smoke.php',
            ...($arguments ?? [
                '--account-id=1',
                '--period=2026-09-01',
                '--document-type=BILL',
                '--max-pages=5',
            ]),
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
