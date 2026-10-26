<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Integrations\MercadoLibre\Client\MeliApiException;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliClientResponse;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Integrations\MercadoLibre\Client\MeliRateLimitException;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;
use Throwable;

final class MeliListingOperationContractTest extends TestCase
{
    public function testRegistryHasOnlyReadOnlyListingOperations(): void
    {
        $operations = $this->operations();

        self::assertArrayHasKey('users.items.search', $operations);
        self::assertArrayHasKey('items.bulk', $operations);

        self::assertSame('GET', $operations['users.items.search']['method']);
        self::assertSame('/users/{user_id}/items/search', $operations['users.items.search']['path']);
        self::assertSame('items', $operations['users.items.search']['family']);
        self::assertSame('READ', $operations['users.items.search']['classification']);

        self::assertSame('GET', $operations['items.bulk']['method']);
        self::assertSame('/items/bulk', $operations['items.bulk']['path']);
        self::assertSame('items', $operations['items.bulk']['family']);
        self::assertSame('READ', $operations['items.bulk']['classification']);
        self::assertTrue($operations['items.bulk']['preserve_numbers']);
    }

    public function testSellerSearchUsesTheRealClientAndFakeTransport(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new MeliListingOperationFakeTransport([
            new MeliTransportResponse(
                200,
                [],
                '{"seller_id":123456,"paging":{"limit":50,"offset":950,"total":1001},"results":["MCO123","MLA456"]}',
            ),
        ]);
        $client = $this->client($transport, $pdo);

        $response = $client->request(
            'users.items.search',
            'synthetic-access-token',
            pathParams: ['user_id' => '123456'],
            queryParams: ['offset' => 950, 'limit' => 50],
        );

        self::assertSame(200, $response->status);
        self::assertSame(['MCO123', 'MLA456'], $response->data['results']);
        self::assertSame(1, count($transport->requests));
        self::assertSame('GET', $transport->requests[0]['method']);
        self::assertSame(
            'https://api.mercadolibre.com/users/123456/items/search?offset=950&limit=50',
            $transport->requests[0]['url'],
        );
        self::assertSame('Bearer synthetic-access-token', $transport->requests[0]['headers']['Authorization']);
    }

    public function testSellerAndPaginationInputsFailBeforeTransportOrUsage(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new MeliListingOperationFakeTransport([]);
        $client = $this->client($transport, $pdo, new ApiUsageRecorder($pdo));
        $operations = $this->operations();
        self::assertArrayHasKey('users.items.search', $operations);

        foreach ([
            ['user_id' => ''],
            ['user_id' => '0'],
            ['user_id' => '00012'],
            ['user_id' => '../orders'],
            ['user_id' => '12/3'],
            ['user_id' => str_repeat('1', 33)],
            [],
            ['user_id' => '123456', 'site_id' => 'MCO'],
        ] as $pathParams) {
            $this->assertRejected($client, 'users.items.search', $pathParams, [], 'Invalid Mercado Libre seller user_id.');
        }

        foreach ([
            [['offset' => -1], 'Seller listing search page is out of bounds.'],
            [['limit' => 0], 'Seller listing search page is out of bounds.'],
            [['limit' => 51], 'Seller listing search page is out of bounds.'],
            [['offset' => 951, 'limit' => 50], 'Seller listing search page is out of bounds.'],
            [['offset' => '0'], 'Unsupported seller listing search filter.'],
            [['status' => 'active'], 'Unsupported seller listing search filter.'],
            [['search_type' => 'scan'], 'Unsupported seller listing search filter.'],
        ] as [$query, $message]) {
            $this->assertRejected($client, 'users.items.search', ['user_id' => '123456'], $query, $message);
        }

        self::assertSame([], $transport->requests);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
    }

