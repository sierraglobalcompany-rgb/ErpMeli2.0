<?php

declare(strict_types=1);

namespace App\Work;

use InvalidArgumentException;
use PDO;

final class WorkRunner
{
    private bool $ownsLock = false;

    public function __construct(
        private readonly PDO $lockConnection,
        private readonly string $lockName,
    ) {
    }

    public function acquireLock(): bool
    {
        if ($this->ownsLock) {
            return true;
        }

        $statement = $this->lockConnection->prepare('SELECT GET_LOCK(:lock_name, 0)');
        $statement->execute(['lock_name' => $this->lockName]);
        $this->ownsLock = (int) $statement->fetchColumn() === 1;

        return $this->ownsLock;
    }

    public function releaseLock(): void
    {
        if (!$this->ownsLock) {
            return;
        }

        $statement = $this->lockConnection->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => $this->lockName]);
        $this->ownsLock = false;
    }

    /**
     * @param callable(array{
     *   id:int,company_id:?int,account_id:?int,type:string,resource_key:?string,payload:array<string,mixed>,
     *   status:string,attempts:int,claim_token:string,claimed_at:string
     * }): void $processor
     */
    public function run(
        WorkRepository $repository,
        callable $processor,
        int $maxItems,
        int $maxSeconds,
    ): int {
        if ($maxItems < 1) {
            throw new InvalidArgumentException('maxItems must be at least 1.');
        }
        if ($maxSeconds < 1) {
            throw new InvalidArgumentException('maxSeconds must be at least 1.');
        }
        if (!$this->acquireLock()) {
            return 0;
        }

        $startedAt = hrtime(true);
        $processed = 0;

        try {
            $repository->recoverRunning();

            while ($processed < $maxItems) {
                $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;
                if ($elapsedSeconds >= $maxSeconds) {
                    break;
                }

                $claim = $repository->claimNext();
                if ($claim === null) {
                    break;
                }

                $processor($claim);
                $processed++;
            }

            return $processed;
        } finally {
            $this->releaseLock();
        }
    }
}
