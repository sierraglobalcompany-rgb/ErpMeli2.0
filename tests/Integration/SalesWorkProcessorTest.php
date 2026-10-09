<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\ReconcileOrders\ReconcileOrdersHandler;
use App\Modules\Sales\SalesWorkProcessor;
use App\Modules\Sales\SyncOrder\OrderSyncWorkProcessor;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesWorkProcessorTest extends TestCase
{
    public function testSameProcessorDispatchesReconcileThenGeneratedOrderSync(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-processor-test-key');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Sales','sales')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) "
            . "VALUES (1,1,'99887766','MCO','connected')"
        );
        $token = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (1,?,?,?,0)'
        );
        $token->execute([
            $cipher->encrypt('valid-access'),
            $cipher->encrypt('unused-refresh'),
            '2030-01-01 00:00:00.000000',
        ]);

        $transport = new SalesWorkSequenceTransport();
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
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $cipher,
            'client-id',
            'client-secret',
            'erp_meli2.test.sales.processor.oauth',
        );
        $work = new WorkRepository($pdo);
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens)),
            new ReconcileOrdersHandler($pdo, $work, $client, $tokens),
        );

        $from = '2026-10-08T00:00:00.000-05:00';
        $to = '2026-10-08T23:59:59.999-05:00';
        $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'orders.reconcile',
            $from . '|' . $to . '|0',
            'orders.reconcile:' . $from . ':' . $to . ':0:50',
            ['from' => $from, 'to' => $to, 'offset' => 0, 'limit' => 50],
        );

        $reconcileClaim = $work->claimNext();
        self::assertIsArray($reconcileClaim);
        $processor($reconcileClaim);

        $orderClaim = $work->claimNext();
        self::assertIsArray($orderClaim);
        self::assertSame('order.sync', $orderClaim['type']);
        $processor($orderClaim);

        self::assertCount(2, $transport->requests);
        self::assertStringContainsString('/orders/search?', $transport->requests[0]);
        self::assertSame('https://api.mercadolibre.com/orders/200000000099', $transport->requests[1]);
        self::assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status='done'")->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE external_order_id='200000000099'")->fetchColumn());
    }
}

final class SalesWorkSequenceTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;

        if (str_contains($url, '/orders/search?')) {
            return new MeliTransportResponse(200, [], json_encode([
                'paging' => ['total' => 1, 'offset' => 0, 'limit' => 50],
                'results' => [['id' => 200000000099]],
            ], JSON_THROW_ON_ERROR));
        }

        if ($url === 'https://api.mercadolibre.com/orders/200000000099') {
            return new MeliTransportResponse(200, [], json_encode([
                'id' => 200000000099,
                'status' => 'paid',
                'status_detail' => null,
                'date_created' => '2026-10-08T15:00:00.000Z',
                'date_closed' => null,
                'last_updated' => '2026-10-08T15:05:00.000Z',
                'total_amount' => 123456,
                'currency_id' => 'COP',
                'buyer' => ['id' => 800000099],
                'order_items' => [[
                    'item' => ['id' => 'MCO999999999', 'title' => 'Producto conciliado'],
                    'quantity' => 1,
                    'unit_price' => 123456,
                    'currency_id' => 'COP',
                ]],
            ], JSON_THROW_ON_ERROR));
        }

        throw new RuntimeException('Unexpected Sales work processor request: ' . $url);
    }
}
