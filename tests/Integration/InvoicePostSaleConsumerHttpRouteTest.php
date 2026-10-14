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

final class InvoicePostSaleConsumerHttpRouteTest extends TestCase
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
        $this->pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'operator@example.test','operator@example.test','operator@example.test','unused','active')");
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'member')");
        $this->pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES
            (10,1,'first-account','MCO','connected'),(11,1,'exact-account','MCO','connected'),(20,2,'foreign-account','MCO','connected')");
        $_SESSION = ['user_id'=>1,'company_id'=>1];
    }

    private function order(int $id, int $account, string $external, string $status = 'paid', ?string $pack = null): void
    {
        $statement = $this->pdo->prepare("INSERT INTO orders(id,company_id,account_id,external_order_id,status,date_created,total_amount,currency_id,pack_id) VALUES(?,?,?,?,?,'2026-10-12 00:00:00','10.0000','COP',?)");
        $statement->execute([$id, $account === 20 ? 2 : 1, $account, $external, $status, $pack]);
        $statement = $this->pdo->prepare("INSERT INTO order_items(order_id,external_item_id,title,quantity,unit_price,currency_id,sale_fee) VALUES(?,'ITEM','Local item',1,'10.0000','COP','0.0000')");
        $statement->execute([$id]);
    }

    /** @param array<string,mixed> $query */
    private function detail(string $external, array $query = []): ResponseInterface
    {
        $work = $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll();
        $usage = $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll();
        $artifacts = $this->pdo->query('SELECT * FROM invoicing_artifacts ORDER BY id')->fetchAll();
        $keys = $this->pdo->query('SELECT * FROM invoicing_service_keys ORDER BY id')->fetchAll();
        $writes = $this->writeCounts();
        $this->queries = [];
        $response = (new SalesListController($this->pdo))->detail(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales/' . $external)->withQueryParams($query),
            (new ResponseFactory())->createResponse(), $external,
        );
        self::assertSame($writes, $this->writeCounts());
        self::assertSame($work, $this->pdo->query('SELECT * FROM work_items ORDER BY id')->fetchAll());
        self::assertSame($usage, $this->pdo->query('SELECT * FROM api_usage_daily')->fetchAll());
        self::assertSame($artifacts, $this->pdo->query('SELECT * FROM invoicing_artifacts ORDER BY id')->fetchAll());
        self::assertSame($keys, $this->pdo->query('SELECT * FROM invoicing_service_keys ORDER BY id')->fetchAll());
        return $response;
    }

    public function testCanonicalSaleShowsLocalReviewWithoutMachineCredentials(): void
    {
        $this->order(101, 11, '1001');
        $this->order(201, 20, '1001', 'cancelled');
        $response = $this->detail('1001', ['company_id'=>'2','account_id'=>'10']);
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        self::assertStringContainsString('Factura / Postventa', $html);
        self::assertStringContainsString('ORDER_READY', $html);
        self::assertStringNotContainsString('CANCELLED_BEFORE_INVOICE', $html);
        $projectionQueries = array_values(array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'LEFT JOIN invoicing_artifacts ia')));
        self::assertCount(1, $projectionQueries);
        self::assertStringContainsString('o.account_id = :account_id', $projectionQueries[0]);
        self::assertStringContainsString('o.external_order_id = :external_order_id', $projectionQueries[0]);
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'invoicing_service_keys')));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM invoicing_service_keys')->fetchColumn());
        $route = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/sales/1001'));
        self::assertSame(200, $route->getStatusCode());
        self::assertStringContainsString('Factura / Postventa', (string) $route->getBody());
    }

    public function testForeignAndAmbiguousSalesFailBeforeInvoicing(): void
    {
        $this->order(201, 20, '2001');
        self::assertSame(404, $this->detail('2001')->getStatusCode());
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'invoicing_artifacts')));
        $this->order(101, 10, '1001');
        $this->order(102, 11, '1001');
        self::assertSame(404, $this->detail('1001', ['account_id'=>'11'])->getStatusCode());
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'invoicing_artifacts')));
    }

    public function testSalesListHasZeroInvoicingProjections(): void
    {
        $this->order(101, 11, '1001');
        $this->queries = [];
        $response = (new SalesListController($this->pdo))->show(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales'), (new ResponseFactory())->createResponse(),
        );
        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Factura / Postventa', (string) $response->getBody());
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'invoicing_artifacts')));
    }

    #[DataProvider('reviewStates')]
    public function testReviewRendersCanonicalStatesAndArtifactPresence(string $status, ?string $pack, bool $invoice, bool $credit, string $readiness, string $postSale): void
    {
        $this->order(101, 11, '1001', $status, $pack);
        $invoiceId = $invoice ? $this->artifact('INVOICE') : null;
        if ($credit) { $this->artifact('CREDIT_NOTE', $invoiceId); }
        $html = (string) $this->detail('1001')->getBody();
        self::assertSame($readiness, $this->value($html, 'Readiness'));
        self::assertSame($invoice ? 'Sí' : 'No', $this->value($html, 'Factura registrada'));
        self::assertSame($credit ? 'Sí' : 'No', $this->value($html, 'Nota crédito registrada'));
        self::assertSame($postSale, $this->value($html, 'Postventa'));
        foreach (['PRIVATE_REFERENCE','PRIVATE_NUMBER','PRIVATE_CUFE','PRIVATE_PROVIDER_DOC'] as $private) {
            self::assertStringNotContainsString($private, $html);
        }
    }

    public static function reviewStates(): iterable
    {
        yield 'order ready without artifacts' => ['paid',null,false,false,'ORDER_READY','NONE'];
        yield 'unverified pack' => ['paid','PACK-A',false,false,'PACK_MEMBERSHIP_UNVERIFIED','NONE'];
        yield 'cancelled before invoice' => ['cancelled',null,false,false,'CANCELLED','CANCELLED_BEFORE_INVOICE'];
        yield 'cancelled with invoice' => ['cancelled',null,true,false,'CANCELLED','CREDIT_NOTE_REVIEW_REQUIRED'];
        yield 'recorded credit note' => ['cancelled',null,true,true,'CANCELLED','CREDIT_NOTE_RECORDED'];
    }

    public function testBillingCreditNoteWithoutCustomerCreditNoteCannotBecomeRecorded(): void
    {
        $this->order(101, 11, '1001', 'cancelled');
        $this->artifact('INVOICE');
        $this->pdo->exec("INSERT INTO billing_periods(id,company_id,account_id,period_key,document_type) VALUES(1,1,11,'2026-09-01','CREDIT_NOTE')");
        $context = json_encode(['sales'=>[['order_id'=>'1001']]], JSON_THROW_ON_ERROR);
        $statement = $this->pdo->prepare("INSERT INTO billing_details(billing_period_id,external_detail_id,detail_type,detail_amount,currency_id,context_json) VALUES(1,'BILLING-CN','CHARGE','2.0000','COP',?)");
        $statement->execute([$context]);
        $html = (string) $this->detail('1001')->getBody();
        self::assertStringContainsString('CREDIT_NOTE', $html, 'The separate Billing fact remains visible in Financial.');
        self::assertSame('Sí', $this->value($html, 'Factura registrada'));
        self::assertSame('No', $this->value($html, 'Nota crédito registrada'));
        self::assertSame('CREDIT_NOTE_REVIEW_REQUIRED', $this->value($html, 'Postventa'));
        self::assertStringNotContainsString('CREDIT_NOTE_RECORDED', $html);
    }

    private function artifact(string $kind, ?int $original = null): int
    {
        $statement = $this->pdo->prepare("INSERT INTO invoicing_artifacts(company_id,account_id,source_order_id,document_kind,original_invoice_artifact_id,
            document_number,issued_at,currency,exact_total,pdf_storage_ref,pdf_sha256,cufe,provider_document_id)
            VALUES(1,11,101,?,?,'PRIVATE_NUMBER','2026-10-12 00:00:00','COP','10.0000','PRIVATE_REFERENCE',?,'PRIVATE_CUFE','PRIVATE_PROVIDER_DOC')");
        $statement->execute([$kind, $original, hash('sha256','synthetic-artifact')]);
        return (int) $this->pdo->lastInsertId();
    }

    private function value(string $html, string $label): string
    {
        self::assertSame(1, preg_match('~<dt>' . preg_quote($label, '~') . '</dt><dd>(.*?)</dd>~s', $html, $match), 'Missing review field: ' . $label);
        return html_entity_decode(strip_tags($match[1]), ENT_QUOTES, 'UTF-8');
    }

    public function testReviewIsPureReadAndDoesNotExposeArtifactDetailsOrAddControls(): void
    {
        $this->order(101, 11, '1001', 'cancelled');
        $this->artifact('INVOICE');
        $this->pdo->exec("UPDATE order_items SET title='<script>item</script>'");
        $this->pdo->exec("INSERT INTO work_items(scope_key,type,dedupe_key,status,available_at) VALUES('app','unsupported.test',REPEAT('a',64),'pending',UTC_TIMESTAMP(6))");
        $html = (string) $this->detail('1001')->getBody();
        self::assertStringContainsString('&lt;script&gt;item&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertSame(1, preg_match('~<section id="invoice-review">(.*?)</section>~s', $html, $match));
        $section = $match[1];
        self::assertSame(4, substr_count($section, '<dt>'));
        foreach (['PRIVATE_', 'pdf_storage_ref', 'artifact_id', 'context_json', 'href=', '<form', 'download', '<script'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $section);
        }
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'invoicing_service_keys')));
    }

    public function testDisabledAndUnauthenticatedRequestsNeverProjectInvoicing(): void
    {
        $this->order(101, 11, '1001');
        $this->pdo->exec("UPDATE users SET status='disabled'");
        self::assertSame(403, $this->detail('1001')->getStatusCode());
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'invoicing_artifacts')));
        $route = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET','/sales/1001'));
        self::assertSame(403, $route->getStatusCode());
        $_SESSION = [];
        self::assertSame(403, $this->detail('1001')->getStatusCode());
        self::assertCount(0, array_filter($this->queries, static fn(string $sql): bool => str_contains($sql, 'invoicing_artifacts')));
    }

    /** @return array<string,string> */
    private function writeCounts(): array
    {
        $rows = $this->pdo->query("SHOW SESSION STATUS WHERE Variable_name IN ('Com_insert','Com_update','Com_delete','Com_replace','Com_create_table','Com_alter_table','Com_drop_table','Com_truncate','Com_rename_table')")->fetchAll(PDO::FETCH_ASSOC);
        $counts = [];
        foreach ($rows as $row) { $counts[$row['Variable_name']] = $row['Value']; }
        return $counts;
    }
}
