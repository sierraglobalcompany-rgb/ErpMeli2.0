<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Auth\OAuthConnectService;
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

final class OAuthConnectServiceTest extends TestCase
{
    private const CLIENT_ID = '123456789';
    private const CLIENT_SECRET = 'new-erp2-client-secret';
    private const REDIRECT_URI = 'https://erpmeli.bodegadigitalmedellin.com/oauth/mercadolibre/callback';

    public function testAuthorizationCodeExchangeUsesFormPkceAndPersistsEncryptedTokensUsingExpiresIn(): void
    {
        $pdo = TestDatabase::reset();
        $companyId = $this->seedCompany($pdo);
        $transport = new OAuthQueueTransport([
            new MeliTransportResponse(200, ['x-request-id' => 'token-req'], json_encode([
                'access_token' => 'APP_USR-access-secret',
                'refresh_token' => 'TG-refresh-secret',
                'expires_in' => 10800,
                'token_type' => 'bearer',
            ], JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, ['x-request-id' => 'me-req'], json_encode([
                'id' => 99887766,
                'site_id' => 'MCO',
                'nickname' => 'ERP2 SELLER',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $cipher = new TokenCipher('test-application-secret');
        $service = $this->service($pdo, $transport, $cipher);
        $issuedAt = new DateTimeImmutable('2026-10-08T18:00:00+00:00');

        $accountId = $service->connectAuthorizationCode(
            $companyId,
            'one-use-authorization-code',
            'pkce-code-verifier-1234567890123456789012345678901234567890123',
            $issuedAt,
        );

        self::assertCount(2, $transport->requests);

        $tokenRequest = $transport->requests[0];
        self::assertSame('POST', $tokenRequest['method']);
        self::assertSame('https://api.mercadolibre.com/oauth/token', $tokenRequest['url']);
        self::assertSame('application/x-www-form-urlencoded', $tokenRequest['headers']['Content-Type'] ?? null);
        self::assertArrayNotHasKey('Authorization', $tokenRequest['headers']);
        parse_str((string) $tokenRequest['body'], $form);
        self::assertSame('authorization_code', $form['grant_type'] ?? null);
        self::assertSame(self::CLIENT_ID, $form['client_id'] ?? null);
        self::assertSame(self::CLIENT_SECRET, $form['client_secret'] ?? null);
        self::assertSame('one-use-authorization-code', $form['code'] ?? null);
        self::assertSame(self::REDIRECT_URI, $form['redirect_uri'] ?? null);
        self::assertSame('pkce-code-verifier-1234567890123456789012345678901234567890123', $form['code_verifier'] ?? null);

        $meRequest = $transport->requests[1];
        self::assertSame('GET', $meRequest['method']);
        self::assertSame('https://api.mercadolibre.com/users/me', $meRequest['url']);
        self::assertSame('Bearer APP_USR-access-secret', $meRequest['headers']['Authorization'] ?? null);

        $account = $pdo->query('SELECT * FROM meli_accounts WHERE id = ' . (int) $accountId)->fetch();
        self::assertIsArray($account);
        self::assertSame((string) $companyId, (string) $account['company_id']);
        self::assertSame('99887766', $account['external_user_id']);
        self::assertSame('MCO', $account['site_id']);
        self::assertSame('ERP2 SELLER', $account['nickname']);
        self::assertSame('connected', $account['status']);

        $tokens = $pdo->query('SELECT * FROM meli_tokens WHERE account_id = ' . (int) $accountId)->fetch();
        self::assertIsArray($tokens);
        self::assertNotSame('APP_USR-access-secret', $tokens['access_token_cipher']);
        self::assertNotSame('TG-refresh-secret', $tokens['refresh_token_cipher']);
        self::assertSame('APP_USR-access-secret', $cipher->decrypt((string) $tokens['access_token_cipher']));
        self::assertSame('TG-refresh-secret', $cipher->decrypt((string) $tokens['refresh_token_cipher']));
        self::assertSame('2026-10-08 21:00:00.000000', $tokens['expires_at']);
        self::assertSame(0, (int) $tokens['refresh_version']);
    }

    public function testDifferentExpiresInValueIsUsedInsteadOfHardcodedLifetime(): void
    {
        $pdo = TestDatabase::reset();
        $companyId = $this->seedCompany($pdo);
        $transport = new OAuthQueueTransport([
            new MeliTransportResponse(200, [], json_encode([
                'access_token' => 'access',
                'refresh_token' => 'refresh',
                'expires_in' => 21600,
            ], JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode([
                'id' => 111,
                'site_id' => 'MCO',
                'nickname' => 'seller',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $service = $this->service($pdo, $transport, new TokenCipher('test-app-secret'));

        $accountId = $service->connectAuthorizationCode(
            $companyId,
            'code',
            'verifier',
            new DateTimeImmutable('2026-10-08T18:00:00+00:00'),
        );

        self::assertSame(
            '2026-10-09 00:00:00.000000',
            $pdo->query('SELECT expires_at FROM meli_tokens WHERE account_id=' . (int) $accountId)->fetchColumn(),
        );
    }

    public function testInvalidTokenResponseIsRejectedBeforeAccountPersistence(): void
    {
        $pdo = TestDatabase::reset();
        $companyId = $this->seedCompany($pdo);
        $transport = new OAuthQueueTransport([
            new MeliTransportResponse(200, [], '{"access_token":"only-access","expires_in":10800}'),
        ]);
        $service = $this->service($pdo, $transport, new TokenCipher('test-app-secret'));

        try {
            $service->connectAuthorizationCode(
                $companyId,
                'code',
                'verifier',
                new DateTimeImmutable('2026-10-08T18:00:00+00:00'),
            );
            self::fail('Missing refresh_token must reject OAuth response.');
        } catch (RuntimeException $exception) {
            self::assertSame('Mercado Libre OAuth token response is incomplete.', $exception->getMessage());
        }

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM meli_accounts')->fetchColumn());
        self::assertCount(1, $transport->requests);
    }

    public function testAmbiguousAuthorizationCodeTransportFailureIsNotRetried(): void
    {
        $pdo = TestDatabase::reset();
        $companyId = $this->seedCompany($pdo);
        $transport = new OAuthQueueTransport([
            new RuntimeException('simulated ambiguous network outcome'),
            new MeliTransportResponse(200, [], '{"access_token":"should-never-be-used"}'),
        ]);
        $service = $this->service($pdo, $transport, new TokenCipher('test-app-secret'));

        try {
            $service->connectAuthorizationCode(
                $companyId,
                'one-use-code',
                'verifier',
                new DateTimeImmutable('2026-10-08T18:00:00+00:00'),
            );
            self::fail('Transport ambiguity must escape without retrying the authorization code.');
        } catch (RuntimeException $exception) {
            self::assertSame('simulated ambiguous network outcome', $exception->getMessage());
        }

        self::assertCount(1, $transport->requests);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM meli_accounts')->fetchColumn());
    }

    private function service(PDO $pdo, OAuthQueueTransport $transport, TokenCipher $cipher): OAuthConnectService
    {
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

        return new OAuthConnectService(
            $pdo,
            $client,
            $cipher,
            self::CLIENT_ID,
            self::CLIENT_SECRET,
            self::REDIRECT_URI,
        );
    }

    private function seedCompany(PDO $pdo): int
    {
        $pdo->exec("INSERT INTO companies(name,status) VALUES ('OAuth Company','active')");
        return (int) $pdo->lastInsertId();
    }
}

final class OAuthQueueTransport implements MeliTransport
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
            throw new RuntimeException('No OAuth fake response queued.');
        }
        return $next;
    }
}
