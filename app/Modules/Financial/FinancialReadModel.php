<?php

declare(strict_types=1);

namespace App\Modules\Financial;

use InvalidArgumentException;
use PDO;

final class FinancialReadModel
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /** @return array<string,mixed> */
    public function forOrder(int $companyId, int $accountId, string $externalOrderId): array
    {
        $targetStatement = $this->pdo->prepare(
            'SELECT id, external_order_id, currency_id, pack_id FROM orders '
            . 'WHERE company_id = :company_id AND account_id = :account_id AND external_order_id = :external_order_id',
        );
        $targetStatement->execute([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'external_order_id' => $externalOrderId,
        ]);
        $target = $targetStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($target)) {
            throw new InvalidArgumentException('Financial order was not found in this account.');
        }

        $packId = is_string($target['pack_id']) && $target['pack_id'] !== '' ? $target['pack_id'] : null;
        if ($packId === null) {
            $scopeOrders = [$target];
        } else {
            $scopeStatement = $this->pdo->prepare(
                'SELECT id, external_order_id, currency_id, pack_id FROM orders '
                . 'WHERE company_id = :company_id AND account_id = :account_id AND pack_id = :pack_id '
                . 'ORDER BY external_order_id',
            );
            $scopeStatement->execute([
                'company_id' => $companyId,
                'account_id' => $accountId,
                'pack_id' => $packId,
            ]);
            $scopeOrders = $scopeStatement->fetchAll(PDO::FETCH_ASSOC);
        }

        $orderIds = [];
        $scopeOrderIds = [];
        $currencies = [];
        $operationalCurrencies = [];
        foreach ($scopeOrders as $order) {
            $scopeOrderIds[(string) $order['external_order_id']] = true;
            $orderIds[] = (string) $order['external_order_id'];
            $this->addCurrency($currencies, $order['currency_id'] ?? null);
            $this->addCurrency($operationalCurrencies, $order['currency_id'] ?? null);
        }

        $orderPlaceholders = [];
        $orderParameters = [];
        foreach ($scopeOrders as $index => $order) {
            $name = 'order_id_' . $index;
            $orderPlaceholders[] = ':' . $name;
            $orderParameters[$name] = (int) $order['id'];
        }
        $orderIn = implode(', ', $orderPlaceholders);

        $itemStatement = $this->pdo->prepare(
            'SELECT COUNT(*) AS item_count, COUNT(sale_fee) AS fee_count, '
            . 'CAST(SUM(sale_fee * quantity) AS CHAR) AS sale_fee_total '
            . 'FROM order_items WHERE order_id IN (' . $orderIn . ')',
        );
        $itemStatement->execute($orderParameters);
        $itemSummary = $itemStatement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($itemSummary)) {
            throw new InvalidArgumentException('Financial order items could not be read.');
        }
        $itemCount = (int) $itemSummary['item_count'];
        $feeCount = (int) $itemSummary['fee_count'];

        $itemCurrencyStatement = $this->pdo->prepare(
            'SELECT DISTINCT currency_id FROM order_items WHERE order_id IN (' . $orderIn . ')',
        );
        $itemCurrencyStatement->execute($orderParameters);
        foreach ($itemCurrencyStatement->fetchAll(PDO::FETCH_COLUMN) as $itemCurrency) {
            $this->addCurrency($currencies, $itemCurrency);
            $this->addCurrency($operationalCurrencies, $itemCurrency);
        }

        $billingStatement = $this->pdo->prepare(
            'SELECT bd.external_detail_id, bd.associated_detail_id, bd.detail_type, bd.detail_sub_type, '
            . 'bd.detail_amount, bd.currency_id, bd.context_json, bp.document_type '
            . 'FROM billing_periods bp JOIN billing_details bd ON bd.billing_period_id = bp.id '
            . 'WHERE bp.company_id = :company_id AND bp.account_id = :account_id '
            . 'ORDER BY bd.external_detail_id, bp.period_key, bp.document_type, bd.id',
        );
        $billingStatement->execute(['company_id' => $companyId, 'account_id' => $accountId]);

        /** @var array<string,list<array<string,mixed>>> $rowsById */
        $rowsById = [];
        /** @var array<string,bool> $directIds */
        $directIds = [];
        /** @var array<string,bool> $identityConflictById */
        $identityConflictById = [];
        /** @var array<string,string> $signatureById */
        $signatureById = [];
        /** @var array<string,bool> $duplicateConflictById */
        $duplicateConflictById = [];

        foreach ($billingStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (string) $row['external_detail_id'];
            $context = $this->decodeContext($row['context_json'] ?? null);
            [$detailOrderIds, $detailPackIds] = $this->identities($context);
            $isDirect = $this->matchesScope($detailOrderIds, $detailPackIds, $scopeOrderIds, $packId);
            if ($isDirect) {
                $directIds[$id] = true;
            }
            if ($this->identityConflicts($detailOrderIds, $detailPackIds, $scopeOrderIds, $packId)) {
                $identityConflictById[$id] = true;
            }

            $rowsById[$id][] = $row;
            $signature = $this->billingSignature($row);
            if (isset($signatureById[$id]) && $signatureById[$id] !== $signature) {
                $duplicateConflictById[$id] = true;
            }
            $signatureById[$id] ??= $signature;
        }

        /** @var array<string,array<string,mixed>> $billingById */
        $billingById = [];
        /** @var array<string,?string> $associationById */
        $associationById = [];
        /** @var array<string,list<string>> $childrenByParent */
        $childrenByParent = [];
        foreach ($rowsById as $id => $rows) {
            $associations = [];
            foreach ($rows as $row) {
                $associatedId = is_string($row['associated_detail_id'] ?? null) && $row['associated_detail_id'] !== ''
                    ? $row['associated_detail_id']
                    : null;
                $associationKey = $associatedId === null ? 'null' : 'value:' . $associatedId;
                $associations[$associationKey] = $associatedId;
            }

            if (count($associations) === 1) {
                $associatedId = reset($associations);
                $associationById[$id] = $associatedId;
                if (is_string($associatedId)) {
                    $childrenByParent[$associatedId][] = $id;
                }
            }
            if (!isset($duplicateConflictById[$id])) {
                $billingById[$id] = $rows[0];
            }
        }

        $selectedIds = $directIds;
        $queue = array_keys($directIds);
        $visited = [];
        for ($position = 0; $position < count($queue); $position++) {
            $id = $queue[$position];
            if (isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;

            $associatedId = $associationById[$id] ?? null;
            if (is_string($associatedId) && isset($rowsById[$associatedId]) && !isset($selectedIds[$associatedId])) {
                $selectedIds[$associatedId] = true;
                $queue[] = $associatedId;
            }
            foreach ($childrenByParent[$id] ?? [] as $childId) {
                if (!isset($selectedIds[$childId])) {
                    $selectedIds[$childId] = true;
                    $queue[] = $childId;
                }
            }
        }

        $billingDetails = [];
        $receiverShipping = [];
        $billingIdentityConflict = false;
        $receiverShippingKnown = false;
        foreach (array_keys($selectedIds) as $id) {
            if (isset($identityConflictById[$id]) || isset($duplicateConflictById[$id])) {
                $billingIdentityConflict = true;
            }
            foreach ($rowsById[$id] ?? [] as $row) {
                $this->addCurrency($currencies, $row['currency_id'] ?? null);
            }

            $row = $billingById[$id] ?? null;
            if ($row === null) {
                continue;
            }

            $billingDetails[] = [
                'external_detail_id' => $id,
                'associated_detail_id' => is_string($row['associated_detail_id'] ?? null)
                    ? $row['associated_detail_id']
                    : null,
                'document_type' => (string) $row['document_type'],
                'detail_type' => (string) $row['detail_type'],
                'detail_sub_type' => is_string($row['detail_sub_type'] ?? null) ? $row['detail_sub_type'] : null,
                'detail_amount' => (string) $row['detail_amount'],
                'currency_id' => is_string($row['currency_id'] ?? null) ? $row['currency_id'] : null,
            ];

            $context = $this->decodeContext($row['context_json'] ?? null);
            $shipping = $context['shipping'] ?? null;
            if (is_array($shipping) && is_string($shipping['receiver_shipping_cost'] ?? null)) {
                $receiverShippingKnown = true;
                $receiverShipping[] = [
                    'external_detail_id' => $id,
                    'value' => $shipping['receiver_shipping_cost'],
                ];
            }
        }
        usort(
            $billingDetails,
            static fn (array $left, array $right): int => strcmp($left['external_detail_id'], $right['external_detail_id']),
        );
        usort(
            $receiverShipping,
            static fn (array $left, array $right): int => strcmp($left['external_detail_id'], $right['external_detail_id']),
        );

        $currencyValues = array_keys($currencies);
        sort($currencyValues, SORT_STRING);
        $currencyConflict = count($currencyValues) > 1;
        $currency = count($currencyValues) === 1 ? $currencyValues[0] : null;

        $operationalCurrencyConflict = count($operationalCurrencies) > 1;
        $saleFeeKnown = $itemCount > 0 && $feeCount === $itemCount && !$operationalCurrencyConflict;
        $saleFeeTotal = $saleFeeKnown && is_string($itemSummary['sale_fee_total'])
            ? $itemSummary['sale_fee_total']
            : null;
        $saleFeeCoverage = $operationalCurrencyConflict && $itemCount > 0
            ? 'CONFLICT'
            : ($saleFeeKnown ? 'KNOWN' : 'UNKNOWN');

        $orderIds = array_values(array_unique($orderIds));
        sort($orderIds, SORT_STRING);

        return [
            'scope' => [
                'type' => $packId === null ? 'order' : 'pack',
                'pack_id' => $packId,
                'order_ids' => $orderIds,
            ],
            'operational' => [
                'sale_fee_total' => $saleFeeTotal,
                'item_count' => $itemCount,
            ],
            'billing' => ['details' => $billingDetails],
            'shipping' => [
                'receiver_shipping' => $receiverShipping,
                'seller_shipping_cost' => null,
            ],
            'currency' => $currencyConflict ? null : $currency,
            'coverage' => [
                'sale_fee' => $saleFeeCoverage,
                'billing_identity' => $billingIdentityConflict
                    ? 'CONFLICT'
                    : ($selectedIds === [] ? 'UNKNOWN' : 'KNOWN'),
                'currency' => $currencyConflict
                    ? 'CONFLICT'
                    : ($currency === null ? 'UNKNOWN' : 'KNOWN'),
                'receiver_shipping' => $receiverShippingKnown ? 'KNOWN' : 'UNKNOWN',
                'seller_shipping' => 'UNKNOWN',
                'official_reference' => 'UNKNOWN',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array{list<string>,list<string>}
     */
    private function identities(array $context): array
    {
        $orderIds = [];
        foreach (['sales', 'items'] as $section) {
            $entries = $context[$section] ?? null;
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                if (is_array($entry) && is_string($entry['order_id'] ?? null) && $entry['order_id'] !== '') {
                    $orderIds[$entry['order_id']] = true;
                }
            }
        }

        $packIds = [];
        $shipping = $context['shipping'] ?? null;
        if (is_array($shipping) && is_string($shipping['pack_id'] ?? null) && $shipping['pack_id'] !== '') {
            $packIds[$shipping['pack_id']] = true;
        }

        return [array_keys($orderIds), array_keys($packIds)];
    }

    /**
     * @param list<string> $orderIds
     * @param list<string> $packIds
     * @param array<string,bool> $scopeOrderIds
     */
    private function matchesScope(array $orderIds, array $packIds, array $scopeOrderIds, ?string $packId): bool
    {
        if ($packId !== null && in_array($packId, $packIds, true)) {
            return true;
        }
        foreach ($orderIds as $orderId) {
            if (isset($scopeOrderIds[$orderId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $orderIds
     * @param list<string> $packIds
     * @param array<string,bool> $scopeOrderIds
     */
    private function identityConflicts(array $orderIds, array $packIds, array $scopeOrderIds, ?string $packId): bool
    {
        foreach ($orderIds as $orderId) {
            if (!isset($scopeOrderIds[$orderId])) {
                return true;
            }
        }
        if (count($packIds) > 1) {
            return true;
        }
        if ($packId === null && $packIds !== []) {
            return true;
        }
        if ($packId !== null && $packIds !== [] && $packIds[0] !== $packId) {
            return true;
        }

        return false;
    }

    /** @return array<string,mixed> */
    private function decodeContext(mixed $contextJson): array
    {
        if (!is_string($contextJson) || $contextJson === '') {
            return [];
        }
        $context = json_decode($contextJson, true, 512, JSON_THROW_ON_ERROR);

        return is_array($context) ? $context : [];
    }

    /** @param array<string,mixed> $row */
    private function billingSignature(array $row): string
    {
        return json_encode([
            'associated_detail_id' => $row['associated_detail_id'],
            'detail_type' => $row['detail_type'],
            'detail_sub_type' => $row['detail_sub_type'],
            'detail_amount' => (string) $row['detail_amount'],
            'currency_id' => $row['currency_id'],
            'document_type' => $row['document_type'],
            'context_json' => $row['context_json'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @param array<string,bool> $currencies */
    private function addCurrency(array &$currencies, mixed $currency): void
    {
        if (is_string($currency) && $currency !== '') {
            $currencies[$currency] = true;
        }
    }
}
