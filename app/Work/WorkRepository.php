<?php

declare(strict_types=1);

namespace App\Work;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use PDO;
use RuntimeException;
use Throwable;

final class WorkRepository
{
    private const MAX_AUTOMATIC_ATTEMPTS = 5;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Enqueue one logical unit of work without owning the caller transaction.
     *
     * Active duplicates resolve to the existing work id through the database unique key.
     *
     * @param array<string,mixed> $payload
     * @throws JsonException
     */
    public function enqueue(
        ?int $companyId,
        ?int $accountId,
        string $scopeKey,
        string $type,
        ?string $resourceKey,
        string $logicalIdentity,
        array $payload,
        ?DateTimeImmutable $availableAt = null,
    ): int {
        $scopeKey = trim($scopeKey);
        $type = trim($type);
        $logicalIdentity = trim($logicalIdentity);

        if ($scopeKey === '' || $type === '' || $logicalIdentity === '') {
            throw new InvalidArgumentException('Work scope, type and logical identity are required.');
        }

        $availableAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $dedupeKey = hash('sha256', $logicalIdentity);

        $statement = $this->pdo->prepare(
            'INSERT INTO work_items '
            . '(company_id, account_id, scope_key, type, resource_key, dedupe_key, payload_json, status, available_at) '
            . "VALUES (:company_id, :account_id, :scope_key, :type, :resource_key, :dedupe_key, :payload_json, 'pending', :available_at) "
            . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $statement->execute([
            'company_id' => $companyId,
            'account_id' => $accountId,
            'scope_key' => $scopeKey,
            'type' => $type,
            'resource_key' => $resourceKey,
            'dedupe_key' => $dedupeKey,
            'payload_json' => $payloadJson,
            'available_at' => $availableAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ]);

        $workId = (int) $this->pdo->lastInsertId();
        if ($workId < 1) {
            throw new RuntimeException('Enqueued work id is unavailable.');
        }

        return $workId;
    }

    /** @return array{id:int,status:string}|null */
    public function latestLogicalState(string $scopeKey, string $type, string $logicalIdentity): ?array
    {
        $scopeKey = trim($scopeKey);
        $type = trim($type);
        $logicalIdentity = trim($logicalIdentity);
        if ($scopeKey === '' || $type === '' || $logicalIdentity === '') {
            throw new InvalidArgumentException('Work scope, type and logical identity are required.');
        }

        $statement = $this->pdo->prepare(
            'SELECT id,status FROM work_items '
            . 'WHERE scope_key = :scope_key AND type = :type AND dedupe_key = :dedupe_key '
            . 'ORDER BY id DESC LIMIT 1'
        );
        $statement->execute([
            'scope_key' => $scopeKey,
            'type' => $type,
            'dedupe_key' => hash('sha256', $logicalIdentity),
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'status' => (string) $row['status'],
        ];
    }

    /**
     * @return array{
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
     * }|null
     */
    public function claimNext(): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $select = $this->pdo->query(
                "SELECT id FROM work_items "
                . "WHERE status = 'pending' AND available_at <= UTC_TIMESTAMP(6) "
                . "ORDER BY available_at, id LIMIT 1 FOR UPDATE"
            );
            $id = $select->fetchColumn();

            if ($id === false) {
                $this->pdo->commit();
                return null;
            }

            $workId = (int) $id;
            $claimToken = bin2hex(random_bytes(16));
            $update = $this->pdo->prepare(
                "UPDATE work_items SET "
                . "status = 'running', attempts = attempts + 1, claim_token = :claim_token, "
                . "claimed_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6) "
                . "WHERE id = :id AND status = 'pending'"
            );
            $update->execute([
                'claim_token' => $claimToken,
                'id' => $workId,
            ]);

            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Eligible work item could not be claimed.');
            }

            $fetch = $this->pdo->prepare(
                'SELECT id, company_id, account_id, type, resource_key, payload_json, '
                . 'status, attempts, claim_token, claimed_at FROM work_items WHERE id = :id'
            );
            $fetch->execute(['id' => $workId]);
            $row = $fetch->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                throw new RuntimeException('Claimed work item could not be reloaded.');
            }

