<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class OrderWebhookHttpRouteTest extends TestCase
{
    public function testValidOrdersV2HttpWebhookReturns200AndEnqueuesOrderSync(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Webhook HTTP Company','webhook-http-company')");
        $companyId = (int) $pdo->lastInsertId();
        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES (?, '700000020', 'MCO', 'connected')"
        );
        $account->execute([$companyId]);

        $previousClientId = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=987654321');
        $_ENV['MELI_CLIENT_ID'] = '987654321';

        try {
            $payload = json_encode([
                '_id' => 'evt-http-order-1',
                'resource' => '/orders/200000000020',
                'user_id' => 700000020,
                'topic' => 'orders_v2',
                'application_id' => 987654321,
                'attempts' => 1,
                'sent' => '2026-10-09T01:45:00.000Z',
                'received' => '2026-10-09T01:45:00.100Z',
            ], JSON_THROW_ON_ERROR);

            $request = (new ServerRequestFactory())
                ->createServerRequest('POST', '/webhooks/mercadolibre')
                ->withHeader('Content-Type', 'application/json');
            $request->getBody()->write($payload);
            $request->getBody()->rewind();

            $response = Bootstrap::create()->handle($request);

            self::assertSame(200, $response->getStatusCode());
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM webhook_events WHERE event_id='evt-http-order-1'")->fetchColumn());
            self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync' AND resource_key='200000000020'")->fetchColumn());
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
}
