<?php

declare(strict_types=1);

namespace App\Modules\Invoicing;

use InvalidArgumentException;
use JsonException;

final readonly class InvoiceRequest
{
    public string $idempotencyKey;

    /** @var list<array{sourceItemId:string,title:string,quantity:string,unitPrice:string,currencyId:string}> */
    public array $items;

    /**
     * @param array<array-key,array<string,mixed>> $items
     * @throws JsonException
     */
    public function __construct(
        public int $companyId,
        public int $accountId,
        public string $sourceOrderId,
        public string $sourceTotalAmount,
        public string $sourceCurrencyId,
        public ?string $buyerId,
        array $items,
    ) {
        if ($companyId < 1 || $accountId < 1 || trim($sourceOrderId) === '') {
            throw new InvalidArgumentException('Invoice request source identity is incomplete.');
        }
        if (trim($sourceCurrencyId) === '') {
            throw new InvalidArgumentException('Invoice request source currency is missing.');
        }
        if ($buyerId !== null && trim($buyerId) === '') {
            throw new InvalidArgumentException('Invoice request buyer identity is invalid.');
        }
        self::assertDecimal($sourceTotalAmount, 14, 'source total');

        if ($items === [] || !array_is_list($items)) {
            throw new InvalidArgumentException('Invoice request Sales items are missing.');
        }
        $validatedItems = [];
        foreach ($items as $item) {
            $sourceItemId = $item['sourceItemId'] ?? null;
            $title = $item['title'] ?? null;
            $quantity = $item['quantity'] ?? null;
            $unitPrice = $item['unitPrice'] ?? null;
            $currencyId = $item['currencyId'] ?? null;
            if (
                !is_string($sourceItemId)
                || trim($sourceItemId) === ''
                || !is_string($title)
                || !is_string($quantity)
                || !is_string($unitPrice)
                || !is_string($currencyId)
                || trim($currencyId) === ''
            ) {
                throw new InvalidArgumentException('Invoice request Sales item is incomplete.');
            }
            self::assertDecimal($quantity, 10, 'item quantity');
            self::assertDecimal($unitPrice, 14, 'item unit price');
            $validatedItems[] = [
                'sourceItemId' => $sourceItemId,
                'title' => $title,
                'quantity' => $quantity,
                'unitPrice' => $unitPrice,
                'currencyId' => $currencyId,
            ];
        }
        $this->items = $validatedItems;

        $sourceIdentity = json_encode(
            [$companyId, $accountId, $sourceOrderId],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );
        $this->idempotencyKey = hash('sha256', 'invoicing.source-order:' . $sourceIdentity);
    }

    private static function assertDecimal(string $value, int $maxIntegerDigits, string $field): void
    {
        if (preg_match('/^([+-]?)(\d+)(?:\.(\d{1,4}))?$/D', $value, $matches) !== 1) {
            throw new InvalidArgumentException('Invoice request ' . $field . ' is not an exact decimal.');
        }

        $integerDigits = ltrim($matches[2], '0');
        if (strlen($integerDigits) > $maxIntegerDigits) {
            throw new InvalidArgumentException('Invoice request ' . $field . ' exceeds Sales precision.');
        }
    }
}
