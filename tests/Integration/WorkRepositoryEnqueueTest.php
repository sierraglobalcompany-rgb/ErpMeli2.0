<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class WorkRepositoryEnqueueTest extends TestCase
{
    public function testEnqueueCreatesOnePendingWorkWithMinimalPayload(): void
    {
        $pdo = TestDatabase::reset();
        [$companyId, $accountId] = $this->seedAccount($pdo);
        $repository = new WorkRepository($pdo);

        $id = $repository->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            '200000000001',
            'order.sync:200000000001',
            ['order_id' => '200000000001'],
        );

        $row = $pdo->query('SELECT * FROM work_items WHERE id = ' . $id)->fetch();
        self::assertIsArray($row);
        self::assertSame('pending', $row['status']);
        self::assertSame('order.sync', $row['type']);
        self::assertSame('200000000001', $row['resource_key']);
        self::assertSame(['order_id' => '200000000001'], json_decode((string) $row['payload_json'], true));
        self::assertSame(hash('sha256', 'order.sync:200000000001'), $row['dedupe_key']);
    }

    public function testSameActiveLogicalIdentityReturnsExistingWorkInsteadOfDuplicating(): void
    {
        $pdo = TestDatabase::reset();
        [$companyId, $accountId] = $this->seedAccount($pdo);
        $repository = new WorkRepository($pdo);
        $scope = 'company:' . $companyId . ':account:' . $accountId;

        $first = $repository->enqueue($companyId, $accountId, $scope, 'order.sync', '1', 'order.sync:1', ['order_id' => '1']);
        $second = $repository->enqueue($companyId, $accountId, $scope, 'order.sync', '1', 'order.sync:1', ['order_id' => '1']);

        self::assertSame($first, $second);
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn());

        $pdo->exec("UPDATE work_items SET status = 'running', claim_token = '1234567890abcdef1234567890abcdef', claimed_at = UTC_TIMESTAMP(6) WHERE id = {$first}");
        $third = $repository->enqueue($companyId, $accountId, $scope, 'order.sync', '1', 'order.sync:1', ['order_id' => '1']);

        self::assertSame($first, $third, 'A running work item is still an active duplicate.');
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn());
    }

    public function testTerminalWorkAllowsFutureWorkForSameLogicalIdentity(): void
    {
        $pdo = TestDatabase::reset();
        [$companyId, $accountId] = $this->seedAccount($pdo);
        $repository = new WorkRepository($pdo);
        $scope = 'company:' . $companyId . ':account:' . $accountId;

        $first = $repository->enqueue($companyId, $accountId, $scope, 'order.sync', '2', 'order.sync:2', ['order_id' => '2']);
        $pdo->exec("UPDATE work_items SET status = 'done', finished_at = UTC_TIMESTAMP(6) WHERE id = {$first}");
        $second = $repository->enqueue($companyId, $accountId, $scope, 'order.sync', '2', 'order.sync:2', ['order_id' => '2']);

        self::assertNotSame($first, $second);

        $pdo->exec("UPDATE work_items SET status = 'failed', finished_at = UTC_TIMESTAMP(6) WHERE id = {$second}");
        $third = $repository->enqueue($companyId, $accountId, $scope, 'order.sync', '2', 'order.sync:2', ['order_id' => '2']);

        self::assertNotSame($second, $third);
        self::assertSame(3, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn());
    }

    public function testEnqueueParticipatesInCallerTransactionWithoutCommittingIt(): void
    {
        $pdo = TestDatabase::reset();
        [$companyId, $accountId] = $this->seedAccount($pdo);
        $repository = new WorkRepository($pdo);

        $pdo->beginTransaction();
        $repository->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            '3',
            'order.sync:3',
            ['order_id' => '3'],
            new DateTimeImmutable('+30 seconds', new DateTimeZone('UTC')),
        );

        self::assertTrue($pdo->inTransaction());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE resource_key = '3'")->fetchColumn());
        $pdo->rollBack();

        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE resource_key = '3'")->fetchColumn());
    }

    /** @return array{0:int,1:int} */
    private function seedAccount(\PDO $pdo): array
    {
        $pdo->exec("INSERT INTO companies (name) VALUES ('Sales Test Co')");
        $companyId = (int) $pdo->lastInsertId();
        $statement = $pdo->prepare(
            "INSERT INTO meli_accounts (company_id, external_user_id, site_id, status) VALUES (?, ?, 'MCO', 'connected')"
        );
        $statement->execute([$companyId, 'seller-' . $companyId]);

        return [$companyId, (int) $pdo->lastInsertId()];
    }
}
