<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Config\AppConfig;
use App\Modules\Sales\ViewSales\SalesListController;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class FinancialConsumerHttpRouteTest extends TestCase
{
    private PDO $pdo;
    /** @var list<string> */
    private array $queries = [];

    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->pdo = new class(TestDatabase::config(), $this->queries) extends PDO {
            /** @var list<string> */
            private array $queries;
            public function __construct(AppConfig $config, array &$queries)
            {
                parent::__construct(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->dbHost, $config->dbPort, $config->dbName),
                    $config->dbUser, $config->dbPassword, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
                $this->queries =& $queries;
            }
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                $this->queries[] = $query;
                return parent::prepare($query, $options);
            }
        };
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Main','main'),(2,'Foreign','foreign')");
        $this->pdo->exec("INSERT INTO users(id,email,password_hash,status) VALUES(1,'financial@example.test','unused','active')");
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'member')");
        $this->pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES
            (10,1,'first-account','MCO','connected'),(11,1,'exact-account','MCO','connected'),(20,2,'foreign-account','MCO','connected')");
        $_SESSION = ['user_id'=>1, 'company_id'=>1];
    }

    private function order(int $id, int $account, string $external, ?string $pack = null, ?string $fee = '0.0000'): void
    {
        $statement = $this->pdo->prepare("INSERT INTO orders(id,company_id,account_id,external_order_id,status,currency_id,total_amount,pack_id) VALUES(?,?,?,?, 'paid','COP','10.0000',?)");
        $statement->execute([$id, $account === 20 ? 2 : 1, $account, $external, $pack]);
        $statement = $this->pdo->prepare("INSERT INTO order_items(order_id,external_item_id,title,quantity,unit_price,currency_id,sale_fee) VALUES(?,'ITEM','Local item',1,'10.0000','COP',?)");
        $statement->execute([$id, $fee]);
    }

    /** @param array<string,mixed> $query */
    private function detail(string $external, array $query = []): ResponseInterface
    {
        $work = $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll();
        $usage = $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll();
        $writes = $this->writeCounts();
        $this->queries = [];
        $response = (new SalesListController($this->pdo))->detail(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales/' . $external)->withQueryParams($query),
            (new ResponseFactory())->createResponse(), $external,
        );
        self::assertSame($writes, $this->writeCounts(), 'Detail must not write domain data.');
        self::assertSame($work, $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll(), 'No Work may be created, claimed or mutated.');
        self::assertSame($usage, $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll(), 'No Meli dispatch accounting.');
        return $response;
    }

    public function testUniqueSaleLookupCarriesItsExactAccountAndRejectsForeignSale(): void
    {
        $this->order(101, 11, '1001');
        $this->order(201, 20, '1001');
        $response = $this->detail('1001', ['account_id'=>'10','company_id'=>'2']);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('exact-account', (string) $response->getBody());
        self::assertStringNotContainsString('foreign-account', (string) $response->getBody());
        $this->order(202, 20, '2002');
        self::assertSame(404, $this->detail('2002')->getStatusCode());
        self::assertSame(404, $this->detail('9999')->getStatusCode());
    }

    public function testCrossAccountExternalIdAmbiguityFailsClosedWithoutChoosingClientAccount(): void
    {
        $this->order(101, 10, '1001');
        $this->order(102, 11, '1001');
        $response = $this->detail('1001', ['account_id'=>'11']);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testDetailReadsFinancialOnceFromExactAccountAndRendersOrderScope(): void
    {
        $this->order(101, 11, '1001', fee: '9.1250');
        $response = $this->detail('1001', ['account_id'=>'10']);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Reconciliación', $html);
        self::assertStringContainsString('Ámbito: order', $html);
        self::assertStringContainsString('9.12500000', $html);
        self::assertCount(1, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'AND external_order_id = :external_order_id')));
        $route = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales/1001'));
        self::assertSame(200, $route->getStatusCode());
        self::assertStringContainsString('Reconciliación', (string) $route->getBody());
    }

    public function testDetailRendersExistingPackScopeAndItsLocalMembers(): void
    {
        $this->order(101, 11, '1001', 'PACK-A', '1.0000');
        $this->order(102, 11, '1002', 'PACK-A', '2.0000');
        $html = (string) $this->detail('1001')->getBody();
        self::assertStringContainsString('Ámbito: pack', $html);
        self::assertStringContainsString('PACK-A', $html);
        self::assertStringContainsString('1002', $html);
        self::assertStringContainsString('3.00000000', $html);
        self::assertCount(1, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'AND external_order_id = :external_order_id')));
    }

    #[DataProvider('feeStates')]
    public function testRenderedCoveragePreservesZeroUnknownAndConflict(string $case, string $value, string $coverage): void
    {
        $this->order(101, 11, '1001', $case === 'conflict' ? 'PACK-A' : null, $case === 'unknown' ? null : '0.0000');
        if ($case === 'conflict') {
            $this->order(102, 11, '1002', 'PACK-A');
            $this->pdo->exec("UPDATE order_items SET currency_id='USD' WHERE order_id=102");
        }
        $html = (string) $this->detail('1001')->getBody();
        self::assertSame($value, $this->value($html, 'Comisión operativa'));
        self::assertSame($coverage, $this->value($html, 'Cobertura de comisión'));
        self::assertSame('UNKNOWN', $this->value($html, 'Envío del vendedor'));
        self::assertSame('UNKNOWN', $this->value($html, 'Referencia oficial'));
        self::assertSame('UNKNOWN', $this->value($html, 'Cobertura Billing'));
        self::assertStringNotContainsString('Neto', $html);
    }

    public static function feeStates(): iterable
    {
        yield 'explicit known zero' => ['zero','0.00000000','KNOWN'];
        yield 'missing fee' => ['unknown','UNKNOWN','UNKNOWN'];
        yield 'mixed operational currencies' => ['conflict','CONFLICT','CONFLICT'];
    }

    public function testSharedPackBillingFactAndShippingEvidenceAppearOnceWithoutAllocation(): void
    {
        $this->order(101, 11, '1001', 'PACK-A');
        $this->order(102, 11, '1002', 'PACK-A');
        $this->billingFact('SHARED-FACT', '7.2500', 'COP', ['sales'=>[['order_id'=>'1001'],['order_id'=>'1002']],
            'shipping'=>['pack_id'=>'PACK-A','receiver_shipping_cost'=>'0.0000']]);
        $html = (string) $this->detail('1001')->getBody();
        self::assertSame(1, substr_count($html, '<td>SHARED-FACT</td>'));
        self::assertSame(1, substr_count($html, '<td>7.2500</td>'));
        self::assertSame('KNOWN', $this->value($html, 'Cobertura Billing'));
        self::assertSame('KNOWN', $this->value($html, 'Cobertura envío del receptor'));
        self::assertStringContainsString('0.0000', $html);
        self::assertSame('UNKNOWN', $this->value($html, 'Envío del vendedor'));
    }

    public function testNumericPackAndDetailsRenderReconciliationAndInvoiceReviewWithoutSideEffects(): void
    {
        $this->order(101, 11, '1001', '9007199254740993');
        $this->order(102, 11, '1002', '9007199254740993');
        foreach (['102', '101'] as $id) {
            $this->billingFact($id, '1.0000', 'COP', [
                'sales'=>[['order_id'=>'1001']],
                'shipping'=>['pack_id'=>'9007199254740993','receiver_shipping_cost'=>'0.0000'],
            ]);
        }
        $response = $this->detail('1001', ['account_id'=>'10']);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Reconciliación', $html);
        self::assertStringContainsString('Factura / Postventa', $html);
        self::assertStringContainsString('9007199254740993', $html);
        self::assertSame('KNOWN', $this->value($html, 'Cobertura Billing'));
        foreach (['101', '102'] as $id) {
            self::assertSame(1, substr_count($html, '<td>' . $id . '</td>'));
            self::assertStringContainsString('Detalle ' . $id . ': 0.0000', $html);
        }
        $work = $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll();
        $usage = $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll();
        $writes = $this->pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Com_insert','Com_update','Com_delete','Com_replace','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Com_rename_table')")->fetchAll();
        $route = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales/1001'));
        self::assertSame(200, $route->getStatusCode());
        self::assertStringContainsString('Reconciliación', (string) $route->getBody());
        self::assertStringContainsString('Factura / Postventa', (string) $route->getBody());
        self::assertSame($writes, $this->pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Com_insert','Com_update','Com_delete','Com_replace','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Com_rename_table')")->fetchAll());
        self::assertSame($work, $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll());
        self::assertSame($usage, $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll());
        self::assertSame('0', getenv('REAL_MELI_HTTP'));
    }

    public function testBillingIdentityConflictIsDisplayedWithoutChoosingOrAddingTotals(): void
    {
        $this->order(101, 11, '1001');
        $this->billingFact('AMBIGUOUS-FACT', '7.2500', 'USD', ['sales'=>[['order_id'=>'1001'],['order_id'=>'9999']]]);
        $html = (string) $this->detail('1001')->getBody();
        self::assertSame('CONFLICT', $this->value($html, 'Cobertura Billing'));
        self::assertSame('CONFLICT', $this->value($html, 'Moneda del ámbito'));
        self::assertSame(1, substr_count($html, '<td>7.2500</td>'));
        self::assertSame('UNKNOWN', $this->value($html, 'Referencia oficial'));
    }

    public function testLargeExactFeeIsRenderedWithoutFloatAndReadHasNoSideEffects(): void
    {
        $this->order(101, 11, '1001', fee: '90071992547409.1234');
        $this->pdo->exec("INSERT INTO work_items(scope_key,type,dedupe_key,status,available_at) VALUES('app','unsupported.test',REPEAT('a',64),'pending',UTC_TIMESTAMP(6))");
        $html = (string) $this->detail('1001')->getBody();
        self::assertSame('90071992547409.12340000', $this->value($html, 'Comisión operativa'));
    }

    /** @param array<string,mixed> $context */
    private function billingFact(string $id, string $amount, string $currency, array $context): void
    {
        $this->pdo->exec("INSERT IGNORE INTO billing_periods(id,company_id,account_id,period_key,document_type) VALUES(1,1,11,'2026-09-01','BILL')");
        $statement = $this->pdo->prepare("INSERT INTO billing_details(billing_period_id,external_detail_id,detail_type,detail_amount,currency_id,context_json) VALUES(1,?,'CHARGE',?,?,?)");
        $statement->execute([$id, $amount, $currency, json_encode($context, JSON_THROW_ON_ERROR)]);
    }

    private function value(string $html, string $label): string
    {
        self::assertSame(1, preg_match('~<dt>' . preg_quote($label, '~') . '</dt><dd>(.*?)</dd>~s', $html, $match), 'Missing reconciliation field: ' . $label);
        return html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
    }

    /** @return array<string,string> */
    private function writeCounts(): array
    {
        $rows = $this->pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_update','Com_delete','Com_replace','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Com_rename_table')")->fetchAll(PDO::FETCH_ASSOC);
        $counts = [];
        foreach ($rows as $row) { $counts[$row['Variable_name']] = $row['Value']; }
        return $counts;
    }

    public function testSalesListDoesNotReadFinancialEvenWithManyRows(): void
    {
        for ($i = 1; $i <= 100; $i++) { $this->order($i, 11, (string) (1000 + $i)); }
        $this->queries = [];
        $writes = $this->writeCounts();
        $response = (new SalesListController($this->pdo))->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales'), (new ResponseFactory())->createResponse(),
        );
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Reconciliación', (string) $response->getBody());
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'AND external_order_id = :external_order_id') || str_contains($sql, 'FROM billing_periods bp')));
        self::assertSame($writes, $this->writeCounts());
    }

    #[DataProvider('deniedRequests')]
    public function testDeniedOrAmbiguousDetailNeverReadsFinancial(string $case, int $status): void
    {
        $this->order(101, 11, '1001');
        $external = '1001';
        if ($case === 'anonymous') { $_SESSION = []; }
        if ($case === 'disabled') { $this->pdo->exec("UPDATE users SET status='disabled'"); }
        if ($case === 'foreign') { $this->order(201, 20, '2002'); $external = '2002'; }
        if ($case === 'missing') { $external = '9999'; }
        if ($case === 'ambiguous') { $this->order(102, 10, '1001'); }
        self::assertSame($status, $this->detail($external)->getStatusCode());
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'AND external_order_id = :external_order_id')));
        $route = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales/' . $external));
        self::assertSame($status, $route->getStatusCode());
    }

    public static function deniedRequests(): iterable
    {
        yield 'anonymous' => ['anonymous',403];
        yield 'disabled' => ['disabled',403];
        yield 'foreign tenant sale' => ['foreign',404];
        yield 'missing' => ['missing',404];
        yield 'cross-account ambiguity' => ['ambiguous',404];
    }

    public function testReconciliationEscapesDynamicFieldsAndNeverExposesRawContext(): void
    {
        $this->order(101, 11, '1001', '<pack&>');
        $this->pdo->exec("UPDATE order_items SET title='<script>item</script>'");
        $this->billingFact('<billed&>', '7.2500', '<C&>', ['sales'=>[['order_id'=>'1001']],
            'shipping'=>['pack_id'=>'<pack&>'], 'secret'=>'RAW_CONTEXT_SECRET']);
        $html = (string) $this->detail('1001')->getBody();
        foreach (['&lt;pack&amp;&gt;', '&lt;billed&amp;&gt;', '&lt;C&amp;&gt;', '&lt;script&gt;item&lt;/script&gt;'] as $escaped) {
            self::assertStringContainsString($escaped, $html);
        }
        foreach (['<script>', 'RAW_CONTEXT_SECRET', 'context_json', 'claim_token', 'payload_json'] as $raw) {
            self::assertStringNotContainsString($raw, $html);
        }
    }
}
