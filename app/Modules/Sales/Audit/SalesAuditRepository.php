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
