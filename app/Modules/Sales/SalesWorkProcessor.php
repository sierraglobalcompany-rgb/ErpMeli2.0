<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use App\Modules\Sales\Audit\SalesAuditHandler;
use App\Modules\Sales\SyncOrder\OrderSyncWorkProcessor;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;

final class SalesWorkProcessor
{
    public function __construct(
        private readonly OrderSyncWorkProcessor $orderSync,
        private readonly SalesAuditHandler $salesAudit,
        private readonly WorkRepository $work,
    ) {
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
        if ($claim['type'] === 'order.sync') {
            ($this->orderSync)($claim);
            return;
        }

        if ($claim['type'] === 'sales.audit') {
            $companyId = $claim['company_id'];
            $accountId = $claim['account_id'];

            if (
                $companyId === null
                || $companyId < 1
                || $accountId === null
                || $accountId < 1
            ) {
                $this->work->failCurrentClaim(
                    $claim['id'],
                    $claim['claim_token'],
                    'invalid_work_claim',
                    'Work claim payload is invalid.',
                );
                return;
            }

            $this->salesAudit->processCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                $companyId,
                $accountId,
                $claim['payload'],
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            );
            return;
        }

        $this->work->failCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            'unsupported_work_type',
            'Work type is not supported by this processor.',
        );
    }
}
