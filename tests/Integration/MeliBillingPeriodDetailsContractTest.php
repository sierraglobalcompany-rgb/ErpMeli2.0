<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class MeliBillingPeriodDetailsContractTest extends TestCase
{
    public function testRegistryDeclaresExactBillingPeriodDetailsReadOperation(): void
    {
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';

        self::assertArrayHasKey('billing.period.details', $operations);
        self::assertSame('GET', $operations['billing.period.details']['method']);
        self::assertSame(
            '/billing/integration/periods/key/{period_key}/group/ML/details',
            $operations['billing.period.details']['path'],
        );
        self::assertSame('billing', $operations['billing.period.details']['family']);
        self::assertSame('READ', $operations['billing.period.details']['classification']);
        self::assertSame('2026-10-09', $operations['billing.period.details']['verified_at']);
    }

    public function testBillingPeriodRequestUsesPeriodFirstCursorContract(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new BillingPeriodRecordingTransport();
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        self::assertArrayHasKey('billing.period.details', $operations);

        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );

        $client->request(
            'billing.period.details',
            'billing-access-token',
            pathParams: ['period_key' => '2026-10-01'],
            queryParams: [
                'document_type' => 'BILL',
                'limit' => 1000,
                'from_id' => '0',
                'sort_by' => 'ID',
                'order_by' => 'ASC',
            ],
            scopeKey: 'company:1:account:1',
        );

        self::assertCount(1, $transport->requests);
        self::assertSame('GET', $transport->requests[0]['method']);
        self::assertSame(
            'https://api.mercadolibre.com/billing/integration/periods/key/2026-10-01/group/ML/details'
            . '?document_type=BILL&limit=1000&from_id=0&sort_by=ID&order_by=ASC',
            $transport->requests[0]['url'],
        );
        self::assertSame('Bearer billing-access-token', $transport->requests[0]['headers']['Authorization']);
    }

    public function testBillingPeriodKeyRejectsMalformedOrNonFirstDayInputBeforeTransport(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new BillingPeriodRecordingTransport();
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        self::assertArrayHasKey('billing.period.details', $operations);

        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );

        foreach (['../2026-10-01', '2026-02-30', '2026-10-02', '2026-1-01'] as $invalid) {
            try {
                $client->request(
                    'billing.period.details',
                    'billing-access-token',
                    pathParams: ['period_key' => $invalid],
                );
                self::fail('Invalid Billing period keys must be rejected before transport: ' . $invalid);
            } catch (InvalidArgumentException $exception) {
                self::assertSame('Invalid Mercado Libre period_key path parameter.', $exception->getMessage());
            }
        }

        self::assertCount(0, $transport->requests);
    }

    public function testBillingPeriodPathRejectsUnexpectedPathParametersBeforeTransport(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new BillingPeriodRecordingTransport();
        /** @var array<string,array<string,string>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        self::assertArrayHasKey('billing.period.details', $operations);

        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );

        try {
            $client->request(
                'billing.period.details',
                'billing-access-token',
                pathParams: [
                    'period_key' => '2026-10-01',
                    'other' => 'unexpected',
                ],
            );
            self::fail('Unexpected Billing path parameters must be rejected before transport.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Unexpected Mercado Libre path parameters.', $exception->getMessage());
        }

        self::assertCount(0, $transport->requests);
    }
}

final class BillingPeriodRecordingTransport implements MeliTransport
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

        return new MeliTransportResponse(200, [], '{"results":[],"last_id":"0"}');
    }
}
