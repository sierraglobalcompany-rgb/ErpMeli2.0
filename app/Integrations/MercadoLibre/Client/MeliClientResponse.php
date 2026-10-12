<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use RuntimeException;

final readonly class MeliClientResponse
{
    /** @param array<string,mixed> $data */
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

    private static function resourceId(mixed $value): ?string
    {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }
        $id = (string) $value;
        return preg_match('/^[1-9][0-9]{0,31}$/D', $id) === 1 ? $id : null;
    }
}
