<?php

declare(strict_types=1);

namespace App\Work;

use PDO;
use RuntimeException;
use Throwable;

final class WorkRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array{id:int,status:string,attempts:int,claim_token:string,claimed_at:string}|null
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
                'SELECT id, status, attempts, claim_token, claimed_at FROM work_items WHERE id = :id'
            );
            $fetch->execute(['id' => $workId]);
            $row = $fetch->fetch(PDO::FETCH_ASSOC);

            if (!is_array($row)) {
                throw new RuntimeException('Claimed work item could not be reloaded.');
            }

            $this->pdo->commit();

            return [
                'id' => (int) $row['id'],
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
}
