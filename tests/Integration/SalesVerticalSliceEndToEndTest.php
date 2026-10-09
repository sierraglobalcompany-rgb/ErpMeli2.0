<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\SyncOrder\OrderSyncWorkProcessor;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use App\Work\WorkRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesVerticalSliceEndToEndTest extends TestCase
{
    public function testWebhookRunsThroughWorkerAndBecomesVisibleInTenantSalesUi(): void
    {
        $pdo = TestDatabase::reset();
        $config = TestDatabase::config();
        $cipher = new TokenCipher($config->appKey);
        [$companyId, $accountId] = $this->seedTenantAndConnectedAccount($pdo, $cipher);

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = $companyId;

        $previousClientId = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=123456789');
        $_ENV['MELI_CLIENT_ID'] = '123456789';

        try {
            $payload = json_encode([
                '_id' => 'evt-sales-e2e-1',
                'resource' => '/orders/200000000303',
                'user_id' => 700000303,
                'topic' => 'orders_v2',
                'application_id' => 123456789,
                'attempts' => 1,
                'sent' => '2026-10-09T02:00:00.000Z',
                'received' => '2026-10-09T02:00:00.100Z',
            ], JSON_THROW_ON_ERROR);

            // Duplicate delivery is a normal webhook condition; it must not duplicate active work.
            $firstWebhook = Bootstrap::create()->handle($this->jsonPost('/webhooks/mercadolibre', $payload));
            $duplicateWebhook = Bootstrap::create()->handle($this->jsonPost('/webhooks/mercadolibre', $payload));

            self::assertSame(200, $firstWebhook->getStatusCode());
            self::assertSame(200, $duplicateWebhook->getStatusCode());
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM webhook_events WHERE event_id='evt-sales-e2e-1'")->fetchColumn());
            self::assertSame(
                1,
                (int) $pdo->query(
                    "SELECT COUNT(*) FROM work_items WHERE type='order.sync' AND resource_key='200000000303' AND status='pending'"
                )->fetchColumn(),
            );

            $transport = new SalesSliceOrderTransport();
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
                Connection::fromConfig($config),
                $client,
                $cipher,
                '123456789',
                'client-secret',
                'erp_meli2.oauth.account',
            );
            $work = new WorkRepository($pdo);
            $processor = new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens));
            $runner = new WorkRunner(
                Connection::fromConfig($config),
                'erp_meli2.test.sales.vertical.slice',
            );

            $processed = $runner->run($work, $processor, maxItems: 1, maxSeconds: 5);

            self::assertSame(1, $processed);
            self::assertSame(
                'done',
                $pdo->query("SELECT status FROM work_items WHERE type='order.sync' AND resource_key='200000000303'")->fetchColumn(),
            );
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE external_order_id='200000000303'")->fetchColumn());
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM order_items')->fetchColumn());
            self::assertCount(1, $transport->requests);

            $salesResponse = Bootstrap::create()->handle(
                (new ServerRequestFactory())->createServerRequest('GET', '/sales'),
            );
            $html = (string) $salesResponse->getBody();

            self::assertSame(200, $salesResponse->getStatusCode());
            self::assertStringContainsString('200000000303', $html);
            self::assertStringContainsString('76543', $html);
            self::assertStringContainsString('paid', $html);

            // Running the queue again after completion must not refetch the finished work.
            self::assertSame(0, $runner->run($work, $processor, maxItems: 1, maxSeconds: 5));
            self::assertCount(1, $transport->requests);
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE external_order_id='200000000303'")->fetchColumn());
        } finally {
            if ($previousClientId === false) {
                putenv('MELI_CLIENT_ID');
                unset($_ENV['MELI_CLIENT_ID']);
            } else {
                putenv('MELI_CLIENT_ID=' . $previousClientId);
                $_ENV['MELI_CLIENT_ID'] = $previousClientId;
            }
        }
    }

    private function jsonPost(string $path, string $json): \Psr\Http\Message\ServerRequestInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write($json);
        $request->getBody()->rewind();
        return $request;
    }

    /** @return array{0:int,1:int} */
    private function seedTenantAndConnectedAccount(PDO $pdo, TokenCipher $cipher): array
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Sales E2E Company','sales-e2e-company')");
        $hash = (new PasswordService())->hash('secret');
        $user = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'sales-e2e@example.test',?,'active')");
        $user->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");

        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES (?, '700000303', 'MCO', 'connected')"
        );
        $account->execute([1]);
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (?,?,?,?,0)'
        );
        $tokens->execute([
            $accountId,
            $cipher->encrypt('valid-sales-e2e-access'),
            $cipher->encrypt('unused-sales-e2e-refresh'),
            '2026-10-09 08:00:00.000000',
        ]);

        return [1, $accountId];
    }
}

final class SalesSliceOrderTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        if ($method !== 'GET' || $url !== 'https://api.mercadolibre.com/orders/200000000303') {
            throw new RuntimeException('Unexpected Sales vertical-slice HTTP request.');
        }

        return new MeliTransportResponse(200, [], json_encode([
            'id' => 200000000303,
            'status' => 'paid',
            'status_detail' => null,
            'date_created' => '2026-10-09T02:00:00.000Z',
            'date_closed' => null,
            'last_updated' => '2026-10-09T02:05:00.000Z',
            'total_amount' => 76543,
            'currency_id' => 'COP',
            'buyer' => ['id' => 800000303],
            'order_items' => [[
                'item' => [
                    'id' => 'MCO303030303',
                    'title' => 'Producto E2E',
                    'seller_sku' => 'E2E-303',
                ],
                'quantity' => 1,
                'unit_price' => 76543,
                'currency_id' => 'COP',
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}
