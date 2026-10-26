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

    public function testRunnerTerminallyFailsScalarJsonAndContinuesWithinClaimLimit(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);

        $scalarIds = [];
        foreach (['123', 'null', '"texto"', 'true'] as $index => $payloadJson) {
            $scalarIds[] = $this->insertWork(
                $pdo,
                'scalar-' . $index,
                'UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE',
                $payloadJson,
            );
        }
        $arrayId = $this->insertWork($pdo, 'array-successor', 'UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE', '[7,"valid"]');
        $objectId = $this->insertWork($pdo, 'object-successor', 'UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE', '{"valid":true}');
        $otherCompanyId = $this->insertWork($pdo, 'other-company', 'UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE', '{"valid":true}', 2);

        $processedIds = [];
        $processedPayloads = [];
        $runner = new \App\Work\WorkRunner($pdo, 'erp2-test-invalid-json-claim-limit');
        $processed = null;
        $error = null;
        try {
            $processed = $runner->run(
                $repository,
                static function (array $claim) use ($repository, &$processedIds, &$processedPayloads): void {
                    $processedIds[] = $claim['id'];
                    $processedPayloads[] = $claim['payload'];
                    $repository->completeCurrentClaim(
                        $claim['id'],
                        $claim['claim_token'],
                        static fn (\PDO $connection) => null,
                    );
                },
                maxItems: 6,
                maxSeconds: 5,
            );
        } catch (\Throwable $exception) {
            $error = $exception;
        }

        $rows = $pdo->query(
            'SELECT id, status, attempts, claim_token, claimed_at, (finished_at IS NOT NULL) AS is_finished, '
            . 'last_error_code, last_error_safe FROM work_items ORDER BY id',
        )->fetchAll(\PDO::FETCH_ASSOC);
        $states = array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'status' => (string) $row['status'],
                'attempts' => (int) $row['attempts'],
                'claim_token' => $row['claim_token'],
                'claimed_at' => $row['claimed_at'],
                'finished' => (int) $row['is_finished'],
                'last_error_code' => $row['last_error_code'],
                'last_error_safe' => $row['last_error_safe'],
            ],
            $rows,
        );

        $expectedStates = [];
        foreach ($scalarIds as $id) {
            $expectedStates[] = [
                'id' => $id,
                'status' => 'failed',
                'attempts' => 1,
                'claim_token' => null,
                'claimed_at' => null,
                'finished' => 1,
                'last_error_code' => 'invalid_payload_json',
                'last_error_safe' => 'Work payload must be a JSON array or object.',
            ];
        }
        foreach ([$arrayId, $objectId] as $id) {
            $expectedStates[] = [
                'id' => $id,
                'status' => 'done',
                'attempts' => 1,
                'claim_token' => null,
                'claimed_at' => null,
                'finished' => 1,
                'last_error_code' => null,
                'last_error_safe' => null,
            ];
        }
        $expectedStates[] = [
            'id' => $otherCompanyId,
            'status' => 'pending',
            'attempts' => 0,
            'claim_token' => null,
            'claimed_at' => null,
            'finished' => 0,
            'last_error_code' => null,
            'last_error_safe' => null,
        ];

        self::assertSame($expectedStates, $states, 'Scalar JSON must become terminal while successors remain processable.');
        self::assertNull($error);
        self::assertSame(2, $processed, 'Discarded scalar claims consume maxItems but not the processed count.');
        self::assertSame([$arrayId, $objectId], $processedIds);
        self::assertSame([[7, 'valid'], ['valid' => true]], $processedPayloads);
        foreach ($scalarIds as $index => $id) {
            self::assertStringNotContainsString(
                ['123', 'null', 'texto', 'true'][$index],
                (string) $states[$index]['last_error_safe'],
                'Safe error data must not disclose the rejected payload.',
            );
        }
    }

    public function testSqlFailureWhileTerminallyDiscardingScalarRollsBackAndPropagates(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);
        $workId = $this->insertWork($pdo, 'injected-sql-failure', 'UTC_TIMESTAMP(6) - INTERVAL 1 MINUTE', '123');
        $pdo->exec(
            "CREATE TRIGGER reject_invalid_payload_transition BEFORE UPDATE ON work_items "
            . "FOR EACH ROW BEGIN IF NEW.last_error_code = 'invalid_payload_json' THEN "
            . "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'injected terminal transition failure'; "
            . "END IF; END",
        );

        $exception = null;
        try {
            $repository->claimNext();
        } catch (\Throwable $failure) {
            $exception = $failure;
        }
        self::assertInstanceOf(\PDOException::class, $exception);
        self::assertStringContainsString('injected terminal transition failure', $exception->getMessage());

        $statement = $pdo->prepare(
            'SELECT status, attempts, claim_token, claimed_at, finished_at, last_error_code '
            . 'FROM work_items WHERE id = :id',
        );
        $statement->execute(['id' => $workId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertNull($row['claimed_at']);
        self::assertNull($row['finished_at']);
        self::assertNull($row['last_error_code']);
    }

    private function insertWork(
        \PDO $pdo,
        string $resourceKey,
        string $availableExpression,
        ?string $payloadJson = null,
        int $companyId = 1,
    ): int
    {
        $sql = sprintf(
            'INSERT INTO work_items '
            . '(company_id, account_id, scope_key, type, resource_key, dedupe_key, payload_json, status, '
            . 'available_at, created_at, updated_at) '
            . "VALUES (:company_id, 10, :scope_key, 'order.sync', :resource_key, :dedupe_key, :payload_json, "
            . "'pending', %s, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))",
            $availableExpression
        );
        $statement = $pdo->prepare($sql);
        $statement->execute([
            'company_id' => $companyId,
            'scope_key' => 'company:' . $companyId . ':account:10',
            'resource_key' => $resourceKey,
            'dedupe_key' => hash('sha256', 'order.sync|' . $resourceKey),
            'payload_json' => $payloadJson,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
