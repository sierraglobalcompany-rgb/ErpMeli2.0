<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Modules\Invoicing\InvoicingBridge;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Tests\Support\TestDatabase;

final class InvoicingBridgeHttpRouteTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main'),(2,'Other','other')");
        $this->pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) VALUES "
            . "(10,1,'700000101','MCO','connected'),(20,2,'700000202','MCO','connected')",
        );
        $this->pdo->exec(
            "INSERT INTO orders(id,company_id,account_id,external_order_id,status,date_created,total_amount,currency_id,buyer_id,pack_id) VALUES "
            . "(101,1,10,'200000000101','paid','2026-10-10 12:00:00.000000','123456.0000','COP','998877',NULL),"
            . "(102,1,10,'200000000102','cancelled','2026-10-10 13:00:00.000000','50000.0000','COP',NULL,'300000000001'),"
            . "(103,1,10,'200000000103','cancelled','2026-10-10 15:00:00.000000','1000.0000','COP',NULL,NULL),"
            . "(104,1,10,'200000000104','paid','2026-10-10 16:00:00.000000','1000.0000','COP',NULL,NULL),"
            . "(201,2,20,'200000000201','paid','2026-10-10 14:00:00.000000','90000.0000','COP',NULL,NULL)",
        );
        $this->pdo->exec(
            "INSERT INTO order_items(order_id,external_item_id,title,quantity,unit_price,currency_id) VALUES "
            . "(101,'SKU-101','Producto uno','2.0000','61728.0000','COP'),"
            . "(102,'SKU-102','Producto cancelado','1.0000','50000.0000','COP'),"
            . "(103,'SKU-103','Producto cancelado simple','1.0000','1000.0000','COP'),"
            . "(201,'SKU-201','Producto de otra empresa','1.0000','90000.0000','COP')",
        );
    }

    public function testSourcesEndpointRequiresAValidMachineCredential(): void
    {
        $response = $this->request('GET', '/api/integrations/v1/invoicing/sources');

        self::assertSame(401, $response->getStatusCode());
        self::assertSame(['error' => 'invalid_api_key'], $this->json($response));
    }

    public function testF11SchemaStoresOnlyCredentialHashesAndOneArtifactPerSource(): void
    {
        foreach (['invoicing_service_keys', 'invoicing_artifacts'] as $table) {
            $statement = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
            self::assertSame($table, $statement->fetchColumn());
        }

        $columns = $this->pdo->query('SHOW COLUMNS FROM invoicing_service_keys')->fetchAll(\PDO::FETCH_COLUMN);
        self::assertContains('key_hash', $columns);
        self::assertNotContains('raw_key', $columns);
        self::assertNotContains('key_prefix', $columns);

        $indexes = $this->pdo->query('SHOW INDEX FROM invoicing_artifacts')->fetchAll(\PDO::FETCH_ASSOC);
        self::assertContains('uq_invoicing_artifacts_source', array_column($indexes, 'Key_name'));
    }

    public function testActiveCompanyBoundReadKeyIsAcceptedAndRawSecretIsNotStored(): void
    {
        $key = 'erp2_inv_' . str_repeat('a', 64);
        $this->seedKey($key, 1, 'invoicing.read');

        $response = $this->request('GET', '/api/integrations/v1/invoicing/sources', $key);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(hash('sha256', $key), $this->pdo->query(
            'SELECT key_hash FROM invoicing_service_keys',
        )->fetchColumn());
        self::assertStringNotContainsString($key, (string) $response->getBody());
    }

    public function testInvalidAndRevokedKeysAreRejected(): void
    {
        $revoked = 'erp2_inv_' . str_repeat('b', 64);
        $this->seedKey($revoked, 1, 'invoicing.read', 'revoked');

        $invalidResponse = $this->request('GET', '/api/integrations/v1/invoicing/sources', 'invalid');
        $revokedResponse = $this->request('GET', '/api/integrations/v1/invoicing/sources', $revoked);

        self::assertSame(401, $invalidResponse->getStatusCode());
        self::assertSame(401, $revokedResponse->getStatusCode());
        self::assertSame(['error' => 'invalid_api_key'], $this->json($revokedResponse));
    }

    public function testSourceExportIsCompanyBoundAndFailsClosedForPacks(): void
    {
        $key = 'erp2_inv_' . str_repeat('c', 64);
        $this->seedKey($key, 1, 'invoicing.read');

        $response = $this->request(
            'GET',
            '/api/integrations/v1/invoicing/sources',
            $key,
            ['company_id' => '2', 'date_from' => '2026-10-10', 'date_to' => '2026-10-10'],
        );
        $body = $this->json($response);
        $sources = $body['data'] ?? [];

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(4, $sources);
        $byOrder = array_column($sources, null, 'order_id');
        self::assertArrayNotHasKey('200000000201', $byOrder);
        self::assertSame('ORDER_READY', $byOrder['200000000101']['readiness']);
        self::assertTrue($byOrder['200000000101']['invoice_eligible']);
        self::assertSame('998877', $byOrder['200000000101']['buyer_id']);
        self::assertSame('UNKNOWN', $byOrder['200000000101']['billing_data_status']);
        self::assertSame('NO_LOCAL_FISCAL_DATA_SOURCE', $byOrder['200000000101']['billing_data_reason']);
        self::assertFalse($byOrder['200000000101']['buyer_id_is_fiscal_id']);
        self::assertSame('123456.0000', $byOrder['200000000101']['total_amount']);
        self::assertSame('2.0000', $byOrder['200000000101']['items'][0]['quantity']);
        self::assertSame('61728.0000', $byOrder['200000000101']['items'][0]['unit_price']);
        self::assertSame('PACK_MEMBERSHIP_UNVERIFIED', $byOrder['200000000102']['readiness']);
        self::assertFalse($byOrder['200000000102']['invoice_eligible']);
        self::assertTrue($byOrder['200000000102']['cancelled']);
        self::assertSame('CANCELLED', $byOrder['200000000103']['readiness']);
        self::assertSame('cancelled', $byOrder['200000000103']['status']);
        self::assertSame('SOURCE_DATA_MISSING', $byOrder['200000000104']['readiness']);
        self::assertSame(50, $body['pagination']['limit']);
    }

    public function testStatusReadinessDateFiltersAndBoundedPaginationWork(): void
    {
        $key = 'erp2_inv_' . str_repeat('3', 64);
        $this->seedKey($key, 1, 'invoicing.read');

        $first = $this->request('GET', '/api/integrations/v1/invoicing/sources', $key, [
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-10',
            'status' => 'paid',
            'limit' => '1',
            'offset' => '0',
        ]);
        $firstBody = $this->json($first);
        $secondBody = $this->json($this->request('GET', '/api/integrations/v1/invoicing/sources', $key, [
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-10',
            'status' => 'paid',
            'readiness' => 'ORDER_READY',
            'limit' => '1',
            'offset' => '0',
        ]));
        $invalidDate = $this->request('GET', '/api/integrations/v1/invoicing/sources', $key, [
            'date_from' => '2026-02-30',
        ]);

        self::assertSame(200, $first->getStatusCode());
        self::assertSame('200000000101', $firstBody['data'][0]['order_id']);
        self::assertTrue($firstBody['pagination']['has_more']);
        self::assertFalse($secondBody['pagination']['has_more']);
        self::assertSame('ORDER_READY', $secondBody['data'][0]['readiness']);
        self::assertSame(422, $invalidDate->getStatusCode());
        self::assertSame(['error' => 'invalid_filter'], $this->json($invalidDate));
    }

    public function testAccountFilterCannotSelectAnotherCompany(): void
    {
        $key = 'erp2_inv_' . str_repeat('d', 64);
        $this->seedKey($key, 1, 'invoicing.read');

        $response = $this->request(
            'GET',
            '/api/integrations/v1/invoicing/sources',
            $key,
            ['account_id' => '20'],
        );

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'account_not_found'], $this->json($response));
    }

    public function testReadScopeCannotUploadArtifactAndPackArtifactIsRejected(): void
    {
        $readKey = 'erp2_inv_' . str_repeat('e', 64);
        $this->seedKey($readKey, 1, 'invoicing.read');
        $readResponse = $this->artifactRequest('200000000101', $readKey, $this->artifactFields());

        $writeKey = 'erp2_inv_' . str_repeat('f', 64);
        $this->seedKey($writeKey, 1, 'invoicing.write');
        $packResponse = $this->artifactRequest('200000000102', $writeKey, $this->artifactFields());
        $cancelledResponse = $this->artifactRequest('200000000103', $writeKey, $this->artifactFields());

        self::assertSame(403, $readResponse->getStatusCode());
        self::assertSame(['error' => 'insufficient_scope'], $this->json($readResponse));
        self::assertSame(409, $packResponse->getStatusCode());
        self::assertSame(['error' => 'pack_membership_unverified'], $this->json($packResponse));
        self::assertSame(409, $cancelledResponse->getStatusCode());
        self::assertSame(['error' => 'source_not_invoiceable'], $this->json($cancelledResponse));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM invoicing_artifacts')->fetchColumn());
    }

    public function testOrderArtifactIsStoredWithServerHashesAndIdempotentConflictRules(): void
    {
        $key = 'erp2_inv_' . str_repeat('1', 64);
        $this->seedKey($key, 1, 'invoicing.write');
        $fields = $this->artifactFields();
        $pdf = "%PDF-1.7\ninvoice fixture\n";
        $xml = '<?xml version="1.0"?><Invoice id="INV-101"/>';

        $first = $this->artifactRequest('200000000101', $key, $fields, $pdf, $xml);
        $repeat = $this->artifactRequest('200000000101', $key, $fields, $pdf, $xml);
        $conflict = $this->artifactRequest('200000000101', $key, $fields, "%PDF-1.7\ndifferent\n", $xml);

        self::assertSame(201, $first->getStatusCode());
        self::assertSame(200, $repeat->getStatusCode());
        self::assertSame(409, $conflict->getStatusCode());
        self::assertSame(['error' => 'artifact_conflict'], $this->json($conflict));
        $row = $this->pdo->query(
            'SELECT pdf_storage_ref, pdf_sha256, xml_sha256, channel FROM invoicing_artifacts',
        )->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertMatchesRegularExpression('/^1\/10\/101\/[a-f0-9]{64}\.pdf$/D', $row['pdf_storage_ref']);
        self::assertSame(hash('sha256', $pdf), $row['pdf_sha256']);
        self::assertSame(hash('sha256', $xml), $row['xml_sha256']);
        self::assertSame('WINDOWS_BRIDGE', $row['channel']);
        self::assertSame(1, (int) $this->pdo->query(
            'SELECT company_id FROM invoicing_artifacts',
        )->fetchColumn());
        self::assertStringNotContainsString('invoice.pdf', (string) $first->getBody());

        $readKey = 'erp2_inv_' . str_repeat('6', 64);
        $this->seedKey($readKey, 1, 'invoicing.read');
        $export = $this->json($this->request('GET', '/api/integrations/v1/invoicing/sources', $readKey));
        $acceptedSource = array_values(array_filter(
            $export['data'],
            static fn (array $source): bool => $source['order_id'] === '200000000101',
        ))[0];
        self::assertSame('ACCEPTED', $acceptedSource['artifact_status']);
    }

    public function testArtifactMustMatchExactLocalCurrencyAndTotal(): void
    {
        $key = 'erp2_inv_' . str_repeat('4', 64);
        $this->seedKey($key, 1, 'invoicing.write');
        $fields = $this->artifactFields();
        $fields['exact_total'] = '123456.0001';

        $response = $this->artifactRequest('200000000101', $key, $fields);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(['error' => 'source_total_mismatch'], $this->json($response));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM invoicing_artifacts')->fetchColumn());
    }

    public function testCrossCompanySourceCannotReceiveArtifact(): void
    {
        $key = 'erp2_inv_' . str_repeat('2', 64);
        $this->seedKey($key, 1, 'invoicing.write');

        $response = $this->artifactRequest('200000000201', $key, $this->artifactFields());

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(['error' => 'source_not_found'], $this->json($response));
    }

    public function testAmbiguousExternalOrderIdentityAcrossCompanyAccountsFailsClosed(): void
    {
        $this->pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,status) "
            . "VALUES (11,1,'700000111','MCO','connected')",
        );
        $this->pdo->exec(
            "INSERT INTO orders(id,company_id,account_id,external_order_id,status,date_created,total_amount,currency_id) "
            . "VALUES (111,1,11,'200000000101','paid','2026-10-10 17:00:00.000000','123456.0000','COP')",
        );
        $this->pdo->exec(
            "INSERT INTO order_items(order_id,external_item_id,title,quantity,unit_price,currency_id) "
            . "VALUES (111,'SKU-DUP','Duplicado','2.0000','61728.0000','COP')",
        );
        $key = 'erp2_inv_' . str_repeat('5', 64);
        $this->seedKey($key, 1, 'invoicing.write');

        $response = $this->artifactRequest('200000000101', $key, $this->artifactFields());

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(['error' => 'source_ambiguous'], $this->json($response));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM invoicing_artifacts')->fetchColumn());
    }

    public function testCredentialIssuerShowsRawKeyOnceAndRevocationDisablesIt(): void
    {
        $bridge = new InvoicingBridge($this->pdo, dirname(__DIR__, 2));
        $issued = $bridge->issueServiceKey(1, ['invoicing.read']);
        $key = $issued['key'];
        $stored = $this->pdo->prepare('SELECT key_hash FROM invoicing_service_keys WHERE id = :id');
        $stored->execute(['id' => $issued['id']]);

        self::assertMatchesRegularExpression('/^erp2_inv_[a-f0-9]{64}$/D', $key);
        self::assertSame(hash('sha256', $key), $stored->fetchColumn());
        self::assertTrue($bridge->revokeServiceKey($issued['id']));
        self::assertFalse($bridge->revokeServiceKey($issued['id']));
        self::assertSame(401, $this->request(
            'GET',
            '/api/integrations/v1/invoicing/sources',
            $key,
        )->getStatusCode());
    }

    /** @param array<string,string> $query */
    private function request(
        string $method,
        string $uri,
        ?string $key = null,
        array $query = [],
    ): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri);
        if ($query !== []) {
            $request = $request->withQueryParams($query);
        }
        if ($key !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $key);
        }

        return Bootstrap::create()->handle($request);
    }

    /** @param array<string,string> $fields */
    private function artifactRequest(
        string $orderId,
        string $key,
        array $fields,
        string $pdf = "%PDF-1.7\nfixture\n",
        ?string $xml = null,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest(
            'POST',
            '/api/integrations/v1/invoicing/sources/' . $orderId . '/artifact',
        )
            ->withHeader('Authorization', 'Bearer ' . $key)
            ->withParsedBody($fields)
            ->withUploadedFiles([
                'pdf' => new UploadedFile(
                    (new StreamFactory())->createStream($pdf),
                    '../../invoice.pdf',
                    'application/pdf',
                    strlen($pdf),
                    UPLOAD_ERR_OK,
                ),
                ...($xml === null ? [] : [
                    'xml' => new UploadedFile(
                        (new StreamFactory())->createStream($xml),
                        'invoice.xml',
                        'application/xml',
                        strlen($xml),
                        UPLOAD_ERR_OK,
                    ),
                ]),
            ]);

        return Bootstrap::create()->handle($request);
    }

    /** @return array<string,string> */
    private function artifactFields(): array
    {
        return [
            'invoice_number' => 'INV-101',
            'issued_at' => '2026-10-10T14:30:00-05:00',
            'currency' => 'COP',
            'exact_total' => '123456.0000',
            'cufe' => 'CUFE-101',
            'provider_document_id' => 'DOC-101',
            'company_id' => '2',
        ];
    }

    private function seedKey(
        string $key,
        int $companyId,
        string $scopes,
        string $status = 'active',
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO invoicing_service_keys (company_id, key_hash, scopes, status) '
            . 'VALUES (:company_id, :key_hash, :scopes, :status)',
        );
        $statement->execute([
            'company_id' => $companyId,
            'key_hash' => hash('sha256', $key),
            'scopes' => $scopes,
            'status' => $status,
        ]);
    }

    /** @return array<string,mixed> */
    private function json(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
