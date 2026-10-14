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
use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class CanonicalSellerIdentityTest extends TestCase
{
    private const CLIENT_ID = 'canonical-seller-client';
    private const CLIENT_SECRET = 'canonical-seller-secret';
    private const REDIRECT_URI = 'https://example.test/oauth/mercadolibre/callback';

    public function testExternalSellerIdentityIsGloballyUniqueAcrossCompanies(): void
    {
        $pdo = TestDatabase::reset();
        [$companyA, $companyB] = $this->seedCompanies($pdo);
        $insert = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,nickname,status) VALUES(?,?,? ,?,'connected')"
        );
        $insert->execute([$companyA, '99887766', 'MCO', 'Seller A']);

        $this->expectException(PDOException::class);
        $insert->execute([$companyB, '99887766', 'MCO', 'Duplicate seller']);
    }

    public function testOAuthCannotMoveCanonicalSellerAcrossCompaniesOrReplaceOriginalTokens(): void
    {
        $pdo = TestDatabase::reset();
        [$companyA, $companyB] = $this->seedCompanies($pdo);
        $cipher = new TokenCipher('canonical-seller-test-key');

        $firstTransport = new CanonicalSellerQueueTransport($this->oauthResponses(
            'access-a',
            'refresh-a',
            '99887766',
            'SELLER CANONICAL',
        ));
        $accountId = $this->service($pdo, $firstTransport, $cipher)->connectAuthorizationCode(
            $companyA,
            'code-a',
            'verifier-a',
            new DateTimeImmutable('2026-10-14T00:00:00Z'),
        );

        $before = $pdo->query('SELECT * FROM meli_tokens WHERE account_id=' . $accountId)->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($before);
        self::assertSame('access-a', $cipher->decrypt((string) $before['access_token_cipher']));
        self::assertSame('refresh-a', $cipher->decrypt((string) $before['refresh_token_cipher']));

        $secondTransport = new CanonicalSellerQueueTransport($this->oauthResponses(
            'access-b',
            'refresh-b',
            '99887766',
            'SELLER CANONICAL',
        ));

        try {
            $this->service($pdo, $secondTransport, $cipher)->connectAuthorizationCode(
                $companyB,
                'code-b',
                'verifier-b',
                new DateTimeImmutable('2026-10-14T01:00:00Z'),
            );
            self::fail('The same Mercado Libre seller must not be linked to another Company.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'This Mercado Libre seller is already linked to another Company.',
                $exception->getMessage(),
            );
        }

        self::assertCount(2, $secondTransport->requests, 'Seller identity is known only after token + users.me.');
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM meli_accounts WHERE external_user_id='99887766'")->fetchColumn());
        self::assertSame($companyA, (int) $pdo->query("SELECT company_id FROM meli_accounts WHERE external_user_id='99887766'")->fetchColumn());

        $after = $pdo->query('SELECT * FROM meli_tokens WHERE account_id=' . $accountId)->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($after);
        self::assertSame($before['access_token_cipher'], $after['access_token_cipher']);
        self::assertSame($before['refresh_token_cipher'], $after['refresh_token_cipher']);
        self::assertSame('access-a', $cipher->decrypt((string) $after['access_token_cipher']));
        self::assertSame('refresh-a', $cipher->decrypt((string) $after['refresh_token_cipher']));
    }

    public function testReauthorizationInsideSameCompanyReusesCanonicalAccount(): void
    {
        $pdo = TestDatabase::reset();
        [$companyA] = $this->seedCompanies($pdo);
        $cipher = new TokenCipher('canonical-seller-test-key');

        $first = new CanonicalSellerQueueTransport($this->oauthResponses('access-1', 'refresh-1', '555', 'OLD'));
        $service = $this->service($pdo, $first, $cipher);
        $accountId = $service->connectAuthorizationCode(
            $companyA,
            'code-1',
            'verifier-1',
            new DateTimeImmutable('2026-10-14T00:00:00Z'),
        );

        $second = new CanonicalSellerQueueTransport($this->oauthResponses('access-2', 'refresh-2', '555', 'NEW'));
        $sameId = $this->service($pdo, $second, $cipher)->connectAuthorizationCode(
            $companyA,
            'code-2',
            'verifier-2',
            new DateTimeImmutable('2026-10-14T02:00:00Z'),
        );

        self::assertSame($accountId, $sameId);
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM meli_accounts WHERE external_user_id='555'")->fetchColumn());
        self::assertSame('NEW', $pdo->query('SELECT nickname FROM meli_accounts WHERE id=' . $accountId)->fetchColumn());
        $token = $pdo->query('SELECT * FROM meli_tokens WHERE account_id=' . $accountId)->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($token);
        self::assertSame('access-2', $cipher->decrypt((string) $token['access_token_cipher']));
        self::assertSame('refresh-2', $cipher->decrypt((string) $token['refresh_token_cipher']));
        self::assertSame(1, (int) $token['refresh_version']);
    }

    /** @return array{int,int} */
    private function seedCompanies(PDO $pdo): array
    {
        $pdo->exec("INSERT INTO companies(name,slug) VALUES('Company A','company-a'),('Company B','company-b')");
        $ids = $pdo->query('SELECT id FROM companies ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        return [(int) $ids[0], (int) $ids[1]];
    }

    /** @return list<MeliTransportResponse> */
    private function oauthResponses(string $access, string $refresh, string $sellerId, string $nickname): array
    {
        return [
            new MeliTransportResponse(200, [], json_encode([
                'access_token' => $access,
                'refresh_token' => $refresh,
                'expires_in' => 10800,
            ], JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode([
                'id' => $sellerId,
                'site_id' => 'MCO',
                'nickname' => $nickname,
            ], JSON_THROW_ON_ERROR)),
        ];
    }

    private function service(PDO $pdo, CanonicalSellerQueueTransport $transport, TokenCipher $cipher): OAuthConnectService
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
}

final class CanonicalSellerQueueTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse> */
    private array $queue;

    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse> $queue */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $response = array_shift($this->queue);
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('No canonical seller fake response queued.');
        }
        return $response;
    }
}
