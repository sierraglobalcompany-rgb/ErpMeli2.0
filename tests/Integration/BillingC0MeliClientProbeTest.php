<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Billing\C0\BillingC0Probe;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class BillingC0MeliClientProbeTest extends TestCase
{
    public function testProbeComposesExactReadOnlyBillingRequestAndSequentialCursor(): void
    {
        self::assertTrue(
            method_exists(BillingC0Probe::class, 'forMeliClient'),
            'Billing C0 probe must expose one minimal MeliClient composition boundary.',
        );

        $pdo = TestDatabase::reset();
        $transport = new BillingC0SequenceTransport([
            new MeliTransportResponse(
                200,
                [],
                '{"results":[{"id":9007199254740993}],"last_id":"41"}',
            ),
            new MeliTransportResponse(
                200,
                [],
                '{"results":[],"last_id":"41"}',
            ),
        ]);
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string,preserve_numbers?:bool}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );

        /** @var BillingC0Probe $probe */
        $probe = BillingC0Probe::forMeliClient(
            $client,
            'secret-access-token',
            7,
            11,
            '2026-09-01',
            'BILL',
        );
        $evidence = $probe->run(5);

        self::assertSame('empty_results', $evidence['stop_reason']);
        self::assertFalse($evidence['partial_observed']);
        self::assertFalse($evidence['non_progress_observed']);
        self::assertCount(2, $evidence['pages']);
        self::assertSame('0', $evidence['pages'][0]['cursor_in']);
        self::assertSame('41', $evidence['pages'][1]['cursor_in']);
        self::assertSame(1, $evidence['pages'][0]['results_count']);
        self::assertSame(0, $evidence['pages'][1]['results_count']);
        self::assertSame(['last_id', 'results'], $evidence['pages'][0]['top_level_keys']);

        self::assertCount(2, $transport->requests);
        self::assertSame(
            'https://api.mercadolibre.com/billing/integration/periods/key/2026-09-01/group/ML/details'
            . '?document_type=BILL&limit=1000&from_id=0&sort_by=ID&order_by=ASC',
            $transport->requests[0]['url'],
        );
        self::assertSame(
            'https://api.mercadolibre.com/billing/integration/periods/key/2026-09-01/group/ML/details'
            . '?document_type=BILL&limit=1000&from_id=41&sort_by=ID&order_by=ASC',
            $transport->requests[1]['url'],
        );
        self::assertSame('Bearer secret-access-token', $transport->requests[0]['headers']['Authorization']);

        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM api_usage_daily')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM meli_cooldowns')->fetchColumn());

        $serializedEvidence = json_encode($evidence, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('secret-access-token', $serializedEvidence);
        self::assertStringNotContainsString('9007199254740993', $serializedEvidence);
    }
}

final class BillingC0SequenceTransport implements MeliTransport
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
            throw new RuntimeException('Billing C0 fake transport ran out of responses.');
        }

        return $response;
    }
}
