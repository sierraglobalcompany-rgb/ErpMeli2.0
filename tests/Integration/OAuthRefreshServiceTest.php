<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class OAuthRefreshServiceTest extends TestCase
{
    private const CLIENT_ID = '123456789';
    private const CLIENT_SECRET = 'erp2-client-secret';
    private const LOCK_PREFIX = 'erp_meli2.oauth.account';

    public function testValidAccessTokenIsReturnedWithoutRefreshHttp(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('test-app-secret');
        $accountId = $this->seedAccount(
            $pdo,
            $cipher,
            'still-valid-access',
            'unused-refresh',
            '2026-10-08 20:00:00.000000',
        );
        $transport = new RefreshQueueTransport([]);
        $service = $this->service($pdo, Connection::fromConfig(TestDatabase::config()), $transport, $cipher);

        $token = $service->getValidAccessToken(
            $accountId,
            new DateTimeImmutable('2026-10-08T18:00:00+00:00'),
        );

        self::assertSame('still-valid-access', $token);
        self::assertCount(0, $transport->requests);
    }

    public function testExpiredTokenRefreshesOnceAndPersistsNewSingleUsePair(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('test-app-secret');
        $accountId = $this->seedAccount(
            $pdo,
            $cipher,
            'expired-access',
            'old-refresh-one-use',
            '2026-10-08 17:59:00.000000',
        );
        $transport = new RefreshQueueTransport([
            new MeliTransportResponse(200, ['x-request-id' => 'refresh-1'], json_encode([
                'access_token' => 'new-access',
                'refresh_token' => 'new-refresh-one-use',
                'expires_in' => 10800,
            ], JSON_THROW_ON_ERROR)),
        ]);
        $lockConnection = Connection::fromConfig(TestDatabase::config());
        $service = $this->service($pdo, $lockConnection, $transport, $cipher);

        $token = $service->getValidAccessToken(
            $accountId,
            new DateTimeImmutable('2026-10-08T18:00:00+00:00'),
        );

        self::assertSame('new-access', $token);
        self::assertCount(1, $transport->requests);
        $request = $transport->requests[0];
        self::assertSame('POST', $request['method']);
        self::assertSame('https://api.mercadolibre.com/oauth/token', $request['url']);
        self::assertSame('application/x-www-form-urlencoded', $request['headers']['Content-Type'] ?? null);
        self::assertArrayNotHasKey('Authorization', $request['headers']);
        parse_str((string) $request['body'], $form);
        self::assertSame('refresh_token', $form['grant_type'] ?? null);
        self::assertSame(self::CLIENT_ID, $form['client_id'] ?? null);
        self::assertSame(self::CLIENT_SECRET, $form['client_secret'] ?? null);
        self::assertSame('old-refresh-one-use', $form['refresh_token'] ?? null);

        $stored = $pdo->query('SELECT * FROM meli_tokens WHERE account_id=' . $accountId)->fetch();
        self::assertIsArray($stored);
        self::assertSame('new-access', $cipher->decrypt((string) $stored['access_token_cipher']));
        self::assertSame('new-refresh-one-use', $cipher->decrypt((string) $stored['refresh_token_cipher']));
        self::assertSame('2026-10-08 21:00:00.000000', $stored['expires_at']);
        self::assertSame(1, (int) $stored['refresh_version']);
        self::assertSame(
            'connected',
            $pdo->query('SELECT status FROM meli_accounts WHERE id=' . $accountId)->fetchColumn(),
        );

        $probe = Connection::fromConfig(TestDatabase::config());
        self::assertTrue($this->acquireProbeLock($probe, $this->lockName($accountId)), 'Refresh lock must be released on the same connection after success.');
        $this->releaseProbeLock($probe, $this->lockName($accountId));
    }

    public function testSecondCallerRereadsPersistedTokenAndDoesNotReuseRefreshToken(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('test-app-secret');
        $accountId = $this->seedAccount(
            $pdo,
            $cipher,
            'expired-access',
            'single-use-refresh',
            '2026-10-08 17:00:00.000000',
        );
        $transport = new RefreshQueueTransport([
            new MeliTransportResponse(200, [], json_encode([
                'access_token' => 'fresh-access',
                'refresh_token' => 'fresh-refresh',
                'expires_in' => 21600,
            ], JSON_THROW_ON_ERROR)),
        ]);
        $now = new DateTimeImmutable('2026-10-08T18:00:00+00:00');

        $first = $this->service($pdo, Connection::fromConfig(TestDatabase::config()), $transport, $cipher);
        $second = $this->service($pdo, Connection::fromConfig(TestDatabase::config()), $transport, $cipher);

        self::assertSame('fresh-access', $first->getValidAccessToken($accountId, $now));
        self::assertSame('fresh-access', $second->getValidAccessToken($accountId, $now));
        self::assertCount(1, $transport->requests, 'The second caller must not consume the already-used refresh token.');
        self::assertSame(1, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=' . $accountId)->fetchColumn());
    }

    public function testBusyAccountRefreshLockBlocksBeforeHttp(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('test-app-secret');
        $accountId = $this->seedAccount(
            $pdo,
            $cipher,
            'expired-access',
            'refresh',
            '2026-10-08 17:00:00.000000',
        );
        $owner = Connection::fromConfig(TestDatabase::config());
        self::assertTrue($this->acquireProbeLock($owner, $this->lockName($accountId)));

        $transport = new RefreshQueueTransport([
            new MeliTransportResponse(200, [], '{}'),
        ]);
        $service = $this->service($pdo, Connection::fromConfig(TestDatabase::config()), $transport, $cipher);

        try {
            $service->getValidAccessToken($accountId, new DateTimeImmutable('2026-10-08T18:00:00+00:00'));
            self::fail('A busy per-account refresh lock must stop before HTTP.');
        } catch (RuntimeException $exception) {
            self::assertSame('Mercado Libre OAuth refresh is already in progress.', $exception->getMessage());
        }

        self::assertCount(0, $transport->requests);
        $this->releaseProbeLock($owner, $this->lockName($accountId));
    }

    public function testInvalidGrantMarksAccountForReauthorizationWithoutRetry(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('test-app-secret');
        $accountId = $this->seedAccount(
            $pdo,
            $cipher,
            'expired-access',
            'invalid-refresh',
            '2026-10-08 17:00:00.000000',
        );
        $transport = new RefreshQueueTransport([
            new MeliTransportResponse(400, ['x-request-id' => 'invalid-grant'], json_encode([
                'error' => 'invalid_grant',
                'error_description' => 'sensitive remote description',
            ], JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], '{"access_token":"must-not-retry"}'),
        ]);
        $service = $this->service($pdo, Connection::fromConfig(TestDatabase::config()), $transport, $cipher);

        try {
            $service->getValidAccessToken($accountId, new DateTimeImmutable('2026-10-08T18:00:00+00:00'));
            self::fail('invalid_grant must require reauthorization.');
        } catch (RuntimeException $exception) {
            self::assertSame('Mercado Libre authorization must be renewed.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive remote description', $exception->getMessage());
        }

        self::assertCount(1, $transport->requests);
        self::assertSame(
            'reauth_required',
            $pdo->query('SELECT status FROM meli_accounts WHERE id=' . $accountId)->fetchColumn(),
        );
        self::assertSame(0, (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=' . $accountId)->fetchColumn());
    }

    public function testAmbiguousRefreshTransportFailureMarksAttentionAndNeverRetries(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('test-app-secret');
        $accountId = $this->seedAccount(
            $pdo,
            $cipher,
            'expired-access',
            'ambiguous-refresh',
            '2026-10-08 17:00:00.000000',
        );
        $transport = new RefreshQueueTransport([
            new RuntimeException('simulated ambiguous refresh transport outcome'),
            new MeliTransportResponse(200, [], '{"access_token":"must-not-retry"}'),
        ]);
        $service = $this->service($pdo, Connection::fromConfig(TestDatabase::config()), $transport, $cipher);

        try {
            $service->getValidAccessToken($accountId, new DateTimeImmutable('2026-10-08T18:00:00+00:00'));
            self::fail('Ambiguous refresh outcome must escape without a second POST.');
        } catch (RuntimeException $exception) {
            self::assertSame('Mercado Libre OAuth refresh outcome is uncertain; reauthorization may be required.', $exception->getMessage());
        }

        self::assertCount(1, $transport->requests);
        self::assertSame(
            'attention',
            $pdo->query('SELECT status FROM meli_accounts WHERE id=' . $accountId)->fetchColumn(),
        );

        $stored = $pdo->query('SELECT * FROM meli_tokens WHERE account_id=' . $accountId)->fetch();
        self::assertIsArray($stored);
        self::assertSame('ambiguous-refresh', $cipher->decrypt((string) $stored['refresh_token_cipher']));
        self::assertSame(0, (int) $stored['refresh_version']);

        $probe = Connection::fromConfig(TestDatabase::config());
        self::assertTrue($this->acquireProbeLock($probe, $this->lockName($accountId)), 'Refresh lock must be released after ambiguous failure.');
        $this->releaseProbeLock($probe, $this->lockName($accountId));
    }

    private function service(
        PDO $pdo,
        PDO $lockConnection,
        RefreshQueueTransport $transport,
        TokenCipher $cipher,
    ): OAuthRefreshService {
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            new ApiUsageRecorder($pdo),
            new MeliCooldownRepository($pdo),
        );

        return new OAuthRefreshService(
            $pdo,
            $lockConnection,
            $client,
            $cipher,
            self::CLIENT_ID,
            self::CLIENT_SECRET,
            self::LOCK_PREFIX,
        );
    }

    private function seedAccount(
        PDO $pdo,
        TokenCipher $cipher,
        string $accessToken,
        string $refreshToken,
        string $expiresAt,
    ): int {
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Refresh Company','refresh-company')");
        $companyId = (int) $pdo->lastInsertId();
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (:company_id,'99887766','MCO','Refresh Seller','connected')"
        );
        $account->execute(['company_id' => $companyId]);
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (:account_id,:access_token,:refresh_token,:expires_at,0)'
        );
        $tokens->execute([
            'account_id' => $accountId,
            'access_token' => $cipher->encrypt($accessToken),
            'refresh_token' => $cipher->encrypt($refreshToken),
            'expires_at' => $expiresAt,
        ]);

        return $accountId;
    }

    private function lockName(int $accountId): string
    {
        return TestDatabase::config()->dbName . '.' . self::LOCK_PREFIX . '.' . $accountId;
    }

    private function acquireProbeLock(PDO $pdo, string $lockName): bool
    {
        $statement = $pdo->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $statement->execute(['lock_name' => $lockName]);
        return (int) $statement->fetchColumn() === 1;
    }

    private function releaseProbeLock(PDO $pdo, string $lockName): void
    {
        $statement = $pdo->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => $lockName]);
    }
}

final class RefreshQueueTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse|RuntimeException> */
    private array $queue;

    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse|RuntimeException> $queue */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $next = array_shift($this->queue);
        if ($next instanceof RuntimeException) {
            throw $next;
        }
        if (!$next instanceof MeliTransportResponse) {
            throw new RuntimeException('No refresh fake response queued.');
        }
        return $next;
    }
}
