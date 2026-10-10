<?php

declare(strict_types=1);

namespace App\Modules\Sales\Audit;

use App\Work\WorkRepository;
use DateTimeImmutable;

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

        return $this->work->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            $externalOrderId,
            'order.sync:' . $externalOrderId,
            ['order_id' => $externalOrderId],
            $now,
        );
    }
}
