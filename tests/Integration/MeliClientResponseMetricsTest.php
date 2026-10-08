<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Integrations\MercadoLibre\Client\MeliApiException;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class MeliClientResponseMetricsTest extends TestCase
{
    public function testSuccessfulJsonResponseIsNormalizedAndOnePhysicalCallIsCounted(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new QueueTransport([
            new MeliTransportResponse(
                200,
                ['x-request-id' => 'req-success'],
                '{"id":123,"nickname":"seller"}',
            ),
        ]);
        $client = $this->client($pdo, $transport);

        $response = $client->request(
            'users.me',
            'token',
            scopeKey: 'account:7',
            resourceCount: 1,
        );

        self::assertSame(200, $response->status);
        self::assertSame('req-success', $response->requestId);
        self::assertSame(123, $response->data['id']);
        self::assertSame('seller', $response->data['nickname']);
        self::assertCount(1, $transport->requests);

        $usage = $pdo->query(
            "SELECT requests,resources,successes,client_errors,server_errors,rate_limited "
            . "FROM api_usage_daily WHERE scope_key='account:7' AND operation_key='users.me'"
        )->fetch();
        self::assertIsArray($usage);
        self::assertSame(1, (int) $usage['requests']);
        self::assertSame(1, (int) $usage['resources']);
        self::assertSame(1, (int) $usage['successes']);
        self::assertSame(0, (int) $usage['client_errors']);
        self::assertSame(0, (int) $usage['server_errors']);
        self::assertSame(0, (int) $usage['rate_limited']);
    }

    public function testClientErrorIsNormalizedAndCountedWithoutLeakingResponseBody(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new QueueTransport([
            new MeliTransportResponse(
                401,
                ['x-request-id' => 'req-401'],
                '{"message":"sensitive remote detail","error":"unauthorized"}',
            ),
        ]);
        $client = $this->client($pdo, $transport);

        try {
            $client->request('users.me', 'expired-token', scopeKey: 'account:7');
            self::fail('HTTP 401 must become a normalized API exception.');
        } catch (MeliApiException $exception) {
            self::assertSame(401, $exception->status);
            self::assertSame('req-401', $exception->requestId);
            self::assertSame('Mercado Libre request failed with HTTP 401.', $exception->getMessage());
            self::assertStringNotContainsString('sensitive remote detail', $exception->getMessage());
        }

        $usage = $pdo->query(
            "SELECT requests,client_errors FROM api_usage_daily "
            . "WHERE scope_key='account:7' AND operation_key='users.me'"
        )->fetch();
        self::assertIsArray($usage);
        self::assertSame(1, (int) $usage['requests']);
        self::assertSame(1, (int) $usage['client_errors']);
    }

    public function testServerErrorIsNormalizedAndCounted(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new QueueTransport([
            new MeliTransportResponse(503, ['x-request-id' => 'req-503'], '{"error":"unavailable"}'),
        ]);
        $client = $this->client($pdo, $transport);

        try {
            $client->request('users.me', 'token', scopeKey: 'account:7');
            self::fail('HTTP 503 must become a normalized API exception.');
        } catch (MeliApiException $exception) {
            self::assertSame(503, $exception->status);
        }

        self::assertSame(
            1,
            (int) $pdo->query(
                "SELECT server_errors FROM api_usage_daily "
                . "WHERE scope_key='account:7' AND operation_key='users.me'"
            )->fetchColumn(),
        );
    }

    public function testMalformedSuccessJsonFailsSafelyButStillCountsPhysicalRequest(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new QueueTransport([
            new MeliTransportResponse(200, ['x-request-id' => 'req-json'], '{not-json'),
        ]);
        $client = $this->client($pdo, $transport);

        try {
            $client->request('users.me', 'token', scopeKey: 'account:7');
            self::fail('Malformed JSON must not be returned as successful data.');
        } catch (RuntimeException $exception) {
            self::assertSame('Mercado Libre returned invalid JSON.', $exception->getMessage());
            self::assertStringNotContainsString('{not-json', $exception->getMessage());
        }

        self::assertSame(
            1,
            (int) $pdo->query(
                "SELECT requests FROM api_usage_daily "
                . "WHERE scope_key='account:7' AND operation_key='users.me'"
            )->fetchColumn(),
        );
    }

    private function client(\PDO $pdo, QueueTransport $transport): MeliClient
    {
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        return new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            new ApiUsageRecorder($pdo),
        );
    }
}

final class QueueTransport implements MeliTransport
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
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        $response = array_shift($this->responses);
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('No fake response queued.');
        }

        return $response;
    }
}
