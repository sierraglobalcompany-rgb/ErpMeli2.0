<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesAuditHttpStartRouteTest extends TestCase
{
    public function testCompanyAdminStartsTenantBoundAuditAndInitialWorkAtomically(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');

        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Audit Company','audit-company')");
        $stmt = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'audit-admin@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (10,1,'700000010','MCO','Main Seller','connected')"
        );

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;
        $_SESSION['csrf_token'] = str_repeat('a', 64);

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/sales/audits')
            ->withParsedBody([
                'csrf_token' => $_SESSION['csrf_token'],
                'account_id' => '10',
                'period_key' => '2026-10-01',
            ]);
        $response = Bootstrap::create()->handle($request);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/sales', $response->getHeaderLine('Location'));

        $run = $pdo->query(
            'SELECT id,company_id,account_id,period_key,contract_version,status FROM sales_audit_runs'
        )->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($run);
        self::assertSame('1', (string) $run['company_id']);
        self::assertSame('10', (string) $run['account_id']);
        self::assertSame('2026-10-01', $run['period_key']);
        self::assertSame('seller-search-v1', $run['contract_version']);
        self::assertSame('capturing', $run['status']);

        $work = $pdo->query(
            "SELECT company_id,account_id,scope_key,type,resource_key,payload_json,status FROM work_items WHERE type='sales.audit'"
        )->fetch(\PDO::FETCH_ASSOC);
        self::assertIsArray($work);
        self::assertSame('1', (string) $work['company_id']);
        self::assertSame('10', (string) $work['account_id']);
        self::assertSame('company:1:account:10', $work['scope_key']);
        self::assertSame('sales.audit', $work['type']);
        self::assertSame((string) $run['id'], (string) $work['resource_key']);
        self::assertSame('pending', $work['status']);
        self::assertSame(
            ['run_id' => (int) $run['id'], 'offset' => 0, 'limit' => 50],
            json_decode((string) $work['payload_json'], true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
