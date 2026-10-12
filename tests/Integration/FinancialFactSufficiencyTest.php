<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Billing\BillingDetailNormalizer;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class FinancialFactSufficiencyTest extends TestCase
{
    private PDO $pdo;
    private int $companyId;
    private int $accountId;
    private int $billPeriodId;
    private int $creditNotePeriodId;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
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

    private function insertPeriod(string $documentType): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO billing_periods (company_id, account_id, period_key, document_type) VALUES (?, ?, ?, ?)',
        );
        $statement->execute([$this->companyId, $this->accountId, '2026-09-01', $documentType]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertSalesOrder(
        string $externalOrderId,
        string $currencyId,
        ?string $packId,
        ?string $saleFee,
    ): void {
        $order = $this->pdo->prepare(
            'INSERT INTO orders (company_id, account_id, external_order_id, status, total_amount, currency_id, pack_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $order->execute([$this->companyId, $this->accountId, $externalOrderId, 'paid', '90.0000', $currencyId, $packId]);
        $orderId = (int) $this->pdo->lastInsertId();

        $item = $this->pdo->prepare(
            'INSERT INTO order_items '
            . '(order_id, external_item_id, title, quantity, unit_price, sale_fee, currency_id) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        $item->execute([$orderId, 'MCO-' . $externalOrderId, 'Fixture item', '3.0000', '30.0000', $saleFee, $currencyId]);
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

        return $normalized;
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
