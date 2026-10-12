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
use App\Modules\Financial\FinancialReadModel;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class FinancialFactSufficiencyTest extends TestCase
{
    private PDO $pdo;
    private TokenCipher $cipher;
    private int $companyId;
    private int $accountId;
    private int $billPeriodId;
    private int $creditNotePeriodId;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->cipher = new TokenCipher(TestDatabase::config()->appKey);
        $company = $this->pdo->prepare('INSERT INTO companies (name, slug) VALUES (?, ?)');
        $company->execute(['Financial Facts Test', 'financial-facts-' . bin2hex(random_bytes(4))]);
        $this->companyId = (int) $this->pdo->lastInsertId();

        $account = $this->pdo->prepare(
            "INSERT INTO meli_accounts (company_id, external_user_id, site_id, status) "
            . "VALUES (?, '700000909', 'MCO', 'connected')",
        );
        $account->execute([$this->companyId]);
        $this->accountId = (int) $this->pdo->lastInsertId();
        $this->billPeriodId = $this->insertPeriod('BILL');
        $this->creditNotePeriodId = $this->insertPeriod('CREDIT_NOTE');
    }

    public function testCanonicalBillingContextSupportsDirectDerivedAndAmbiguousIdentity(): void
    {
        $this->insertSalesOrder('200000000101', 'COP', 'PACK-SHARED', '12.3400');
        $this->insertSalesOrder('200000000102', 'COP', 'PACK-SHARED', null);
        $this->insertSalesOrder('200000000201', 'COP', 'PACK-VIA-ORDER', null);
        $this->insertSalesOrder('200000000301', 'COP', null, null);
        $this->insertSalesOrder('200000000302', 'COP', null, null);

        $directOrder = $this->persistBillingDetail(
            $this->billPeriodId,
            $this->billingDetail(
                'FACT-ORDER-DIRECT',
                'CHARGE',
                'CV',
                '1.0000',
                'COP',
                '2026-09-01T00:00:00Z',
                salesInfo: [['order_id' => '200000000101']],
                itemsInfo: [['item_id' => 'MCO101', 'order_id' => '200000000101']],
            ),
        );
        $directOrderContext = $this->context($directOrder['context_json']);
        self::assertSame('200000000101', $directOrderContext['sales'][0]['order_id']);
        self::assertSame('200000000101', $directOrderContext['items'][0]['order_id']);

        $directPack = $this->persistBillingDetail(
            $this->billPeriodId,
            $this->billingDetail(
                'FACT-PACK-DIRECT',
                'CHARGE',
                'CV',
                '2.0000',
                'COP',
                '2026-09-01T00:00:00Z',
                shippingInfo: ['pack_id' => 'PACK-DIRECT'],
            ),
        );
        self::assertSame('PACK-DIRECT', $this->context($directPack['context_json'])['shipping']['pack_id']);

        $orderPack = $this->persistBillingDetail(
            $this->billPeriodId,
            $this->billingDetail(
                'FACT-PACK-VIA-ORDER',
                'CHARGE',
                'CV',
                '3.0000',
                'COP',
                '2026-09-01T00:00:00Z',
                salesInfo: [['order_id' => '200000000201']],
            ),
        );
        $orderId = $this->context($orderPack['context_json'])['sales'][0]['order_id'];
        $packStatement = $this->pdo->prepare(
            'SELECT pack_id FROM orders WHERE company_id = :company_id AND account_id = :account_id '
            . 'AND external_order_id = :order_id',
        );
        $packStatement->execute([
            'company_id' => $this->companyId,
            'account_id' => $this->accountId,
            'order_id' => $orderId,
        ]);
        self::assertSame('PACK-VIA-ORDER', $packStatement->fetchColumn());

        $conflict = $this->persistBillingDetail(
            $this->billPeriodId,
            $this->billingDetail(
                'FACT-ORDER-CONFLICT',
                'CHARGE',
                'CV',
                '4.0000',
                'COP',
                '2026-09-01T00:00:00Z',
                salesInfo: [['order_id' => '200000000301']],
                itemsInfo: [['item_id' => 'MCO302', 'order_id' => '200000000302']],
            ),
        );
        $conflictContext = $this->context($conflict['context_json']);
        $orderIds = array_values(array_unique([
            $conflictContext['sales'][0]['order_id'],
            $conflictContext['items'][0]['order_id'],
        ]));
        self::assertSame(['200000000301', '200000000302'], $orderIds);
        self::assertCount(2, $orderIds, 'Conflicting Billing identities remain visible; no ID wins by position.');
        self::assertSame(
            2,
            (int) $this->pdo->query("SELECT COUNT(*) FROM orders WHERE pack_id = 'PACK-SHARED'")->fetchColumn(),
            'Two child orders sharing a pack remain distinguishable as separate canonical orders.',
        );
    }

    public function testCanonicalFactsPreserveShippingBillingAssociationsCurrencyAndLateAdjustments(): void
    {
        $this->insertSalesOrder('200000000101', 'COP', 'PACK-SHARED', '12.3400');
        $this->insertSalesOrder('200000000102', 'COP', 'PACK-SHARED', null);

        $sharedCharge = $this->persistBillingDetail(
            $this->billPeriodId,
            $this->billingDetail(
                'FACT-SHARED-CHARGE',
                'CHARGE',
                'CV',
                '12.3456',
                'USD',
                '2026-09-05T15:00:00Z',
                associatedDetailId: 'FACT-BASE-CHARGE',
                chargeFields: [
                    'debited_from_operation' => 'sale_fee',
                    'status' => 'released',
                    'charge_bonified_id' => 'FACT-BONUS',
                ],
                salesInfo: [[
                    'order_id' => '200000000101',
                    'sale_date_time' => '2026-08-01T10:00:00-05:00',
                ]],
                shippingInfo: ['pack_id' => 'PACK-SHARED', 'receiver_shipping_cost' => '15.2300'],
                itemsInfo: [['item_id' => 'MCO101', 'order_id' => '200000000101', 'item_amount' => '1']],
            ),
        );
        $sharedContext = $this->context($sharedCharge['context_json']);
        self::assertSame('12.3456', $sharedCharge['detail_amount']);
        self::assertSame('CHARGE', $sharedCharge['detail_type']);
        self::assertSame('CV', $sharedCharge['detail_sub_type']);
        self::assertSame('FACT-BASE-CHARGE', $sharedCharge['associated_detail_id']);
        self::assertSame('FACT-BONUS', $sharedContext['charge']['charge_bonified_id']);
        self::assertSame('15.2300', $sharedContext['shipping']['receiver_shipping_cost']);
        self::assertSame('PACK-SHARED', $sharedContext['shipping']['pack_id']);
        self::assertArrayNotHasKey('seller_shipping_cost', $sharedContext['shipping']);

        $saleDate = new DateTimeImmutable($sharedContext['sales'][0]['sale_date_time']);
        $billingCreatedAt = new DateTimeImmutable((string) $sharedCharge['remote_created_at'], new DateTimeZone('UTC'));
        self::assertGreaterThan($saleDate, $billingCreatedAt, 'The later Billing source date keeps a late adjustment detectable.');

        $currencyStatement = $this->pdo->prepare(
            'SELECT o.currency_id AS order_currency, bd.currency_id AS billing_currency, '
            . 'bp.document_type AS billing_document_type '
            . 'FROM billing_details bd '
            . 'JOIN billing_periods bp ON bp.id = bd.billing_period_id '
            . 'JOIN orders o ON o.external_order_id = :order_id '
            . 'WHERE bd.external_detail_id = :detail_id AND bp.company_id = o.company_id AND bp.account_id = o.account_id',
        );
        $currencyStatement->execute([
            'order_id' => '200000000101',
            'detail_id' => 'FACT-SHARED-CHARGE',
        ]);
        $currency = $currencyStatement->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($currency);
        self::assertSame('COP', $currency['order_currency']);
        self::assertSame('USD', $currency['billing_currency']);
        self::assertSame('BILL', $currency['billing_document_type']);
        self::assertNotSame($currency['order_currency'], $currency['billing_currency']);
        self::assertSame(
            1,
            (int) $this->pdo->query(
                "SELECT COUNT(*) FROM billing_details WHERE external_detail_id = 'FACT-SHARED-CHARGE'",
            )->fetchColumn(),
            'One shared pack charge remains one Billing fact; no per-child allocation is created.',
        );

        $zeroShipping = $this->persistBillingDetail(
            $this->billPeriodId,
            $this->billingDetail(
                'FACT-SHIPPING-ZERO',
                'CHARGE',
                'CV',
                '0.0000',
                'COP',
                '2026-09-06T00:00:00Z',
                shippingInfo: ['receiver_shipping_cost' => 0],
            ),
        );
        self::assertSame('0', $this->context($zeroShipping['context_json'])['shipping']['receiver_shipping_cost']);

        $missingShipping = $this->persistBillingDetail(
            $this->billPeriodId,
            $this->billingDetail(
                'FACT-SHIPPING-MISSING',
                'CHARGE',
                'CV',
                '0.0000',
                'COP',
                '2026-09-06T00:00:00Z',
                shippingInfo: ['pack_id' => 'PACK-SHARED'],
            ),
        );
        $nullShipping = $this->persistBillingDetail(
            $this->billPeriodId,
            $this->billingDetail(
                'FACT-SHIPPING-NULL',
                'CHARGE',
                'CV',
                '0.0000',
                'COP',
                '2026-09-06T00:00:00Z',
                shippingInfo: ['pack_id' => 'PACK-SHARED', 'receiver_shipping_cost' => null],
            ),
        );
        foreach ([$missingShipping, $nullShipping] as $unknown) {
            self::assertArrayNotHasKey('receiver_shipping_cost', $this->context($unknown['context_json'])['shipping']);
        }

        $credit = $this->persistBillingDetail(
            $this->creditNotePeriodId,
            $this->billingDetail(
                'FACT-CREDIT-NOTE',
                'CHARGE',
                'CV',
                '0.5000',
                'USD',
                '2026-09-06T01:00:00Z',
                associatedDetailId: 'FACT-SHARED-CHARGE',
                chargeFields: ['charge_bonified_id' => 'FACT-BONUS'],
            ),
        );
        $creditType = $this->pdo->prepare(
            'SELECT bp.document_type FROM billing_details bd JOIN billing_periods bp ON bp.id = bd.billing_period_id '
            . 'WHERE bd.external_detail_id = :detail_id',
        );
        $creditType->execute(['detail_id' => 'FACT-CREDIT-NOTE']);
        self::assertSame('CREDIT_NOTE', $creditType->fetchColumn());
        self::assertSame('0.5000', $credit['detail_amount']);
        self::assertSame('FACT-SHARED-CHARGE', $credit['associated_detail_id']);
        self::assertSame('FACT-BONUS', $this->context($credit['context_json'])['charge']['charge_bonified_id']);

        self::assertSame('12.3400', $this->pdo->query(
            "SELECT sale_fee FROM order_items oi JOIN orders o ON o.id = oi.order_id "
            . "WHERE o.external_order_id = '200000000101'",
        )->fetchColumn());
        self::assertNull($this->pdo->query(
            "SELECT sale_fee FROM order_items oi JOIN orders o ON o.id = oi.order_id "
            . "WHERE o.external_order_id = '200000000102'",
        )->fetchColumn());
    }

    public function testFinancialProjectionKeepsOperationalAndBillingFactsSeparateAcrossMatrix(): void
    {
        $this->insertSalesOrder('A-ORDER', 'COP', null, '12.3400');
        $this->persistBillingDetail($this->billPeriodId, $this->billingDetail(
            'A-BILL', 'CHARGE', 'CV', '7.0000', 'COP', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'A-ORDER']],
        ));

        $this->insertSalesOrder('B-ORDER-1', 'COP', 'B-PACK', '1.0000');
        $this->insertSalesOrder('B-ORDER-2', 'COP', 'B-PACK', '2.0000');
        $this->persistBillingDetail($this->billPeriodId, $this->billingDetail(
            'B-SHARED', 'CHARGE', 'CV', '11.0000', 'COP', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'B-ORDER-1']],
            shippingInfo: ['pack_id' => 'B-PACK', 'receiver_shipping_cost' => '4.2500'],
            itemsInfo: [['item_id' => 'B-ITEM-2', 'order_id' => 'B-ORDER-2']],
        ));

        $this->insertSalesOrder('C-MISSING', 'COP', null, null);
        $this->persistBillingDetail($this->billPeriodId, $this->billingDetail(
            'C-RECEIVER-MISSING', 'CHARGE', 'CV', '1.0000', 'COP', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'C-MISSING']],
            shippingInfo: ['shipping_id' => 'C-SHIP-MISSING'],
        ));
        $this->insertSalesOrder('C-ZERO', 'COP', null, '0.0000');
        $this->persistBillingDetail($this->billPeriodId, $this->billingDetail(
            'C-RECEIVER-ZERO', 'CHARGE', 'CV', '1.0000', 'COP', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'C-ZERO']],
            shippingInfo: ['receiver_shipping_cost' => '0.0000'],
        ));

        $this->insertSalesOrder('D-ORDER', 'COP', null, '1.0000');
        $this->persistBillingDetail($this->billPeriodId, $this->billingDetail(
            'D-BASE', 'CHARGE', 'CV', '10.0000', 'COP', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'D-ORDER']],
        ));
        $this->persistBillingDetail($this->creditNotePeriodId, $this->billingDetail(
            'D-CREDIT', 'CHARGE', 'CV', '2.0000', 'COP', '2026-09-02T00:00:00Z',
            associatedDetailId: 'D-BASE',
        ));
        $this->persistBillingDetail($this->billPeriodId, $this->billingDetail(
            'D-BONUS', 'CHARGE', 'CV', '3.0000', 'COP', '2026-09-03T00:00:00Z',
            associatedDetailId: 'D-CREDIT',
        ));
        $duplicateDetail = $this->billingDetail(
            'D-DUPLICATE', 'CHARGE', 'CV', '4.0000', 'COP', '2026-09-04T00:00:00Z',
            associatedDetailId: 'D-DUP-PARENT',
            salesInfo: [['order_id' => 'D-ORDER']],
        );
        $this->persistBillingDetail($this->billPeriodId, $duplicateDetail);
        $duplicateDetail['charge_info']['detail_amount'] = '5.0000';
        $this->persistBillingDetail($this->creditNotePeriodId, $duplicateDetail);
        $this->persistBillingDetail($this->creditNotePeriodId, $this->billingDetail(
            'D-DUP-PARENT', 'CHARGE', 'CV', '1.0000', 'COP', '2026-09-05T00:00:00Z',
        ));

        $this->insertSalesOrder('E-ORDER-1', 'COP', null, '1.0000');
        $this->insertSalesOrder('E-ORDER-2', 'COP', null, '1.0000');
        $this->persistBillingDetail($this->billPeriodId, $this->billingDetail(
            'E-CONFLICT', 'CHARGE', 'CV', '5.0000', 'USD', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'E-ORDER-1']],
            itemsInfo: [['item_id' => 'E-OTHER', 'order_id' => 'E-ORDER-2']],
        ));
        $this->insertSalesOrder('E-PACK-ORDER', 'COP', null, '1.0000');
        $this->persistBillingDetail($this->billPeriodId, $this->billingDetail(
            'E-PACK-UNVERIFIABLE', 'CHARGE', 'CV', '1.0000', 'COP', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'E-PACK-ORDER']],
            shippingInfo: ['pack_id' => 'PACK-NOT-LOCAL'],
        ));

        $single = $this->projection('A-ORDER');
        self::assertSame('order', $single['scope']['type']);
        self::assertSame(['A-ORDER'], $single['scope']['order_ids']);
        self::assertSame('37.02000000', $single['operational']['sale_fee_total']);
        self::assertSame('KNOWN', $single['coverage']['sale_fee']);
        self::assertSame('COP', $single['currency']);
        self::assertSame('7.0000', $single['billing']['details'][0]['detail_amount']);
        self::assertArrayNotHasKey('net', $single);
        self::assertArrayNotHasKey('official_total', $single);
        self::assertArrayNotHasKey('final_cost', $single);

        $pack = $this->projection('B-ORDER-1');
        self::assertSame('pack', $pack['scope']['type']);
        self::assertSame(['B-ORDER-1', 'B-ORDER-2'], $pack['scope']['order_ids']);
        self::assertSame('B-PACK', $pack['scope']['pack_id']);
        self::assertCount(1, $pack['billing']['details']);
        self::assertSame('B-SHARED', $pack['billing']['details'][0]['external_detail_id']);
        self::assertSame('KNOWN', $pack['coverage']['billing_identity']);
        self::assertSame('4.2500', $pack['shipping']['receiver_shipping'][0]['value']);
        self::assertNull($pack['shipping']['seller_shipping_cost']);
        self::assertSame('UNKNOWN', $pack['coverage']['seller_shipping']);

        $missing = $this->projection('C-MISSING');
        self::assertNull($missing['operational']['sale_fee_total']);
        self::assertSame('UNKNOWN', $missing['coverage']['sale_fee']);
        self::assertSame('UNKNOWN', $missing['coverage']['receiver_shipping']);
        self::assertNull($missing['shipping']['seller_shipping_cost']);
        $zero = $this->projection('C-ZERO');
        self::assertSame('0.00000000', $zero['operational']['sale_fee_total']);
        self::assertSame('KNOWN', $zero['coverage']['sale_fee']);
        self::assertSame('0.0000', $zero['shipping']['receiver_shipping'][0]['value']);
        self::assertSame('KNOWN', $zero['coverage']['receiver_shipping']);

        $associated = $this->projection('D-ORDER');
        $associatedIds = array_column($associated['billing']['details'], 'external_detail_id');
        $detailsById = array_column($associated['billing']['details'], null, 'external_detail_id');
        self::assertSame(['D-BASE', 'D-BONUS', 'D-CREDIT', 'D-DUP-PARENT'], $associatedIds);
        self::assertCount(count(array_unique($associatedIds)), $associatedIds);
        self::assertSame('D-CREDIT', $detailsById['D-BONUS']['associated_detail_id']);
        self::assertSame('D-BASE', $detailsById['D-CREDIT']['associated_detail_id']);
        self::assertArrayNotHasKey('D-DUPLICATE', $detailsById);
        self::assertArrayHasKey('D-DUP-PARENT', $detailsById);
        self::assertSame('CONFLICT', $associated['coverage']['billing_identity']);
        self::assertSame('UNKNOWN', $associated['coverage']['official_reference']);

        $conflict = $this->projection('E-ORDER-1');
        self::assertSame('CONFLICT', $conflict['coverage']['billing_identity']);
        self::assertSame('CONFLICT', $conflict['coverage']['currency']);
        self::assertNull($conflict['currency']);
        self::assertSame('E-CONFLICT', $conflict['billing']['details'][0]['external_detail_id']);
        self::assertArrayNotHasKey('billing_total', $conflict['billing']);

        $unverifiablePack = $this->projection('E-PACK-ORDER');
        self::assertSame('CONFLICT', $unverifiablePack['coverage']['billing_identity']);
    }

    public function testFinancialProjectionIsTenantScopedAndReadOnly(): void
    {
        $this->insertSalesOrder('F-ORDER', 'COP', 'F-PACK', '0.0000');

        $otherCompany = $this->pdo->prepare('INSERT INTO companies (name, slug) VALUES (?, ?)');
        $otherCompany->execute(['Financial Other Tenant', 'financial-other-' . bin2hex(random_bytes(4))]);
        $otherCompanyId = (int) $this->pdo->lastInsertId();
        $otherAccount = $this->pdo->prepare(
            "INSERT INTO meli_accounts (company_id, external_user_id, site_id, status) VALUES (?, '700000910', 'MCO', 'connected')",
        );
        $otherAccount->execute([$otherCompanyId]);
        $otherAccountId = (int) $this->pdo->lastInsertId();
        $this->insertSalesOrder('F-ORDER', 'USD', 'F-PACK', '99.0000', $otherCompanyId, $otherAccountId);
        $otherPeriodId = $this->insertPeriodFor($otherCompanyId, $otherAccountId, 'BILL');
        $this->persistBillingDetail($otherPeriodId, $this->billingDetail(
            'F-OTHER-TENANT', 'CHARGE', 'CV', '99.0000', 'USD', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'F-ORDER']],
            shippingInfo: ['pack_id' => 'F-PACK'],
        ));

        $workBefore = (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn();
        $writesBefore = $this->writeCommandCounts();
        $projection = $this->projection('F-ORDER');
        $writesAfter = $this->writeCommandCounts();
        $workAfter = (int) $this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn();

        self::assertSame(['F-ORDER'], $projection['scope']['order_ids']);
        self::assertSame('COP', $projection['currency']);
        self::assertSame([], $projection['billing']['details']);
        self::assertSame($workBefore, $workAfter);
        self::assertSame($writesBefore, $writesAfter);
        self::assertSame('0', getenv('REAL_MELI_HTTP'));

        $constructor = (new \ReflectionClass(FinancialReadModel::class))->getConstructor();
        self::assertNotNull($constructor);
        self::assertCount(1, $constructor->getParameters());
        self::assertSame(PDO::class, $constructor->getParameters()[0]->getType()?->getName());
    }

    public function testCanonicalReplayLeavesFinancialProjectionUnchanged(): void
    {
        $this->insertSalesOrder('G-ORDER', 'COP', null, '1.0000');
        $sourceDetail = $this->billingDetail(
            'G-REPLAY', 'CHARGE', 'CV', '2.0000', 'COP', '2026-09-01T00:00:00Z',
            salesInfo: [['order_id' => 'G-ORDER']],
        );
        $this->persistBillingDetail($this->billPeriodId, $sourceDetail);
        $before = $this->projection('G-ORDER');

        $token = $this->pdo->prepare(
            'INSERT INTO meli_tokens (account_id, access_token_cipher, refresh_token_cipher, expires_at) '
            . 'VALUES (:account_id, :access_token_cipher, :refresh_token_cipher, :expires_at)',
        );
        $token->execute([
            'account_id' => $this->accountId,
            'access_token_cipher' => $this->cipher->encrypt('financial-replay-access'),
            'refresh_token_cipher' => $this->cipher->encrypt('financial-replay-refresh'),
            'expires_at' => '2030-01-02 00:00:00.000000',
        ]);

        $work = new WorkRepository($this->pdo);
        $scope = 'company:' . $this->companyId . ':account:' . $this->accountId;
        $resource = '2026-09-01:BILL';
        $work->enqueue(
            $this->companyId,
            $this->accountId,
            $scope,
            'billing.period.sync',
            $resource,
            'billing.period.sync:' . $resource . ':0',
            ['period_key' => '2026-09-01', 'document_type' => 'BILL', 'from_id' => '0'],
            new DateTimeImmutable('2000-01-01T00:00:00+00:00'),
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        $transport = new FinancialReplayFakeTransport([
            new MeliTransportResponse(200, [], json_encode(
                ['results' => [$sourceDetail], 'last_id' => 101],
                JSON_THROW_ON_ERROR,
            )),
        ]);
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
        $handler = new BillingPeriodSyncHandler($work, $client, $tokens, new BillingDetailNormalizer());
        self::assertTrue($handler->syncCurrentClaim(
            (int) $claim['id'],
            (string) $claim['claim_token'],
            $this->companyId,
            $this->accountId,
            '2026-09-01',
            'BILL',
            '0',
            new DateTimeImmutable('2026-10-11T12:00:00-05:00'),
        ));

        self::assertCount(1, $transport->requests);
        self::assertSame($before, $this->projection('G-ORDER'));
        self::assertSame(1, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM billing_details WHERE external_detail_id = 'G-REPLAY'",
        )->fetchColumn());
    }

    private function insertPeriod(string $documentType): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO billing_periods (company_id, account_id, period_key, document_type) VALUES (?, ?, ?, ?)',
        );
        $statement->execute([$this->companyId, $this->accountId, '2026-09-01', $documentType]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertPeriodFor(int $companyId, int $accountId, string $documentType): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO billing_periods (company_id, account_id, period_key, document_type) VALUES (?, ?, ?, ?)',
        );
        $statement->execute([$companyId, $accountId, '2026-09-01', $documentType]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertSalesOrder(
        string $externalOrderId,
        string $currencyId,
        ?string $packId,
        ?string $saleFee,
        ?int $companyId = null,
        ?int $accountId = null,
        string $quantity = '3.0000',
    ): int {
        $companyId ??= $this->companyId;
        $accountId ??= $this->accountId;
        $order = $this->pdo->prepare(
            'INSERT INTO orders (company_id, account_id, external_order_id, status, total_amount, currency_id, pack_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $order->execute([$companyId, $accountId, $externalOrderId, 'paid', '90.0000', $currencyId, $packId]);
        $orderId = (int) $this->pdo->lastInsertId();

        $item = $this->pdo->prepare(
            'INSERT INTO order_items '
            . '(order_id, external_item_id, title, quantity, unit_price, sale_fee, currency_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $item->execute([$orderId, 'MCO-' . $externalOrderId, 'Fixture item', $quantity, '30.0000', $saleFee, $currencyId]);

        return $orderId;
    }

    /** @return array<string,mixed> */
    private function projection(string $externalOrderId): array
    {
        return (new FinancialReadModel($this->pdo))->forOrder($this->companyId, $this->accountId, $externalOrderId);
    }

    /** @return array<string,string> */
    private function writeCommandCounts(): array
    {
        $statement = $this->pdo->query(
            "SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_update','Com_delete','Com_replace',"
            . "'Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Com_rename_table')",
        );
        $counts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['Variable_name']] = (string) $row['Value'];
        }
        ksort($counts);

        return $counts;
    }

    /** @param array<string,mixed> $detail
     * @return array{
     *   external_detail_id:string,associated_detail_id:?string,detail_type:string,detail_sub_type:?string,
     *   detail_amount:string,currency_id:?string,document_id:?string,marketplace:?string,
     *   context_json:?string,remote_created_at:?string
     * }
     */
    private function persistBillingDetail(int $periodId, array $detail): array
    {
        $normalized = (new BillingDetailNormalizer())->normalize($detail);
        $statement = $this->pdo->prepare(
            'INSERT INTO billing_details '
            . '(billing_period_id, external_detail_id, associated_detail_id, detail_type, detail_sub_type, '
            . 'detail_amount, currency_id, document_id, marketplace, context_json, remote_created_at) '
            . 'VALUES (:billing_period_id, :external_detail_id, :associated_detail_id, :detail_type, :detail_sub_type, '
            . ':detail_amount, :currency_id, :document_id, :marketplace, :context_json, :remote_created_at)',
        );
        $statement->execute(['billing_period_id' => $periodId] + $normalized);

        $stored = $this->pdo->prepare(
            'SELECT external_detail_id, associated_detail_id, detail_type, detail_sub_type, detail_amount, '
            . 'currency_id, document_id, marketplace, context_json, remote_created_at '
            . 'FROM billing_details WHERE billing_period_id = :billing_period_id AND external_detail_id = :external_detail_id',
        );
        $stored->execute([
            'billing_period_id' => $periodId,
            'external_detail_id' => $normalized['external_detail_id'],
        ]);
        $row = $stored->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @param array<string,mixed> $chargeFields
     * @param list<array<string,mixed>> $salesInfo
     * @param array<string,mixed> $shippingInfo
     * @param list<array<string,mixed>> $itemsInfo
     * @return array<string,mixed>
     */
    private function billingDetail(
        string $detailId,
        string $type,
        ?string $subType,
        string $amount,
        string $currency,
        string $createdAt,
        ?string $associatedDetailId = null,
        array $chargeFields = [],
        array $salesInfo = [],
        array $shippingInfo = [],
        array $itemsInfo = [],
    ): array {
        $detail = [
            'charge_info' => array_merge([
                'detail_id' => $detailId,
                'detail_associated_id' => $associatedDetailId,
                'detail_amount' => $amount,
                'detail_type' => $type,
                'detail_sub_type' => $subType,
                'creation_date_time' => $createdAt,
            ], $chargeFields),
            'currency_info' => ['currency_id' => $currency],
            'marketplace_info' => ['marketplace' => 'MCO'],
        ];
        if ($salesInfo !== []) {
            $detail['sales_info'] = $salesInfo;
        }
        if ($shippingInfo !== []) {
            $detail['shipping_info'] = $shippingInfo;
        }
        if ($itemsInfo !== []) {
            $detail['items_info'] = $itemsInfo;
        }

        return $detail;
    }

    /** @return array<string,mixed> */
    private function context(?string $contextJson): array
    {
        self::assertNotNull($contextJson);
        $context = json_decode($contextJson, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($context);

        return $context;
    }
}

final class FinancialReplayFakeTransport implements MeliTransport
{
    /** @param list<MeliTransportResponse|RuntimeException> $responses */
    public function __construct(private array $responses)
    {
    }

    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $response = array_shift($this->responses);
        if ($response instanceof RuntimeException) {
            throw $response;
        }
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('No queued Financial replay response.');
        }

        return $response;
    }
}
