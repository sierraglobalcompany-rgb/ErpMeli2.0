<?php

declare(strict_types=1);

namespace Tests\Integration;

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
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class OrderSyncWorkProcessorTest extends TestCase
{
    public function testProcessorMapsClaimToSyncOrderHandler(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('work-processor-test-key');
        [$companyId, $accountId] = $this->seedConnectedAccount($pdo, $cipher);
        $transport = new OneOrderTransport();

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
            '123456789',
            'client-secret',
            'erp_meli2.oauth.account',
        );
        $work = new WorkRepository($pdo);
        $handler = new SyncOrderHandler($work, $client, $tokens);
        $processor = new OrderSyncWorkProcessor($handler, $work);

        $workId = $work->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            '200000000004',
            'order.sync:200000000004',
            ['order_id' => '200000000004'],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $processor($claim);

        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id=' . $workId)->fetchColumn());
        self::assertSame(
            'paid',
            $pdo->query("SELECT status FROM orders WHERE external_order_id='200000000004'")->fetchColumn(),
        );
        self::assertCount(1, $transport->requests);
    }

    /** @return array{0:int,1:int} */
    private function seedConnectedAccount(PDO $pdo, TokenCipher $cipher): array
    {
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Processor Company','processor-company')");
        $companyId = (int) $pdo->lastInsertId();
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES (?, '700000005', 'MCO', 'connected')"
        );
        $account->execute([$companyId]);
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (?,?,?,?,0)'
        );
        $tokens->execute([
            $accountId,
            $cipher->encrypt('valid-access'),
            $cipher->encrypt('unused-refresh'),
            '2026-10-09 08:00:00.000000',
        ]);

        return [$companyId, $accountId];
    }
}

final class OneOrderTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        if ($url !== 'https://api.mercadolibre.com/orders/200000000004') {
            throw new RuntimeException('Unexpected work processor HTTP request.');
        }

        return new MeliTransportResponse(200, [], json_encode([
            'id' => 200000000004,
            'status' => 'paid',
            'status_detail' => null,
            'date_created' => '2026-10-09T01:00:00.000Z',
            'date_closed' => null,
            'last_updated' => '2026-10-09T01:30:00.000Z',
            'total_amount' => 50000,
            'currency_id' => 'COP',
            'buyer' => ['id' => 800000004],
            'order_items' => [[
                'item' => ['id' => 'MCO444444444', 'title' => 'Producto'],
                'quantity' => 1,
                'unit_price' => 50000,
                'currency_id' => 'COP',
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}
