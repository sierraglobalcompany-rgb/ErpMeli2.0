<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Invoicing\InvoiceRequest;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionParameter;

final class InvoiceRequestTest extends TestCase
{
    public function testBuildsImmutableRequestFromExistingSalesFacts(): void
    {
        $request = $this->request();

        self::assertSame(12, $request->companyId);
        self::assertSame(34, $request->accountId);
        self::assertSame('200000000001', $request->sourceOrderId);
        self::assertSame('123.4500', $request->sourceTotalAmount);
        self::assertSame('COP', $request->sourceCurrencyId);
        self::assertSame('998877', $request->buyerId);
        self::assertSame([
            [
                'sourceItemId' => 'MLA123',
                'title' => 'Item A',
                'quantity' => '2.0000',
                'unitPrice' => '61.7250',
                'currencyId' => 'COP',
            ],
            [
                'sourceItemId' => 'MLA456',
                'title' => 'Item B',
                'quantity' => '1.0000',
                'unitPrice' => '0.0001',
                'currencyId' => 'USD',
            ],
        ], $request->items);
        self::assertTrue((new ReflectionClass($request))->isReadOnly());
    }

    public function testPreservesExactDecimalStringsAndCurrencyWithoutRecalculation(): void
    {
        $request = $this->request();

        self::assertSame('123.4500', $request->sourceTotalAmount);
        self::assertSame('61.7250', $request->items[0]['unitPrice']);
        self::assertSame('2.0000', $request->items[0]['quantity']);
        self::assertSame('0.0001', $request->items[1]['unitPrice']);
        self::assertSame('COP', $request->sourceCurrencyId);
        self::assertSame('USD', $request->items[1]['currencyId']);
    }

    public function testUsesDeterministicCompanyAndAccountScopedSourceIdentity(): void
    {
        $first = $this->request();
        $sameSource = $this->request();
        $otherCompany = $this->request(companyId: 13);
        $otherAccount = $this->request(accountId: 35);
        $otherOrder = $this->request(sourceOrderId: '200000000002');

        self::assertSame($first->idempotencyKey, $sameSource->idempotencyKey);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $first->idempotencyKey);
        self::assertNotSame($first->idempotencyKey, $otherCompany->idempotencyKey);
        self::assertNotSame($first->idempotencyKey, $otherAccount->idempotencyKey);
        self::assertNotSame($first->idempotencyKey, $otherOrder->idempotencyKey);
    }

    public function testAllowsOnlyTheNullableBuyerIdThatSalesActuallyStores(): void
    {
        $request = $this->request(buyerId: null);

        self::assertNull($request->buyerId);
        $fields = array_keys(get_object_vars($request));
        sort($fields);
        self::assertSame([
            'accountId',
            'buyerId',
            'companyId',
            'idempotencyKey',
            'items',
            'sourceCurrencyId',
            'sourceOrderId',
            'sourceTotalAmount',
        ], $fields);
    }

    public function testRejectsMissingOrUnrepresentableSourceFacts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvoiceRequest(0, 34, '200000000001', '123.4500', 'COP', null, $this->items());
    }

    public function testRejectsMalformedMoneyInsteadOfRoundingOrGuessing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new InvoiceRequest(12, 34, '200000000001', '123.45001', 'COP', null, $this->items());
    }

    public function testSourceMoneyIsTypedAsStringAtTheBoundary(): void
    {
        $totalAmount = new ReflectionParameter([InvoiceRequest::class, '__construct'], 'sourceTotalAmount');

        self::assertSame('string', $totalAmount->getType()?->getName());
    }

    public function testRejectsFloatWithinRawSalesItemFacts(): void
    {
        $items = [[
            'sourceItemId' => 'MLA123',
            'title' => 'Item A',
            'quantity' => '1.0000',
            'unitPrice' => 1.25,
            'currencyId' => 'COP',
        ]];

        $this->expectException(InvalidArgumentException::class);
        new InvoiceRequest(12, 34, '200000000001', '1.0000', 'COP', null, $items);
    }

    public function testRejectsItemsMissingRequiredSalesFacts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $items = [['sourceItemId' => 'MLA123', 'title' => 'Item A', 'quantity' => '1.0000', 'unitPrice' => '1.0000']];
        new InvoiceRequest(12, 34, '200000000001', '1.0000', 'COP', null, $items);
    }

    /** @param list<array{sourceItemId:string,title:string,quantity:string,unitPrice:string,currencyId:string}> $items */
    private function request(
        int $companyId = 12,
        int $accountId = 34,
        string $sourceOrderId = '200000000001',
        ?string $buyerId = '998877',
        ?array $items = null,
    ): InvoiceRequest {
        return new InvoiceRequest(
            $companyId,
            $accountId,
            $sourceOrderId,
            '123.4500',
            'COP',
            $buyerId,
            $items ?? $this->items(),
        );
    }

    /** @return list<array{sourceItemId:string,title:string,quantity:string,unitPrice:string,currencyId:string}> */
    private function items(): array
    {
        return [
            [
                'sourceItemId' => 'MLA123',
                'title' => 'Item A',
                'quantity' => '2.0000',
                'unitPrice' => '61.7250',
                'currencyId' => 'COP',
            ],
            [
                'sourceItemId' => 'MLA456',
                'title' => 'Item B',
                'quantity' => '1.0000',
                'unitPrice' => '0.0001',
                'currencyId' => 'USD',
            ],
        ];
    }
}
