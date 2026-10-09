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
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class ReconcileMalformedWorkTest extends TestCase
{
    public function testReconcileWithoutAccountIsTerminallyFailedAndCannotBeRecovered(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Reconcile Guard','reconcile-guard')");

        $work = new WorkRepository($pdo);
        $transport = new ReconcileMalformedGuardTransport();
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
            new TokenCipher('malformed-reconcile-test-key'),
            'client-id',
            'client-secret',
            'erp_meli2.test.malformed.reconcile.oauth',
        );
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new ReconcileOrdersHandler($pdo, $work, $client, $tokens),
            $work,
        );

        $workId = $work->enqueue(
            1,
            null,
            'company:1',
            'orders.reconcile',
            'missing-account',
            'orders.reconcile:missing-account',
            [
                'from' => '2026-10-08T00:00:00.000-05:00',
                'to' => '2026-10-08T23:59:59.999-05:00',
                'offset' => 0,
                'limit' => 50,
            ],
        );

        $claim = $work->claimNext();
        self::assertIsArray($claim);
        self::assertSame($workId, $claim['id']);

        $processor($claim);

        $row = $pdo->query(
            'SELECT status,attempts,claim_token,claimed_at,finished_at,last_error_code,last_error_safe '
            . 'FROM work_items WHERE id = ' . $workId
        )->fetch();
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertNull($row['claimed_at']);
        self::assertNotNull($row['finished_at']);
        self::assertSame('invalid_work_claim', $row['last_error_code']);
        self::assertSame('Work claim payload is invalid.', $row['last_error_safe']);
        self::assertSame(0, $work->recoverRunning());
        self::assertNull($work->claimNext(), 'Malformed reconciliation work must remain terminally failed.');
        self::assertSame([], $transport->requests, 'Malformed local work must never reach Mercado Libre HTTP.');
    }
}

final class ReconcileMalformedGuardTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;
        throw new RuntimeException('Malformed reconciliation work must not reach transport.');
    }
}
