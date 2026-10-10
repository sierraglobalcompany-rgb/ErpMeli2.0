<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\Audit\SalesAuditHandler;
use App\Modules\Sales\Audit\SalesAuditRepository;
use App\Modules\Sales\Audit\SalesAuditWindow;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SalesAuditTerminalFingerprintTest extends TestCase
{
    public function testTerminalHandlerPersistsFingerprintTransitionsToRepairingAndEnqueuesOneContinuation(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-terminal-fingerprint-secret');
        $this->seedAccountAndToken($pdo, $cipher);

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 3));
        self::assertTrue($audit->recordObservation(
            $runId,
            '200000000001',
            new DateTimeImmutable('2026-10-01T04:30:00+00:00'),
        ));
        self::assertTrue($audit->recordObservation(
            $runId,
            '200000000002',
            new DateTimeImmutable('2026-10-01T05:00:00+00:00'),
        ));

        $work = new WorkRepository($pdo);
        $workId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':2:2',
            ['run_id' => $runId, 'offset' => 2, 'limit' => 2],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $handler = $this->handler(
            $pdo,
            $work,
            $audit,
            new SalesAuditTerminalFingerprintTransport(new MeliTransportResponse(
                200,
                [],
                '{"paging":{"total":3,"offset":2,"limit":2},"results":['
                . '{"id":200000000003,"date_created":"2026-11-01T05:30:00+00:00"}'
                . ']}',
            )),
            $cipher,
        );

        self::assertTrue($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        $row = $pdo->query(
            'SELECT canonical_count,set_hash,status FROM sales_audit_runs WHERE id = ' . $runId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('1', (string) $row['canonical_count']);
        self::assertSame(
            '04c877d65985ebec0e96d15636a1a5c7e3d9e7c46832a6d45ed973f6d2de4335',
            $row['set_hash'],
        );
        self::assertSame('repairing', $row['status']);
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id = ' . $workId)->fetchColumn());

        $continuations = $pdo->query(
            "SELECT status,resource_key,payload_json FROM work_items "
            . "WHERE type='sales.audit' AND id <> " . $workId . ' ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $continuations);
        self::assertSame('pending', $continuations[0]['status']);
        self::assertSame((string) $runId, $continuations[0]['resource_key']);
        self::assertSame(
            ['run_id' => $runId],
            json_decode((string) $continuations[0]['payload_json'], true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
            'CAPTURE must never fan out order.sync directly.',
        );
    }

    public function testTerminalCaptureWithNoMissingLocalOrdersTransitionsDirectlyToConfirming(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-terminal-complete-local-secret');
        $this->seedAccountAndToken($pdo, $cipher);
        $this->insertLocalOrder($pdo, '200000000001', '2026-10-10 15:00:00.000000');

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        $work = new WorkRepository($pdo);
        $workId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':0:50',
            ['run_id' => $runId, 'offset' => 0, 'limit' => 50],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $handler = $this->handler(
            $pdo,
            $work,
            $audit,
            new SalesAuditTerminalFingerprintTransport(new MeliTransportResponse(
                200,
                [],
                '{"paging":{"total":1,"offset":0,"limit":50},"results":['
                . '{"id":200000000001,"date_created":"2026-10-10T10:00:00-05:00"}'
                . ']}',
            )),
            $cipher,
        );

        self::assertTrue($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        self::assertSame(
            'confirming',
            $pdo->query('SELECT status FROM sales_audit_runs WHERE id = ' . $runId)->fetchColumn(),
        );
        self::assertSame('done', $pdo->query('SELECT status FROM work_items WHERE id = ' . $workId)->fetchColumn());
        self::assertSame(
            1,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn(),
            'No repair continuation is needed when local coverage is already complete.',
        );
        self::assertSame(
            0,
            (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='order.sync'")->fetchColumn(),
        );
    }

    public function testFingerprintPersistenceFailureRollsBackTerminalObservationAndFailsAsAuditState(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sales-audit-terminal-fingerprint-rollback-secret');
        $this->seedAccountAndToken($pdo, $cipher);

        $audit = new SalesAuditRepository($pdo);
        $runId = $audit->createCapturingRun(
            1,
            1,
            '2026-10-01',
            SalesAuditRepository::CONTRACT_VERSION,
            new DateTimeImmutable('2026-10-10T01:00:00+00:00'),
        );
        self::assertTrue($audit->acceptRemoteTotal($runId, 1, 1, 2));
        self::assertTrue($audit->recordObservation(
            $runId,
            '200000000001',
            new DateTimeImmutable('2026-10-10T10:00:00+00:00'),
        ));
        $fingerprint = $audit->persistCanonicalFingerprint(
            $runId,
            1,
            1,
            SalesAuditWindow::forSitePeriod('MCO', '2026-10-01'),
        );

        $work = new WorkRepository($pdo);
        $workId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'sales.audit',
            (string) $runId,
            'sales.audit:' . $runId . ':1:1',
            ['run_id' => $runId, 'offset' => 1, 'limit' => 1],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $handler = $this->handler(
            $pdo,
            $work,
            $audit,
            new SalesAuditTerminalFingerprintTransport(new MeliTransportResponse(
                200,
                [],
                '{"paging":{"total":2,"offset":1,"limit":1},"results":['
                . '{"id":200000000002,"date_created":"2026-10-11T10:00:00+00:00"}'
                . ']}',
            )),
            $cipher,
        );

        self::assertFalse($handler->processCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            1,
            1,
            $claim['payload'],
            new DateTimeImmutable('2026-10-10T02:00:00+00:00'),
        ));

        $workRow = $pdo->query(
            'SELECT status,last_error_code FROM work_items WHERE id = ' . $workId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($workRow);
        self::assertSame('failed', $workRow['status']);
        self::assertSame(
            1,
            (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_orders WHERE audit_run_id = ' . $runId)->fetchColumn(),
            'Internal lifecycle failure must roll back the terminal observation.',
        );

        $runRow = $pdo->query(
            'SELECT status,canonical_count,set_hash FROM sales_audit_runs WHERE id = ' . $runId
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($runRow);
        self::assertSame('capturing', $runRow['status']);
        self::assertSame((string) $fingerprint['canonical_count'], (string) $runRow['canonical_count']);
        self::assertSame($fingerprint['set_hash'], $runRow['set_hash']);
        self::assertSame(
            'sales_audit_state',
            $workRow['last_error_code'],
            'Internal audit lifecycle failure must not be blamed on Mercado Libre contract data.',
        );
    }

    private function handler(
        PDO $pdo,
        WorkRepository $work,
        SalesAuditRepository $audit,
        MeliTransport $transport,
        TokenCipher $cipher,
    ): SalesAuditHandler {
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string,preserve_numbers?:bool}> $operations */
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
            $pdo,
            $client,
            $cipher,
            'client-id',
            'client-secret',
            'erp2.oauth.sales-audit-terminal-fingerprint',
        );

        return new SalesAuditHandler($work, $audit, $client, $tokens);
    }

    private function insertLocalOrder(PDO $pdo, string $externalOrderId, string $dateCreated): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO orders '
            . '(company_id,account_id,external_order_id,status,date_created,total_amount,currency_id) '
            . "VALUES (1,1,:external_order_id,'paid',:date_created,'1000.0000','COP')"
        );
        $statement->execute([
            'external_order_id' => $externalOrderId,
            'date_created' => $dateCreated,
        ]);
    }

    private function seedAccountAndToken(PDO $pdo, TokenCipher $cipher): void
    {
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (1,1,'99887766','MCO','Seller','connected')"
        );
        $statement = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (1,:access_token,:refresh_token,:expires_at,0)'
        );
        $statement->execute([
            'access_token' => $cipher->encrypt('valid-access-token'),
            'refresh_token' => $cipher->encrypt('unused-refresh-token'),
            'expires_at' => '2030-01-01 00:00:00.000000',
        ]);
    }
}

final class SalesAuditTerminalFingerprintTransport implements MeliTransport
{
    public function __construct(private readonly MeliTransportResponse $response)
    {
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        return $this->response;
    }
}