            $payload = [];
            $payloadJson = $row['payload_json'] ?? null;
            if (is_string($payloadJson) && $payloadJson !== '') {
                $decoded = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new RuntimeException('Claimed work payload is invalid.');
                }
                /** @var array<string,mixed> $decoded */
                $payload = $decoded;
            }

            $this->pdo->commit();

            return [
                'id' => (int) $row['id'],
                'company_id' => $row['company_id'] === null ? null : (int) $row['company_id'],
                'account_id' => $row['account_id'] === null ? null : (int) $row['account_id'],
                'type' => (string) $row['type'],
                'resource_key' => $row['resource_key'] === null ? null : (string) $row['resource_key'],
                'payload' => $payload,
                'status' => (string) $row['status'],
                'attempts' => (int) $row['attempts'],
                'claim_token' => (string) $row['claim_token'],
                'claimed_at' => (string) $row['claimed_at'],
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Persist local business effects and complete a work item only while the supplied claim is still current.
     *
     * @param callable(PDO): void $persist
     */
    public function completeCurrentClaim(int $workId, string $claimToken, callable $persist): bool
    {
        $this->pdo->beginTransaction();

        try {
            $current = $this->pdo->prepare(
                "SELECT id FROM work_items "
                . "WHERE id = :id AND status = 'running' AND claim_token = :claim_token "
                . 'FOR UPDATE'
            );
            $current->execute([
                'id' => $workId,
                'claim_token' => $claimToken,
            ]);

            if ($current->fetchColumn() === false) {
                $this->pdo->commit();
                return false;
            }

            $persist($this->pdo);

            $complete = $this->pdo->prepare(
                "UPDATE work_items SET "
                . "status = 'done', claim_token = NULL, claimed_at = NULL, "
                . "finished_at = UTC_TIMESTAMP(6), updated_at = UTC_TIMESTAMP(6), "
                . "last_error_code = NULL, last_error_safe = NULL "
                . "WHERE id = :id AND status = 'running' AND claim_token = :claim_token"
            );
            $complete->execute([
                'id' => $workId,
                'claim_token' => $claimToken,
            ]);

            if ($complete->rowCount() !== 1) {
                throw new RuntimeException('Current work claim changed before completion.');
            }

            $this->pdo->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    public function retryCurrentClaim(
        int $workId,
        string $claimToken,
        DateTimeImmutable $availableAt,
        string $errorCode,
        string $safeMessage,
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE work_items SET "
            . "status = IF(attempts >= " . self::MAX_AUTOMATIC_ATTEMPTS . ", 'failed', 'pending'), "
            . "available_at = :available_at, claim_token = NULL, claimed_at = NULL, "
            . "finished_at = IF(attempts >= " . self::MAX_AUTOMATIC_ATTEMPTS . ", UTC_TIMESTAMP(6), NULL), "
            . "last_error_code = :error_code, last_error_safe = :safe_message, "
            . "updated_at = UTC_TIMESTAMP(6) "
            . "WHERE id = :id AND status = 'running' AND claim_token = :claim_token"
        );
        $statement->execute([
            'available_at' => $availableAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'error_code' => $errorCode,
            'safe_message' => $safeMessage,
            'id' => $workId,
            'claim_token' => $claimToken,
        ]);

        return $statement->rowCount() === 1;
    }

    public function deferCurrentClaim(
        int $workId,
        string $claimToken,
        DateTimeImmutable $availableAt,
        string $errorCode,
        string $safeMessage,
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE work_items SET "
            . "status = 'pending', available_at = :available_at, attempts = IF(attempts > 0, attempts - 1, 0), "
            . "claim_token = NULL, claimed_at = NULL, finished_at = NULL, "
            . "last_error_code = :error_code, last_error_safe = :safe_message, updated_at = UTC_TIMESTAMP(6) "
            . "WHERE id = :id AND status = 'running' AND claim_token = :claim_token"
        );
        $statement->execute([
            'available_at' => $availableAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'error_code' => $errorCode,
            'safe_message' => $safeMessage,
            'id' => $workId,
            'claim_token' => $claimToken,
        ]);

        return $statement->rowCount() === 1;
    }

    public function failCurrentClaim(
        int $workId,
        string $claimToken,
        string $errorCode,
        string $safeMessage,
    ): bool {
        $statement = $this->pdo->prepare(
            "UPDATE work_items SET "
            . "status = 'failed', claim_token = NULL, claimed_at = NULL, "
            . "finished_at = UTC_TIMESTAMP(6), last_error_code = :error_code, last_error_safe = :safe_message, "
            . "updated_at = UTC_TIMESTAMP(6) "
            . "WHERE id = :id AND status = 'running' AND claim_token = :claim_token"
        );
        $statement->execute([
            'error_code' => $errorCode,
            'safe_message' => $safeMessage,
            'id' => $workId,
            'claim_token' => $claimToken,
        ]);

        return $statement->rowCount() === 1;
    }

    public function recoverRunning(): int
    {
        $statement = $this->pdo->prepare(
            "UPDATE work_items SET "
            . "status = IF(attempts >= " . self::MAX_AUTOMATIC_ATTEMPTS . ", 'failed', 'pending'), "
            . "available_at = IF(attempts >= " . self::MAX_AUTOMATIC_ATTEMPTS . ", available_at, UTC_TIMESTAMP(6)), "
            . "claim_token = NULL, claimed_at = NULL, "
            . "finished_at = IF(attempts >= " . self::MAX_AUTOMATIC_ATTEMPTS . ", UTC_TIMESTAMP(6), NULL), "
            . "last_error_code = IF(attempts >= " . self::MAX_AUTOMATIC_ATTEMPTS . ", 'work_attempts_exhausted', last_error_code), "
            . "last_error_safe = IF(attempts >= " . self::MAX_AUTOMATIC_ATTEMPTS . ", "
            . "'Work exhausted its automatic attempt limit after interruption.', last_error_safe), "
            . "updated_at = UTC_TIMESTAMP(6) WHERE status = 'running'"
        );
        $statement->execute();

        return $statement->rowCount();
    }

    public function purgeTerminalBefore(DateTimeImmutable $cutoff): int
    {
        $statement = $this->pdo->prepare(
            "DELETE FROM work_items "
            . "WHERE status IN ('done', 'failed') "
            . "AND finished_at IS NOT NULL AND finished_at < :cutoff"
        );
        $statement->execute([
            'cutoff' => $cutoff->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
        ]);

        return $statement->rowCount();
    }
}
