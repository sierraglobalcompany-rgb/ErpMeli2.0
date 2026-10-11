<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Billing\BillingDetailNormalizer;
use App\Modules\Billing\BillingPeriodSyncHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class BillingPeriodSyncHandlerTest extends TestCase
{
    private PDO $pdo;
    private TokenCipher $cipher;
    private int $companyId;
    private int $accountId;
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->cipher = new TokenCipher(TestDatabase::config()->appKey);
        $this->now = new DateTimeImmutable('2026-10-10T19:00:00-05:00');

        $company = $this->pdo->prepare('INSERT INTO companies (name, slug) VALUES (?, ?)');
        $company->execute(['Billing Page Test', 'billing-page-' . bin2hex(random_bytes(4))]);
        $this->companyId = (int) $this->pdo->lastInsertId();

        $account = $this->pdo->prepare(
            "INSERT INTO meli_accounts (company_id, external_user_id, site_id, status) "
            . "VALUES (:company_id, '700000001', 'MCO', 'connected')",
        );
        $account->execute(['company_id' => $this->companyId]);
        $this->accountId = (int) $this->pdo->lastInsertId();

        $token = $this->pdo->prepare(
            'INSERT INTO meli_tokens (account_id, access_token_cipher, refresh_token_cipher, expires_at) '
            . 'VALUES (:account_id, :access_token_cipher, :refresh_token_cipher, :expires_at)',
        );
        $token->execute([
            'account_id' => $this->accountId,
            'access_token_cipher' => $this->cipher->encrypt('seeded-billing-access'),
            'refresh_token_cipher' => $this->cipher->encrypt('unused-billing-refresh'),
            'expires_at' => '2030-01-02 00:00:00.000000',
        ]);
    }

    public function testNonEmptyFirstPagePersistsDetailsAdvancesCursorAndQueuesContinuationAtomically(): void
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        $transport = new BillingPeriodSyncFakeTransport([
            new MeliTransportResponse(
                200,
                ['x-request-id' => 'billing-page-test'],
                '{"results":[{"charge_info":{"detail_id":101,"detail_associated_id":90,'
                . '"detail_amount":28265.86,"detail_type":"CHARGE","detail_sub_type":"CV",'
                . '"creation_date_time":"2026-09-12T15:12:02.123-05:00",'
                . '"debited_from_operation":"sale_fee","status":"released","charge_bonified_id":92},'
                . '"currency_info":{"currency_id":"COP"},"document_info":{"document_id":3454540850},'
                . '"marketplace_info":{"marketplace":"MCO"}}],"last_id":101}',
            ),
        ]);
        $handler = $this->handler($work, $transport);

        $completed = $handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $this->companyId,
            $this->accountId,
            '2026-09-01',
            'BILL',
            '0',
            $this->now,
        );

        self::assertTrue($completed);
        self::assertCount(1, $transport->requests);
        self::assertSame('GET', $transport->requests[0]['method']);
        self::assertSame(
            'https://api.mercadolibre.com/billing/integration/periods/key/2026-09-01/group/ML/details'
            . '?document_type=BILL&limit=1000&from_id=0&sort_by=ID&order_by=ASC',
            $transport->requests[0]['url'],
        );
        self::assertTrue(hash_equals(
            'Bearer seeded-billing-access',
            (string) ($transport->requests[0]['headers']['Authorization'] ?? ''),
        ));
        self::assertNull($transport->requests[0]['body']);

        $periodStatement = $this->pdo->prepare(
            'SELECT id, cursor_last_id, sync_state, partial_flag, last_synced_at '
            . 'FROM billing_periods WHERE company_id = :company_id AND account_id = :account_id '
            . 'AND period_key = :period_key AND document_type = :document_type',
        );
        $periodStatement->execute([
            'company_id' => $this->companyId,
            'account_id' => $this->accountId,
            'period_key' => '2026-09-01',
            'document_type' => 'BILL',
        ]);
        $period = $periodStatement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($period);
        self::assertSame('101', $period['cursor_last_id']);
        self::assertSame('pending', $period['sync_state']);
        self::assertSame(0, (int) $period['partial_flag']);
        self::assertSame('2026-10-11 00:00:00.000000', $period['last_synced_at']);

        $detailStatement = $this->pdo->prepare(
            'SELECT external_detail_id, associated_detail_id, detail_type, detail_sub_type, detail_amount, '
            . 'currency_id, document_id, marketplace, context_json, remote_created_at '
            . 'FROM billing_details WHERE billing_period_id = :period_id',
        );
        $detailStatement->execute(['period_id' => $period['id']]);
        $details = $detailStatement->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $details);
        self::assertSame([
            'external_detail_id' => '101',
            'associated_detail_id' => '90',
            'detail_type' => 'CHARGE',
            'detail_sub_type' => 'CV',
            'detail_amount' => '28265.8600',
            'currency_id' => 'COP',
            'document_id' => '3454540850',
            'marketplace' => 'MCO',
            'context_json' => '{"charge":{"debited_from_operation":"sale_fee","status":"released","charge_bonified_id":"92"}}',
            'remote_created_at' => '2026-09-12 20:12:02.123000',
        ], $details[0]);

        $workRows = $this->pdo->query(
            'SELECT id, scope_key, type, resource_key, dedupe_key, payload_json, status '
            . 'FROM work_items ORDER BY id',
        )->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(2, $workRows);
        self::assertSame('done', $workRows[0]['status']);
        self::assertSame($claim['id'], (int) $workRows[0]['id']);
        self::assertSame('pending', $workRows[1]['status']);
        self::assertNotSame($workRows[0]['dedupe_key'], $workRows[1]['dedupe_key']);
        self::assertSame('billing.period.sync', $workRows[1]['type']);
        self::assertSame('2026-09-01:BILL', $workRows[1]['resource_key']);
        self::assertSame(
            'company:' . $this->companyId . ':account:' . $this->accountId,
            $workRows[1]['scope_key'],
        );
        self::assertSame([
            'period_key' => '2026-09-01',
            'document_type' => 'BILL',
            'from_id' => '101',
        ], json_decode((string) $workRows[1]['payload_json'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(1, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM work_items WHERE type = 'billing.period.sync' AND status = 'pending'",
        )->fetchColumn());
    }

    public function testEmptyPageMarksPeriodCaughtUpWithoutContinuation(): void
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        $transport = new BillingPeriodSyncFakeTransport([
            new MeliTransportResponse(200, [], '{"results":[],"last_id":null}'),
        ]);
        $handler = $this->handler($work, $transport);

        self::assertTrue($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $this->companyId,
            $this->accountId,
            '2026-09-01',
            'BILL',
            '0',
            $this->now,
        ));

        $period = $this->pdo->query(
            'SELECT cursor_last_id, sync_state, partial_flag, last_synced_at FROM billing_periods',
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($period);
        self::assertSame('0', $period['cursor_last_id']);
        self::assertSame('caught_up', $period['sync_state']);
        self::assertSame(0, (int) $period['partial_flag']);
        self::assertSame('2026-10-11 00:00:00.000000', $period['last_synced_at']);
        self::assertSame('done', $this->pdo->query(
            'SELECT status FROM work_items WHERE id = ' . $claim['id'],
        )->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM work_items WHERE type = 'billing.period.sync'",
        )->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM work_items WHERE type = 'billing.period.sync' AND status = 'pending'",
        )->fetchColumn());
    }

    public function testRateLimitedPageDefersCurrentClaimWithoutConsumingAttemptOrCanonicalMutation(): void
    {
        $transport = new BillingPeriodSyncFakeTransport([new MeliTransportResponse(429, ['retry-after' => '60'], '{}')]);
        $claim = $this->processFailure($transport);
        $row = $this->workRow($claim['id']);
        self::assertSame('pending', $row['status']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertSame('meli_rate_limited', $row['last_error_code']);
        self::assertGreaterThan(new DateTimeImmutable($claim['claimed_at'], new DateTimeZone('UTC')), new DateTimeImmutable((string) $row['available_at'], new DateTimeZone('UTC')));
        $this->assertNoCanonicalMutation($transport);
    }

    public function testUnauthorizedPageRefreshesOnceRetriesOnceAndPersistsSuccessfulPage(): void
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        $transport = new BillingPeriodSyncFakeTransport([
            new MeliTransportResponse(401, [], '{}'),
            $this->refreshResponse(),
            new MeliTransportResponse(200, [], '{"results":[{"charge_info":{"detail_id":101,"detail_amount":10.25,"detail_type":"CHARGE"}}],"last_id":101}'),
        ]);
        self::assertTrue($this->handler($work, $transport)->syncCurrentClaim(
            $claim['id'], $claim['claim_token'], $this->companyId, $this->accountId,
            '2026-09-01', 'BILL', '0', $this->now,
        ));
        $this->assertSingleRefresh($transport);
        self::assertSame('done', $this->workRow($claim['id'])['status']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame('101', $this->pdo->query('SELECT cursor_last_id FROM billing_periods')->fetchColumn());
        self::assertSame('pending', $this->pdo->query('SELECT sync_state FROM billing_periods')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM work_items WHERE status='pending'")->fetchColumn());
    }

    public function testUnauthorizedAfterRefreshFailsCurrentClaimWithoutThirdBillingRequest(): void
    {
        $transport = new BillingPeriodSyncFakeTransport([
            new MeliTransportResponse(401, [], '{}'),
            $this->refreshResponse(),
            new MeliTransportResponse(401, [], '{}'),
        ]);
        $claim = $this->processFailure($transport);
        self::assertSame('failed', $this->workRow($claim['id'])['status']);
        self::assertSame('meli_unauthorized', $this->workRow($claim['id'])['last_error_code']);
        $this->assertSingleRefresh($transport);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
    }

    public function testServerErrorSchedulesBoundedRetryAndConsumesAttemptWithoutCanonicalMutation(): void
    {
        $transport = new BillingPeriodSyncFakeTransport([new MeliTransportResponse(500, [], '{}')]);
        $claim = $this->processFailure($transport);
        $this->assertRemoteRetry($claim['id']);
        $this->assertNoCanonicalMutation($transport);
    }

    public function testServerErrorPreservesRetryAfterInWorkRetry(): void
    {
        $transport = new BillingPeriodSyncFakeTransport([
            new MeliTransportResponse(503, ['retry-after' => '600'], '{}'),
        ]);
        $claim = $this->processFailure($transport);
        $this->assertRemoteRetry($claim['id'], 600);
        $this->assertNoCanonicalMutation($transport);
    }

    public function testServerErrorInvalidRetryAfterKeepsLocalWorkRetry(): void
    {
        $transport = new BillingPeriodSyncFakeTransport([
            new MeliTransportResponse(503, ['retry-after' => 'garbage'], '{}'),
        ]);
        $claim = $this->processFailure($transport);
        $this->assertRemoteRetry($claim['id']);
        $this->assertNoCanonicalMutation($transport);
    }

    public function testTransportFailureSchedulesBoundedRetryWithoutCanonicalMutation(): void
    {
        $transport = new BillingPeriodSyncFakeTransport([new RuntimeException('Fake transport failed.')]);
        $claim = $this->processFailure($transport);
        $this->assertRemoteRetry($claim['id']);
        $this->assertNoCanonicalMutation($transport);
    }

    public function testInvalidHttpJsonSchedulesRemoteRetryWithoutCanonicalMutation(): void
    {
        $transport = new BillingPeriodSyncFakeTransport([new MeliTransportResponse(200, [], '{')]);
        $claim = $this->processFailure($transport);
        $this->assertRemoteRetry($claim['id']);
        $this->assertNoCanonicalMutation($transport);
    }

    public function testPartial206DefersWithoutPersistingOrAdvancingCursor(): void
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        $transport = new BillingPeriodSyncFakeTransport([
            new MeliTransportResponse(
                206,
                [],
                '{"results":[{"charge_info":{"detail_id":101,"detail_amount":28265.86,'
                . '"detail_type":"CHARGE","detail_sub_type":"CV",'
                . '"creation_date_time":"2026-09-12T15:12:02.123-05:00"},'
                . '"currency_info":{"currency_id":"COP"}}],"last_id":101}',
            ),
        ]);
        $handler = $this->handler($work, $transport);

        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'],
            $claim['claim_token'],
            $this->companyId,
            $this->accountId,
            '2026-09-01',
            'BILL',
            '0',
            $this->now,
        ));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM work_items WHERE type = 'billing.period.sync'",
        )->fetchColumn());
        self::assertSame('pending', $this->pdo->query(
            'SELECT status FROM work_items WHERE id = ' . $claim['id'],
        )->fetchColumn());
        $row = $this->workRow($claim['id']);
        self::assertSame(0, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertSame('meli_billing_partial', $row['last_error_code']);
        self::assertSame('2026-10-11 00:00:30.000000', $row['available_at']);
        self::assertCount(1, $transport->requests);
    }

    #[DataProvider('invalidSuccessfulPages')]
    public function testInvalidSuccessfulPageFailsCurrentClaimWithoutCanonicalMutation(string $body): void
    {
        $transport = new BillingPeriodSyncFakeTransport([new MeliTransportResponse(200, [], $body)]);
        $claim = $this->processFailure($transport);
        $row = $this->workRow($claim['id']);
        self::assertSame('failed', $row['status']);
        self::assertSame('meli_billing_contract', $row['last_error_code']);
        self::assertSame('Mercado Libre returned an unusable billing period page.', $this->pdo->query(
            'SELECT last_error_safe FROM work_items WHERE id=' . $claim['id'],
        )->fetchColumn());
        $this->assertNoCanonicalMutation($transport);
    }

    /** @return array<string,array{string}> */
    public static function invalidSuccessfulPages(): array
    {
        $detail = '{"charge_info":{"detail_id":"101","detail_amount":"10.25","detail_type":"CHARGE"}}';
        $pages = [
            'results missing' => '{"last_id":"101"}',
            'results not array' => '{"results":false,"last_id":"101"}',
            'last_id missing' => '{"results":[' . $detail . ']}',
            'result not array' => '{"results":[false],"last_id":"101"}',
            'normalizer rejects after valid detail' => '{"results":[' . $detail . ',{}],"last_id":"101"}',
        ];
        foreach ([
            'null' => 'null', 'bool' => 'false', 'array' => '[]', 'object' => '{}',
            'blank' => '"   "', 'too long' => '"' . str_repeat('x', 41) . '"',
            'same cursor' => '"0"', 'trimmed same cursor' => '" 0 "',
        ] as $name => $cursor) {
            $pages['last_id ' . $name] = '{"results":[' . $detail . '],"last_id":' . $cursor . '}';
        }
        return array_map(static fn (string $body): array => [$body], $pages);
    }

    #[DataProvider('emptyTerminalPages')]
    public function testEmptyPageDoesNotRequireLastId(string $body): void
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        $transport = new BillingPeriodSyncFakeTransport([new MeliTransportResponse(200, [], $body)]);
        self::assertTrue($this->handler($work, $transport)->syncCurrentClaim(
            $claim['id'], $claim['claim_token'], $this->companyId, $this->accountId,
            '2026-09-01', 'BILL', '0', $this->now,
        ));
        self::assertSame('done', $this->workRow($claim['id'])['status']);
        self::assertSame('caught_up', $this->pdo->query('SELECT sync_state FROM billing_periods')->fetchColumn());
        self::assertSame('0', $this->pdo->query('SELECT cursor_last_id FROM billing_periods')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertSame(0, $this->continuationCount());
    }

    /** @return array<string,array{string}> */
    public static function emptyTerminalPages(): array
    {
        return ['absent' => ['{"results":[]}'], 'irrelevant object' => ['{"results":[],"last_id":{}}']];
    }

    public function testNumericAndQuotedNumericLastIdAreEquivalentOpaqueCursors(): void
    {
        $work = new WorkRepository($this->pdo);
        $values = [];
        foreach (['2026-08-01' => '1.5', '2026-09-01' => '"1.5"'] as $periodKey => $cursor) {
            $claim = $this->enqueueAndClaim($work, $periodKey, 'BILL', '0');
            $transport = new BillingPeriodSyncFakeTransport([$this->validPage($cursor)]);
            self::assertTrue($this->handler($work, $transport)->syncCurrentClaim(
                $claim['id'], $claim['claim_token'], $this->companyId, $this->accountId,
                $periodKey, 'BILL', '0', $this->now,
            ));
            self::assertSame('done', $this->workRow($claim['id'])['status']);
            $statement = $this->pdo->prepare('SELECT cursor_last_id FROM billing_periods WHERE period_key=:period');
            $statement->execute(['period' => $periodKey]);
            $actual = $statement->fetchColumn();
            self::assertSame('1.5', $actual);
            $statement = $this->pdo->prepare("SELECT payload_json FROM work_items WHERE resource_key=:resource AND status='pending'");
            $statement->execute(['resource' => $periodKey . ':BILL']);
            $payloads = $statement->fetchAll(PDO::FETCH_COLUMN);
            self::assertCount(1, $payloads);
            $payload = json_decode((string) $payloads[0], true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('1.5', $payload['from_id']);
            $values[] = [$actual, $payload['from_id']];
        }
        self::assertSame($values[0], $values[1]);
    }

    public function testStalePageReplayFailsCursorConflictWithoutChangingCanonicalState(): void
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        $transport = new BillingPeriodSyncFakeTransport([$this->validPage('101'), $this->validPage('101')]);
        $handler = $this->handler($work, $transport);
        self::assertTrue($handler->syncCurrentClaim(
            $claim['id'], $claim['claim_token'], $this->companyId, $this->accountId,
            '2026-09-01', 'BILL', '0', $this->now,
        ));
        self::assertSame('done', $this->workRow($claim['id'])['status']);
        $periods = $this->pdo->query('SELECT * FROM billing_periods')->fetchAll(PDO::FETCH_ASSOC);
        $details = $this->pdo->query('SELECT * FROM billing_details')->fetchAll(PDO::FETCH_ASSOC);
        $continuations = $this->pdo->query("SELECT * FROM work_items WHERE status='pending'")->fetchAll(PDO::FETCH_ASSOC);
        self::assertCount(1, $periods);
        self::assertSame('101', $periods[0]['cursor_last_id']);
        self::assertSame('pending', $periods[0]['sync_state']);
        self::assertCount(1, $details);
        self::assertCount(1, $continuations);
        self::assertSame('101', json_decode((string) $continuations[0]['payload_json'], true, 512, JSON_THROW_ON_ERROR)['from_id']);
        $replay = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        self::assertNotSame($claim['id'], $replay['id']);
        self::assertFalse($handler->syncCurrentClaim(
            $replay['id'], $replay['claim_token'], $this->companyId, $this->accountId,
            '2026-09-01', 'BILL', '0', $this->now,
        ));
        $this->assertCursorConflict($replay['id']);
        self::assertSame($periods, $this->pdo->query('SELECT * FROM billing_periods')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($details, $this->pdo->query('SELECT * FROM billing_details')->fetchAll(PDO::FETCH_ASSOC));
        self::assertSame($continuations, $this->pdo->query("SELECT * FROM work_items WHERE status='pending'")->fetchAll(PDO::FETCH_ASSOC));
        self::assertCount(2, $transport->requests);
    }

    public function testNonInitialCursorWithoutDurablePeriodFailsCursorConflict(): void
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '101');
        $transport = new BillingPeriodSyncFakeTransport([$this->validPage('102')]);
        self::assertFalse($this->handler($work, $transport)->syncCurrentClaim(
            $claim['id'], $claim['claim_token'], $this->companyId, $this->accountId,
            '2026-09-01', 'BILL', '101', $this->now,
        ));
        $this->assertCursorConflict($claim['id']);
        $this->assertNoCanonicalMutation($transport);
    }

    public function testLostClaimAfterRemotePageCannotPersistAndRecoveredWorkCanReplayOnce(): void
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        $replacement = str_repeat('f', 32);
        $transport = new BillingPeriodSyncFakeTransport(
            [$this->validPage('101'), $this->validPage('101')],
            function (int $requestNumber) use ($claim, $replacement): void {
                if ($requestNumber === 1) {
                    $statement = $this->pdo->prepare('UPDATE work_items SET claim_token=:token WHERE id=:id');
                    $statement->execute(['token' => $replacement, 'id' => $claim['id']]);
                }
            },
        );
        $handler = $this->handler($work, $transport);
        self::assertFalse($handler->syncCurrentClaim(
            $claim['id'], $claim['claim_token'], $this->companyId, $this->accountId,
            '2026-09-01', 'BILL', '0', $this->now,
        ));
        $this->assertNoCanonicalMutation($transport);
        self::assertSame('running', $this->workRow($claim['id'])['status']);
        self::assertSame($replacement, $this->workRow($claim['id'])['claim_token']);
        self::assertSame(1, $work->recoverRunning());
        self::assertSame('pending', $this->workRow($claim['id'])['status']);
        $recovered = $work->claimNext();
        self::assertIsArray($recovered);
        self::assertSame($claim['id'], $recovered['id']);
        self::assertNotSame($claim['claim_token'], $recovered['claim_token']);
        self::assertNotSame($replacement, $recovered['claim_token']);
        self::assertTrue($handler->syncCurrentClaim(
            $recovered['id'], $recovered['claim_token'], $this->companyId, $this->accountId,
            '2026-09-01', 'BILL', '0', $this->now,
        ));
        self::assertSame('done', $this->workRow($claim['id'])['status']);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertSame('101', $this->pdo->query('SELECT cursor_last_id FROM billing_periods')->fetchColumn());
        self::assertSame(1, $this->continuationCount());
        self::assertSame('101', json_decode((string) $this->pdo->query("SELECT payload_json FROM work_items WHERE status='pending'")->fetchColumn(), true, 512, JSON_THROW_ON_ERROR)['from_id']);
        self::assertCount(2, $transport->requests);
    }

    private function validPage(string $cursorJson): MeliTransportResponse
    {
        return new MeliTransportResponse(200, [], '{"results":[{"charge_info":{"detail_id":"101","detail_amount":"10.25","detail_type":"CHARGE"}}],"last_id":' . $cursorJson . '}');
    }

    private function continuationCount(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM work_items WHERE status='pending'")->fetchColumn();
    }

    private function assertCursorConflict(int $id): void
    {
        self::assertSame('failed', $this->workRow($id)['status']);
        self::assertSame('billing_cursor_conflict', $this->workRow($id)['last_error_code']);
        self::assertSame('Billing period cursor no longer matches this Work.', $this->pdo->query('SELECT last_error_safe FROM work_items WHERE id=' . $id)->fetchColumn());
        self::assertFalse($this->pdo->inTransaction());
    }

    /** @return array{id:int,claim_token:string,claimed_at:string} */
    private function processFailure(BillingPeriodSyncFakeTransport $transport): array
    {
        $work = new WorkRepository($this->pdo);
        $claim = $this->enqueueAndClaim($work, '2026-09-01', 'BILL', '0');
        self::assertFalse($this->handler($work, $transport)->syncCurrentClaim(
            $claim['id'], $claim['claim_token'], $this->companyId, $this->accountId,
            '2026-09-01', 'BILL', '0', $this->now,
        ));
        return $claim;
    }

    /** @return array<string,mixed> */
    private function workRow(int $id): array
    {
        $row = $this->pdo->query('SELECT status,attempts,claim_token,last_error_code,available_at FROM work_items WHERE id=' . $id)->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        return $row;
    }

    private function assertRemoteRetry(int $id, int $seconds = 30): void
    {
        $row = $this->workRow($id);
        self::assertSame('pending', $row['status']);
        self::assertSame(1, (int) $row['attempts']);
        self::assertNull($row['claim_token']);
        self::assertSame('meli_remote_retry', $row['last_error_code']);
        self::assertSame(
            $this->now->setTimezone(new DateTimeZone('UTC'))
                ->modify('+' . $seconds . ' seconds')
                ->format('Y-m-d H:i:s.u'),
            $row['available_at'],
        );
    }

    private function assertNoCanonicalMutation(BillingPeriodSyncFakeTransport $transport): void
    {
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_periods')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM billing_details')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        self::assertCount(1, $transport->requests);
    }

    private function refreshResponse(): MeliTransportResponse
    {
        return new MeliTransportResponse(200, [], '{"access_token":"fresh-billing-access","refresh_token":"fresh-billing-refresh","expires_in":21600}');
    }

    private function assertSingleRefresh(BillingPeriodSyncFakeTransport $transport): void
    {
        self::assertCount(3, $transport->requests);
        self::assertSame('GET', $transport->requests[0]['method']);
        self::assertStringContainsString('/billing/integration/', $transport->requests[0]['url']);
        self::assertSame('Bearer seeded-billing-access', $transport->requests[0]['headers']['Authorization']);
        self::assertSame('POST', $transport->requests[1]['method']);
        self::assertSame('https://api.mercadolibre.com/oauth/token', $transport->requests[1]['url']);
        self::assertSame('GET', $transport->requests[2]['method']);
        self::assertSame($transport->requests[0]['url'], $transport->requests[2]['url']);
        self::assertSame('Bearer fresh-billing-access', $transport->requests[2]['headers']['Authorization']);
    }

    private function handler(WorkRepository $work, BillingPeriodSyncFakeTransport $transport): BillingPeriodSyncHandler
    {
        /** @var array<string,array<string,mixed>> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($this->pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
        $tokens = new OAuthRefreshService(
            $this->pdo,
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $this->cipher,
            '123456789',
            'client-secret',
            'erp_meli2.oauth.account',
        );

        return new BillingPeriodSyncHandler($work, $client, $tokens, new BillingDetailNormalizer());
    }

    /** @return array{id:int,claim_token:string,claimed_at:string} */
    private function enqueueAndClaim(
        WorkRepository $work,
        string $periodKey,
        string $documentType,
        string $fromId,
    ): array {
        $scope = 'company:' . $this->companyId . ':account:' . $this->accountId;
        $resourceKey = $periodKey . ':' . $documentType;
        $work->enqueue(
            $this->companyId,
            $this->accountId,
            $scope,
            'billing.period.sync',
            $resourceKey,
            'billing.period.sync:' . $resourceKey . ':' . $fromId,
            [
                'period_key' => $periodKey,
                'document_type' => $documentType,
                'from_id' => $fromId,
            ],
            new DateTimeImmutable('2000-01-01T00:00:00+00:00'),
        );

        $claim = $work->claimNext();
        self::assertIsArray($claim);

        return ['id' => $claim['id'], 'claim_token' => $claim['claim_token'], 'claimed_at' => $claim['claimed_at']];
    }
}

final class BillingPeriodSyncFakeTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse|RuntimeException> */
    private array $responses;

    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param list<MeliTransportResponse|RuntimeException> $responses */
    public function __construct(array $responses, private readonly ?\Closure $onSend = null)
    {
        $this->responses = $responses;
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        if ($this->onSend !== null) {
            ($this->onSend)(count($this->requests));
        }
        $response = array_shift($this->responses);
        if ($response instanceof RuntimeException) {
            throw $response;
        }
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('No queued billing page response.');
        }

        return $response;
    }
}
