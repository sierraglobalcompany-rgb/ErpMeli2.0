<?php

declare(strict_types=1);

namespace App\Modules\Sales\SyncOrder;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class OrderSyncWorkProcessor
{
    public function __construct(private readonly SyncOrderHandler $handler)
    {
    }

    /**
     * @param array{
     *   id:int,
     *   company_id:?int,
     *   account_id:?int,
     *   type:string,
     *   resource_key:?string,
     *   payload:array<string,mixed>,
     *   status:string,
     *   attempts:int,
     *   claim_token:string,
     *   claimed_at:string
     * } $claim
     */
    public function __invoke(array $claim): void
    {
        $companyId = $claim['company_id'];
        $accountId = $claim['account_id'];
        $orderId = $claim['payload']['order_id'] ?? null;

        if (
            $claim['type'] !== 'order.sync'
            || $companyId === null
            || $companyId < 1
            || $accountId === null
            || $accountId < 1
            || !(is_string($orderId) || is_int($orderId))
        ) {
            throw new RuntimeException('Invalid order.sync work claim.');
        }

        $orderId = (string) $orderId;
        if (preg_match('/^[0-9]{1,32}$/D', $orderId) !== 1) {
            throw new RuntimeException('Invalid order.sync work claim.');
        }

        $this->handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            $orderId,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
    }
}
