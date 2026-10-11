<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Billing\C0\BillingC0Runtime;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class BillingC0RuntimeTest extends TestCase
{
    private PDO $pdo;
    private TokenCipher $cipher;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->cipher = new TokenCipher(TestDatabase::config()->appKey);

        $this->pdo->exec("INSERT INTO companies (id, name, slug) VALUES (1, 'Billing C0', 'billing-c0')");
    }

    public function testConnectedAccountWithFreshTokenReturnsOnlyRequiredRuntimeData(): void
    {
        $this->seedAccount('connected');
        $this->seedToken('secret-access-token', '2026-10-11 01:00:00.000000');

        $runtime = new BillingC0Runtime($this->pdo, $this->cipher);
        $resolved = $runtime->resolve(1, new DateTimeImmutable('2026-10-10T19:00:00-05:00'));

        self::assertSame([
            'account_id' => 1,
            'company_id' => 1,
            'site_id' => 'MCO',
            'access_token' => 'secret-access-token',
            'expires_at' => '2026-10-11 01:00:00.000000',
        ], $resolved);
    }

    public function testProbeComposesExistingMeliClientReadOnlyWithInjectedTransport(): void
    {
        $this->seedAccount('connected');
        $this->seedToken('secret-access-token', '2026-10-11 01:00:00.000000');
        $beforeToken = $this->tokenSnapshot();
        $beforeStatus = $this->accountStatus();

        $transport = new BillingC0RuntimeTransport([
            new MeliTransportResponse(
                200,
                [],
                '{"results":[],"last_id":null}',
            ),
        ]);
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string,preserve_numbers?:bool}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        $runtime = new BillingC0Runtime($this->pdo, $this->cipher);
        $evidence = $runtime->probe(
            1,
            new DateTimeImmutable('2026-10-10T19:00:00-05:00'),
            $transport,
            $operations,
            '2026-09-01',
            'BILL',
            5,
        );

        self::assertSame('empty_results', $evidence['stop_reason']);
        self::assertFalse($evidence['partial_observed']);
        self::assertFalse($evidence['non_progress_observed']);
        self::assertCount(1, $evidence['pages']);
        self::assertSame('0', $evidence['pages'][0]['cursor_in']);
        self::assertSame(0, $evidence['pages'][0]['results_count']);

        self::assertCount(1, $transport->requests);
        self::assertSame('GET', $transport->requests[0]['method']);
        self::assertSame(
            'https://api.mercadolibre.com/billing/integration/periods/key/2026-09-01/group/ML/details'
            . '?document_type=BILL&limit=1000&from_id=0&sort_by=ID&order_by=ASC',
            $transport->requests[0]['url'],
        );
        self::assertSame('Bearer secret-access-token', $transport->requests[0]['headers']['Authorization']);

        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM meli_cooldowns')->fetchColumn());
        self::assertSame($beforeToken, $this->tokenSnapshot());
        self::assertSame($beforeStatus, $this->accountStatus());

        $serializedEvidence = json_encode($evidence, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('secret-access-token', $serializedEvidence);
        self::assertStringNotContainsString('secret-refresh-token', $serializedEvidence);
    }

    public function testMissingAccountFailsClosed(): void
    {
        $runtime = new BillingC0Runtime($this->pdo, $this->cipher);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Billing C0 Mercado Libre account/token is unavailable.');

        $runtime->resolve(999, new DateTimeImmutable('2026-10-10T19:00:00-05:00'));
    }

    public function testNonConnectedAccountFailsClosed(): void
    {
        $this->seedAccount('reauth_required');
        $this->seedToken('secret-access-token', '2026-10-11 01:00:00.000000');

        $runtime = new BillingC0Runtime($this->pdo, $this->cipher);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Billing C0 requires a connected Mercado Libre account.');

        $runtime->resolve(1, new DateTimeImmutable('2026-10-10T19:00:00-05:00'));
    }

    public function testExpiredOrNearExpiryTokenFailsWithoutMutationOrRefresh(): void
    {
        $this->seedAccount('connected');
        $this->seedToken('secret-access-token', '2026-10-11 00:00:30.000000');

        $before = $this->tokenSnapshot();
        $runtime = new BillingC0Runtime($this->pdo, $this->cipher);

        try {
            $runtime->resolve(1, new DateTimeImmutable('2026-10-10T19:00:00-05:00'));
            self::fail('Expired or near-expiry C0 tokens must fail closed without refresh.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'Billing C0 requires a stored access token valid for more than 60 seconds; OAuth refresh is disabled.',
                $exception->getMessage(),
            );
        }

        self::assertSame($before, $this->tokenSnapshot());
        self::assertSame('connected', $this->accountStatus());
    }

    private function seedAccount(string $status): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO meli_accounts (id, company_id, external_user_id, site_id, nickname, status) '
            . 'VALUES (1, 1, :external_user_id, :site_id, :nickname, :status)'
        );
        $statement->execute([
            'external_user_id' => '123456789',
            'site_id' => 'MCO',
            'nickname' => 'billing-c0-test',
            'status' => $status,
        ]);
    }

    private function seedToken(string $accessToken, string $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO meli_tokens '
            . '(account_id, access_token_cipher, refresh_token_cipher, expires_at, refresh_version) '
            . 'VALUES (1, :access_token_cipher, :refresh_token_cipher, :expires_at, 7)'
        );
        $statement->execute([
            'access_token_cipher' => $this->cipher->encrypt($accessToken),
            'refresh_token_cipher' => $this->cipher->encrypt('secret-refresh-token'),
            'expires_at' => $expiresAt,
        ]);
    }

    /** @return array{access_token_cipher:string,refresh_token_cipher:string,expires_at:string,refresh_version:int} */
    private function tokenSnapshot(): array
    {
        $row = $this->pdo->query(
            'SELECT access_token_cipher, refresh_token_cipher, expires_at, refresh_version '
            . 'FROM meli_tokens WHERE account_id = 1'
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return [
            'access_token_cipher' => (string) $row['access_token_cipher'],
            'refresh_token_cipher' => (string) $row['refresh_token_cipher'],
            'expires_at' => (string) $row['expires_at'],
            'refresh_version' => (int) $row['refresh_version'],
        ];
    }

    private function accountStatus(): string
    {
        return (string) $this->pdo->query('SELECT status FROM meli_accounts WHERE id = 1')->fetchColumn();
    }
}

final class BillingC0RuntimeTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse> */
    private array $responses;

    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse> $responses */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        $response = array_shift($this->responses);
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('Billing C0 runtime fake transport ran out of responses.');
        }

        return $response;
    }
}
