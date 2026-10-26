<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use RuntimeException;

final readonly class MeliClientResponse
{
    /** @param array<array-key,mixed> $data */
    public function __construct(
        public int $status,
        public array $data,
        public ?string $requestId,
        public ?string $contentMissing = null,
    ) {
    }

    /** @return array{admitted:bool,complete:bool,ownership:string,billing:string,billing_info_id:?string,shipping:string} */
    public function orderReadState(string $orderId, string $expectedSellerId): array
    {
        $seller = is_array($this->data['seller'] ?? null) ? $this->data['seller'] : [];
        $sellerId = self::resourceId($seller['id'] ?? null);
        $ownership = $sellerId === null || self::resourceId($expectedSellerId) === null
            ? 'UNKNOWN' : ($sellerId === $expectedSellerId ? 'MATCH' : 'MISMATCH');
        $buyer = is_array($this->data['buyer'] ?? null) ? $this->data['buyer'] : [];
        $billing = is_array($buyer['billing_info'] ?? null) ? $buyer['billing_info'] : [];
        $shipping = is_array($this->data['shipping'] ?? null) ? $this->data['shipping'] : [];
        $complete = $this->status === 200 && trim($this->contentMissing ?? '') === ''
            && self::resourceId($this->data['id'] ?? null) !== null && self::resourceId($this->data['id']) === $orderId;
        return [
            'admitted' => $complete && $ownership === 'MATCH',
            'complete' => $complete,
            'ownership' => $ownership,
            'billing' => self::resourceId($buyer['id'] ?? null) === null ? 'UNAVAILABLE' : 'UNKNOWN',
            'billing_info_id' => self::resourceId($billing['id'] ?? null),
            'shipping' => self::resourceId($shipping['id'] ?? null) === null ? 'UNAVAILABLE' : 'AVAILABLE',
        ];
    }

    /**
     * Caller resolves every reference; no HTTP, reconstruction or global completeness claim here.
     * @param array<int|string,self> $resolvedOrders
     * @return array{pack_id:string,order_ids:list<string>}
     */
    public function packMembership(string $packId, string $expectedSellerId, array $resolvedOrders): array
    {
        $refs = $this->data['orders'] ?? null;
        if ($this->status !== 200 || trim($this->contentMissing ?? '') !== ''
            || self::resourceId($this->data['id'] ?? null) !== $packId || self::resourceId($packId) === null
            || !is_array($refs) || !array_is_list($refs) || $refs === []) {
            throw new RuntimeException('Mercado Libre pack membership is unverified.');
        }
        $seen = [];
        $owned = [];
        foreach ($refs as $ref) {
            $id = is_array($ref) ? self::resourceId($ref['id'] ?? null) : null;
            if ($id === null || isset($seen[$id]) || !isset($resolvedOrders[$id])) {
                throw new RuntimeException('Mercado Libre pack member is unresolved or invalid.');
            }
            $seen[$id] = true;
            $state = $resolvedOrders[$id]->orderReadState($id, $expectedSellerId);
            if (!$state['complete'] || $state['ownership'] === 'UNKNOWN') {
                throw new RuntimeException('Mercado Libre pack member authority is unknown.');
            }
            if ($state['ownership'] === 'MATCH') {
                $owned[] = $id;
            }
        }
        return ['pack_id' => $packId, 'order_ids' => $owned];
    }

    /** Structural MCO readiness only, not tax/legal validity; no profile values leave this method. */
    public function billingInfoReadiness(string $siteId, string $expectedSellerId, string $expectedBuyerId): string
    {
        if ($this->status === 404) {
            return 'UNAVAILABLE';
        }
        if ($this->status !== 200 || trim($this->contentMissing ?? '') !== '' || $siteId !== 'MCO'
            || ($this->data['site_id'] ?? null) !== $siteId) {
            return 'UNKNOWN';
        }
        $buyer = is_array($this->data['buyer'] ?? null) ? $this->data['buyer'] : [];
        $seller = is_array($this->data['seller'] ?? null) ? $this->data['seller'] : [];
        if (self::resourceId($expectedSellerId) === null || self::resourceId($expectedBuyerId) === null
            || self::resourceId($seller['cust_id'] ?? null) !== $expectedSellerId
            || self::resourceId($buyer['cust_id'] ?? null) !== $expectedBuyerId) {
            return 'UNKNOWN';
        }
        $profile = $buyer['billing_info'] ?? null;
        if ($profile === null) {
            return 'MISSING';
        }
        if (!is_array($profile)) {
            return 'UNKNOWN';
        }
        $identification = is_array($profile['identification'] ?? null) ? $profile['identification'] : [];
        $address = is_array($profile['address'] ?? null) ? $profile['address'] : [];
        $state = is_array($address['state'] ?? null) ? $address['state'] : [];
        if (!in_array($identification['type'] ?? null, ['CC', 'CE', 'NIT'], true) || ($address['country_id'] ?? null) !== 'CO') {
            return 'UNKNOWN';
        }
        foreach ([$profile['name'] ?? null, $identification['number'] ?? null, $address['street_name'] ?? null,
            $address['street_number'] ?? null, $address['city_name'] ?? null, $state['name'] ?? null] as $value) {
            if (!is_string($value) || trim($value) === '') {
                return 'UNKNOWN';
            }
        }
        return 'COMPLETE';
    }

    /**
     * Returns only requested entries whose item and seller identities are confirmed.
     * The expected seller must come from a company-authorized connected account.
     * @param list<string> $requestedIds
     * @return array<string,array{read_state:string,title:?string,status:?string,price:?string,currency_id:?string,public_quantity_reference:?string}>
     */
    public function sellerBulkReadState(string $expectedSellerId, array $requestedIds): array
    {
        if (self::resourceId($expectedSellerId) !== $expectedSellerId || $requestedIds === []
            || count($requestedIds) > 20 || count(array_unique($requestedIds)) !== count($requestedIds)) {
            throw new RuntimeException('Invalid seller bulk response context.');
        }

        $result = [];
        foreach ($requestedIds as $id) {
            if (preg_match('/^M[A-Z]{2}[1-9][0-9]{0,31}$/D', $id) !== 1) {
                throw new RuntimeException('Invalid requested listing ID.');
            }
            $result[$id] = [
                'read_state' => 'UNVERIFIED', 'title' => null, 'status' => null,
                'price' => null, 'currency_id' => null, 'public_quantity_reference' => null,
            ];
        }

        if ($this->status !== 200 || trim($this->contentMissing ?? '') !== '' || !array_is_list($this->data)) {
            return $result;
        }

        $seen = [];
        $duplicates = [];
        foreach ($this->data as $entry) {
            if (!is_array($entry) || !is_string($entry['id'] ?? null) || !array_key_exists($entry['id'], $result)) {
                continue;
            }
            $id = $entry['id'];
            if (isset($seen[$id])) {
                $duplicates[$id] = true;
                continue;
            }
            $seen[$id] = true;
            $code = $entry['status_code'] ?? null;
            if ($code !== 200 && $code !== '200') {
                continue;
            }
            $body = $entry['body'] ?? null;
            if (!is_array($body) || ($body['id'] ?? null) !== $id
                || self::resourceId($body['seller_id'] ?? null) !== $expectedSellerId) {
                continue;
            }

            $price = $body['price'] ?? null;
            $quantity = $body['available_quantity'] ?? null;
            $result[$id] = [
                'read_state' => 'VERIFIED',
                'title' => is_string($body['title'] ?? null) ? $body['title'] : null,
                'status' => is_string($body['status'] ?? null) ? $body['status'] : null,
                'price' => is_string($price) && preg_match('/^[0-9]+(?:\.[0-9]+)?$/D', $price) === 1 ? $price : null,
                'currency_id' => is_string($body['currency_id'] ?? null) ? $body['currency_id'] : null,
                'public_quantity_reference' => is_string($quantity) && ctype_digit($quantity) ? $quantity : null,
            ];
        }

        foreach ($duplicates as $id => $_) {
            $result[$id] = [
                'read_state' => 'UNVERIFIED', 'title' => null, 'status' => null,
                'price' => null, 'currency_id' => null, 'public_quantity_reference' => null,
            ];
        }

        return $result;
    }

    private static function resourceId(mixed $value): ?string
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }
        $id = (string) $value;
        return preg_match('/^[1-9][0-9]{0,31}$/D', $id) === 1 ? $id : null;
    }
}
