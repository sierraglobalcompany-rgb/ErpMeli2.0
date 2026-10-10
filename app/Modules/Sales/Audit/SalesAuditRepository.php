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
}
