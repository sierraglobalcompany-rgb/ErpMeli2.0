<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class WorkRetryRecoveryTest extends TestCase
{
    public function testRetryableFailureReturnsCurrentClaimToPendingForFutureTime(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);
        $workId = $this->insertEligibleWork($pdo, 'retry');
        $claim = $repository->claimNext();
        self::assertNotNull($claim);

        $availableAt = new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC'));
        $requeued = $repository->retryCurrentClaim(
            $workId,
            $claim['claim_token'],
            $availableAt,
            'remote_timeout',
            'Remote request timed out.'
        );

        self::assertTrue($requeued);
        $row = $pdo->query('SELECT status, attempts, available_at, claim_token, claimed_at, finished_at, last_error_code, last_error_safe FROM work_items WHERE id = ' . $workId)->fetch();
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertNull($row['claimed_at']);
        self::assertNull($row['finished_at']);
        self::assertSame('remote_timeout', $row['last_error_code']);
        self::assertSame('Remote request timed out.', $row['last_error_safe']);
        self::assertGreaterThan(time() + 8 * 60, strtotime((string) $row['available_at']));
        self::assertNull($repository->claimNext(), 'A retry scheduled in the future must not be immediately reclaimed.');
    }

    public function testRetryAtAutomaticAttemptCapBecomesTerminal(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);
        $workId = $this->insertEligibleWork($pdo, 'retry-cap', 4);
        $claim = $repository->claimNext();
        self::assertNotNull($claim);
        self::assertSame(5, $claim['attempts']);

        self::assertTrue($repository->retryCurrentClaim(
            $workId,
            $claim['claim_token'],
            new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC')),
            'remote_timeout',
            'Remote request timed out.'
        ));

        $row = $pdo->query('SELECT status, attempts, claim_token, claimed_at, finished_at, last_error_code, last_error_safe FROM work_items WHERE id = ' . $workId)->fetch();
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertSame(5, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertNull($row['claimed_at']);
        self::assertNotNull($row['finished_at']);
        self::assertSame('remote_timeout', $row['last_error_code']);
        self::assertSame('Remote request timed out.', $row['last_error_safe']);
    }

    public function testDeferReturnsClaimToPendingWithoutConsumingAttempt(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);
        $workId = $this->insertEligibleWork($pdo, 'defer');
        $claim = $repository->claimNext();
        self::assertNotNull($claim);
        self::assertSame(1, $claim['attempts']);

        $availableAt = new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC'));
        self::assertTrue($repository->deferCurrentClaim(
            $workId,
            $claim['claim_token'],
            $availableAt,
            'meli_rate_limited',
            'Mercado Libre operation is cooling down.'
        ));

        $row = $pdo->query('SELECT status, attempts, available_at, claim_token, claimed_at, finished_at, last_error_code, last_error_safe FROM work_items WHERE id = ' . $workId)->fetch();
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertNull($row['claimed_at']);
        self::assertNull($row['finished_at']);
        self::assertSame('meli_rate_limited', $row['last_error_code']);
        self::assertSame('Mercado Libre operation is cooling down.', $row['last_error_safe']);
        self::assertGreaterThan(time() + 8 * 60, strtotime((string) $row['available_at']));
    }

    public function testTerminalFailureMarksOnlyCurrentClaimFailed(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);
        $workId = $this->insertEligibleWork($pdo, 'terminal');
        $claim = $repository->claimNext();
        self::assertNotNull($claim);

        self::assertFalse($repository->failCurrentClaim(
            $workId,
            str_repeat('0', 32),
            'invalid_payload',
            'Payload cannot be processed.'
        ));

        $stillRunning = $pdo->query('SELECT status, claim_token FROM work_items WHERE id = ' . $workId)->fetch();
        self::assertIsArray($stillRunning);
        self::assertSame('running', $stillRunning['status']);
        self::assertSame($claim['claim_token'], $stillRunning['claim_token']);

        self::assertTrue($repository->failCurrentClaim(
            $workId,
            $claim['claim_token'],
            'invalid_payload',
            'Payload cannot be processed.'
        ));

        $failed = $pdo->query('SELECT status, claim_token, claimed_at, finished_at, last_error_code, last_error_safe FROM work_items WHERE id = ' . $workId)->fetch();
        self::assertIsArray($failed);
        self::assertSame('failed', $failed['status']);
        self::assertNull($failed['claim_token']);
        self::assertNull($failed['claimed_at']);
        self::assertNotNull($failed['finished_at']);
        self::assertSame('invalid_payload', $failed['last_error_code']);
        self::assertSame('Payload cannot be processed.', $failed['last_error_safe']);
    }

    public function testRecoverRunningReturnsOrphanedClaimsToPendingWithoutResettingAttempts(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);
        $firstId = $this->insertEligibleWork($pdo, 'crash-a');
        $firstClaim = $repository->claimNext();
        self::assertNotNull($firstClaim);
        self::assertSame($firstId, $firstClaim['id']);

        $secondId = $this->insertEligibleWork($pdo, 'crash-b');
        $secondClaim = $repository->claimNext();
        self::assertNotNull($secondClaim);
        self::assertSame($secondId, $secondClaim['id']);

        $recovered = $repository->recoverRunning();
        self::assertSame(2, $recovered);

        $rows = $pdo->query('SELECT id, status, attempts, claim_token, claimed_at FROM work_items ORDER BY id')->fetchAll();
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertSame('pending', $row['status']);
            self::assertSame(1, (int) $row['attempts']);
            self::assertNull($row['claim_token']);
            self::assertNull($row['claimed_at']);
        }

        $reclaimed = $repository->claimNext();
        self::assertNotNull($reclaimed);
        self::assertSame($firstId, $reclaimed['id']);
        self::assertSame(2, $reclaimed['attempts']);
        self::assertNotSame($firstClaim['claim_token'], $reclaimed['claim_token']);
    }

    public function testRecoverRunningAtAutomaticAttemptCapFailsInsteadOfRequeueing(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);
        $workId = $this->insertEligibleWork($pdo, 'crash-cap', 4);
        $claim = $repository->claimNext();
        self::assertNotNull($claim);
        self::assertSame(5, $claim['attempts']);

        self::assertSame(1, $repository->recoverRunning());

        $row = $pdo->query('SELECT status, attempts, claim_token, claimed_at, finished_at, last_error_code, last_error_safe FROM work_items WHERE id = ' . $workId)->fetch();
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertSame(5, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertNull($row['claimed_at']);
        self::assertNotNull($row['finished_at']);
        self::assertSame('work_attempts_exhausted', $row['last_error_code']);
        self::assertSame('Work exhausted its automatic attempt limit after interruption.', $row['last_error_safe']);
    }

    private function insertEligibleWork(PDO $pdo, string $resourceKey, int $attempts = 0): int
    {
        $statement = $pdo->prepare(
            "INSERT INTO work_items "
            . "(company_id, account_id, scope_key, type, resource_key, dedupe_key, status, available_at, attempts, created_at, updated_at) "
            . "VALUES (1, 10, 'company:1:account:10', 'order.sync', :resource_key, :dedupe_key, 'pending', "
            . "UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE, :attempts, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))"
        );
        $statement->execute([
            'resource_key' => $resourceKey,
            'dedupe_key' => hash('sha256', 'order.sync|' . $resourceKey),
            'attempts' => $attempts,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
