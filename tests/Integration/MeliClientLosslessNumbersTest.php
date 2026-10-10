<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class MeliClientLosslessNumbersTest extends TestCase
{
    public function testExactNumberOperationPreservesJsonNumberLexemesAndLeavesStringsUntouched(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new LosslessNumberTransport([
            new MeliTransportResponse(200, [], <<<'JSON'
{
  "large_decimal": 90071992547409.1234,
  "zero": 0,
  "scaled_zero": 0.00,
  "negative": -0.01,
  "exponent": 1e3,
  "small_exponent": 1.23e-4,
  "huge_integer": 123456789012345678901234567890,
  "numeric_text": "90071992547409.1234",
  "escaped_text": "value \"123\" and 45.6"
}
JSON),
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

        $response = $client->request(
            'orders.get',
            'token',
            pathParams: ['order_id' => '200000000999'],
        );

        self::assertSame('90071992547409.1234', $response->data['large_decimal']);
        self::assertSame('0', $response->data['zero']);
        self::assertSame('0.00', $response->data['scaled_zero']);
        self::assertSame('-0.01', $response->data['negative']);
        self::assertSame('1e3', $response->data['exponent']);
        self::assertSame('1.23e-4', $response->data['small_exponent']);
        self::assertSame('123456789012345678901234567890', $response->data['huge_integer']);
        self::assertSame('90071992547409.1234', $response->data['numeric_text']);
        self::assertSame('value "123" and 45.6', $response->data['escaped_text']);
    }

    public function testNormalOperationKeepsNativeJsonDecodeBehavior(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new LosslessNumberTransport([
            new MeliTransportResponse(200, [], '{"id":123,"ratio":1.5}'),
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

        $response = $client->request('users.me', 'token');

        self::assertSame(123, $response->data['id']);
        self::assertSame(1.5, $response->data['ratio']);
    }
}

final class LosslessNumberTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse> */
    private array $responses;

    /** @param list<MeliTransportResponse> $responses */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $response = array_shift($this->responses);
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('No lossless-number fake response queued.');
        }

        return $response;
    }
}
