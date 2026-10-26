<?php

declare(strict_types=1);

namespace App\Modules\Financial;

use App\Modules\Billing\BillingDetailNormalizer;
use InvalidArgumentException;
use PDO;
use RuntimeException;

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
            $scopeOrderIds[$this->opaqueKey((string) $order['external_order_id'])] = true;
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

        $this->assertBillingReferenceIndexReady();
        $directIds = $this->directBillingIds(
            $companyId,
            $accountId,
            array_values(array_unique($orderIds, SORT_STRING)),
            $packId,
        );
        [$rowsById, $selectedIds] = $this->loadBillingGraph($companyId, $accountId, $directIds, $scopeOrderIds, $packId);

        /** @var array<string,list<array<string,mixed>>> $rowsById */
        /** @var array<string,bool> $identityConflictById */
        $identityConflictById = [];
        /** @var array<string,string> $signatureById */
        $signatureById = [];
        /** @var array<string,bool> $duplicateConflictById */
        $duplicateConflictById = [];

        foreach ($rowsById as $id => $rows) {
            $key = (string) $id;
            foreach ($rows as $row) {
                $context = $this->decodeContext($row['context_json'] ?? null);
                [$detailOrderIds, $detailPackIds] = $this->identities($context);
                if ($this->identityConflicts($detailOrderIds, $detailPackIds, $scopeOrderIds, $packId)) {
                    $identityConflictById[$key] = true;
                }

                $signature = $this->billingSignature($row);
                if (isset($signatureById[$key]) && $signatureById[$key] !== $signature) {
                    $duplicateConflictById[$key] = true;
                }
                $signatureById[$key] ??= $signature;
            }
        }

        /** @var array<string,array<string,mixed>> $billingById */
        $billingById = [];
        foreach ($rowsById as $rows) {
            $id = (string) $rows[0]['external_detail_id'];
            $key = $this->opaqueKey($id);
            if (!isset($duplicateConflictById[$key])) {
                $billingById[$key] = $rows[0];
            }
        }

        $billingDetails = [];
        $receiverShipping = [];
        $billingIdentityConflict = false;
        $receiverShippingKnown = false;
        foreach ($selectedIds as $key => $id) {
            if (isset($identityConflictById[$key]) || isset($duplicateConflictById[$key])) {
                $billingIdentityConflict = true;
            }
            foreach ($rowsById[$key] ?? [] as $row) {
                $this->addCurrency($currencies, $row['currency_id'] ?? null);
            }

            $row = $billingById[$key] ?? null;
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
                    $orderIds[$this->opaqueKey($entry['order_id'])] = $entry['order_id'];
                }
            }
        }

        $packIds = [];
        $shipping = $context['shipping'] ?? null;
        if (is_array($shipping) && is_string($shipping['pack_id'] ?? null) && $shipping['pack_id'] !== '') {
            $packIds[$this->opaqueKey($shipping['pack_id'])] = $shipping['pack_id'];
        }

        return [array_values($orderIds), array_values($packIds)];
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
            if (isset($scopeOrderIds[$this->opaqueKey($orderId)])) {
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
            if (!isset($scopeOrderIds[$this->opaqueKey($orderId)])) {
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

    private function assertBillingReferenceIndexReady(): void
    {
        $statement = $this->pdo->prepare('SELECT checksum FROM schema_migrations WHERE version = ?');
        $statement->execute([BillingDetailNormalizer::REFERENCE_BACKFILL_VERSION]);
        $checksum = $statement->fetchColumn();
        if (!is_string($checksum)
            || !hash_equals(BillingDetailNormalizer::REFERENCE_BACKFILL_CHECKSUM, $checksum)) {
            throw new RuntimeException('Financial Billing reference index is not ready.');
        }
    }

    /**
     * @param list<string> $orderIds
     * @return array<string,string>
     */
    private function directBillingIds(int $companyId, int $accountId, array $orderIds, ?string $packId): array
    {
        $directIds = [];
        $scopes = [];
        if ($orderIds !== []) {
            $scopes[] = ['order', $orderIds];
        }
        if ($packId !== null) {
            $scopes[] = ['pack', [$packId]];
        }

        foreach ($scopes as [$kind, $values]) {
            foreach (array_chunk($values, 400) as $batch) {
                $placeholders = [];
                $parameters = ['company_id' => $companyId, 'account_id' => $accountId, 'reference_kind' => $kind];
                foreach ($batch as $index => $value) {
                    $name = 'reference_' . $index;
                    $placeholders[] = ':' . $name;
                    $parameters[$name] = $value;
                }

                $statement = $this->pdo->prepare(
                    'SELECT bd.external_detail_id FROM billing_detail_references r '
                    . 'FORCE INDEX (idx_billing_detail_references_lookup) '
                    . 'STRAIGHT_JOIN billing_details bd ON bd.id = r.billing_detail_id '
                    . 'STRAIGHT_JOIN billing_periods bp ON bp.id = bd.billing_period_id '
                    . 'WHERE bp.company_id = :company_id AND bp.account_id = :account_id '
                    . 'AND r.reference_kind = :reference_kind AND r.reference_value IN ('
                    . implode(', ', $placeholders) . ')',
                );
                $statement->execute($parameters);
                foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $externalDetailId) {
                    $id = (string) $externalDetailId;
                    $directIds[$this->opaqueKey($id)] = $id;
                }
            }
        }

        return $directIds;
    }

    /**
     * @param array<string,string> $directIds
     * @param array<string,bool> $scopeOrderIds
     * @return array{array<string,list<array<string,mixed>>>,array<string,string>}
     */
    private function loadBillingGraph(
        int $companyId,
        int $accountId,
        array $directIds,
        array $scopeOrderIds,
        ?string $packId,
    ): array {
        $rowsById = [];
        $selectedIds = $directIds;
        $loaded = [];
        $processed = [];
        $directVerified = false;

        while (true) {
            $frontier = [];
            foreach ($selectedIds as $id) {
                $key = $this->opaqueKey($id);
                if (!isset($processed[$key])) {
                    $processed[$key] = true;
                    $frontier[] = $id;
                }
            }
            if ($frontier === []) {
                break;
            }

            $unloadedFrontier = array_values(array_filter(
                $frontier,
                fn (string $id): bool => !isset($loaded[$this->opaqueKey($id)]),
            ));
            $this->mergeBillingRows(
                $rowsById,
                $loaded,
                $this->billingRowsForExternalIds($companyId, $accountId, $unloadedFrontier),
            );

            if (!$directVerified) {
                foreach ($directIds as $id) {
                    $matches = false;
                    foreach ($rowsById[$this->opaqueKey($id)] ?? [] as $row) {
                        $context = $this->decodeContext($row['context_json'] ?? null);
                        [$detailOrderIds, $detailPackIds] = $this->identities($context);
                        if ($this->matchesScope($detailOrderIds, $detailPackIds, $scopeOrderIds, $packId)) {
                            $matches = true;
                            break;
                        }
                    }
                    if (!$matches) {
                        throw new RuntimeException('Financial Billing reference index disagrees with its source context.');
                    }
                }
                $directVerified = true;
            }

            $parents = [];
            foreach ($frontier as $id) {
                $association = $this->consistentAssociation($rowsById[$this->opaqueKey($id)] ?? []);
                if ($association['consistent'] && is_string($association['id'])) {
                    $parents[$this->opaqueKey($association['id'])] = $association['id'];
                }
            }

            $children = $this->billingChildrenForParents($companyId, $accountId, $frontier);
            $unloadedCandidates = [];
            foreach ($parents + $children as $candidateId) {
                if (!isset($loaded[$this->opaqueKey($candidateId)])) {
                    $unloadedCandidates[$this->opaqueKey($candidateId)] = $candidateId;
                }
            }
            $this->mergeBillingRows(
                $rowsById,
                $loaded,
                $this->billingRowsForExternalIds($companyId, $accountId, array_values($unloadedCandidates)),
            );

            foreach ($parents as $parentId) {
                if (isset($rowsById[$this->opaqueKey($parentId)])) {
                    $selectedIds[$this->opaqueKey($parentId)] = $parentId;
                }
            }
            foreach ($children as $childId) {
                $association = $this->consistentAssociation($rowsById[$this->opaqueKey($childId)] ?? []);
                if ($association['consistent'] && is_string($association['id'])
                    && in_array($association['id'], $frontier, true)) {
                    $selectedIds[$this->opaqueKey($childId)] = $childId;
                }
            }
        }

        return [$rowsById, $selectedIds];
    }

    /**
     * @param list<string> $externalIds
     * @return list<array<string,mixed>>
     */
    private function billingRowsForExternalIds(int $companyId, int $accountId, array $externalIds): array
    {
        $unique = [];
        foreach ($externalIds as $externalId) {
            $unique[$this->opaqueKey($externalId)] = $externalId;
        }

        $rows = [];
        foreach (array_chunk(array_values($unique), 400) as $batch) {
            $placeholders = [];
            $parameters = ['company_id' => $companyId, 'account_id' => $accountId];
            foreach ($batch as $index => $externalId) {
                $name = 'external_id_' . $index;
                $placeholders[] = ':' . $name;
                $parameters[$name] = $externalId;
            }

            $statement = $this->pdo->prepare(
                'SELECT r.reference_value AS indexed_external_detail_id, bd.id AS billing_detail_id, '
                . 'bd.external_detail_id, bd.associated_detail_id, bd.detail_type, bd.detail_sub_type, '
                . 'bd.detail_amount, bd.currency_id, bd.context_json, bp.document_type, bp.period_key '
                . 'FROM billing_detail_references r FORCE INDEX (idx_billing_detail_references_lookup) '
                . 'STRAIGHT_JOIN billing_details bd ON bd.id = r.billing_detail_id '
                . 'STRAIGHT_JOIN billing_periods bp ON bp.id = bd.billing_period_id '
                . 'WHERE bp.company_id = :company_id AND bp.account_id = :account_id '
                . "AND r.reference_kind = 'detail' AND r.reference_value IN (" . implode(', ', $placeholders) . ') '
                . 'ORDER BY r.reference_value, bp.period_key, bp.document_type, bd.id',
            );
            $statement->execute($parameters);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!hash_equals((string) $row['indexed_external_detail_id'], (string) $row['external_detail_id'])) {
                    throw new RuntimeException('Financial Billing detail reference index is inconsistent.');
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @param list<string> $parentIds
     * @return array<string,string>
     */
    private function billingChildrenForParents(int $companyId, int $accountId, array $parentIds): array
    {
        $children = [];
        foreach (array_chunk(array_values(array_unique($parentIds, SORT_STRING)), 400) as $batch) {
            $placeholders = [];
            $parameters = ['company_id' => $companyId, 'account_id' => $accountId];
            foreach ($batch as $index => $parentId) {
                $name = 'parent_id_' . $index;
                $placeholders[] = ':' . $name;
                $parameters[$name] = $parentId;
            }

            $statement = $this->pdo->prepare(
                'SELECT r.reference_value AS indexed_associated_detail_id, '
                . 'bd.external_detail_id, bd.associated_detail_id '
                . 'FROM billing_detail_references r FORCE INDEX (idx_billing_detail_references_lookup) '
                . 'STRAIGHT_JOIN billing_details bd ON bd.id = r.billing_detail_id '
                . 'STRAIGHT_JOIN billing_periods bp ON bp.id = bd.billing_period_id '
                . 'WHERE bp.company_id = :company_id AND bp.account_id = :account_id '
                . "AND r.reference_kind = 'association' AND r.reference_value IN ("
                . implode(', ', $placeholders) . ')',
            );
            $statement->execute($parameters);
            foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!is_string($row['associated_detail_id'] ?? null)
                    || !hash_equals((string) $row['indexed_associated_detail_id'], $row['associated_detail_id'])) {
                    throw new RuntimeException('Financial Billing association reference index is inconsistent.');
                }
                $childId = (string) $row['external_detail_id'];
                $children[$this->opaqueKey($childId)] = $childId;
            }
        }

        return $children;
    }

    /**
     * @param array<string,list<array<string,mixed>>> $rowsById
     * @param array<string,bool> $loaded
     * @param list<array<string,mixed>> $rows
     */
    private function mergeBillingRows(array &$rowsById, array &$loaded, array $rows): void
    {
        foreach ($rows as $row) {
            $id = (string) $row['external_detail_id'];
            $rowsById[$this->opaqueKey($id)][] = $row;
            $loaded[$this->opaqueKey($id)] = true;
        }
    }

    /** @param list<array<string,mixed>> $rows
     * @return array{consistent:bool,id:?string}
     */
    private function consistentAssociation(array $rows): array
    {
        $associations = [];
        foreach ($rows as $row) {
            $id = is_string($row['associated_detail_id'] ?? null) && $row['associated_detail_id'] !== ''
                ? $row['associated_detail_id']
                : null;
            $key = $id === null ? 'null' : 'value:' . bin2hex($id);
            $associations[$key] = $id;
        }
        if (count($associations) !== 1) {
            return ['consistent' => false, 'id' => null];
        }

        $id = reset($associations);

        return ['consistent' => true, 'id' => is_string($id) ? $id : null];
    }

    private function opaqueKey(string $value): string
    {
        return 'id:' . bin2hex($value);
    }
}
