<?php

declare(strict_types=1);

namespace App\Modules\Sales\Audit;

use App\Work\WorkRepository;
use DateTimeImmutable;
use RuntimeException;

final class SalesAuditRepairHandler
{
    public function __construct(
        private readonly SalesAuditRepository $audit,
        private readonly WorkRepository $work,
    ) {
    }

    public function enqueueNextMissingOrder(
        int $runId,
        int $companyId,
        int $accountId,
        DateTimeImmutable $now,
    ): ?int {
        $externalOrderId = $this->audit->nextRepairingMissingCanonicalOrderId(
            $runId,
            $companyId,
            $accountId,
        );
        if ($externalOrderId === null) {
            return null;
        }

        $scopeKey = 'company:' . $companyId . ':account:' . $accountId;
        $logicalIdentity = 'order.sync:' . $externalOrderId;
        $latest = $this->work->latestLogicalState($scopeKey, 'order.sync', $logicalIdentity);
        if ($latest !== null) {
            if ($latest['status'] === 'pending' || $latest['status'] === 'running') {
                return $latest['id'];
            }

            throw new RuntimeException('Sales audit repair sync ended without repairing the local order.');
        }

        return $this->work->enqueue(
            $companyId,
            $accountId,
            $scopeKey,
            'order.sync',
            $externalOrderId,
            $logicalIdentity,
            ['order_id' => $externalOrderId],
            $now,
        );
    }
}
