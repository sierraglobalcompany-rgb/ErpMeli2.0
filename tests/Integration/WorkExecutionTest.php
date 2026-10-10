<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkExecution;
use App\Work\WorkRepository;
use App\Work\WorkRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class WorkExecutionTest extends TestCase
{
    public function testAutomationOffBlocksAutomaticButManualProcessesExactlyOne(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedSettingsActor($pdo);
        $config = TestDatabase::config();
        $settings = new SystemSettingsRepository($pdo);
        $settings->updateOperationalToggles(false, false, 7, 100, 1);
        $repository = new WorkRepository($pdo);
        $runner = new WorkRunner(
            Connection::fromConfig($config),
            $config->dbName . '.erp_meli2.runner.execution-test'
        );
        $execution = new WorkExecution($settings, $runner, $repository);

        $this->insertWork($pdo, 'one');
        $this->insertWork($pdo, 'two');

        $processor = $this->processor($repository);

        self::assertSame(0, $execution->runAutomatic($processor, maxItems: 10, maxSeconds: 45));
        self::assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status = 'pending'")->fetchColumn());

        self::assertSame(1, $execution->runManualOne($processor));
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status = 'done'")->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status = 'pending'")->fetchColumn());
    }

    public function testAutomaticUsesSameRunnerLockAndRunsWhenEnabled(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedSettingsActor($pdo);
        $config = TestDatabase::config();
        $settings = new SystemSettingsRepository($pdo);
        $settings->updateOperationalToggles(true, false, 7, 100, 1);
        $repository = new WorkRepository($pdo);
        $lockName = $config->dbName . '.erp_meli2.runner.shared-entry-test';

        $blockingRunner = new WorkRunner(Connection::fromConfig($config), $lockName);
        self::assertTrue($blockingRunner->acquireLock());

        $execution = new WorkExecution(
            $settings,
            new WorkRunner(Connection::fromConfig($config), $lockName),
            $repository,
        );
        $this->insertWork($pdo, 'blocked');

        $processor = $this->processor($repository);

        self::assertSame(0, $execution->runAutomatic($processor, maxItems: 10, maxSeconds: 45));
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status = 'pending'")->fetchColumn());

        $blockingRunner->releaseLock();
        self::assertSame(1, $execution->runAutomatic($processor, maxItems: 10, maxSeconds: 45));
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status = 'done'")->fetchColumn());
    }

    public function testManualUsesSameRunnerLock(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedSettingsActor($pdo);
        $config = TestDatabase::config();
        $settings = new SystemSettingsRepository($pdo);
        $repository = new WorkRepository($pdo);
        $lockName = $config->dbName . '.erp_meli2.runner.manual-entry-test';

        $blockingRunner = new WorkRunner(Connection::fromConfig($config), $lockName);
        self::assertTrue($blockingRunner->acquireLock());

        $execution = new WorkExecution(
            $settings,
            new WorkRunner(Connection::fromConfig($config), $lockName),
            $repository,
        );
        $this->insertWork($pdo, 'manual-blocked');

        self::assertSame(0, $execution->runManualOne($this->processor($repository)));
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status = 'pending'")->fetchColumn());

        $blockingRunner->releaseLock();
        self::assertSame(1, $execution->runManualOne($this->processor($repository)));
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status = 'done'")->fetchColumn());
    }

    public function testProcessorCrashReleasesLockAndNextRunRecoversWork(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedSettingsActor($pdo);
        $config = TestDatabase::config();
        $settings = new SystemSettingsRepository($pdo);
        $repository = new WorkRepository($pdo);
        $lockName = $config->dbName . '.erp_meli2.runner.crash-test';
        $this->insertWork($pdo, 'crash');

        $firstExecution = new WorkExecution(
            $settings,
            new WorkRunner(Connection::fromConfig($config), $lockName),
            $repository,
        );

        try {
            $firstExecution->runManualOne(
                static function (array $claim): void {
                    throw new RuntimeException('simulated processor crash');
                }
            );
            self::fail('The simulated processor crash must escape the runner.');
        } catch (RuntimeException $exception) {
            self::assertSame('simulated processor crash', $exception->getMessage());
        }

        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status = 'running'")->fetchColumn());

        $secondExecution = new WorkExecution(
            $settings,
            new WorkRunner(Connection::fromConfig($config), $lockName),
            $repository,
        );
        self::assertSame(1, $secondExecution->runManualOne($this->processor($repository)));

        $row = $pdo->query("SELECT status, attempts, claim_token FROM work_items WHERE resource_key = 'crash'")->fetch();
        self::assertIsArray($row);
        self::assertSame('done', $row['status']);
        self::assertSame(2, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
    }

    /**
     * @return callable(array{id:int,status:string,attempts:int,claim_token:string,claimed_at:string}): void
     */
    private function processor(WorkRepository $repository): callable
    {
        return static function (array $claim) use ($repository): void {
            $completed = $repository->completeCurrentClaim(
                (int) $claim['id'],
                (string) $claim['claim_token'],
                static function (PDO $connection): void {
                    // F2 intentionally has no business handler yet.
                }
            );
            self::assertTrue($completed);
        };
    }

    private function seedSettingsActor(PDO $pdo): void
    {
        $pdo->exec(
            "INSERT INTO users(id,email,password_hash,status) "
            . "VALUES (1,'f2-settings@example.test','not-used-in-this-test','active')"
        );
    }

    private function insertWork(PDO $pdo, string $resourceKey): void
    {
        $statement = $pdo->prepare(
            "INSERT INTO work_items "
            . "(company_id, account_id, scope_key, type, resource_key, dedupe_key, status, available_at, created_at, updated_at) "
            . "VALUES (1, 10, 'company:1:account:10', 'test.work', :resource_key, :dedupe_key, 'pending', "
            . "UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))"
        );
        $statement->execute([
            'resource_key' => $resourceKey,
            'dedupe_key' => hash('sha256', 'test.work|' . $resourceKey),
        ]);
    }
}
