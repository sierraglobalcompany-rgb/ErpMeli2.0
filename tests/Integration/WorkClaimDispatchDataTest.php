<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Work\WorkRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class WorkClaimDispatchDataTest extends TestCase
{
    public function testClaimCarriesDataNeededToDispatchOrderSync(): void
    {
        $pdo = TestDatabase::reset();
        $repository = new WorkRepository($pdo);

        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Dispatch Company','dispatch-company')");
        $companyId = (int) $pdo->lastInsertId();
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES (?, '700000004', 'MCO', 'connected')"
        );
        $account->execute([$companyId]);
        $accountId = (int) $pdo->lastInsertId();

        $repository->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            '200000000003',
            'order.sync:200000000003',
            ['order_id' => '200000000003'],
        );

        $claim = $repository->claimNext();
        self::assertIsArray($claim);
        self::assertSame($companyId, $claim['company_id']);
        self::assertSame($accountId, $claim['account_id']);
        self::assertSame('order.sync', $claim['type']);
        self::assertSame('200000000003', $claim['resource_key']);
        self::assertSame(['order_id' => '200000000003'], $claim['payload']);
        self::assertArrayNotHasKey('dedupe_key', $claim);
    }
}
