<?php

declare(strict_types=1);

namespace App\Modules\Sales\Audit;

use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class SalesAuditRepairHandler
{
    private const int REPAIR_WAIT_SECONDS = 30;

    public function __construct(
        private readonly SalesAuditRepository $audit,
        private readonly WorkRepository $work,
    ) {
    }

    public function processCurrentClaim(
        int $workId,
        string $claimToken,
        int $runId,
        int $companyId,
        int $accountId,
        DateTimeImmutable $now,
    ): bool {
        try {
            $childWorkId = $this->enqueueNextMissingOrder($runId, $companyId, $accountId, $now);
        } catch (RuntimeException) {
            $this->work->failCurrentClaim(
                $workId,
                $claimToken,
                'sales_audit_repair_attention',
                'Sales audit repair requires attention.',
            );
            return false;
        }

        if ($childWorkId === null) {
            return $this->work->completeCurrentClaim(
                $workId,
                $claimToken,
                static function (PDO $_pdo): void {
                },
            );
        }

        return $this->work->deferCurrentClaim(
            $workId,
            $claimToken,
            $now->modify('+' . self::REPAIR_WAIT_SECONDS . ' seconds'),
            'sales_audit_repair_wait',
            'Sales audit repair is waiting for the current order sync.',
        );
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
