<?php

declare(strict_types=1);

namespace App\Modules\Sales\Audit;

use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

final class SalesAuditRepairHandler
{
    private const int REPAIR_WAIT_SECONDS = 30;
    private const int CONFIRMATION_PAGE_LIMIT = 50;

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
        } catch (PDOException $exception) {
            throw $exception;
        } catch (RuntimeException) {
            try {
                return $this->work->completeCurrentClaim(
                    $workId,
                    $claimToken,
                    function (PDO $_pdo) use ($runId, $companyId, $accountId): void {
                        if (!$this->audit->markRepairAttention($runId, $companyId, $accountId)) {
                            throw new RuntimeException('Sales audit repair attention state could not be persisted.');
                        }
                    },
                );
            } catch (PDOException $exception) {
                throw $exception;
            } catch (RuntimeException) {
                $this->work->failCurrentClaim(
                    $workId,
                    $claimToken,
                    'sales_audit_repair_attention',
                    'Sales audit repair requires attention.',
                );
                return false;
            }
        }

        if ($childWorkId === null) {
            try {
                return $this->work->completeCurrentClaim(
                    $workId,
                    $claimToken,
                    function (PDO $_pdo) use ($runId, $companyId, $accountId, $now): void {
                        if (!$this->audit->transitionToConfirmingIfRepaired($runId, $companyId, $accountId)) {
                            throw new RuntimeException('Sales audit repair verification did not confirm local coverage.');
                        }
                        $this->work->enqueue(
                            $companyId,
                            $accountId,
                            'company:' . $companyId . ':account:' . $accountId,
                            'sales.audit',
                            (string) $runId,
                            'sales.audit:' . $runId . ':confirm:0:' . self::CONFIRMATION_PAGE_LIMIT,
                            ['run_id' => $runId, 'offset' => 0, 'limit' => self::CONFIRMATION_PAGE_LIMIT],
                            $now,
                        );
                    },
                );
            } catch (PDOException $exception) {
                throw $exception;
            } catch (RuntimeException) {
                $this->work->failCurrentClaim(
                    $workId,
                    $claimToken,
                    'sales_audit_repair_attention',
                    'Sales audit repair verification requires attention.',
                );
                return false;
            }
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
