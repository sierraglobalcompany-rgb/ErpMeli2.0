<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\CurlMeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Integrations\MercadoLibre\Transport\RemoteHostPolicy;
use App\Modules\Settings\SystemSettingsRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class MeliClientBoundaryTest extends TestCase
{
    public function testOfficialOperationRegistryContainsOnlyInitialF3Contracts(): void
    {
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        self::assertSame(['oauth.token', 'users.me'], array_keys($operations));
        self::assertSame('POST', $operations['oauth.token']['method']);
        self::assertSame('/oauth/token', $operations['oauth.token']['path']);
        self::assertSame('AUTH', $operations['oauth.token']['classification']);
        self::assertSame('GET', $operations['users.me']['method']);
        self::assertSame('/users/me', $operations['users.me']['path']);
        self::assertSame('READ', $operations['users.me']['classification']);
    }

    public function testFakeTransportObservesExactlyOnePhysicalRequest(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RecordingTransport();
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
        );

        $response = $client->request('users.me', 'test-access-token');

        self::assertSame(200, $response->status);
        self::assertCount(1, $transport->requests);
        self::assertSame('GET', $transport->requests[0]['method']);
        self::assertSame('https://api.mercadolibre.com/users/me', $transport->requests[0]['url']);
        self::assertSame('Bearer test-access-token', $transport->requests[0]['headers']['Authorization']);
    }

    public function testPhysicalRequestsRespectConfiguredMinimumInterval(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new TimedRecordingTransport();
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 30,
        );

        $client->request('users.me', 'token-1');
        $client->request('users.me', 'token-2');

        self::assertCount(2, $transport->sentAtNs);
        $gapMs = ($transport->sentAtNs[1] - $transport->sentAtNs[0]) / 1_000_000;
        self::assertGreaterThanOrEqual(25.0, $gapMs, 'Physical Mercado Libre dispatches must be paced, not merely counted as jobs/batches.');
    }

    public function testUnknownOperationIsRejectedBeforeTransport(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RecordingTransport();
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
        );

        try {
            $client->request('does.not.exist', 'test-access-token');
            self::fail('Unknown Mercado Libre operations must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Unknown Mercado Libre operation: does.not.exist', $exception->getMessage());
        }

        self::assertCount(0, $transport->requests);
    }

    public function testWriteClassificationIsBlockedBeforeTransportWhenToggleIsOff(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RecordingTransport();
        $operations = [
            'test.write' => [
                'method' => 'POST',
                'path' => '/items/1',
                'family' => 'items',
                'classification' => 'WRITE',
                'official_doc_url' => 'https://developers.mercadolibre.com.co/',
                'verified_at' => '2026-10-08',
            ],
        ];
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
        );

        try {
            $client->request('test.write', 'test-access-token');
            self::fail('WRITE must be blocked while meli_writes_enabled is OFF.');
        } catch (RuntimeException $exception) {
            self::assertSame('Remote Mercado Libre writes are disabled.', $exception->getMessage());
        }

        self::assertCount(0, $transport->requests);
    }

    public function testCurlTransportBlocksRealMercadoLibreBeforeCurlInTestEnvironment(): void
    {
        $transport = new CurlMeliTransport(new RemoteHostPolicy(), 'test');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Real Mercado Libre HTTP is blocked in local/test.');

        $transport->send('GET', 'https://api.mercadolibre.com/users/me', [], null);
    }
}

final class RecordingTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        return new MeliTransportResponse(200, [], '{}');
    }
}

final class TimedRecordingTransport implements MeliTransport
{
    /** @var list<int> */
    public array $sentAtNs = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->sentAtNs[] = hrtime(true);
        return new MeliTransportResponse(200, [], '{}');
    }
}
