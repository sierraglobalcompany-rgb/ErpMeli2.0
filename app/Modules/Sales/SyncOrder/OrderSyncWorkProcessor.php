<?php

declare(strict_types=1);

namespace App\Modules\Sales\SyncOrder;

use App\Core\Logging\DebugRecorder;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class OrderSyncWorkProcessor
{
    public function __construct(
        private readonly SyncOrderHandler $handler,
        private readonly WorkRepository $work,
        private readonly ?DebugRecorder $debugRecorder = null,
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
        $companyId = $claim['company_id'];
        $accountId = $claim['account_id'];
        $orderId = $claim['payload']['order_id'] ?? null;
        $hasScalarOrderId = is_string($orderId) || is_int($orderId);

        if (
            $claim['type'] !== 'order.sync'
            || $companyId === null
            || $companyId < 1
            || $accountId === null
            || $accountId < 1
            || !$hasScalarOrderId
            || preg_match('/^[0-9]{1,32}$/D', (string) $orderId) !== 1
        ) {
            $this->work->failCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                'invalid_work_claim',
                'Work claim payload is invalid.',
            );
            return;
        }

        $this->beginDebugWork($claim['id'], (string) $orderId, $companyId, $accountId, $claim['type']);

        try {
            $this->handler->syncCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                $companyId,
                $accountId,
                (string) $orderId,
                new DateTimeImmutable('now', new DateTimeZone('UTC')),
            );
        } finally {
            $this->debugRecorder?->endWork();
        }
    }

    private function beginDebugWork(
        int $workId,
        string $resourceId,
        int $companyId,
        int $accountId,
        string $workType,
    ): void {
        if ($this->debugRecorder === null) {
            return;
        }

        try {
            $this->debugRecorder->beginWork($workId, $resourceId);
            $this->debugRecorder->record('work.started', [
                'work_type' => $workType,
                'company_id' => $companyId,
                'account_id' => $accountId,
            ]);
        } catch (Throwable) {
            $this->debugRecorder->endWork();
        }
    }
}
