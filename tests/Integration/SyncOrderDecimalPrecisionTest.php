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
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SyncOrderDecimalPrecisionTest extends TestCase
{
    public function testLargeJsonNumberDecimalsArePersistedWithoutFloatPrecisionLoss(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('decimal-precision-test-key');

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Decimal Company','decimal-company')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) "
            . "VALUES (1,1,'700000999','MCO','connected')"
        );
        $token = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (1,?,?,?,0)'
        );
        $token->execute([
            $cipher->encrypt('valid-decimal-access'),
            $cipher->encrypt('unused-decimal-refresh'),
            '2030-01-01 00:00:00.000000',
        ]);

        $transport = new DecimalPrecisionTransport();
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

        $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'order.sync',
            '200000000999',
            'order.sync:200000000999',
            ['order_id' => '200000000999'],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        self::assertTrue($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            '200000000999',
            new DateTimeImmutable('2026-10-09T12:00:00+00:00'),
        ));

        self::assertSame(
            '90071992547409.1234',
            $pdo->query("SELECT total_amount FROM orders WHERE external_order_id='200000000999'")->fetchColumn(),
        );
        self::assertSame('12345.6789', $pdo->query('SELECT quantity FROM order_items')->fetchColumn());
        self::assertSame('90071992547409.1234', $pdo->query('SELECT unit_price FROM order_items')->fetchColumn());
        self::assertCount(1, $transport->requests);
    }
}

final class DecimalPrecisionTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;
        if ($method !== 'GET' || $url !== 'https://api.mercadolibre.com/orders/200000000999') {
            throw new RuntimeException('Unexpected decimal precision HTTP request.');
        }

        $json = <<<'JSON'
{
  "id": 200000000999,
  "status": "paid",
  "status_detail": null,
  "date_created": "2026-10-09T11:00:00.000Z",
  "date_closed": null,
  "last_updated": "2026-10-09T11:05:00.000Z",
  "total_amount": 90071992547409.1234,
  "currency_id": "COP",
  "buyer": {"id": 800000999},
  "order_items": [{
    "item": {
      "id": "MCO999999999",
      "title": "Producto precision decimal"
    },
    "quantity": 12345.6789,
    "unit_price": 90071992547409.1234,
    "currency_id": "COP"
  }]
}
JSON;

        return new MeliTransportResponse(200, [], $json);
    }
}
