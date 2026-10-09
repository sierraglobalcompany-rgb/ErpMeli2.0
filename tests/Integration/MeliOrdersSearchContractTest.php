<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class MeliOrdersSearchContractTest extends TestCase
{
    public function testRegistryDeclaresSellerOrderSearchReadOperation(): void
    {
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        self::assertArrayHasKey('orders.search', $operations);
        self::assertSame('GET', $operations['orders.search']['method']);
        self::assertSame('/orders/search', $operations['orders.search']['path']);
        self::assertSame('orders', $operations['orders.search']['family']);
        self::assertSame('READ', $operations['orders.search']['classification']);
    }

    public function testSellerOrderSearchBuildsBoundedDateFilteredPage(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new OrdersSearchRecordingTransport();
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
            'orders.search',
            'sales-access-token',
            scopeKey: 'company:1:account:1',
            queryParams: [
                'seller' => '99887766',
                'order.date_created.from' => '2026-10-07T00:00:00.000-05:00',
                'order.date_created.to' => '2026-10-08T23:59:59.999-05:00',
                'sort' => 'date_asc',
                'offset' => 50,
                'limit' => 50,
            ],
        );

        self::assertCount(1, $transport->requests);
        self::assertSame('GET', $transport->requests[0]['method']);
        self::assertSame(
            'https://api.mercadolibre.com/orders/search?seller=99887766'
            . '&order.date_created.from=2026-10-07T00%3A00%3A00.000-05%3A00'
            . '&order.date_created.to=2026-10-08T23%3A59%3A59.999-05%3A00'
            . '&sort=date_asc&offset=50&limit=50',
            $transport->requests[0]['url'],
        );
        self::assertSame('Bearer sales-access-token', $transport->requests[0]['headers']['Authorization']);
    }
}

final class OrdersSearchRecordingTransport implements MeliTransport
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

        return new MeliTransportResponse(200, [], '{"paging":{"total":0,"offset":50,"limit":50},"results":[]}');
    }
}
