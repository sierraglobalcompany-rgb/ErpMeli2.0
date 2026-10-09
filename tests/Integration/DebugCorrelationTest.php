<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Core\Logging\DebugRecorder;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\ReceiveOrderWebhook\OrderWebhookReceiver;
use App\Modules\Sales\SyncOrder\OrderSyncWorkProcessor;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use App\Work\WorkRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class DebugCorrelationTest extends TestCase
{
    public function testDebugOnCorrelatesWebhookWorkAndPhysicalOrderHttpWithoutSecrets(): void
    {
        $result = $this->runFlow(true);

        self::assertSame('done', $result['status']);
        self::assertSame(1, $result['request_count']);
        self::assertFileExists($result['debug_file']);

        $lines = file($result['debug_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);

        $events = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            $events[(string) ($decoded['event'] ?? '')][] = $decoded['fields'] ?? [];
        }

        $correlationId = 'work:' . $result['work_id'];
        self::assertSame($correlationId, $events['webhook.accepted'][0]['correlation_id'] ?? null);
        self::assertSame('evt-debug-correlation-1', $events['webhook.accepted'][0]['event_id'] ?? null);
        self::assertSame($result['work_id'], $events['webhook.accepted'][0]['work_id'] ?? null);
        self::assertSame('200000000404', $events['webhook.accepted'][0]['resource_id'] ?? null);

        self::assertSame($correlationId, $events['work.started'][0]['correlation_id'] ?? null);
        self::assertSame($result['work_id'], $events['work.started'][0]['work_id'] ?? null);
        self::assertSame('order.sync', $events['work.started'][0]['work_type'] ?? null);

        self::assertSame($correlationId, $events['meli.http'][0]['correlation_id'] ?? null);
        self::assertSame('orders.get', $events['meli.http'][0]['operation'] ?? null);
        self::assertSame(200, $events['meli.http'][0]['http_status'] ?? null);
        self::assertSame('success', $events['meli.http'][0]['outcome'] ?? null);
        self::assertSame('200000000404', $events['meli.http'][0]['resource_id'] ?? null);

        $content = implode("\n", $lines);
        self::assertStringNotContainsString('valid-correlation-access-token', $content);
        self::assertStringNotContainsString('unused-correlation-refresh-token', $content);
        self::assertStringNotContainsString('client-secret', $content);

        $this->removeTree($result['root']);
    }

    public function testDebugOffKeepsSameBusinessOutcomeAndWritesNoDvr(): void
    {
        $result = $this->runFlow(false);

        self::assertSame('done', $result['status']);
        self::assertSame(1, $result['request_count']);
        self::assertFileDoesNotExist($result['debug_file']);
        self::assertSame(
            1,
            (int) $result['pdo']->query("SELECT COUNT(*) FROM orders WHERE external_order_id='200000000404'")->fetchColumn(),
        );

        $this->removeTree($result['root']);
    }

    /**
     * @return array{
     *   root:string,debug_file:string,work_id:int,status:string,request_count:int,pdo:PDO
     * }
     */
    private function runFlow(bool $debugEnabled): array
    {
        $pdo = TestDatabase::reset();
        $config = TestDatabase::config();
        $cipher = new TokenCipher($config->appKey);
        [$companyId, $accountId] = $this->seedConnectedAccount($pdo, $cipher);

        $root = sys_get_temp_dir() . '/erp-meli2-debug-correlation-' . bin2hex(random_bytes(4));
        $debugDir = $root . '/debug';
        self::assertTrue(mkdir($debugDir, 0700, true));
        $recorder = new DebugRecorder($debugDir, $debugEnabled, 1024 * 1024);

        $work = new WorkRepository($pdo);
        $receiver = new OrderWebhookReceiver(
            $pdo,
            $work,
            '123456789',
            debugRecorder: $recorder,
        );
        self::assertTrue($receiver->receive([
            '_id' => 'evt-debug-correlation-1',
            'resource' => '/orders/200000000404',
            'user_id' => '700000404',
            'topic' => 'orders_v2',
            'application_id' => '123456789',
            'attempts' => 1,
            'sent' => '2026-10-09T03:00:00.000Z',
            'received' => '2026-10-09T03:00:00.100Z',
        ]));

        $workId = (int) $pdo->query(
            "SELECT id FROM work_items WHERE type='order.sync' AND resource_key='200000000404'"
        )->fetchColumn();
        self::assertGreaterThan(0, $workId);

        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $transport = new DebugCorrelationTransport();
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
            debugRecorder: $recorder,
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
        $processor = new OrderSyncWorkProcessor(
            new SyncOrderHandler($work, $client, $tokens),
            $work,
            debugRecorder: $recorder,
        );
        $runner = new WorkRunner(
            Connection::fromConfig($config),
            'erp_meli2.test.debug.correlation.' . ($debugEnabled ? 'on' : 'off'),
        );

        self::assertSame(1, $runner->run($work, $processor, maxItems: 1, maxSeconds: 5));

        return [
            'root' => $root,
            'debug_file' => $debugDir . '/debug-' . gmdate('Y-m-d') . '.jsonl',
            'work_id' => $workId,
            'status' => (string) $pdo->query('SELECT status FROM work_items WHERE id=' . $workId)->fetchColumn(),
            'request_count' => count($transport->requests),
            'pdo' => $pdo,
        ];
    }

    /** @return array{0:int,1:int} */
    private function seedConnectedAccount(PDO $pdo, TokenCipher $cipher): array
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Debug Correlation','debug-correlation')");
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES (1,'700000404','MCO','connected')"
        );
        $account->execute();
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (?,?,?,?,0)'
        );
        $tokens->execute([
            $accountId,
            $cipher->encrypt('valid-correlation-access-token'),
            $cipher->encrypt('unused-correlation-refresh-token'),
            '2030-01-01 00:00:00.000000',
        ]);

        return [1, $accountId];
    }

    private function removeTree(string $root): void
    {
        $debugDir = $root . '/debug';
        foreach (glob($debugDir . '/*') ?: [] as $path) {
            @unlink($path);
        }
        @rmdir($debugDir);
        @rmdir($root);
    }
}

final class DebugCorrelationTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string}> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url];
        if ($method !== 'GET' || $url !== 'https://api.mercadolibre.com/orders/200000000404') {
            throw new RuntimeException('Unexpected debug-correlation HTTP request.');
        }

        return new MeliTransportResponse(200, ['x-request-id' => 'req-debug-404'], json_encode([
            'id' => 200000000404,
            'status' => 'paid',
            'status_detail' => null,
            'date_created' => '2026-10-09T03:00:00.000Z',
            'date_closed' => null,
            'last_updated' => '2026-10-09T03:05:00.000Z',
            'total_amount' => 40400,
            'currency_id' => 'COP',
            'buyer' => ['id' => 800000404],
            'order_items' => [[
                'item' => [
                    'id' => 'MCO404040404',
                    'title' => 'Producto correlacionado',
                    'seller_sku' => 'DBG-404',
                ],
                'quantity' => 1,
                'unit_price' => 40400,
                'currency_id' => 'COP',
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}
