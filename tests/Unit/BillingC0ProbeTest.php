<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\MercadoLibre\Client\MeliClientResponse;
use App\Modules\Billing\C0\BillingC0Probe;
use PHPUnit\Framework\TestCase;

final class BillingC0ProbeTest extends TestCase
{
    public function testFollowsLastIdSequentiallyAndStopsOnEmptyResults(): void
    {
        $responses = [
            new MeliClientResponse(200, [
                'results' => [['id' => '101'], ['id' => '102']],
                'last_id' => '102',
            ], null),
            new MeliClientResponse(200, [
                'results' => [],
                'last_id' => '102',
            ], null),
        ];
        $seen = [];

        $probe = new BillingC0Probe(function (string $cursor) use (&$responses, &$seen): MeliClientResponse {
            $seen[] = $cursor;
            $response = array_shift($responses);
            self::assertInstanceOf(MeliClientResponse::class, $response);
            return $response;
        });

        $result = $probe->run(5);

        self::assertSame(['0', '102'], $seen);
        self::assertSame('empty_results', $result['stop_reason']);
        self::assertFalse($result['partial_observed']);
        self::assertFalse($result['non_progress_observed']);
        self::assertCount(2, $result['pages']);
        self::assertSame(2, $result['pages'][0]['results_count']);
        self::assertSame('string', $result['pages'][0]['last_id_type']);
        self::assertSame('102', $result['pages'][0]['last_id']);
        self::assertSame(['last_id', 'results'], $result['pages'][0]['top_level_keys']);
    }

    public function testStopsOn206WithoutAdvancingCursor(): void
    {
        $seen = [];
        $probe = new BillingC0Probe(function (string $cursor) use (&$seen): MeliClientResponse {
            $seen[] = $cursor;
            return new MeliClientResponse(206, [
                'results' => [['id' => '201']],
                'last_id' => '201',
            ], null);
        });

        $result = $probe->run(5);

        self::assertSame(['0'], $seen);
        self::assertSame('partial_206', $result['stop_reason']);
        self::assertTrue($result['partial_observed']);
        self::assertFalse($result['non_progress_observed']);
        self::assertCount(1, $result['pages']);
    }

    public function testStopsWhenLastIdDoesNotProgress(): void
    {
        $seen = [];
        $probe = new BillingC0Probe(function (string $cursor) use (&$seen): MeliClientResponse {
            $seen[] = $cursor;
            return new MeliClientResponse(200, [
                'results' => [['id' => '301']],
                'last_id' => $cursor,
            ], null);
        });

        $result = $probe->run(5);

        self::assertSame(['0'], $seen);
        self::assertSame('non_progress', $result['stop_reason']);
        self::assertFalse($result['partial_observed']);
        self::assertTrue($result['non_progress_observed']);
        self::assertCount(1, $result['pages']);
    }

    public function testDoesNotExposeResultRowsInEvidence(): void
    {
        $probe = new BillingC0Probe(static fn (string $cursor): MeliClientResponse => new MeliClientResponse(200, [
            'results' => [[
                'id' => '401',
                'buyer_email' => 'private@example.com',
                'access_token' => 'never-output-this',
            ]],
            'last_id' => $cursor,
        ], null));

        $result = $probe->run(1);
        $encoded = json_encode($result, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('private@example.com', $encoded);
        self::assertStringNotContainsString('never-output-this', $encoded);
        self::assertStringNotContainsString('buyer_email', $encoded);
        self::assertStringNotContainsString('access_token', $encoded);
    }
}
