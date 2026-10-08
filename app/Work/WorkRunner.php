<?php

declare(strict_types=1);

namespace App\Work;

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
}
