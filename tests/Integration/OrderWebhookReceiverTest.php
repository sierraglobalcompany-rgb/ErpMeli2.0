<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Sales\ReceiveOrderWebhook\OrderWebhookReceiver;
use App\Work\WorkRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class OrderWebhookReceiverTest extends TestCase
{
    public function testValidOrdersV2NotificationStoresEventAndEnqueuesOneOrderSyncAtomically(): void
    {
        $pdo = TestDatabase::reset();
        [$companyId, $accountId, $sellerId] = $this->seedAccount($pdo);
        $receiver = new OrderWebhookReceiver($pdo, new WorkRepository($pdo), '987654321');

        $accepted = $receiver->receive($this->payload($sellerId));

        self::assertTrue($accepted);
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn());
        $timestamps = $pdo->query('SELECT sent_at, received_at FROM webhook_events')->fetch();
        self::assertSame(
            ['sent_at' => '2026-10-09 01:00:00.000000', 'received_at' => '2026-10-09 01:00:00.100000'],
            $timestamps,
        );

        $work = $pdo->query("SELECT company_id, account_id, scope_key, resource_key, payload_json, status FROM work_items WHERE type = 'order.sync'")->fetch();
        self::assertIsArray($work);
        self::assertSame($companyId, (int) $work['company_id']);
        self::assertSame($accountId, (int) $work['account_id']);
        self::assertSame('company:' . $companyId . ':account:' . $accountId, $work['scope_key']);
        self::assertSame('200000000001', $work['resource_key']);
        self::assertSame(['order_id' => '200000000001'], json_decode((string) $work['payload_json'], true));
        self::assertSame('pending', $work['status']);
    }

    public function testOffsetlessWebhookTimestampsAreStoredAsNullWithoutRejectingNotification(): void
    {
        $pdo = TestDatabase::reset();
        [, , $sellerId] = $this->seedAccount($pdo);
        $receiver = new OrderWebhookReceiver($pdo, new WorkRepository($pdo), '987654321');
        $payload = $this->payload($sellerId);
        $payload['sent'] = '2026-10-09T01:00:00.000';
        $payload['received'] = '2026-10-09T01:00:00.100';

        self::assertTrue($receiver->receive($payload));
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn());

        $timestamps = $pdo->query('SELECT sent_at, received_at FROM webhook_events')->fetch();

        self::assertSame(['sent_at' => null, 'received_at' => null], $timestamps);
    }

    public function testExplicitOffsetWebhookTimestampsAreNormalizedToUtc(): void
    {
        $pdo = TestDatabase::reset();
        [, , $sellerId] = $this->seedAccount($pdo);
        $receiver = new OrderWebhookReceiver($pdo, new WorkRepository($pdo), '987654321');
        $payload = $this->payload($sellerId);
        $payload['sent'] = '2026-10-09T01:00:00.123-05:00';
        $payload['received'] = '2026-10-09T08:00:00.456+02:00';

        self::assertTrue($receiver->receive($payload));

        $timestamps = $pdo->query('SELECT sent_at, received_at FROM webhook_events')->fetch();

        self::assertSame(
            ['sent_at' => '2026-10-09 06:00:00.123000', 'received_at' => '2026-10-09 06:00:00.456000'],
            $timestamps,
        );
    }

    public function testImpossibleZonedOptionalTimestampsBecomeNullWithoutRejectingWebhook(): void
    {
        $pdo = TestDatabase::reset();
        [, , $sellerId] = $this->seedAccount($pdo);
        $work = new WorkRepository($pdo);
        $receiver = new OrderWebhookReceiver($pdo, $work, '987654321');
        $payload = $this->payload($sellerId);
        $payload['sent'] = '2026-02-30T10:00:00-05:00';
        $payload['received'] = '2026-02-30T10:00:00.123+02:00';

        self::assertTrue($receiver->receive($payload));
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn());
        self::assertSame(
            ['sent_at' => null, 'received_at' => null],
            $pdo->query('SELECT sent_at,received_at FROM webhook_events')->fetch(),
        );
        self::assertSame('pending', $pdo->query("SELECT status FROM work_items WHERE type='order.sync'")->fetchColumn());
    }

    public function testDuplicateNotificationDoesNotCreateDuplicateEventOrActiveWork(): void
    {
        $pdo = TestDatabase::reset();
        [, , $sellerId] = $this->seedAccount($pdo);
        $receiver = new OrderWebhookReceiver($pdo, new WorkRepository($pdo), '987654321');
        $payload = $this->payload($sellerId);

        self::assertTrue($receiver->receive($payload));
        self::assertTrue($receiver->receive($payload));

        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn());
    }

    public function testNewNotificationForSameOrderStillReusesExistingActiveSync(): void
    {
        $pdo = TestDatabase::reset();
        [, , $sellerId] = $this->seedAccount($pdo);
        $receiver = new OrderWebhookReceiver($pdo, new WorkRepository($pdo), '987654321');

        $first = $this->payload($sellerId);
        $second = $first;
        $second['_id'] = 'evt-order-2';
        $second['sent'] = '2026-10-09T01:05:00.000Z';

        self::assertTrue($receiver->receive($first));
        self::assertTrue($receiver->receive($second));

        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn());
    }

    public function testSameNotificationAfterCompletedSyncDoesNotCreateAnotherOrderSync(): void
    {
        $pdo = TestDatabase::reset();
        [, , $sellerId] = $this->seedAccount($pdo);
        $work = new WorkRepository($pdo);
        $receiver = new OrderWebhookReceiver($pdo, $work, '987654321');
        $payload = $this->payload($sellerId);

        self::assertTrue($receiver->receive($payload));
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        self::assertSame('order.sync', $claim['type']);
        self::assertTrue($work->completeCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            static function (\PDO $_pdo): void {},
        ));
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id = ' . $claim['id'])->fetchColumn());

        self::assertTrue($receiver->receive($payload));
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync' AND status='done'")->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync' AND status='pending'")->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync' AND status='running'")->fetchColumn());
        self::assertNull($work->claimNext());
    }

    public function testNewNotificationForSameOrderAfterCompletedSyncCreatesNewOrderSync(): void
    {
        $pdo = TestDatabase::reset();
        [, , $sellerId] = $this->seedAccount($pdo);
        $work = new WorkRepository($pdo);
        $receiver = new OrderWebhookReceiver($pdo, $work, '987654321');
        $payload = $this->payload($sellerId);

        self::assertTrue($receiver->receive($payload));
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        self::assertTrue($work->completeCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            static function (\PDO $_pdo): void {},
        ));

        $payload['_id'] = 'evt-order-2';
        $payload['sent'] = '2026-10-09T01:05:00.000Z';
        $payload['received'] = '2026-10-09T01:05:00.100Z';
        self::assertTrue($receiver->receive($payload));
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
        self::assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn());
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id = ' . $claim['id'])->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync' AND status='pending'")->fetchColumn());
        $nextClaim = $work->claimNext();
        self::assertIsArray($nextClaim);
        self::assertNotSame($claim['id'], $nextClaim['id']);
        self::assertSame('order.sync', $nextClaim['type']);
        self::assertSame(['order_id' => '200000000001'], $nextClaim['payload']);
    }

    #[DataProvider('ignoredPayloadProvider')]
    public function testUnknownOrUnsupportedNotificationIsSafelyIgnored(array $changes): void
    {
        $pdo = TestDatabase::reset();
        [, , $sellerId] = $this->seedAccount($pdo);
        $receiver = new OrderWebhookReceiver($pdo, new WorkRepository($pdo), '987654321');
        $payload = array_replace($this->payload($sellerId), $changes);

        self::assertFalse($receiver->receive($payload));
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type = 'order.sync'")->fetchColumn());
    }

    /** @return iterable<string,array{0:array<string,mixed>}> */
    public static function ignoredPayloadProvider(): iterable
    {
        yield 'wrong application' => [['application_id' => '111111111']];
        yield 'unsupported topic' => [['topic' => 'items']];
        yield 'unknown seller' => [['user_id' => '999999999']];
        yield 'invalid resource' => [['resource' => '/orders/not-numeric']];
        yield 'missing event id' => [['_id' => '']];
    }

    /** @return array<string,mixed> */
    private function payload(string $sellerId): array
    {
        return [
            '_id' => 'evt-order-1',
            'resource' => '/orders/200000000001',
            'user_id' => $sellerId,
            'topic' => 'orders_v2',
            'application_id' => '987654321',
            'attempts' => 1,
            'sent' => '2026-10-09T01:00:00.000Z',
            'received' => '2026-10-09T01:00:00.100Z',
        ];
    }

    /** @return array{0:int,1:int,2:string} */
    private function seedAccount(\PDO $pdo): array
    {
        $slug = 'webhook-test-' . bin2hex(random_bytes(4));
        $company = $pdo->prepare('INSERT INTO companies (name, slug) VALUES (?, ?)');
        $company->execute(['Webhook Test Co', $slug]);
        $companyId = (int) $pdo->lastInsertId();
        $sellerId = '700000001';

        $account = $pdo->prepare(
            "INSERT INTO meli_accounts (company_id, external_user_id, site_id, status) VALUES (?, ?, 'MCO', 'connected')"
        );
        $account->execute([$companyId, $sellerId]);

        return [$companyId, (int) $pdo->lastInsertId(), $sellerId];
    }
}
