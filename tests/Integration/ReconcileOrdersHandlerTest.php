<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\ReconcileOrders\ReconcileOrdersHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class ReconcileOrdersHandlerTest extends TestCase
{
    public function testOneSearchPageEnqueuesSameOrderSyncWorkAndOnlyNextPage(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('test-reconcile-secret');
        $this->seedAccount($pdo, $cipher);

        $work = new WorkRepository($pdo);
        $scope = 'company:1:account:1';
        $from = '2026-10-07T00:00:00.000-05:00';
        $to = '2026-10-08T23:59:59.999-05:00';

        $reconcileId = $work->enqueue(
            1,
            1,
            $scope,
            'orders.reconcile',
            $from . '|' . $to . '|0',
            'orders.reconcile:' . $from . ':' . $to . ':0:2',
            ['from' => $from, 'to' => $to, 'offset' => 0, 'limit' => 2],
        );

        // Simulate a webhook that already discovered one of the same orders.
        $existingSyncId = $work->enqueue(
            1,
            1,
            $scope,
            'order.sync',
            '200000000001',
            'order.sync:200000000001',
            ['order_id' => '200000000001'],
        );

        $claim = $work->claimNext();
        self::assertIsArray($claim);
        self::assertSame($reconcileId, $claim['id']);
        self::assertSame('orders.reconcile', $claim['type']);

        $transport = new ReconcileOrdersTransport(new MeliTransportResponse(
            200,
            [],
            json_encode([
                'paging' => ['total' => 3, 'offset' => 0, 'limit' => 2],
                'results' => [
                    ['id' => 200000000001, 'buyer' => ['first_name' => 'SENSITIVE-NOT-STORED']],
                    ['id' => 200000000002],
                ],
            ], JSON_THROW_ON_ERROR),
        ));

        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
        $tokens = new OAuthRefreshService(
            $pdo,
            $pdo,
            $client,
            $cipher,
            'client-id',
            'client-secret',
            'erp2.oauth',
        );
        $handler = new ReconcileOrdersHandler($pdo, $work, $client, $tokens);

        $completed = $handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-09T02:00:00+00:00'),
        );

        self::assertTrue($completed);
        self::assertCount(1, $transport->requests, 'One reconcile work item must consume only one search page.');
        self::assertSame(
            'https://api.mercadolibre.com/orders/search?seller=99887766'
            . '&order.date_created.from=2026-10-07T00%3A00%3A00.000-05%3A00'
            . '&order.date_created.to=2026-10-08T23%3A59%3A59.999-05%3A00'
            . '&sort=date_asc&offset=0&limit=2',
            $transport->requests[0]['url'],
        );

        $currentStatus = $pdo->query('SELECT status FROM work_items WHERE id = ' . $reconcileId)->fetchColumn();
        self::assertSame('done', $currentStatus);

        $syncRows = $pdo->query(
            "SELECT id, resource_key, payload_json FROM work_items WHERE type = 'order.sync' AND status = 'pending' ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $syncRows, 'Reconciliation must dedupe against order.sync already created by webhook.');
        self::assertSame($existingSyncId, (int) $syncRows[0]['id']);
        self::assertSame('200000000001', $syncRows[0]['resource_key']);
        self::assertSame('200000000002', $syncRows[1]['resource_key']);
        self::assertSame(['order_id' => '200000000002'], json_decode((string) $syncRows[1]['payload_json'], true, 512, JSON_THROW_ON_ERROR));

        $nextRows = $pdo->query(
            "SELECT resource_key, payload_json FROM work_items WHERE type = 'orders.reconcile' AND status = 'pending' ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $nextRows);
        self::assertSame($from . '|' . $to . '|2', $nextRows[0]['resource_key']);
        self::assertSame(
            ['from' => $from, 'to' => $to, 'offset' => 2, 'limit' => 2],
            json_decode((string) $nextRows[0]['payload_json'], true, 512, JSON_THROW_ON_ERROR),
        );

        $allPayloads = (string) $pdo->query("SELECT GROUP_CONCAT(payload_json SEPARATOR '\n') FROM work_items")->fetchColumn();
        self::assertStringNotContainsString('SENSITIVE-NOT-STORED', $allPayloads);
    }

    private function seedAccount(PDO $pdo, TokenCipher $cipher): void
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (1,1,'99887766','MCO','Seller','connected')"
        );

        $statement = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (1,:access_token,:refresh_token,:expires_at,0)'
        );
        $statement->execute([
            'access_token' => $cipher->encrypt('valid-access-token'),
            'refresh_token' => $cipher->encrypt('unused-refresh-token'),
            'expires_at' => '2026-10-09 05:00:00.000000',
        ]);
    }
}

final class ReconcileOrdersTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    public function __construct(private readonly MeliTransportResponse $response)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        return $this->response;
    }
}
