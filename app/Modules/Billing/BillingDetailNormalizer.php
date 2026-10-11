<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class BillingDetailNormalizer
{
    /**
     * @param array<string,mixed> $detail
     * @return array{
     *     external_detail_id:string,
     *     associated_detail_id:?string,
     *     detail_type:string,
     *     detail_sub_type:?string,
     *     detail_amount:string,
     *     currency_id:?string,
     *     document_id:?string,
     *     marketplace:?string,
     *     context_json:?string,
     *     remote_created_at:?string
     * }
     */
    public function normalize(array $detail): array
    {
        $chargeInfo = $detail['charge_info'] ?? null;
        if (!is_array($chargeInfo)) {
            $this->invalidContract();
        }

        return [
            'external_detail_id' => $this->requiredString($chargeInfo['detail_id'] ?? null, 40),
            'associated_detail_id' => $this->optionalSectionString($detail, 'charge_info', 'detail_associated_id', 40),
            'detail_type' => $this->requiredString($chargeInfo['detail_type'] ?? null, 16),
            'detail_sub_type' => $this->optionalSectionString($detail, 'charge_info', 'detail_sub_type', 40),
            'detail_amount' => $this->decimal4($chargeInfo['detail_amount'] ?? null),
            'currency_id' => $this->optionalSectionString($detail, 'currency_info', 'currency_id', 8),
            'document_id' => $this->optionalSectionString($detail, 'document_info', 'document_id', 40),
            'marketplace' => $this->optionalSectionString($detail, 'marketplace_info', 'marketplace', 40),
            'context_json' => $this->contextJson($detail, $chargeInfo),
            'remote_created_at' => $this->nullableUtcTimestamp($chargeInfo['creation_date_time'] ?? null),
        ];
    }

    private function requiredString(mixed $value, int $maxLength): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            $this->invalidContract();
        }

        $value = trim($value);
        if ($value === '' || mb_strlen($value, 'UTF-8') > $maxLength) {
            $this->invalidContract();
        }

        return $value;
    }

    /** @param array<string,mixed> $detail */
    private function optionalSectionString(array $detail, string $section, string $field, int $maxLength): ?string
    {
        $values = $detail[$section] ?? null;
        if ($values === null) {
            return null;
        }
        if (!is_array($values)) {
            $this->invalidContract();
        }

        $value = $values[$field] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            $this->invalidContract();
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            $this->invalidContract();
        }

        return $value;
    }

    private function decimal4(mixed $value): string
    {
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            $this->invalidContract();
        }

        $raw = trim($value);
        if (preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $raw, $matches) !== 1) {
            $this->invalidContract();
        }

        $whole = ltrim($matches[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = $matches[3] ?? '';
        if (strlen($whole) > 14 || strlen($fraction) > 4) {
            $this->invalidContract();
        }

        $sign = $matches[1] === '-' && ($whole !== '0' || trim($fraction, '0') !== '') ? '-' : '';

        return $sign . $whole . '.' . str_pad($fraction, 4, '0');
    }

    private function nullableUtcTimestamp(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = trim($value);
        if (str_contains($timestamp, "\0") || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp) !== 1) {
            return null;
        }

        try {
            $date = new DateTimeImmutable($timestamp);
            $parseErrors = DateTimeImmutable::getLastErrors();
            if ($parseErrors !== false && ($parseErrors['warning_count'] > 0 || $parseErrors['error_count'] > 0)) {
                return null;
            }

            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $detail
     * @param array<string,mixed> $chargeInfo
     */
    private function contextJson(array $detail, array $chargeInfo): ?string
    {
        $context = [];
        $charge = $this->contextFields($chargeInfo, [
            'debited_from_operation',
            'status',
            'charge_bonified_id',
        ]);
        if ($charge !== []) {
            $context['charge'] = $charge;
        }

        $discount = $this->optionalContextObject($detail, 'discount_info', [
            'charge_amount_without_discount',
            'discount_amount',
            'rebate',
            'discount_reason',
        ]);
        if ($discount !== []) {
            $context['discount'] = $discount;
        }

        $sales = $this->optionalContextList($detail, 'sales_info', [
            'order_id',
            'operation_id',
            'sale_date_time',
            'transaction_amount',
        ]);
        if ($sales !== []) {
            $context['sales'] = $sales;
        }

        $shipping = $this->optionalContextObject($detail, 'shipping_info', [
            'shipping_id',
            'pack_id',
            'receiver_shipping_cost',
        ]);
        if ($shipping !== []) {
            $context['shipping'] = $shipping;
        }

        $items = $this->optionalContextList($detail, 'items_info', [
            'item_id',
            'order_id',
            'item_amount',
            'item_price',
        ]);
        if ($items !== []) {
            $context['items'] = $items;
        }

        if ($context === []) {
            return null;
        }

        return json_encode(
            $context,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param array<string,mixed> $detail
     * @param list<string> $fields
     * @return array<string,string>
     */
    private function optionalContextObject(array $detail, string $section, array $fields): array
    {
        $source = $detail[$section] ?? null;
        if ($source === null) {
            return [];
        }
        if (!is_array($source)) {
            $this->invalidContract();
        }

        return $this->contextFields($source, $fields);
    }

    /**
     * @param array<string,mixed> $detail
     * @param list<string> $fields
     * @return list<array<string,string>>
     */
    private function optionalContextList(array $detail, string $section, array $fields): array
    {
        $source = $detail[$section] ?? null;
        if ($source === null) {
            return [];
        }
        if (!is_array($source) || !array_is_list($source)) {
            $this->invalidContract();
        }

        $items = [];
        foreach ($source as $entry) {
            if (!is_array($entry)) {
                $this->invalidContract();
            }

            $item = $this->contextFields($entry, $fields);
            if ($item !== []) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $source
     * @param list<string> $fields
     * @return array<string,string>
     */
    private function contextFields(array $source, array $fields): array
    {
        $context = [];
        foreach ($fields as $field) {
            $value = $this->contextString($source[$field] ?? null);
            if ($value !== null) {
                $context[$field] = $value;
            }
        }

        return $context;
    }

    private function contextString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            $value = (string) $value;
        }
        if (!is_string($value)) {
            $this->invalidContract();
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function invalidContract(): never
    {
        throw new RuntimeException('Mercado Libre billing detail contract is invalid.');
    }
}
