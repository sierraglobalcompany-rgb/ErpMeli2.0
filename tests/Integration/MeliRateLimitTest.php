<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Integrations\MercadoLibre\Client\MeliRateLimitException;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class MeliRateLimitTest extends TestCase
{
    public function testRetryAfterCreatesCooldownAndDoesNotRetryPhysicalHttp(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RateLimitQueueTransport([
            new MeliTransportResponse(429, ['retry-after' => '20', 'x-request-id' => 'req-429'], '{"error":"rate_limit"}'),
            new MeliTransportResponse(200, [], '{"id":1}'),
        ]);
        $client = $this->client($pdo, $transport);
        $before = new DateTimeImmutable('now UTC');

        try {
            $client->request('users.me', 'token', scopeKey: 'account:7');
            self::fail('429 must produce a rate-limit exception.');
        } catch (MeliRateLimitException $exception) {
            $after = new DateTimeImmutable('now UTC');
            self::assertSame(429, $exception->status);
            self::assertSame('req-429', $exception->requestId);
            self::assertGreaterThanOrEqual($before->modify('+20 seconds')->getTimestamp(), $exception->retryAt->getTimestamp());
            self::assertLessThanOrEqual($after->modify('+22 seconds')->getTimestamp(), $exception->retryAt->getTimestamp());
        }

        self::assertCount(1, $transport->requests, 'MeliClient must not retry a 429 inline.');

        try {
            $client->request('users.me', 'token', scopeKey: 'account:7');
            self::fail('An active cooldown must block before physical HTTP.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame('Mercado Libre operation is cooling down.', $exception->getMessage());
        }
        self::assertCount(1, $transport->requests);
    }

    public function testMissingRetryAfterUsesBoundedBackoffWithJitter(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RateLimitQueueTransport([
            new MeliTransportResponse(429, [], '{}'),
        ]);
        $client = $this->client($pdo, $transport);
        $before = new DateTimeImmutable('now UTC');

        try {
            $client->request('users.me', 'token', scopeKey: 'account:7');
            self::fail('429 must produce a rate-limit exception.');
        } catch (MeliRateLimitException $exception) {
            $after = new DateTimeImmutable('now UTC');
            self::assertGreaterThanOrEqual(
                $before->modify('+15 seconds')->getTimestamp(),
                $exception->retryAt->getTimestamp(),
            );
            self::assertLessThanOrEqual(
                $after->modify('+17 seconds')->getTimestamp(),
                $exception->retryAt->getTimestamp(),
            );
        }

        self::assertCount(1, $transport->requests);
    }

    public function testCooldownIsFamilyScopedInsteadOfFreezingAllMercadoLibreTraffic(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RateLimitQueueTransport([
            new MeliTransportResponse(200, [], '{"ok":true}'),
        ]);
        $cooldowns = new MeliCooldownRepository($pdo);
        $cooldowns->blockUntil('app:billing', new DateTimeImmutable('+5 minutes UTC'), 1);

        $operations = $this->operations();
        $operations['billing.test'] = [
            'method' => 'GET',
            'path' => '/billing/test',
            'family' => 'billing',
            'classification' => 'READ',
            'official_doc_url' => 'https://developers.mercadolibre.com.co/',
            'verified_at' => '2026-10-08',
        ];
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            new ApiUsageRecorder($pdo),
            $cooldowns,
        );

        $response = $client->request('users.me', 'token', scopeKey: 'account:7');
        self::assertSame(200, $response->status);
        self::assertCount(1, $transport->requests, 'Billing cooldown must not block users family.');

        try {
            $client->request('billing.test', 'token', scopeKey: 'account:7');
            self::fail('Billing family should still be blocked.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame('Mercado Libre operation is cooling down.', $exception->getMessage());
        }
        self::assertCount(1, $transport->requests);
    }

    private function client(\PDO $pdo, RateLimitQueueTransport $transport): MeliClient
    {
        return new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $this->operations(),
            'https://api.mercadolibre.com',
            new ApiUsageRecorder($pdo),
            new MeliCooldownRepository($pdo),
        );
    }

    /** @return array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> */
    private function operations(): array
    {
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        return $operations;
    }
}

final class RateLimitQueueTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse> */
    private array $responses;

    /** @var list<string> */
    public array $requests = [];

    /** @param list<MeliTransportResponse> $responses */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $method . ' ' . $url;
        $response = array_shift($this->responses);
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('No fake response queued.');
        }
        return $response;
    }
}
