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
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SalesWorkPoisonLoopTest extends TestCase
{
    public function testUnsupportedWorkTypeIsTerminallyFailedAndCannotBeRecoveredToPending(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Poison Guard','poison-guard')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) "
            . "VALUES (1,1,'99887766','MCO','connected')"
        );

        $work = new WorkRepository($pdo);
        $transport = new PoisonLoopGuardTransport();
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
            new TokenCipher('poison-loop-test-key'),
            'client-id',
            'client-secret',
            'erp_meli2.test.poison.oauth',
        );
        $processor = new SalesWorkProcessor(
            new OrderSyncWorkProcessor(new SyncOrderHandler($work, $client, $tokens), $work),
            new SalesAuditHandler($work, new SalesAuditRepository($pdo), $client, $tokens),
            $work,
        );

        $workId = $work->enqueue(
            1,
            1,
            'company:1:account:1',
            'unsupported.sales.work',
            'poison-1',
            'unsupported.sales.work:poison-1',
            ['unexpected' => true],
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
        self::assertSame('unsupported_work_type', $row['last_error_code']);
        self::assertSame('Work type is not supported by this processor.', $row['last_error_safe']);
        self::assertSame(0, $work->recoverRunning());
        self::assertNull($work->claimNext(), 'A terminally failed unsupported work item must never be recovered into the queue.');
        self::assertSame([], $transport->requests, 'Unsupported work must never reach Mercado Libre HTTP.');
    }
}

final class PoisonLoopGuardTransport implements MeliTransport
{
    /** @var list<string> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $url;
        throw new RuntimeException('Unsupported work must not reach transport.');
    }
}
