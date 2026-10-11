<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Auth\TokenCipher;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class BillingC0CliRuntimeTest extends TestCase
{
    private PDO $pdo;
    private TokenCipher $cipher;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->cipher = new TokenCipher(TestDatabase::config()->appKey);

        $this->pdo->exec("INSERT INTO companies (id, name, slug) VALUES (1, 'Billing C0 CLI', 'billing-c0-cli')");
        $this->pdo->exec(
            "INSERT INTO meli_accounts (id, company_id, external_user_id, site_id, nickname, status) "
            . "VALUES (1, 1, '123456789', 'MCO', 'billing-c0-cli', 'connected')"
        );

        $expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('+1 hour')
            ->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'INSERT INTO meli_tokens '
            . '(account_id, access_token_cipher, refresh_token_cipher, expires_at, refresh_version) '
            . 'VALUES (1, :access_token_cipher, :refresh_token_cipher, :expires_at, 11)'
        );
        $statement->execute([
            'access_token_cipher' => $this->cipher->encrypt('secret-access-token'),
            'refresh_token_cipher' => $this->cipher->encrypt('secret-refresh-token'),
            'expires_at' => $expiresAt,
        ]);
    }

    public function testCliResolvesFreshStoredTokenReadOnlyAndStopsBeforeHttp(): void
    {
        $before = $this->runtimeSnapshot();
        $result = $this->runCli();

        self::assertNotSame(0, $result['exit_code']);
        self::assertStringContainsString(
            'Billing C0 account/token ready; HTTP smoke is not configured yet.',
            $result['stderr'],
        );
        self::assertSame('', $result['stdout']);
        self::assertStringNotContainsString('secret-access-token', $result['stderr']);
        self::assertStringNotContainsString('secret-refresh-token', $result['stderr']);
        self::assertStringNotContainsString('secret-access-token', $result['stdout']);
        self::assertStringNotContainsString('secret-refresh-token', $result['stdout']);
        self::assertSame($before, $this->runtimeSnapshot());
    }

    /** @return array{exit_code:int,stdout:string,stderr:string} */
    private function runCli(): array
    {
        $root = dirname(__DIR__, 2);
        $config = TestDatabase::config();
        $environment = [
            'APP_ENV' => 'production',
            'BILLING_C0_REAL_HTTP' => '1',
            'REAL_MELI_HTTP' => '0',
            'APP_URL' => 'http://localhost',
            'APP_KEY' => $config->appKey,
            'DB_HOST' => $config->dbHost,
            'DB_PORT' => (string) $config->dbPort,
            'DB_NAME' => $config->dbName,
            'DB_USER' => $config->dbUser,
            'DB_PASSWORD' => $config->dbPassword,
        ];
        $command = [
            PHP_BINARY,
            $root . '/bin/billing-c0-smoke.php',
            '--account-id=1',
            '--period=2026-09-01',
            '--document-type=BILL',
            '--max-pages=5',
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

    /** @return array{account_status:string,access_token_cipher:string,refresh_token_cipher:string,expires_at:string,refresh_version:int} */
    private function runtimeSnapshot(): array
    {
        $row = $this->pdo->query(
            'SELECT a.status AS account_status, t.access_token_cipher, t.refresh_token_cipher, '
            . 't.expires_at, t.refresh_version '
            . 'FROM meli_accounts a INNER JOIN meli_tokens t ON t.account_id = a.id WHERE a.id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return [
            'account_status' => (string) $row['account_status'],
            'access_token_cipher' => (string) $row['access_token_cipher'],
            'refresh_token_cipher' => (string) $row['refresh_token_cipher'],
            'expires_at' => (string) $row['expires_at'],
            'refresh_version' => (int) $row['refresh_version'],
        ];
    }
}
