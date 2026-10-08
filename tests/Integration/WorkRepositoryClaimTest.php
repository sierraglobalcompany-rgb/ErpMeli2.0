<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Work\WorkRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class WorkRepositoryClaimTest extends TestCase
{
    public function testClaimsOldestEligiblePendingWorkAndIgnoresFutureWork(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);

        $this->insertWork($pdo, 'future', 'UTC_TIMESTAMP(6) + INTERVAL 1 HOUR');
        $newerEligibleId = $this->insertWork($pdo, 'newer', 'UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE');
        $oldestEligibleId = $this->insertWork($pdo, 'oldest', 'UTC_TIMESTAMP(6) - INTERVAL 2 MINUTE');

        $claim = $repository->claimNext();

        self::assertNotNull($claim);
        self::assertSame($oldestEligibleId, $claim['id']);
        self::assertSame('running', $claim['status']);
        self::assertSame(1, $claim['attempts']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $claim['claim_token']);
        self::assertNotSame('', $claim['claimed_at']);

        $row = $pdo->query('SELECT status, attempts, claim_token, claimed_at FROM work_items WHERE id = ' . $oldestEligibleId)->fetch();
        self::assertIsArray($row);
        self::assertSame('running', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertSame($claim['claim_token'], $row['claim_token']);
        self::assertNotNull($row['claimed_at']);

        $second = $repository->claimNext();
        self::assertNotNull($second);
        self::assertSame($newerEligibleId, $second['id']);

        $future = $pdo->query("SELECT status, attempts, claim_token FROM work_items WHERE resource_key = 'future'")->fetch();
        self::assertIsArray($future);
        self::assertSame('pending', $future['status']);
        self::assertSame(0, (int) $future['attempts']);
        self::assertNull($future['claim_token']);
    }

    public function testReturnsNullWhenNoWorkIsEligible(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);

        $this->insertWork($pdo, 'future-only', 'UTC_TIMESTAMP(6) + INTERVAL 1 HOUR');

        self::assertNull($repository->claimNext());
    }

    private function insertWork(\PDO $pdo, string $resourceKey, string $availableExpression): int
    {
        $sql = sprintf(
            "INSERT INTO work_items (company_id, account_id, scope_key, type, resource_key, dedupe_key, status, available_at, created_at, updated_at) "
            . "VALUES (1, 10, 'company:1:account:10', 'order.sync', :resource_key, :dedupe_key, 'pending', %s, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            $availableExpression
        );
        $statement = $pdo->prepare($sql);
        $statement->execute([
            'resource_key' => $resourceKey,
            'dedupe_key' => hash('sha256', 'order.sync|' . $resourceKey),
        ]);

        return (int) $pdo->lastInsertId();
    }
}