    public function testBulkUsesTheRealClientParsesRootListAndFailsClosedPerItem(): void
    {
        $pdo = TestDatabase::reset();
        $body = <<<'JSON'
[
  {"id":"MCO123","status_code":200,"body":{"id":"MCO123","seller_id":123456,"price":129990.10,"title":"Owned item","currency_id":"COP","available_quantity":50}},
  {"id":"MCO456","status_code":404,"body":{"id":"MCO456","seller_id":123456,"price":1.00,"title":"Unavailable"}},
  {"id":"MCO654","status_code":500,"body":{"id":"MCO654","seller_id":123456,"price":9.99,"title":"Failed item"}},
  {"id":"MCO667","body":{"id":"MCO667","seller_id":123456,"price":8.88,"title":"Missing item status"}},
  {"id":"MCO789","status_code":200,"body":{"id":"MCO789","seller_id":999999,"price":999999.99,"title":"Foreign seller item"}},
  {"id":"MCO321","status_code":200,"body":{"id":"MCO322","seller_id":123456,"price":123.45,"title":"Mismatched body id"}},
  {"id":"MCO999","status_code":200,"body":{"id":"MCO999","seller_id":123456,"price":456.70,"title":"Unrequested item"}}
]
JSON;
        $transport = new MeliListingOperationFakeTransport([
            new MeliTransportResponse(200, [], $body),
        ]);
        $client = $this->client($transport, $pdo);

        $response = $client->request(
            'items.bulk',
            'synthetic-access-token',
            queryParams: ['ids' => 'MCO123,MCO456,MCO654,MCO667,MCO789,MCO321,MLA111'],
        );

        self::assertTrue(array_is_list($response->data));
        self::assertSame('129990.10', $response->data[0]['body']['price']);
        self::assertSame('123456', $response->data[0]['body']['seller_id']);
        self::assertSame('200', $response->data[0]['status_code']);

        $items = $response->sellerBulkReadState(
            '123456',
            ['MCO123', 'MCO456', 'MCO654', 'MCO667', 'MCO789', 'MCO321', 'MLA111'],
        );

        self::assertSame('VERIFIED', $items['MCO123']['read_state']);
        self::assertSame('129990.10', $items['MCO123']['price']);
        self::assertSame('50', $items['MCO123']['public_quantity_reference']);
        self::assertSame('COP', $items['MCO123']['currency_id']);

        foreach (['MCO456', 'MCO654', 'MCO667', 'MCO789', 'MCO321', 'MLA111'] as $unverifiedId) {
            self::assertSame('UNVERIFIED', $items[$unverifiedId]['read_state']);
            self::assertNull($items[$unverifiedId]['title']);
            self::assertNull($items[$unverifiedId]['price']);
        }
        self::assertArrayNotHasKey('MCO999', $items);

        self::assertSame(1, count($transport->requests));
        self::assertSame(
            'https://api.mercadolibre.com/items/bulk?ids=MCO123%2CMCO456%2CMCO654%2CMCO667%2CMCO789%2CMCO321%2CMLA111',
            $transport->requests[0]['url'],
        );
    }

    public function testBulkRejectsInvalidIdsAndExtraQueryBeforeTransportOrUsage(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new MeliListingOperationFakeTransport([]);
        $client = $this->client($transport, $pdo, new ApiUsageRecorder($pdo));
        $operations = $this->operations();
        self::assertArrayHasKey('items.bulk', $operations);

        $twentyOne = implode(',', array_map(
            static fn (int $i): string => 'MCO' . ($i + 1),
            range(0, 20),
        ));

        foreach ([
            [[], 'Item bulk requires only an ids query.'],
            [['ids' => ''], 'Invalid Mercado Libre item ID.'],
            [['ids' => 'MCO1,MCO1'], 'Item bulk requires 1–20 distinct item IDs.'],
            [['ids' => 'MCO1,,MCO2'], 'Invalid Mercado Libre item ID.'],
            [['ids' => 'MCO1,%2F'], 'Invalid Mercado Libre item ID.'],
            [['ids' => 'MCO1', 'attributes' => 'body.price'], 'Item bulk requires only an ids query.'],
            [['ids' => $twentyOne], 'Item bulk requires 1–20 distinct item IDs.'],
        ] as [$query, $message]) {
            $this->assertRejected($client, 'items.bulk', [], $query, $message);
        }

        self::assertSame([], $transport->requests);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
    }

    public function testBulkPartialDuplicateAndNon200ResponsesRemainUnverified(): void
    {
        $responses = [
            new MeliClientResponse(200, [
                ['id' => 'MCO123', 'status_code' => '200', 'body' => ['id' => 'MCO123', 'seller_id' => '123456', 'price' => '1.00']],
            ], null, 'partial'),
            new MeliClientResponse(200, [
                ['id' => 'MCO123', 'status_code' => '200', 'body' => ['id' => 'MCO123', 'seller_id' => '123456', 'price' => '1.00']],
                ['id' => 'MCO123', 'status_code' => '200', 'body' => ['id' => 'MCO123', 'seller_id' => '123456', 'price' => '999.00']],
            ], null),
            new MeliClientResponse(206, [
                ['id' => 'MCO123', 'status_code' => '200', 'body' => ['id' => 'MCO123', 'seller_id' => '123456', 'price' => '1.00']],
            ], null),
        ];

        foreach ($responses as $response) {
            $items = $response->sellerBulkReadState('123456', ['MCO123']);
            self::assertSame('UNVERIFIED', $items['MCO123']['read_state']);
            self::assertNull($items['MCO123']['title']);
            self::assertNull($items['MCO123']['price']);
        }
    }

