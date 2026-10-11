<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Billing\BillingDetailNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BillingDetailNormalizerTest extends TestCase
{
    public function testNormalizesRequiredBillingFactsWithoutFloatLoss(): void
    {
        $detail = [
            'charge_info' => [
                'detail_id' => '126303124',
                'detail_associated_id' => '126303120',
                'detail_amount' => '28265.86',
                'detail_type' => 'CHARGE',
                'detail_sub_type' => 'CV',
                'creation_date_time' => '2024-04-22T23:12:02',
            ],
            'document_info' => ['document_id' => '3454540850'],
            'marketplace_info' => ['marketplace' => 'MCO'],
            'currency_info' => ['currency_id' => 'COP'],
        ];

        self::assertSame([
            'external_detail_id' => '126303124',
            'associated_detail_id' => '126303120',
            'detail_type' => 'CHARGE',
            'detail_sub_type' => 'CV',
            'detail_amount' => '28265.8600',
            'currency_id' => 'COP',
            'document_id' => '3454540850',
            'marketplace' => 'MCO',
            'context_json' => null,
            'remote_created_at' => null,
        ], (new BillingDetailNormalizer())->normalize($detail));
    }

    #[DataProvider('validAmounts')]
    public function testNormalizesExactMoneyToFourDecimalPlaces(mixed $amount, string $expected): void
    {
        $detail = [
            'charge_info' => [
                'detail_id' => '126303124',
                'detail_amount' => $amount,
                'detail_type' => 'CHARGE',
            ],
        ];

        self::assertSame($expected, (new BillingDetailNormalizer())->normalize($detail)['detail_amount']);
    }

    public static function validAmounts(): array
    {
        return [
            'pads fraction' => ['2.75', '2.7500'],
            'negative exact decimal' => ['-10.5', '-10.5000'],
            'integer input' => [123, '123.0000'],
            'maximum DECIMAL value' => ['99999999999999.9999', '99999999999999.9999'],
        ];
    }

    public function testRejectsFloatMalformedAndOutOfRangeRequiredData(): void
    {
        $detail = [
            'charge_info' => [
                'detail_id' => '126303124',
                'detail_amount' => '1',
                'detail_type' => 'CHARGE',
            ],
        ];
        $invalidDetails = [
            'float amount' => ['charge_info' => array_replace($detail['charge_info'], ['detail_amount' => 2.75])],
            'too many decimals' => ['charge_info' => array_replace($detail['charge_info'], ['detail_amount' => '1.23456'])],
            'exponent amount' => ['charge_info' => array_replace($detail['charge_info'], ['detail_amount' => '1e3'])],
            'comma decimal' => ['charge_info' => array_replace($detail['charge_info'], ['detail_amount' => '1,20'])],
            'NaN amount' => ['charge_info' => array_replace($detail['charge_info'], ['detail_amount' => 'NaN'])],
            'infinite amount' => ['charge_info' => array_replace($detail['charge_info'], ['detail_amount' => 'INF'])],
            'blank amount' => ['charge_info' => array_replace($detail['charge_info'], ['detail_amount' => ''])],
            'over DECIMAL range' => ['charge_info' => array_replace($detail['charge_info'], ['detail_amount' => '100000000000000.0000'])],
            'missing charge info' => [],
            'missing detail id' => ['charge_info' => ['detail_amount' => '1', 'detail_type' => 'CHARGE']],
            'blank detail type' => ['charge_info' => ['detail_id' => '126303124', 'detail_amount' => '1', 'detail_type' => '  ']],
            'missing amount' => ['charge_info' => ['detail_id' => '126303124', 'detail_type' => 'CHARGE']],
        ];

        foreach ($invalidDetails as $case => $invalidDetail) {
            try {
                (new BillingDetailNormalizer())->normalize($invalidDetail);
            } catch (RuntimeException $exception) {
                self::assertSame('Mercado Libre billing detail contract is invalid.', $exception->getMessage(), $case);
                continue;
            }

            self::fail('Expected invalid contract for ' . $case . '.');
        }
    }

    public function testBuildsOnlyAllowlistedContextAndSeparatesBonifiedId(): void
    {
        $detail = [
            'charge_info' => [
                'detail_id' => '126303124',
                'detail_associated_id' => 111,
                'detail_amount' => '1.25',
                'detail_type' => 'CHARGE',
                'charge_bonified_id' => 222,
                'debited_from_operation' => 'sale_fee',
                'status' => 'released',
                'legal_document_number' => 'LEGAL-123',
                'legal_document_status' => 'authorized',
                'transaction_detail' => 'internal payment detail',
            ],
            'discount_info' => [
                'charge_amount_without_discount' => '112.10',
                'discount_amount' => '-0.01',
                'rebate' => 0,
                'discount_reason' => 'Promo',
                'applied_percentage' => '10',
                'unexpected' => 'secret',
            ],
            'sales_info' => [[
                'order_id' => 123,
                'operation_id' => '456',
                'sale_date_time' => '2024-04-22T23:12:02-05:00',
                'transaction_amount' => '112.0900',
                'payer_nickname' => 'buyer-name',
                'state_name' => 'approved',
                'sales_channel' => 'marketplace',
            ], []],
            'shipping_info' => [
                'shipping_id' => 7001,
                'pack_id' => '8001',
                'receiver_shipping_cost' => '5.60',
                'receiver_nickname' => 'recipient-name',
                'order' => ['order_id' => 123, 'buyer_nickname' => 'buyer-name'],
            ],
            'items_info' => [[
                'item_id' => 'MLA1',
                'order_id' => '123',
                'item_amount' => '2',
                'item_price' => '56.045',
                'item_title' => 'Product title',
                'inventory_id' => 'INV-1',
            ], []],
            'unknown_top_level' => ['secret' => 'do not preserve'],
        ];

        $normalized = (new BillingDetailNormalizer())->normalize($detail);

        self::assertSame('111', $normalized['associated_detail_id']);
        self::assertSame(
            '{"charge":{"debited_from_operation":"sale_fee","status":"released","charge_bonified_id":"222"},"discount":{"charge_amount_without_discount":"112.10","discount_amount":"-0.01","rebate":"0","discount_reason":"Promo"},"sales":[{"order_id":"123","operation_id":"456","sale_date_time":"2024-04-22T23:12:02-05:00","transaction_amount":"112.0900"}],"shipping":{"shipping_id":"7001","pack_id":"8001","receiver_shipping_cost":"5.60"},"items":[{"item_id":"MLA1","order_id":"123","item_amount":"2","item_price":"56.045"}]}' ,
            $normalized['context_json'],
        );

        foreach ([
            'legal_document_number',
            'legal_document_status',
            'transaction_detail',
            'payer_nickname',
            'state_name',
            'sales_channel',
            'receiver_nickname',
            'buyer_nickname',
            'item_title',
            'inventory_id',
            'unknown_top_level',
            'secret',
        ] as $excluded) {
            self::assertStringNotContainsString($excluded, (string) $normalized['context_json']);
        }
    }

    #[DataProvider('timestampCases')]
    public function testNormalizesOnlyValidExplicitTimestampTimezones(mixed $timestamp, ?string $expected): void
    {
        $detail = [
            'charge_info' => [
                'detail_id' => '126303124',
                'detail_amount' => '1',
                'detail_type' => 'CHARGE',
                'creation_date_time' => $timestamp,
            ],
        ];

        self::assertSame($expected, (new BillingDetailNormalizer())->normalize($detail)['remote_created_at']);
    }

    public static function timestampCases(): array
    {
        return [
            'offsetless' => ['2024-11-14T10:36:38', null],
            'UTC' => ['2024-11-14T10:36:38Z', '2024-11-14 10:36:38.000000'],
            'negative offset' => ['2024-11-14T10:36:38.123-05:00', '2024-11-14 15:36:38.123000'],
            'invalid date' => ['2024-13-99T10:36:38Z', null],
            'overflow date' => ['2024-02-31T10:36:38Z', null],
            'non-string timestamp' => [123, null],
            'invalid byte timestamp' => ['2024-11-14T10:36:38' . "\0" . 'Z', null],
        ];
    }

    public function testLeavesMissingOptionalFactsNull(): void
    {
        $detail = [
            'charge_info' => [
                'detail_id' => 126303124,
                'detail_associated_id' => '  ',
                'detail_amount' => '0',
                'detail_type' => 'CHARGE',
                'detail_sub_type' => null,
            ],
            'currency_info' => ['currency_id' => ''],
            'document_info' => ['document_id' => null],
            'marketplace_info' => ['marketplace' => '  '],
            'discount_info' => null,
            'sales_info' => null,
            'shipping_info' => null,
            'items_info' => null,
        ];

        self::assertSame([
            'external_detail_id' => '126303124',
            'associated_detail_id' => null,
            'detail_type' => 'CHARGE',
            'detail_sub_type' => null,
            'detail_amount' => '0.0000',
            'currency_id' => null,
            'document_id' => null,
            'marketplace' => null,
            'context_json' => null,
            'remote_created_at' => null,
        ], (new BillingDetailNormalizer())->normalize($detail));
    }

    public function testRejectsInvalidContextTypesWithoutEchoingSourceValues(): void
    {
        $base = [
            'charge_info' => [
                'detail_id' => '126303124',
                'detail_amount' => '1',
                'detail_type' => 'CHARGE',
            ],
        ];
        $invalidDetails = [
            'float allowlisted value' => array_replace($base, ['discount_info' => ['discount_amount' => 2.75]]),
            'invalid object section' => array_replace($base, ['discount_info' => 'unexpected private value']),
            'non-array sales entry' => array_replace($base, ['sales_info' => ['unexpected private value']]),
            'float charge value' => array_replace($base, ['charge_info' => array_replace(
                $base['charge_info'],
                ['status' => 2.75],
            )]),
        ];

        foreach ($invalidDetails as $case => $invalidDetail) {
            try {
                (new BillingDetailNormalizer())->normalize($invalidDetail);
            } catch (RuntimeException $exception) {
                self::assertSame('Mercado Libre billing detail contract is invalid.', $exception->getMessage(), $case);
                self::assertStringNotContainsString('unexpected private value', $exception->getMessage(), $case);
                continue;
            }

            self::fail('Expected invalid context contract for ' . $case . '.');
        }
    }

    public function testRejectsInvalidOrOverlengthCoreAndOptionalStrings(): void
    {
        $base = [
            'charge_info' => [
                'detail_id' => '126303124',
                'detail_amount' => '1',
                'detail_type' => 'CHARGE',
            ],
        ];
        $invalidDetails = [
            'float detail id' => array_replace($base, ['charge_info' => array_replace(
                $base['charge_info'],
                ['detail_id' => 2.75],
            )]),
            'long detail id' => array_replace($base, ['charge_info' => array_replace(
                $base['charge_info'],
                ['detail_id' => str_repeat('1', 41)],
            )]),
            'long detail type' => array_replace($base, ['charge_info' => array_replace(
                $base['charge_info'],
                ['detail_type' => str_repeat('T', 17)],
            )]),
            'long associated detail id' => array_replace($base, ['charge_info' => array_replace(
                $base['charge_info'],
                ['detail_associated_id' => str_repeat('1', 41)],
            )]),
            'long detail subtype' => array_replace($base, ['charge_info' => array_replace(
                $base['charge_info'],
                ['detail_sub_type' => str_repeat('T', 41)],
            )]),
            'long currency id' => array_replace($base, ['currency_info' => ['currency_id' => str_repeat('C', 9)]]),
            'long document id' => array_replace($base, ['document_info' => ['document_id' => str_repeat('1', 41)]]),
            'long marketplace' => array_replace($base, ['marketplace_info' => ['marketplace' => str_repeat('M', 41)]]),
        ];

        foreach ($invalidDetails as $case => $invalidDetail) {
            try {
                (new BillingDetailNormalizer())->normalize($invalidDetail);
            } catch (RuntimeException $exception) {
                self::assertSame('Mercado Libre billing detail contract is invalid.', $exception->getMessage(), $case);
                continue;
            }

            self::fail('Expected invalid string contract for ' . $case . '.');
        }
    }
}
