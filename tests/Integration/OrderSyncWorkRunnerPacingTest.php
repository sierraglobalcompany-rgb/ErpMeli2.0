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
use App\Work\WorkRunner;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class OrderSyncWorkRunnerPacingTest extends TestCase
{
    public function testTwoOrderSyncClaimsSharePhysicalRequestPacingAcrossRunnerCycle(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('runner-pacing-test-key');
        [$companyId, $accountId] = $this->seedConnectedAccount($pdo, $cipher);
        $transport = new TwoOrderTimedTransport();

        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 80,
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
        $processor = new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work);

        foreach (['200000000010', '200000000011'] as $orderId) {
            $work->enqueue(
                $companyId,
                $accountId,
                'company:' . $companyId . ':account:' . $accountId,
                'order.sync',
                $orderId,
                'order.sync:' . $orderId,
                ['order_id' => $orderId],
            );
        }

        $runner = new WorkRunner(
            Connection::fromConfig(TestDatabase::config()),
            'erp_meli2.test.runner.pacing',
        );

        $processed = $runner->run($work, $processor, maxItems: 2, maxSeconds: 5);

        self::assertSame(2, $processed);
        self::assertCount(2, $transport->sentAtNs);
        $gapMs = ($transport->sentAtNs[1] - $transport->sentAtNs[0]) / 1_000_000;
        self::assertGreaterThanOrEqual(
            70.0,
            $gapMs,
            'Two order.sync items in one WorkRunner cycle must share the same MeliClient pacing state.',
        );
        self::assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE status='done'")->fetchColumn());
        self::assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
    }

    /** @return array{0:int,1:int} */
    private function seedConnectedAccount(PDO $pdo, TokenCipher $cipher): array
    {
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Runner Pacing Company','runner-pacing-company')");
        $companyId = (int) $pdo->lastInsertId();
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES (?, '700000010', 'MCO', 'connected')"
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

final class TwoOrderTimedTransport implements MeliTransport
{
    /** @var list<int> */
    public array $sentAtNs = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->sentAtNs[] = hrtime(true);

        if (preg_match('~/orders/(20000000001[01])$~', $url, $matches) !== 1) {
            throw new RuntimeException('Unexpected runner pacing HTTP request.');
        }

        $orderId = $matches[1];
        $itemId = $orderId === '200000000010' ? 'MCO101010101' : 'MCO111111111';

        return new MeliTransportResponse(200, [], json_encode([
            'id' => (int) $orderId,
            'status' => 'paid',
            'status_detail' => null,
            'date_created' => '2026-10-09T01:00:00.000Z',
            'date_closed' => null,
            'last_updated' => '2026-10-09T01:30:00.000Z',
            'total_amount' => 50000,
            'currency_id' => 'COP',
            'buyer' => ['id' => 800000010],
            'order_items' => [[
                'item' => ['id' => $itemId, 'title' => 'Producto'],
                'quantity' => 1,
                'unit_price' => 50000,
                'currency_id' => 'COP',
            ]],
        ], JSON_THROW_ON_ERROR));
    }
}
