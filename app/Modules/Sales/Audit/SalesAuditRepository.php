<?php

declare(strict_types=1);

namespace App\Modules\Sales\Audit;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class SalesAuditRepository
{
    public const CONTRACT_VERSION = 'seller-search-v1';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function siteIdForScope(int $companyId, int $accountId): string
    {
        $statement = $this->pdo->prepare(
            'SELECT site_id FROM meli_accounts WHERE company_id = :company_id AND id = :account_id LIMIT 1'
        );
        $statement->execute([
            'company_id' => $companyId,
            'account_id' => $accountId,
        ]);
        $siteId = $statement->fetchColumn();

        if (!is_string($siteId) || $siteId === '') {
            throw new RuntimeException('Mercado Libre account scope not found.');
        }

        return $siteId;
    }

    public function createCapturingRun(
        int $companyId,
        int $accountId,
        string $periodKey,
        string $contractVersion,
        DateTimeImmutable $startedAt,
    ): int {
        if ($contractVersion !== self::CONTRACT_VERSION) {
            throw new InvalidArgumentException('Unsupported sales audit contract version.');
        }

        $siteId = $this->siteIdForScope($companyId, $accountId);
        SalesAuditWindow::forSitePeriod($siteId, $periodKey);

        $startedAtUtc = $startedAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'INSERT INTO sales_audit_runs '
            . '(company_id,account_id,period_key,contract_version,status,started_at,updated_at) '
            . "VALUES (:company_id,:account_id,:period_key,:contract_version,'capturing',:started_at,:updated_at)"
        );
        $statement->execute([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'period_key' => $periodKey,
            'contract_version' => $contractVersion,
            'started_at' => $startedAtUtc,
            'updated_at' => $startedAtUtc,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        if ($id < 1) {
            throw new RuntimeException('Sales audit run creation did not return an id.');
        }

        return $id;
    }

    /** @return array{period_key:string,site_id:string,seller_id:string} */
    public function captureContext(int $runId, int $companyId, int $accountId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT r.period_key,a.site_id,a.external_user_id '
            . 'FROM sales_audit_runs r '
            . 'INNER JOIN meli_accounts a ON a.id = r.account_id AND a.company_id = r.company_id '
            . "WHERE r.id = :run_id AND r.company_id = :company_id AND r.account_id = :account_id "
            . "AND r.status = 'capturing' AND r.contract_version = :contract_version "
            . "AND a.status = 'connected' LIMIT 1"
        );
        $statement->execute([
            'run_id' => $runId,
            'company_id' => $companyId,
            'account_id' => $accountId,
            'contract_version' => self::CONTRACT_VERSION,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            throw new RuntimeException('Sales audit capture scope is unavailable.');
        }

        $periodKey = is_string($row['period_key'] ?? null) ? $row['period_key'] : '';
        $siteId = is_string($row['site_id'] ?? null) ? $row['site_id'] : '';
        $sellerId = is_string($row['external_user_id'] ?? null) ? $row['external_user_id'] : '';
        if ($periodKey === '' || $siteId === '' || preg_match('/^[0-9]{1,32}$/D', $sellerId) !== 1) {
            throw new RuntimeException('Sales audit capture scope is invalid.');
        }

        return [
            'period_key' => $periodKey,
            'site_id' => $siteId,
            'seller_id' => $sellerId,
        ];
    }

    public function acceptRemoteTotal(int $runId, int $companyId, int $accountId, int $remoteTotal): bool
    {
        if ($remoteTotal < 0) {
            throw new InvalidArgumentException('Sales audit remote total is invalid.');
        }

        $update = $this->pdo->prepare(
            'UPDATE sales_audit_runs SET remote_total = :remote_total '
            . 'WHERE id = :run_id AND company_id = :company_id AND account_id = :account_id '
            . "AND status = 'capturing' AND contract_version = :contract_version AND remote_total IS NULL"
        );
        $update->execute([
            'remote_total' => $remoteTotal,
            'run_id' => $runId,
            'company_id' => $companyId,
            'account_id' => $accountId,
            'contract_version' => self::CONTRACT_VERSION,
        ]);
        if ($update->rowCount() === 1) {
            return true;
        }

        $select = $this->pdo->prepare(
            'SELECT remote_total FROM sales_audit_runs '
            . 'WHERE id = :run_id AND company_id = :company_id AND account_id = :account_id '
            . "AND status = 'capturing' AND contract_version = :contract_version LIMIT 1"
        );
        $select->execute([
            'run_id' => $runId,
            'company_id' => $companyId,
            'account_id' => $accountId,
            'contract_version' => self::CONTRACT_VERSION,
        ]);
        $stored = $select->fetchColumn();
        if ($stored === false) {
            throw new RuntimeException('Sales audit capturing run is unavailable.');
        }

        return (string) $stored === (string) $remoteTotal;
    }

    public function recordObservation(
        int $runId,
        string $externalOrderId,
        DateTimeImmutable $remoteDateCreated,
    ): bool {
        $statement = $this->pdo->prepare(
            'INSERT INTO sales_audit_orders (audit_run_id,external_order_id,remote_date_created) '
            . 'VALUES (:audit_run_id,:external_order_id,:remote_date_created)'
        );

        try {
            $statement->execute([
                'audit_run_id' => $runId,
                'external_order_id' => $externalOrderId,
                'remote_date_created' => $remoteDateCreated
                    ->setTimezone(new DateTimeZone('UTC'))
                    ->format('Y-m-d H:i:s.u'),
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    /** @return array{canonical_count:int,set_hash:string} */
    public function persistCanonicalFingerprint(
        int $runId,
        int $companyId,
        int $accountId,
        SalesAuditWindow $window,
    ): array {
        $context = $this->captureContext($runId, $companyId, $accountId);
        $expectedWindow = SalesAuditWindow::forSitePeriod($context['site_id'], $context['period_key']);
        if (
            $window->periodKey !== $expectedWindow->periodKey
            || $window->canonicalStartUtc != $expectedWindow->canonicalStartUtc
            || $window->canonicalEndUtc != $expectedWindow->canonicalEndUtc
        ) {
            throw new RuntimeException('Sales audit canonical window does not match the run.');
        }

        $statement = $this->pdo->prepare(
            'SELECT external_order_id FROM sales_audit_orders '
            . 'WHERE audit_run_id = :run_id '
            . 'AND remote_date_created >= :canonical_start '
            . 'AND remote_date_created < :canonical_end '
            . 'ORDER BY external_order_id ASC'
        );
        $statement->execute([
            'run_id' => $runId,
            'canonical_start' => $window->canonicalStartUtc->format('Y-m-d H:i:s.u'),
            'canonical_end' => $window->canonicalEndUtc->format('Y-m-d H:i:s.u'),
        ]);

        $ids = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $value) {
            if (!is_string($value) || preg_match('/^[0-9]{1,32}$/D', $value) !== 1) {
                throw new RuntimeException('Sales audit canonical order id is invalid.');
            }
            $ids[] = $value;
        }

        $canonicalCount = count($ids);
        $setHash = hash('sha256', implode("\n", $ids));

        $update = $this->pdo->prepare(
            'UPDATE sales_audit_runs SET canonical_count = :canonical_count, set_hash = :set_hash '
            . 'WHERE id = :run_id AND company_id = :company_id AND account_id = :account_id '
            . 'AND period_key = :period_key '
            . "AND status = 'capturing' AND contract_version = :contract_version "
            . 'AND remote_total IS NOT NULL AND canonical_count IS NULL AND set_hash IS NULL'
        );
        $update->execute([
            'canonical_count' => $canonicalCount,
            'set_hash' => $setHash,
            'run_id' => $runId,
            'company_id' => $companyId,
            'account_id' => $accountId,
            'period_key' => $window->periodKey,
            'contract_version' => self::CONTRACT_VERSION,
        ]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('Sales audit canonical fingerprint could not be persisted.');
        }

        return [
            'canonical_count' => $canonicalCount,
            'set_hash' => $setHash,
        ];
    }

    public function transitionToRepairingIfMissing(int $runId, int $companyId, int $accountId): bool
    {
        $context = $this->captureContext($runId, $companyId, $accountId);
        $window = SalesAuditWindow::forSitePeriod($context['site_id'], $context['period_key']);
        $canonicalStart = $window->canonicalStartUtc->format('Y-m-d H:i:s.u');
        $canonicalEnd = $window->canonicalEndUtc->format('Y-m-d H:i:s.u');

        $statement = $this->pdo->prepare(
            "UPDATE sales_audit_runs r SET r.status = 'repairing', r.updated_at = UTC_TIMESTAMP(6) "
            . 'WHERE r.id = :run_id AND r.company_id = :company_id AND r.account_id = :account_id '
            . "AND r.status = 'capturing' AND r.contract_version = :contract_version "
            . 'AND r.remote_total IS NOT NULL AND r.canonical_count IS NOT NULL AND r.set_hash IS NOT NULL '
            . 'AND EXISTS ('
            . 'SELECT 1 FROM sales_audit_orders ao '
            . 'WHERE ao.audit_run_id = r.id '
            . 'AND ao.remote_date_created >= :remote_start AND ao.remote_date_created < :remote_end '
            . 'AND NOT EXISTS ('
            . 'SELECT 1 FROM orders o '
            . 'WHERE o.company_id = r.company_id AND o.account_id = r.account_id '
            . 'AND o.external_order_id = ao.external_order_id '
            . 'AND o.date_created >= :local_start AND o.date_created < :local_end'
            . '))'
        );
        $statement->execute([
            'run_id' => $runId,
            'company_id' => $companyId,
            'account_id' => $accountId,
            'contract_version' => self::CONTRACT_VERSION,
            'remote_start' => $canonicalStart,
            'remote_end' => $canonicalEnd,
            'local_start' => $canonicalStart,
            'local_end' => $canonicalEnd,
        ]);

        return $statement->rowCount() === 1;
    }

    public function nextRepairingMissingCanonicalOrderId(int $runId, int $companyId, int $accountId): ?string
    {
        $scope = $this->pdo->prepare(
            'SELECT r.period_key,a.site_id,r.canonical_count,r.set_hash '
            . 'FROM sales_audit_runs r '
            . 'INNER JOIN meli_accounts a ON a.id = r.account_id AND a.company_id = r.company_id '
            . 'WHERE r.id = :run_id AND r.company_id = :company_id AND r.account_id = :account_id '
            . "AND r.status = 'repairing' AND r.contract_version = :contract_version "
            . "AND a.status = 'connected' AND r.remote_total IS NOT NULL "
            . 'AND r.canonical_count IS NOT NULL AND r.set_hash IS NOT NULL LIMIT 1'
        );
        $scope->execute([
            'run_id' => $runId,
            'company_id' => $companyId,
            'account_id' => $accountId,
            'contract_version' => self::CONTRACT_VERSION,
        ]);
        $row = $scope->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Sales audit repairing scope is unavailable.');
        }

        $periodKey = is_string($row['period_key'] ?? null) ? $row['period_key'] : '';
        $siteId = is_string($row['site_id'] ?? null) ? $row['site_id'] : '';
        $canonicalCount = $row['canonical_count'] ?? null;
        $setHash = $row['set_hash'] ?? null;
        if (
            $periodKey === ''
            || $siteId === ''
            || (!is_int($canonicalCount) && !(is_string($canonicalCount) && ctype_digit($canonicalCount)))
            || !is_string($setHash)
            || preg_match('/^[a-f0-9]{64}$/D', $setHash) !== 1
        ) {
            throw new RuntimeException('Sales audit repairing scope is invalid.');
        }

        $window = SalesAuditWindow::forSitePeriod($siteId, $periodKey);
        $canonicalStart = $window->canonicalStartUtc->format('Y-m-d H:i:s.u');
        $canonicalEnd = $window->canonicalEndUtc->format('Y-m-d H:i:s.u');
        $statement = $this->pdo->prepare(
            'SELECT ao.external_order_id FROM sales_audit_orders ao '
            . 'WHERE ao.audit_run_id = :run_id '
            . 'AND ao.remote_date_created >= :remote_start '
            . 'AND ao.remote_date_created < :remote_end '
            . 'AND NOT EXISTS ('
            . 'SELECT 1 FROM orders o '
            . 'WHERE o.company_id = :company_id AND o.account_id = :account_id '
            . 'AND o.external_order_id = ao.external_order_id '
            . 'AND o.date_created >= :local_start AND o.date_created < :local_end'
            . ') ORDER BY ao.external_order_id ASC LIMIT 1'
        );
        $statement->execute([
            'run_id' => $runId,
            'remote_start' => $canonicalStart,
            'remote_end' => $canonicalEnd,
            'company_id' => $companyId,
            'account_id' => $accountId,
            'local_start' => $canonicalStart,
            'local_end' => $canonicalEnd,
        ]);

        $value = $statement->fetchColumn();
        if ($value === false) {
            return null;
        }
        if (!is_string($value) || preg_match('/^[0-9]{1,32}$/D', $value) !== 1) {
            throw new RuntimeException('Sales audit repairing order id is invalid.');
        }

        return $value;
    }

    public function observationCount(int $runId): int
    {
        if ($runId < 1) {
            throw new InvalidArgumentException('Sales audit run id is invalid.');
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id = :run_id'
        );
        $statement->execute(['run_id' => $runId]);
        $count = $statement->fetchColumn();

        if (!is_int($count) && !(is_string($count) && ctype_digit($count))) {
            throw new RuntimeException('Sales audit observation count is unavailable.');
        }

        return (int) $count;
    }
}
