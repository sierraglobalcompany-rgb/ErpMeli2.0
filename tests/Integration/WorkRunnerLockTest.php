<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Work\WorkRunner;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class WorkRunnerLockTest extends TestCase
{
    public function testOnlyOneRunnerCanOwnNamedLockAtATime(): void
    {
        TestDatabase::reset();
        $config = TestDatabase::config();
        $lockName = $config->dbName . '.erp_meli2.runner.test';

        $runnerA = new WorkRunner(Connection::fromConfig($config), $lockName);
        $runnerB = new WorkRunner(Connection::fromConfig($config), $lockName);

        self::assertTrue($runnerA->acquireLock());
        self::assertFalse($runnerB->acquireLock());

        $runnerA->releaseLock();
        self::assertTrue($runnerB->acquireLock());
        $runnerB->releaseLock();
    }

    public function testClosingOwningConnectionReleasesNamedLock(): void
    {
        TestDatabase::reset();
        $config = TestDatabase::config();
        $lockName = $config->dbName . '.erp_meli2.runner.close-test';

        $runnerA = new WorkRunner(Connection::fromConfig($config), $lockName);
        self::assertTrue($runnerA->acquireLock());

        unset($runnerA);
        gc_collect_cycles();

        $runnerB = new WorkRunner(Connection::fromConfig($config), $lockName);
        self::assertTrue($runnerB->acquireLock());
        $runnerB->releaseLock();
    }
}
