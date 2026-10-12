<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\MercadoLibre\Client\MeliClientResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MeliReadContractTest extends TestCase
{
    #[DataProvider('orderCases')]
    public function testOrderAdmissionPreservesUnknownFields(int $status, array $data, ?string $missing, bool $admitted, string $ownership, string $billing, string $shipping): void
    {
        $response = new MeliClientResponse($status, $data, null, $missing);
        $state = $response->orderReadState('10', '100');
        self::assertSame($admitted, $state['admitted']);
        self::assertSame($ownership, $state['ownership']);
        self::assertSame($billing, $state['billing']);
        self::assertSame($shipping, $state['shipping']);
        self::assertSame($data, $response->data, 'Normalization must not synthesize or mutate fields.');
    }

    public static function orderCases(): array
    {
        $complete = ['id' => '10', 'seller' => ['id' => '100'], 'buyer' => ['id' => '200', 'billing_info' => ['id' => '300']], 'shipping' => ['id' => '400']];
        $noSeller = $complete; unset($noSeller['seller']);
        $noBuyer = $complete; unset($noBuyer['buyer']);
        $noShipping = $complete; unset($noShipping['shipping']);
        $wrong = $complete; $wrong['seller']['id'] = '999';
        $badId = $complete; $badId['id'] = '11';
        $unknownSeller = $complete; $unknownSeller['seller']['id'] = 100.0;
        return [
            'complete matching' => [200, $complete, null, true, 'MATCH', 'UNKNOWN', 'AVAILABLE'],
            '206 missing seller' => [206, $noSeller, 'seller', false, 'UNKNOWN', 'UNKNOWN', 'AVAILABLE'],
            '206 missing buyer' => [206, $noBuyer, 'buyer', false, 'MATCH', 'UNAVAILABLE', 'AVAILABLE'],
            '206 missing shipping' => [206, $noShipping, 'shipping', false, 'MATCH', 'UNKNOWN', 'UNAVAILABLE'],
            '200 missing buyer is not fiscal complete' => [200, $noBuyer, null, true, 'MATCH', 'UNAVAILABLE', 'AVAILABLE'],
            '200 advertised partial' => [200, $complete, 'buyer', false, 'MATCH', 'UNKNOWN', 'AVAILABLE'],
            'mismatching seller' => [200, $wrong, null, false, 'MISMATCH', 'UNKNOWN', 'AVAILABLE'],
            'mismatching resource' => [200, $badId, null, false, 'MATCH', 'UNKNOWN', 'AVAILABLE'],
            'float seller is untrusted' => [200, $unknownSeller, null, false, 'UNKNOWN', 'UNKNOWN', 'AVAILABLE'],
        ];
    }

    public function testBillingReferenceDoesNotUseBuyerIdAsTaxIdentity(): void
    {
        $response = new MeliClientResponse(200, ['id' => '10', 'seller' => ['id' => '100'], 'buyer' => ['id' => '200', 'billing_info' => ['id' => '300']]], null);
        self::assertSame('300', $response->orderReadState('10', '100')['billing_info_id']);
        self::assertSame('UNKNOWN', $response->orderReadState('10', '100')['billing']);
    }

    public function testPackMembershipRetainsOnlyMatchingSellerOrders(): void
    {
        $pack = new MeliClientResponse(200, ['id' => '50', 'orders' => [['id' => '10'], ['id' => '11']]], null);
        $one = new MeliClientResponse(200, ['id' => '10', 'seller' => ['id' => '100']], null);
        $two = new MeliClientResponse(200, ['id' => '11', 'seller' => ['id' => '100']], null);
        self::assertSame(['pack_id' => '50', 'order_ids' => ['10', '11']], $pack->packMembership('50', '100', ['10' => $one, '11' => $two]));
        $foreign = new MeliClientResponse(200, ['id' => '11', 'seller' => ['id' => '999']], null);
        self::assertSame(['pack_id' => '50', 'order_ids' => ['10']], $pack->packMembership('50', '100', ['10' => $one, '11' => $foreign]));
    }

    #[DataProvider('invalidPackCases')]
    public function testPackRejectsUnresolvedOrInvalidMembership(array $packData, array $orders, int $status): void
    {
        $this->expectException(RuntimeException::class);
        (new MeliClientResponse($status, $packData, null))->packMembership('50', '100', $orders);
    }

    public static function invalidPackCases(): array
    {
        $order = new MeliClientResponse(200, ['id' => '10', 'seller' => ['id' => '100']], null);
        return [
            'missing member' => [['id' => '50', 'orders' => [['id' => '10']]], [], 200],
            'invalid ref' => [['id' => '50', 'orders' => [['id' => '../10']]], [], 200],
            'unknown seller' => [['id' => '50', 'orders' => [['id' => '10']]], ['10' => new MeliClientResponse(200, ['id' => '10'], null)], 200],
            'partial member' => [['id' => '50', 'orders' => [['id' => '10']]], ['10' => new MeliClientResponse(206, ['id' => '10', 'seller' => ['id' => '100']], null)], 200],
            'duplicate ref' => [['id' => '50', 'orders' => [['id' => '10'], ['id' => '10']]], ['10' => $order], 200],
            'wrong pack' => [['id' => '51', 'orders' => [['id' => '10']]], ['10' => $order], 200],
            'partial pack' => [['id' => '50', 'orders' => [['id' => '10']]], ['10' => $order], 206],
            'empty pack' => [['id' => '50', 'orders' => []], [], 200],
        ];
    }

    #[DataProvider('billingCases')]
    public function testBillingReadinessUsesOnlyProviderFiscalEnvelope(int $status, array $data, string $expected): void
    {
        self::assertSame($expected, (new MeliClientResponse($status, $data, null))->billingInfoReadiness('MCO', '100', '200'));
    }

    public static function billingCases(): array
    {
        $data = ['site_id' => 'MCO', 'seller' => ['cust_id' => '100'], 'buyer' => ['cust_id' => '200', 'billing_info' => [
            'name' => 'Synthetic buyer', 'identification' => ['type' => 'CC', 'number' => 'synthetic-tax-number'],
            'address' => ['street_name' => 'Synthetic street', 'street_number' => 'SN', 'city_name' => 'Synthetic city', 'state' => ['name' => 'Synthetic state'], 'country_id' => 'CO'],
        ]]];
        $null = $data; $null['buyer']['billing_info']['identification']['number'] = null;
        $missing = $data; unset($missing['buyer']['billing_info']);
        $shipping = $missing; $shipping['shipping'] = ['address' => $data['buyer']['billing_info']['address']];
        $wrongBuyer = $data; $wrongBuyer['buyer']['cust_id'] = '999';
        $wrongSeller = $data; $wrongSeller['seller']['cust_id'] = '999';
        $wrongType = $data; $wrongType['buyer']['billing_info']['identification']['type'] = 'INVENTED';
        $wrongSite = $data; $wrongSite['site_id'] = 'MLB';
        $partial = $data; unset($partial['buyer']['billing_info']['address']);
        return [
            'complete MCO structural profile' => [200, $data, 'COMPLETE'],
            'null tax value is unknown' => [200, $null, 'UNKNOWN'],
            'missing profile' => [200, $missing, 'MISSING'],
            'shipping cannot substitute billing' => [200, $shipping, 'MISSING'],
            'buyer association mismatch' => [200, $wrongBuyer, 'UNKNOWN'],
            'seller mismatch' => [200, $wrongSeller, 'UNKNOWN'],
            'unknown document type' => [200, $wrongType, 'UNKNOWN'],
            'unsupported/wrong site' => [200, $wrongSite, 'UNKNOWN'],
            'missing fiscal address' => [200, $partial, 'UNKNOWN'],
            'unavailable resource' => [404, [], 'UNAVAILABLE'],
            'partial response' => [206, $data, 'UNKNOWN'],
        ];
    }
}
