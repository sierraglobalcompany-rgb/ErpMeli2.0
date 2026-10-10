<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\Audit\SalesAuditHandler;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\SalesWorkProcessor;
use App\Modules\Sales\SyncOrder\OrderSyncWorkProcessor;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesWorkProcessorTest extends TestCase
{
    public function testProcessorDispatchesSalesAuditAndRejectsLegacyReconcileType(): void
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

        $transport = new SalesAuditProcessorTransport();
        /** @var array<string,array<string,mixed>> $operations */
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
        $audit = new SalesAuditRepository($pdo);
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new SalesAuditHandler($work, $audit, $client, $tokens),
            $work,
        );

        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        $auditWorkId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':0:50',
            ['run_id' => $runId, 'offset' => 0, 'limit' => 50],
        );
        $auditClaim = $work->claimNext();
        self::assertIsArray($auditClaim);
        $processor($auditClaim);

        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id=' . $auditWorkId)->fetchColumn());
        self::assertSame(
            1,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id=' . $runId)->fetchColumn(),
        );
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn());
        self::assertCount(1, $transport->requests);

        $legacyWorkId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'orders.reconcile',
            'legacy',
            'orders.reconcile:legacy',
            ['offset' => 0, 'limit' => 50],
        );
        $legacyClaim = $work->claimNext();
        self::assertIsArray($legacyClaim);
        $processor($legacyClaim);

        $legacy = $pdo->query(
            'SELECT status,last_error_code FROM work_items WHERE id=' . $legacyWorkId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($legacy);
        self::assertSame('failed', $legacy['status']);
        self::assertSame('unsupported_work_type', $legacy['last_error_code']);
        self::assertCount(1, $transport->requests, 'Legacy reconciliation must not reach Mercado Libre.');
    }
}

final class SalesAuditProcessorTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;

        if (str_contains($url, '/orders/search?')) {
            return new MeliTransportResponse(
                200,
                [],
                '{"paging":{"total":1,"offset":0,"limit":50},"results":['
                . '{"id":200000000099,"date_created":"2026-10-08T15:00:00-05:00"}'
                . ']}',
            );
        }

        throw new RuntimeException('Unexpected Sales work processor request: ' . $url);
    }
}