    public function testItem429UsesExistingCooldownOnceAndDoesNotRetry(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new MeliListingOperationFakeTransport([
            new MeliTransportResponse(429, ['retry-after' => '60'], '{"error":"rate_limited"}'),
        ]);
        $client = $this->client(
            $transport,
            $pdo,
            new ApiUsageRecorder($pdo),
            new MeliCooldownRepository($pdo),
        );

        try {
            $client->request('items.bulk', queryParams: ['ids' => 'MCO123']);
            self::fail('A 429 must remain rate-limited.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame(429, $exception->status);
        }

        try {
            $client->request('items.bulk', queryParams: ['ids' => 'MCO123']);
            self::fail('The existing items-family cooldown must block the next request.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame(429, $exception->status);
        }

        self::assertCount(1, $transport->requests);
        self::assertSame(1, (int) $pdo->query(
            "SELECT COUNT(*) FROM meli_cooldowns WHERE cooldown_key = 'app:items'",
        )->fetchColumn());
        self::assertSame(1, (int) $pdo->query(
            "SELECT rate_limited FROM api_usage_daily WHERE operation_key = 'items.bulk'",
        )->fetchColumn());
    }

    public function testRealClientPropagatesFakeTransportFailureWithoutRetry(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new MeliListingOperationFakeTransport([
            new RuntimeException('synthetic transport failure'),
        ]);
        $client = $this->client($transport, $pdo);

        try {
            $client->request(
                'users.items.search',
                pathParams: ['user_id' => '123456'],
            );
            self::fail('The fake transport failure must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('synthetic transport failure', $exception->getMessage());
        }

        self::assertCount(1, $transport->requests);
    }

    public function testRemoteErrorDoesNotEchoAnAccessToken(): void
    {
        $pdo = TestDatabase::reset();
        $token = 'synthetic-access-token';
        $transport = new MeliListingOperationFakeTransport([
            new MeliTransportResponse(500, [], '{"error":"remote_failure","access_token":"synthetic-access-token"}'),
        ]);
        $client = $this->client($transport, $pdo);

        try {
            $client->request('items.bulk', $token, queryParams: ['ids' => 'MCO123']);
            self::fail('The fake HTTP 500 must surface as a sanitized API exception.');
        } catch (MeliApiException $exception) {
            self::assertSame('remote_failure', $exception->errorCode);
            self::assertStringNotContainsString($token, $exception->getMessage());
        }

        self::assertCount(1, $transport->requests);
    }

    private function client(
        MeliTransport $transport,
        \PDO $pdo,
        ?ApiUsageRecorder $usage = null,
        ?MeliCooldownRepository $cooldowns = null,
    ): MeliClient {
        /** @var array<string,array{
         *     method:string,path:string,family:string,classification:string,
         *     official_doc_url:string,verified_at:string,preserve_numbers?:bool
         * }> $operations
         */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        return new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            usageRecorder: $usage,
            cooldowns: $cooldowns,
            minRequestIntervalMs: 0,
        );
    }

    /** @return array<string,array<string,mixed>> */
    private function operations(): array
    {
        /** @var array<string,array<string,mixed>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        return $operations;
    }

    /**
     * @param array<string,string> $pathParams
     * @param array<string,string|int> $queryParams
     */
    private function assertRejected(
        MeliClient $client,
        string $operation,
        array $pathParams,
        array $queryParams,
        string $message,
    ): void {
        try {
            $client->request($operation, pathParams: $pathParams, queryParams: $queryParams);
            self::fail('Invalid seller listing query must be rejected before transport.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($message, $exception->getMessage());
        }
    }
}

final class MeliListingOperationFakeTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse|Throwable> */
    private array $responses;

    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse|Throwable> $responses */
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
        if ($response instanceof Throwable) {
            throw $response;
        }
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('Synthetic listing transport has no queued response.');
        }

        return $response;
    }
}
