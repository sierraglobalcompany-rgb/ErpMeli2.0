<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\MeliListingReadScope;
use App\Modules\Settings\SystemSettingsRepository;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class MeliListingReadScopeTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status,is_system_admin) VALUES(1,'admin','Admin','admin@example.test','test-only','active',0)");
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(10,'A','a'),(20,'B','b')");
        $this->pdo->exec("INSERT INTO company_users(user_id,company_id,role) VALUES(1,10,'admin')");
        $this->pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES (101,10,'1001','MCO','A','connected'),(102,10,'1002','MCO','B','disabled'),(103,10,'0005','MCO','Malformed seller','connected'),(104,10,'1004','bad','Malformed site','connected'),(201,20,'2001','MCO','Foreign','connected')");
        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 10;
        $_SESSION['role'] = 'admin';
    }

    public function testAdminReceivesOnlyPersistedConnectedSellerIdentity(): void
    {
        $seller = (new MeliListingReadScope($this->pdo))->connectedSeller(101);

        self::assertSame(
            ['account_id' => 101, 'company_id' => 10, 'seller_id' => '1001', 'site_id' => 'MCO'],
            $seller,
        );
        self::assertArrayNotHasKey('access_token', $seller);
    }

    public function testForeignDisabledMissingAndMalformedAccountsShareOneGenericError(): void
    {
        $messages = [];
        foreach ([0, -1, 102, 103, 104, 201, 999] as $accountId) {
            try {
                (new MeliListingReadScope($this->pdo))->connectedSeller($accountId);
                self::fail('Unauthorized account was accepted.');
            } catch (DomainException $exception) {
                $messages[] = $exception->getMessage();
            }
        }

        self::assertSame(array_fill(0, 7, 'Connected Mercado Libre account unavailable.'), $messages);
    }

    public function testViewerAndOperatorAreDeniedBeforeTokenOrTransportEvenWhenSessionSaysAdmin(): void
    {
        $statement = $this->pdo->prepare('UPDATE company_users SET role = ? WHERE user_id = 1 AND company_id = 10');
        foreach (['viewer', 'operator'] as $role) {
            $statement->execute([$role]);
            $_SESSION['role'] = 'admin';
            $tokenCalled = false;
            $transport = new MeliListingScopeFakeTransport([]);

            try {
                (new MeliListingReadScope($this->pdo))->readPage(
                    101,
                    0,
                    2,
                    $this->listingClient($transport),
                    static function (int $accountId) use (&$tokenCalled): string {
                        $tokenCalled = true;
                        return 'synthetic-only-token';
                    },
                );
                self::fail('A non-admin database membership was accepted.');
            } catch (DomainException) {
                self::assertFalse($tokenCalled);
                self::assertSame([], $transport->requests);
            }
        }
    }

    public function testInactiveUserAndRemovedMembershipAreDenied(): void
    {
        $this->pdo->exec("UPDATE users SET status='disabled' WHERE id=1");
        try {
            (new MeliListingReadScope($this->pdo))->connectedSeller(101);
            self::fail('Inactive user was accepted.');
        } catch (DomainException) {
            // Expected: active user is part of CompanyContext authorization.
        }

        $this->pdo->exec("UPDATE users SET status='active' WHERE id=1");
        $this->pdo->exec('DELETE FROM company_users WHERE user_id=1 AND company_id=10');
        $transport = new MeliListingScopeFakeTransport([]);
        $tokenCalled = false;
        try {
            (new MeliListingReadScope($this->pdo))->readPage(
                101,
                0,
                2,
                $this->listingClient($transport),
                static function (int $accountId) use (&$tokenCalled): string {
                    $tokenCalled = true;
                    return 'synthetic-only-token';
                },
            );
            self::fail('A removed membership was accepted.');
        } catch (DomainException) {
            self::assertFalse($tokenCalled);
            self::assertSame([], $transport->requests);
        }
    }

    public function testMalformedLocalPagingFailsBeforeAuthorizationTokenOrTransport(): void
    {
        foreach ([[-1, 1], [0, 0], [0, 21], [980, 21], [1000, 1], [PHP_INT_MAX, 1]] as [$offset, $limit]) {
            $tokenCalled = false;
            $transport = new MeliListingScopeFakeTransport([]);
            try {
                (new MeliListingReadScope($this->pdo))->readPage(
                    101,
                    $offset,
                    $limit,
                    $this->listingClient($transport),
                    static function (int $accountId) use (&$tokenCalled): string {
                        $tokenCalled = true;
                        return 'synthetic-only-token';
                    },
                );
                self::fail('Malformed local paging was accepted.');
            } catch (InvalidArgumentException) {
                self::assertFalse($tokenCalled);
                self::assertSame([], $transport->requests);
            }
        }
    }

    public function testForeignDisabledMissingAndMalformedAccountsFailBeforeTokenOrTransport(): void
    {
        foreach ([102, 103, 104, 201, 999] as $accountId) {
            $transport = new MeliListingScopeFakeTransport([]);
            $tokenCalled = false;
            try {
                (new MeliListingReadScope($this->pdo))->readPage(
                    $accountId,
                    0,
                    2,
                    $this->listingClient($transport),
                    static function (int $authorizedAccountId) use (&$tokenCalled): string {
                        $tokenCalled = true;
                        return 'synthetic-only-token';
                    },
                );
                self::fail('An unavailable connected account was accepted.');
            } catch (DomainException $exception) {
                self::assertSame('Connected Mercado Libre account unavailable.', $exception->getMessage());
                self::assertFalse($tokenCalled);
                self::assertSame([], $transport->requests);
            }
        }
    }
    public function testAuthorizedOfflinePageUsesOnlyDiscoveredIdsAndHidesForeignBulkData(): void
    {
        $before = $this->sideEffectCounts();
        $transport = new MeliListingScopeFakeTransport([
            new MeliTransportResponse(200, [], '{"seller_id":1001,"paging":{"offset":0,"limit":2,"total":3},"results":["MCO100","MCO200"]}'),
            new MeliTransportResponse(200, [], '[{"id":"MCO100","status_code":200,"body":{"id":"MCO100","seller_id":1001,"title":"Owned","price":1299.10,"currency_id":"COP","available_quantity":4}},{"id":"MCO200","status_code":200,"body":{"id":"MCO200","seller_id":2001,"title":"Foreign","price":5.0}}]'),
        ]);
        $tokenCalls = 0;
        $result = (new MeliListingReadScope($this->pdo))->readPage(
            101,
            0,
            2,
            $this->listingClient($transport),
            static function (int $accountId) use (&$tokenCalls): string {
                $tokenCalls++;
                self::assertSame(101, $accountId);
                return 'synthetic-only-token';
            },
        );

        self::assertSame(1, $tokenCalls);
        self::assertSame(101, $result['account_id']);
        self::assertSame('1001', $result['seller_id']);
        self::assertSame(0, $result['offset']);
        self::assertSame(2, $result['next_offset']);
        self::assertSame(['MCO100', 'MCO200'], array_column($result['rows'], 'id'));
        self::assertSame('VERIFIED', $result['rows'][0]['read_state']);
        self::assertSame('1299.10', $result['rows'][0]['price']);
        self::assertSame('4', $result['rows'][0]['public_quantity_reference']);
        self::assertSame('UNVERIFIED', $result['rows'][1]['read_state']);
        self::assertNull($result['rows'][1]['title']);
        self::assertNull($result['rows'][1]['price']);
        self::assertCount(2, $transport->requests);
        self::assertSame(
            'https://api.mercadolibre.com/users/1001/items/search?offset=0&limit=2',
            $transport->requests[0]['url'],
        );
        self::assertSame(
            'https://api.mercadolibre.com/items/bulk?ids=MCO100%2CMCO200',
            $transport->requests[1]['url'],
        );
        self::assertSame('Bearer synthetic-only-token', $transport->requests[0]['headers']['Authorization']);
        self::assertSame($before, $this->sideEffectCounts());
    }

    public function testVerifiedItemLeavesMissingOptionalFieldsUnknown(): void
    {
        $transport = new MeliListingScopeFakeTransport([
            new MeliTransportResponse(200, [], '{"seller_id":1001,"paging":{"offset":0,"limit":1,"total":1},"results":["MCO300"]}'),
            new MeliTransportResponse(200, [], '[{"id":"MCO300","status_code":200,"body":{"id":"MCO300","seller_id":1001}}]'),
        ]);
        $result = (new MeliListingReadScope($this->pdo))->readPage(
            101,
            0,
            1,
            $this->listingClient($transport),
            static fn (int $accountId): string => 'synthetic-only-token',
        );

        self::assertSame('VERIFIED', $result['rows'][0]['read_state']);
        self::assertNull($result['rows'][0]['title']);
        self::assertNull($result['rows'][0]['status']);
        self::assertNull($result['rows'][0]['price']);
        self::assertNull($result['rows'][0]['currency_id']);
        self::assertNull($result['rows'][0]['public_quantity_reference']);
        self::assertCount(2, $transport->requests);
    }
    public function testInvalidDiscoveryNeverRequestsBulk(): void
    {
        $validPage = ['seller_id' => 1001, 'paging' => ['offset' => 0, 'limit' => 2, 'total' => 2], 'results' => ['MCO100', 'MCO200']];
        $missingTotal = $validPage;
        unset($missingTotal['paging']['total']);
        $wrongOffset = $validPage;
        $wrongOffset['paging']['offset'] = 1;
        $wrongLimit = $validPage;
        $wrongLimit['paging']['limit'] = 3;
        $inconsistentCount = $validPage;
        $inconsistentCount['paging']['total'] = 3;
        $inconsistentCount['results'] = ['MCO100'];
        $associativeResults = $validPage;
        $associativeResults['results'] = ['first' => 'MCO100', 'second' => 'MCO200'];
        $invalidIdentity = $validPage;
        $invalidIdentity['results'] = ['not-an-item', 'MCO200'];
        $duplicates = $validPage;
        $duplicates['results'] = ['MCO100', 'MCO100'];

        $invalidResponses = [
            new MeliTransportResponse(206, [], json_encode($validPage, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, ['X-Content-Missing' => 'seller'], json_encode($validPage, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode(array_replace($validPage, ['seller_id' => 2001]), JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode($missingTotal, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode($wrongOffset, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode($wrongLimit, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode($inconsistentCount, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode($associativeResults, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode($invalidIdentity, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], json_encode($duplicates, JSON_THROW_ON_ERROR)),
            new MeliTransportResponse(200, [], '{invalid-json'),
        ];

        foreach ($invalidResponses as $response) {
            $transport = new MeliListingScopeFakeTransport([$response]);
            try {
                (new MeliListingReadScope($this->pdo))->readPage(
                    101,
                    0,
                    2,
                    $this->listingClient($transport),
                    static fn (int $accountId): string => 'synthetic-only-token',
                );
                self::fail('An inadmissible discovery response was accepted.');
            } catch (RuntimeException) {
                self::assertCount(1, $transport->requests);
            }
        }
    }

    public function testEmptyPageSkipsBulkAndDoesNotClaimInventoryCompleteness(): void
    {
        $transport = new MeliListingScopeFakeTransport([
            new MeliTransportResponse(200, [], '{"seller_id":"1001","paging":{"offset":2,"limit":2,"total":2},"results":[]}'),
        ]);
        $result = (new MeliListingReadScope($this->pdo))->readPage(
            101,
            2,
            2,
            $this->listingClient($transport),
            static fn (int $accountId): string => 'synthetic-only-token',
        );

        self::assertSame([], $result['rows']);
        self::assertNull($result['next_offset']);
        self::assertCount(1, $transport->requests);
    }

    public function testNextOffsetStopsAtExhaustionShortPageAndThousandBoundary(): void
    {
        $cases = [
            [0, 2, 2, ['MCO100', 'MCO200'], null],
            [2, 2, 3, ['MCO3'], null],
            [999, 1, 1500, ['MCO999'], null],
        ];

        foreach ($cases as [$offset, $limit, $total, $ids, $expectedNext]) {
            $page = json_encode([
                'seller_id' => 1001,
                'paging' => ['offset' => $offset, 'limit' => $limit, 'total' => $total],
                'results' => $ids,
            ], JSON_THROW_ON_ERROR);
            $transport = new MeliListingScopeFakeTransport([
                new MeliTransportResponse(200, [], $page),
                new MeliTransportResponse(200, [], '[]'),
            ]);
            $result = (new MeliListingReadScope($this->pdo))->readPage(
                101,
                $offset,
                $limit,
                $this->listingClient($transport),
                static fn (int $accountId): string => 'synthetic-only-token',
            );

            self::assertSame($expectedNext, $result['next_offset']);
        }
    }

    public function testBlankSyntheticTokenStopsBeforeTransport(): void
    {
        $transport = new MeliListingScopeFakeTransport([]);
        try {
            (new MeliListingReadScope($this->pdo))->readPage(
                101,
                0,
                2,
                $this->listingClient($transport),
                static fn (int $accountId): string => '  ',
            );
            self::fail('Blank token was accepted.');
        } catch (RuntimeException $exception) {
            self::assertSame('Seller authorization unavailable.', $exception->getMessage());
        }

        self::assertSame([], $transport->requests);
    }

    private function listingClient(MeliTransport $transport): MeliClient
    {
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string,preserve_numbers?:bool}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        return new MeliClient(
            $transport,
            new SystemSettingsRepository($this->pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
    }

    /** @return array{work_items:int,api_usage_daily:int} */
    private function sideEffectCounts(): array
    {
        return [
            'work_items' => (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn(),
            'api_usage_daily' => (int) $this->pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn(),
        ];
    }
}

final class MeliListingScopeFakeTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $response = array_shift($this->responses);
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('Synthetic transport queue exhausted.');
        }

        return $response;
    }
}