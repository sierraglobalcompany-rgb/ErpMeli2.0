<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class MeliOrderGetContractTest extends TestCase
{
    public function testRegistryDeclaresExactOrderReadOperation(): void
    {
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        self::assertArrayHasKey('orders.get', $operations);
        self::assertSame('GET', $operations['orders.get']['method']);
        self::assertSame('/orders/{order_id}', $operations['orders.get']['path']);
        self::assertSame('orders', $operations['orders.get']['family']);
        self::assertSame('READ', $operations['orders.get']['classification']);
    }

    public function testExactOrderRequestResolvesOnlyTheOrderIdPathParameter(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new SalesOrderRecordingTransport();
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );

        $client->request(
            'orders.get',
            'sales-access-token',
            pathParams: ['order_id' => '200000000001'],
            scopeKey: 'company:1:account:1',
        );

        self::assertCount(1, $transport->requests);
        self::assertSame('GET', $transport->requests[0]['method']);
        self::assertSame('https://api.mercadolibre.com/orders/200000000001', $transport->requests[0]['url']);
        self::assertSame('Bearer sales-access-token', $transport->requests[0]['headers']['Authorization']);
    }

    public function testOrderIdPathParameterRejectsNonNumericInputBeforeTransport(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new SalesOrderRecordingTransport();
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );

        try {
            $client->request(
                'orders.get',
                'sales-access-token',
                pathParams: ['order_id' => '../users/me'],
            );
            self::fail('Invalid order IDs must be rejected before transport.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Invalid Mercado Libre order_id path parameter.', $exception->getMessage());
        }

        self::assertCount(0, $transport->requests);
    }
}

final class SalesOrderRecordingTransport implements MeliTransport
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

        return new MeliTransportResponse(200, [], '{"id":200000000001}');
    }
}
