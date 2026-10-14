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

final class OAuthReauthorizationTest extends TestCase
{
    public function testReauthorizationReusesExpectedCanonicalAccount(): void
    {
        $pdo = TestDatabase::reset();
        $companyId = $this->seedCompany($pdo);
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(101,{$companyId},'99887766','MCO','OLD SELLER','disabled')");

        $transport = new OAuthReauthorizationQueueTransport([
            new MeliTransportResponse(200, [], '{"access_token":"new-access","refresh_token":"new-refresh","expires_in":3600}'),
            new MeliTransportResponse(200, [], '{"id":99887766,"site_id":"MCO","nickname":"NEW SELLER"}'),
        ]);
        $cipher = new TokenCipher('test-reauthorization-secret');

        $accountId = $this->service($pdo, $transport, $cipher)->connectAuthorizationCode(
            $companyId,
            'reauthorization-code',
            'reauthorization-verifier',
            new DateTimeImmutable('2026-10-14T16:00:00Z'),
            101,
        );

        self::assertSame(101, $accountId);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM meli_accounts')->fetchColumn());
        $account = $pdo->query('SELECT external_user_id,nickname,status FROM meli_accounts WHERE id=101')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($account);
        self::assertSame('99887766', $account['external_user_id']);
        self::assertSame('NEW SELLER', $account['nickname']);
        self::assertSame('connected', $account['status']);

        $tokens = $pdo->query('SELECT access_token_cipher,refresh_token_cipher FROM meli_tokens WHERE account_id=101')->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($tokens);
        self::assertSame('new-access', $cipher->decrypt((string) $tokens['access_token_cipher']));
        self::assertSame('new-refresh', $cipher->decrypt((string) $tokens['refresh_token_cipher']));
    }

    public function testReauthorizationRejectsDifferentSellerWithoutCreatingOrMovingAccount(): void
    {
        $pdo = TestDatabase::reset();
        $companyId = $this->seedCompany($pdo);
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(101,{$companyId},'111','MCO','EXPECTED SELLER','disabled')");

        $transport = new OAuthReauthorizationQueueTransport([
            new MeliTransportResponse(200, [], '{"access_token":"wrong-access","refresh_token":"wrong-refresh","expires_in":3600}'),
            new MeliTransportResponse(200, [], '{"id":222,"site_id":"MCO","nickname":"DIFFERENT SELLER"}'),
        ]);

        try {
            $this->service($pdo, $transport, new TokenCipher('test-reauthorization-secret'))->connectAuthorizationCode(
                $companyId,
                'reauthorization-code',
                'reauthorization-verifier',
                new DateTimeImmutable('2026-10-14T16:00:00Z'),
                101,
            );
            self::fail('Reauthorization must not bind a different seller.');
        } catch (RuntimeException $exception) {
            self::assertSame('Mercado Libre reauthorization must match the selected canonical seller.', $exception->getMessage());
        }

        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM meli_accounts')->fetchColumn());
        self::assertSame('111', (string) $pdo->query('SELECT external_user_id FROM meli_accounts WHERE id=101')->fetchColumn());
        self::assertSame('disabled', (string) $pdo->query('SELECT status FROM meli_accounts WHERE id=101')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM meli_tokens')->fetchColumn());
    }

    private function service(PDO $pdo, OAuthReauthorizationQueueTransport $transport, TokenCipher $cipher): OAuthConnectService
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
            '123456789',
            'reauthorization-client-secret',
            'https://example.test/oauth/mercadolibre/callback',
        );
    }

    private function seedCompany(PDO $pdo): int
    {
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Reauthorization Company','reauthorization-company')");
        return (int) $pdo->lastInsertId();
    }
}

final class OAuthReauthorizationQueueTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse> */
    private array $queue;

    /** @param list<MeliTransportResponse> $queue */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $next = array_shift($this->queue);
        if (!$next instanceof MeliTransportResponse) {
            throw new RuntimeException('No OAuth reauthorization fake response queued.');
        }
        return $next;
    }
}
