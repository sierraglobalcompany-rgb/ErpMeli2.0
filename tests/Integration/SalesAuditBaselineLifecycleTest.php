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
use App\Modules\Sales\Audit\SalesAuditRepairHandler;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
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

final class SalesAuditBaselineLifecycleTest extends TestCase
{
    public function testNewEquivalentValidRunSupersedesPriorValidBaselineAndPrunesItsEvidence(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-baseline-lifecycle-test-key');
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

        $fingerprintHash = hash('sha256', "200000000100\n200000000101");
        $baseline = $pdo->prepare(
            'INSERT INTO sales_audit_runs '
            . '(company_id,account_id,period_key,contract_version,status,remote_total,canonical_count,set_hash,started_at,completed_at,updated_at) '
            . "VALUES (1,1,'2026-10-01',?,'valid',2,2,?,'2026-10-10 00:00:00.000000','2026-10-10 00:30:00.000000','2026-10-10 00:30:00.000000')"
        );
        $baseline->execute([SalesAuditRepository::CONTRACT_VERSION, $fingerprintHash]);
        $baselineRunId = (int) $pdo->lastInsertId();
        self::assertGreaterThan(0, $baselineRunId);

        $baselineEvidence = $pdo->prepare(
            'INSERT INTO sales_audit_orders(audit_run_id,capture_pass,external_order_id,remote_date_created) '
            . 'VALUES (?, ?, ?, ?)'
        );
        foreach (['A', 'B'] as $pass) {
            $baselineEvidence->execute([$baselineRunId, $pass, '200000000100', '2026-10-09 19:00:00.000000']);
            $baselineEvidence->execute([$baselineRunId, $pass, '200000000101', '2026-10-09 21:00:00.000000']);
        }
        self::assertSame(
            4,
            (int) $pdo->query(
                'SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id=' . $baselineRunId
            )->fetchColumn(),
        );

        $transport = new SalesAuditBaselineLifecycleTransport();
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
            'erp_meli2.test.sales.baseline.lifecycle.oauth',
        );
        $work = new WorkRepository($pdo);
        $audit = new SalesAuditRepository($pdo);
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new SalesAuditHandler($work, $audit, $client, $tokens),
            new SalesAuditRepairHandler($audit, $work),
            $audit,
            $work,
        );

        $newRunId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertNotSame($baselineRunId, $newRunId);
        self::assertTrue($audit->acceptRemoteTotal($newRunId, 1, 1, 2));
        foreach ([
            ['200000000100', '2026-10-09T14:00:00-05:00'],
            ['200000000101', '2026-10-09T16:00:00-05:00'],
        ] as [$orderId, $dateCreated]) {
            self::assertTrue($audit->recordObservation(
                $newRunId,
                $orderId,
                new DateTimeImmutable($dateCreated),
                'A',
            ));
        }

        $window = SalesAuditWindow::forSitePeriod('MCO', '2026-10-01');
        $newFingerprint = $audit->persistCanonicalFingerprint($newRunId, 1, 1, $window);
        self::assertSame(2, $newFingerprint['canonical_count']);
        self::assertSame($fingerprintHash, $newFingerprint['set_hash']);

        $insertOrder = $pdo->prepare(
            "INSERT INTO orders(company_id,account_id,external_order_id,status,date_created,total_amount,currency_id) "
            . "VALUES (1,1,?,'paid',?,'1000.0000','COP')"
        );
        $insertOrder->execute(['200000000100', '2026-10-09 19:00:00.000000']);
        $insertOrder->execute(['200000000101', '2026-10-09 21:00:00.000000']);
        self::assertSame('confirming', $audit->advanceCapturedRun($newRunId, 1, 1));

        self::assertTrue($audit->recordObservation(
            $newRunId,
            '200000000100',
            new DateTimeImmutable('2026-10-09T14:00:00-05:00'),
            'B',
        ));

        $confirmWorkId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $newRunId,
            'sales.audit:' . $newRunId . ':confirm:1:1',
            ['run_id' => $newRunId, 'offset' => 1, 'limit' => 1, 'remote_total' => 2],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        $processor($claim);

        self::assertSame(
            'done',
            $pdo->query('SELECT status FROM work_items WHERE id=' . $confirmWorkId)->fetchColumn(),
        );
        self::assertSame(
            'valid',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id=' . $newRunId)->fetchColumn(),
        );
        self::assertSame(
            1,
            (int) $pdo->query(
                "SELECT COUNT(*) FROM sales_audit_runs WHERE company_id=1 AND account_id=1 "
                . "AND period_key='2026-10-01' AND contract_version='seller-search-v1' AND status='valid'"
            )->fetchColumn(),
            'Equivalent valid replacement must leave exactly one durable valid baseline.',
        );
        self::assertSame(
            0,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_runs WHERE id=' . $baselineRunId)->fetchColumn(),
            'The superseded equivalent baseline run should be removed.',
        );
        self::assertSame(
            0,
            (int) $pdo->query(
                'SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id=' . $baselineRunId
            )->fetchColumn(),
            'Superseded equivalent baseline evidence should be pruned by replacement.',
        );
        self::assertSame(2, $audit->observationCount($newRunId, 'A'));
        self::assertSame(2, $audit->observationCount($newRunId, 'B'));
        self::assertCount(1, $transport->requests);
    }

    public function testNewInternallyConfirmedDifferentFingerprintPreservesPriorBaselineAndEndsAttention(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-baseline-lifecycle-test-key');
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

        $baselineHash = hash('sha256', "200000000100\n200000000101");
        $baseline = $pdo->prepare(
            'INSERT INTO sales_audit_runs '
            . '(company_id,account_id,period_key,contract_version,status,remote_total,canonical_count,set_hash,started_at,completed_at,updated_at) '
            . "VALUES (1,1,'2026-10-01',?,'valid',2,2,?,'2026-10-10 00:00:00.000000','2026-10-10 00:30:00.000000','2026-10-10 00:30:00.000000')"
        );
        $baseline->execute([SalesAuditRepository::CONTRACT_VERSION, $baselineHash]);
        $baselineRunId = (int) $pdo->lastInsertId();
        self::assertGreaterThan(0, $baselineRunId);

        $baselineEvidence = $pdo->prepare(
            'INSERT INTO sales_audit_orders(audit_run_id,capture_pass,external_order_id,remote_date_created) '
            . 'VALUES (?, ?, ?, ?)'
        );
        foreach (['A', 'B'] as $pass) {
            $baselineEvidence->execute([$baselineRunId, $pass, '200000000100', '2026-10-09 19:00:00.000000']);
            $baselineEvidence->execute([$baselineRunId, $pass, '200000000101', '2026-10-09 21:00:00.000000']);
        }

        $transport = new SalesAuditBaselineLifecycleTransport(
            '200000000102',
            '2026-10-09T16:30:00-05:00',
        );
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
            'erp_meli2.test.sales.baseline.lifecycle.divergent.oauth',
        );
        $work = new WorkRepository($pdo);
        $audit = new SalesAuditRepository($pdo);
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new SalesAuditHandler($work, $audit, $client, $tokens),
            new SalesAuditRepairHandler($audit, $work),
            $audit,
            $work,
        );

        $newRunId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertNotSame($baselineRunId, $newRunId);
        self::assertTrue($audit->acceptRemoteTotal($newRunId, 1, 1, 2));
        foreach ([
            ['200000000100', '2026-10-09T14:00:00-05:00'],
            ['200000000102', '2026-10-09T16:30:00-05:00'],
        ] as [$orderId, $dateCreated]) {
            self::assertTrue($audit->recordObservation(
                $newRunId,
                $orderId,
                new DateTimeImmutable($dateCreated),
                'A',
            ));
        }

        $window = SalesAuditWindow::forSitePeriod('MCO', '2026-10-01');
        $newFingerprint = $audit->persistCanonicalFingerprint($newRunId, 1, 1, $window);
        self::assertSame(2, $newFingerprint['canonical_count']);
        self::assertSame(hash('sha256', "200000000100\n200000000102"), $newFingerprint['set_hash']);
        self::assertNotSame($baselineHash, $newFingerprint['set_hash']);

        $insertOrder = $pdo->prepare(
            "INSERT INTO orders(company_id,account_id,external_order_id,status,date_created,total_amount,currency_id) "
            . "VALUES (1,1,?,'paid',?,'1000.0000','COP')"
        );
        $insertOrder->execute(['200000000100', '2026-10-09 19:00:00.000000']);
        $insertOrder->execute(['200000000102', '2026-10-09 21:30:00.000000']);
        self::assertSame('confirming', $audit->advanceCapturedRun($newRunId, 1, 1));

        self::assertTrue($audit->recordObservation(
            $newRunId,
            '200000000100',
            new DateTimeImmutable('2026-10-09T14:00:00-05:00'),
            'B',
        ));

        $confirmWorkId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $newRunId,
            'sales.audit:' . $newRunId . ':confirm:1:1',
            ['run_id' => $newRunId, 'offset' => 1, 'limit' => 1, 'remote_total' => 2],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);
        $processor($claim);

        self::assertSame(
            'done',
            $pdo->query('SELECT status FROM work_items WHERE id=' . $confirmWorkId)->fetchColumn(),
        );
        self::assertSame(
            'valid',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id=' . $baselineRunId)->fetchColumn(),
            'A divergent audit must preserve the prior valid baseline.',
        );
        self::assertSame(
            4,
            (int) $pdo->query(
                'SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id=' . $baselineRunId
            )->fetchColumn(),
            'Prior baseline A+B evidence must remain durable when the new fingerprint differs.',
        );
        self::assertSame(
            'attention',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id=' . $newRunId)->fetchColumn(),
            'An internally confirmed run that differs from the valid baseline must end attention.',
        );
        self::assertSame(2, $audit->observationCount($newRunId, 'A'));
        self::assertSame(2, $audit->observationCount($newRunId, 'B'));
        self::assertSame(
            1,
            (int) $pdo->query(
                "SELECT COUNT(*) FROM sales_audit_runs WHERE company_id=1 AND account_id=1 "
                . "AND period_key='2026-10-01' AND contract_version='seller-search-v1' AND status='valid'"
            )->fetchColumn(),
            'The prior baseline must remain the only valid baseline until divergence is resolved.',
        );
        self::assertSame(
            0,
            (int) $pdo->query(
                "SELECT COUNT(*) FROM work_items WHERE id <> " . $confirmWorkId
                . " AND type IN ('sales.audit','order.sync')"
            )->fetchColumn(),
            'Terminal divergent confirmation must not enqueue continuation or order.sync work.',
        );
        self::assertCount(1, $transport->requests);
    }
}

final class SalesAuditBaselineLifecycleTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    public function __construct(
        private readonly string $terminalOrderId = '200000000101',
        private readonly string $terminalDateCreated = '2026-10-09T16:00:00-05:00',
    ) {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;

        if (str_contains($url, '/orders/search?')) {
            return new MeliTransportResponse(
                200,
                [],
                '{"paging":{"total":2,"offset":1,"limit":1},"results":['
                . '{"id":' . $this->terminalOrderId . ',"date_created":"' . $this->terminalDateCreated . '"}'
                . ']}',
            );
        }

        throw new RuntimeException('Unexpected Sales audit baseline lifecycle request: ' . $url);
    }
}
