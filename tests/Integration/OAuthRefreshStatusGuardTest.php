<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class OAuthRefreshStatusGuardTest extends TestCase
{
    public function testAttentionAccountNeverReusesRefreshTokenAutomatically(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('oauth-status-guard-test-key');
        $accountId = $this->seedAttentionAccount($pdo, $cipher);
        $transport = new OAuthStatusGuardTransport();

        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
        $service = new OAuthRefreshService(
            $pdo,
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $cipher,
            '123456789',
            'client-secret',
            'erp_meli2.oauth.account',
        );

        try {
            $service->getValidAccessToken(
                $accountId,
                new DateTimeImmutable('2026-10-09T01:30:00+00:00'),
            );
            self::fail('An attention account must require manual recovery before any refresh POST.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Mercado Libre account requires attention before token refresh.',
                $exception->getMessage(),
            );
        }

        self::assertCount(0, $transport->requests, 'No OAuth HTTP may be sent while account status is attention.');
        self::assertSame(
            0,
            (int) $pdo->query('SELECT refresh_version FROM meli_tokens WHERE account_id=' . $accountId)->fetchColumn(),
        );
    }

    private function seedAttentionAccount(PDO $pdo, TokenCipher $cipher): int
    {
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('OAuth Guard Company','oauth-guard-company')");
        $companyId = (int) $pdo->lastInsertId();

        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (:company_id,'99887767','MCO','Guard Seller','attention')"
        );
        $account->execute(['company_id' => $companyId]);
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (:account_id,:access_token,:refresh_token,:expires_at,0)'
        );
        $tokens->execute([
            'account_id' => $accountId,
            'access_token' => $cipher->encrypt('expired-access'),
            'refresh_token' => $cipher->encrypt('ambiguous-single-use-refresh'),
            'expires_at' => '2026-10-09 01:00:00.000000',
        ]);

        return $accountId;
    }
}

final class OAuthStatusGuardTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        throw new RuntimeException('OAuth transport must not be reached for attention accounts.');
    }
}
