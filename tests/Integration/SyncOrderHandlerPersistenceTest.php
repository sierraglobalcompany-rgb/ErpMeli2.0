<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SyncOrderHandlerPersistenceTest extends TestCase
{
    public function testExactOrderIsNormalizedAndCurrentClaimCompletesAtomically(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([$this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2)]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');

        $completed = $handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
        );

        self::assertTrue($completed);
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id=' . $claim['id'])->fetchColumn());

        $order = $pdo->query('SELECT * FROM orders')->fetch();
        self::assertIsArray($order);
        self::assertSame($companyId, (int) $order['company_id']);
        self::assertSame($accountId, (int) $order['account_id']);
        self::assertSame('200000000001', $order['external_order_id']);
        self::assertSame('paid', $order['status']);
        self::assertSame('150000.5000', $order['total_amount']);
        self::assertSame('COP', $order['currency_id']);
        self::assertSame('2026-10-09 00:05:00.000000', $order['last_updated']);

        $items = $pdo->query('SELECT external_item_id, variation_id, title, quantity, unit_price, currency_id, seller_sku FROM order_items ORDER BY id')->fetchAll();
        self::assertCount(1, $items);
        self::assertSame('MCO123456789', $items[0]['external_item_id']);
        self::assertSame('987654321', $items[0]['variation_id']);
        self::assertSame('Producto prueba', $items[0]['title']);
        self::assertSame('2.0000', $items[0]['quantity']);
        self::assertSame('75000.2500', $items[0]['unit_price']);
        self::assertSame('COP', $items[0]['currency_id']);
        self::assertSame('SKU-ERP2', $items[0]['seller_sku']);
        self::assertCount(1, $transport->requests);
    }

    public function testSaleFeePersistsExactRemotePerUnitValueWithoutMultiplyingQuantity(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([
            $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 3, '90071992547409.1234'),
        ]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');

        self::assertTrue($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
        ));

        $item = $pdo->query(
            'SELECT oi.sale_fee, oi.quantity, oi.currency_id AS item_currency_id, o.currency_id AS order_currency_id '
            . 'FROM order_items oi JOIN orders o ON o.id = oi.order_id',
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($item);
        self::assertSame('90071992547409.1234', $item['sale_fee']);
        self::assertSame('3.0000', $item['quantity']);
        self::assertSame('COP', $item['item_currency_id']);
        self::assertSame('COP', $item['order_currency_id']);
        self::assertCount(1, $transport->requests);
        self::assertSame('https://api.mercadolibre.com/orders/200000000001', $transport->requests[0]['url']);
    }

    #[DataProvider('optionalSaleFeeCases')]
    public function testMissingNullAndTrueZeroSaleFeeRemainDistinct(?string $saleFeeJson, ?string $expected): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([
            $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2, $saleFeeJson),
        ]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');

        self::assertTrue($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
        ));

        self::assertSame($expected, $pdo->query('SELECT sale_fee FROM order_items')->fetchColumn());
        self::assertCount(1, $transport->requests);
    }

    public static function optionalSaleFeeCases(): array
    {
        return [
            'missing key remains NULL' => [null, null],
            'explicit null remains NULL' => ['null', null],
            'numeric zero remains zero' => ['0', '0.0000'],
        ];
    }

    public function testMalformedSaleFeeUsesExistingOrderContractFailure(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([
            $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2, '1e2'),
        ]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');

        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
        ));

        self::assertSame('failed', $pdo->query('SELECT status FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame('meli_order_contract', $pdo->query('SELECT last_error_code FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        self::assertCount(1, $transport->requests);
    }

    public function testSaleFeePersistenceDatabaseFailureEscapesAndRollsBackCanonicalOrder(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([
            $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 3, '12.3400'),
        ]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');
        $pdo->exec(
            "CREATE TRIGGER fail_order_item_insert BEFORE INSERT ON order_items "
            . "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'simulated item insert failure'",
        );

        $failure = null;
        try {
            $handler->syncCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                $companyId,
                $accountId,
                '200000000001',
                new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
            );
        } catch (PDOException $exception) {
            $failure = $exception;
        }

        self::assertInstanceOf(PDOException::class, $failure);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        $row = $pdo->query(
            'SELECT status, attempts, claim_token, last_error_code FROM work_items WHERE id=' . $claim['id'],
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('running', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNotNull($row['claim_token']);
        self::assertNull($row['last_error_code']);
        self::assertCount(1, $transport->requests);
    }

    public function testSaleFeeReplayKeepsOneItemWithSameExactValue(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $response = $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2, '12.3400');
        $transport = new OrderQueueTransport([$response, $response]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);

        foreach (range(1, 2) as $_) {
            $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');
            self::assertTrue($handler->syncCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                $companyId,
                $accountId,
                '200000000001',
                new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
            ));
        }

        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        self::assertSame('12.3400', $pdo->query('SELECT sale_fee FROM order_items')->fetchColumn());
        self::assertCount(2, $transport->requests);
    }

    public function testUpdatedSyncReplacesCurrentSaleFeeWithoutAddingItemHistory(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([
            $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2, '10.0000'),
            $this->orderResponse('2026-10-09T00:10:00.000Z', 'paid', 2, '11.2500'),
        ]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);

        foreach (range(1, 2) as $_) {
            $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');
            self::assertTrue($handler->syncCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                $companyId,
                $accountId,
                '200000000001',
                new DateTimeImmutable('2026-10-09T00:11:00+00:00'),
            ));
        }

        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        self::assertSame('11.2500', $pdo->query('SELECT sale_fee FROM order_items')->fetchColumn());
        self::assertCount(2, $transport->requests);
    }

    public function testRepeatedAuthoritativeResponseIsIdempotent(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $response = $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2);
        $transport = new OrderQueueTransport([$response, $response]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);

        $first = $this->claimOrder($work, $companyId, $accountId, '200000000001');
        self::assertTrue($handler->syncCurrentClaim($first['id'], $first['claim_token'], $companyId, $accountId, '200000000001', new DateTimeImmutable('2026-10-09T00:10:00+00:00')));

        $second = $this->claimOrder($work, $companyId, $accountId, '200000000001');
        self::assertTrue($handler->syncCurrentClaim($second['id'], $second['claim_token'], $companyId, $accountId, '200000000001', new DateTimeImmutable('2026-10-09T00:11:00+00:00')));

        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        self::assertCount(2, $transport->requests);
    }

    public function testOlderRemoteResponseCannotDowngradeNewerLocalOrder(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([
            $this->orderResponse('2026-10-09T00:10:00.000Z', 'paid', 2),
            $this->orderResponse('2026-10-09T00:05:00.000Z', 'confirmed', 1),
        ]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);

        $newer = $this->claimOrder($work, $companyId, $accountId, '200000000001');
        self::assertTrue($handler->syncCurrentClaim($newer['id'], $newer['claim_token'], $companyId, $accountId, '200000000001', new DateTimeImmutable('2026-10-09T00:12:00+00:00')));

        $older = $this->claimOrder($work, $companyId, $accountId, '200000000001');
        self::assertTrue($handler->syncCurrentClaim($older['id'], $older['claim_token'], $companyId, $accountId, '200000000001', new DateTimeImmutable('2026-10-09T00:13:00+00:00')));

        $order = $pdo->query('SELECT status, last_updated FROM orders')->fetch();
        self::assertIsArray($order);
        self::assertSame('paid', $order['status']);
        self::assertSame('2026-10-09 00:10:00.000000', $order['last_updated']);
        self::assertSame('2.0000', $pdo->query('SELECT quantity FROM order_items')->fetchColumn());
    }

    public function testDateCreatedWithoutExplicitZoneFailsClosedWithoutSalesWrite(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $valid = $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2);
        $payload = json_decode($valid->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $payload['date_created'] = '2026-10-09T00:00:00.000';
        $transport = new OrderQueueTransport([
            new MeliTransportResponse(200, $valid->headers, json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');

        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
        ));
        self::assertSame('failed', $pdo->query('SELECT status FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame('meli_order_contract', $pdo->query('SELECT last_error_code FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
    }

    public function testImpossibleZonedDateCreatedFailsClosedWithoutSalesWrite(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $valid = $this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2);
        $payload = json_decode($valid->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $payload['date_created'] = '2026-02-30T10:00:00-05:00';
        $transport = new OrderQueueTransport([
            new MeliTransportResponse(200, $valid->headers, json_encode($payload, JSON_THROW_ON_ERROR)),
        ]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');

        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
        ));
        self::assertSame('failed', $pdo->query('SELECT status FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame('meli_order_contract', $pdo->query('SELECT last_error_code FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        self::assertCount(1, $transport->requests);
    }

    public function testFloatAtExactMoneyBoundaryFailsClosedWithoutSalesWrite(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([$this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2)]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work, false);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');

        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
        ));
        self::assertSame('failed', $pdo->query('SELECT status FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame('meli_order_contract', $pdo->query('SELECT last_error_code FROM work_items WHERE id=' . $claim['id'])->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
    }

    public function testStaleClaimCannotPersistFetchedOrder(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-sync-test-key');
        [$companyId, $accountId] = $this->seedAccountAndToken($pdo, $cipher);
        $transport = new OrderQueueTransport([$this->orderResponse('2026-10-09T00:05:00.000Z', 'paid', 2)]);
        $work = new WorkRepository($pdo);
        $handler = $this->handler($pdo, $cipher, $transport, $work);
        $claim = $this->claimOrder($work, $companyId, $accountId, '200000000001');

        $pdo->prepare("UPDATE work_items SET claim_token = :replacement WHERE id = :id")
            ->execute(['replacement' => 'ffffffffffffffffffffffffffffffff', 'id' => $claim['id']]);

        $completed = $handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $companyId,
            $accountId,
            '200000000001',
            new DateTimeImmutable('2026-10-09T00:10:00+00:00'),
        );

        self::assertFalse($completed);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
        self::assertCount(1, $transport->requests, 'Remote GET may already have happened; stale ownership must block local persistence.');
    }

    private function handler(
        PDO $pdo,
        TokenCipher $cipher,
        OrderQueueTransport $transport,
        WorkRepository $work,
        bool $preserveNumbers = true,
    ): SyncOrderHandler {
        /** @var array<string,array<string,mixed>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        if (!$preserveNumbers) {
            unset($operations['orders.get']['preserve_numbers']);
        }
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
        $tokens = new OAuthRefreshService(
            $pdo,
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $cipher,
            '123456789',
            'client-secret',
            'erp_meli2.oauth.account',
        );

        return new SyncOrderHandler($work, $client, $tokens);
    }

    /** @return array{id:int,claim_token:string} */
    private function claimOrder(WorkRepository $work, int $companyId, int $accountId, string $orderId): array
    {
        $work->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            $orderId,
            'order.sync:' . $orderId,
            ['order_id' => $orderId],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        return ['id' => $claim['id'], 'claim_token' => $claim['claim_token']];
    }

    /** @return array{0:int,1:int} */
    private function seedAccountAndToken(PDO $pdo, TokenCipher $cipher): array
    {
        $slug = 'sync-order-' . bin2hex(random_bytes(4));
        $company = $pdo->prepare('INSERT INTO companies (name, slug) VALUES (?, ?)');
        $company->execute(['Sync Order Test Co', $slug]);
        $companyId = (int) $pdo->lastInsertId();

        $account = $pdo->prepare("INSERT INTO meli_accounts (company_id, external_user_id, site_id, status) VALUES (?, '700000001', 'MCO', 'connected')");
        $account->execute([$companyId]);
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens (account_id, access_token_cipher, refresh_token_cipher, expires_at) VALUES (?, ?, ?, ?)'
        );
        $tokens->execute([
            $accountId,
            $cipher->encrypt('valid-sales-access'),
            $cipher->encrypt('unused-sales-refresh'),
            '2026-10-09 06:00:00.000000',
        ]);

        return [$companyId, $accountId];
    }

    private function orderResponse(
        string $lastUpdated,
        string $status,
        int $quantity,
        ?string $saleFeeJson = null,
    ): MeliTransportResponse
    {
        $item = [
            'item' => [
                'id' => 'MCO123456789',
                'variation_id' => 987654321,
                'title' => 'Producto prueba',
                'seller_sku' => 'SKU-ERP2',
            ],
            'quantity' => $quantity,
            'unit_price' => 75000.25,
            'currency_id' => 'COP',
        ];
        if ($saleFeeJson !== null) {
            $item['sale_fee'] = '__SALE_FEE_JSON__';
        }

        $body = json_encode([
            'id' => 200000000001,
            'status' => $status,
            'status_detail' => null,
            'date_created' => '2026-10-09T00:00:00.000Z',
            'date_closed' => null,
            'last_updated' => $lastUpdated,
            'total_amount' => 150000.5,
            'currency_id' => 'COP',
            'buyer' => ['id' => 800000001],
            'pack_id' => 300000000001,
            'order_items' => [$item],
        ], JSON_THROW_ON_ERROR);
        if ($saleFeeJson !== null) {
            $body = str_replace('"sale_fee":"__SALE_FEE_JSON__"', '"sale_fee":' . $saleFeeJson, $body);
        }

        return new MeliTransportResponse(200, ['x-request-id' => 'order-test'], $body);
    }
}

final class OrderQueueTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $response = array_shift($this->responses);
        if (!$response instanceof MeliTransportResponse) {
            throw new \RuntimeException('No queued order response.');
        }

        return $response;
    }
}
