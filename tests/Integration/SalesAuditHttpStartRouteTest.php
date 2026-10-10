<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use App\Modules\Sales\Audit\SalesAuditWindow;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SalesAuditHttpStartRouteTest extends TestCase
{
    public function testCurrentMcoMonthCannotStartAudit(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCompanyUserAndAccount($pdo);
        $csrfToken = $this->authenticate();

        $periodKey = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-01');
        $response = $this->startAudit($csrfToken, '10', $periodKey);
        $runCount = (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_runs')->fetchColumn();
        $workCount = (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn();

        self::assertSame(
            [422, 0, 0],
            [$response->getStatusCode(), $runCount, $workCount],
            sprintf(
                'Expected HTTP 422 and no persisted state; got HTTP %d, runs=%d, Work=%d.',
                $response->getStatusCode(),
                $runCount,
                $workCount,
            ),
        );
    }

    public function testFutureMcoMonthCannotStartAudit(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCompanyUserAndAccount($pdo);
        $csrfToken = $this->authenticate();

        $periodKey = (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))
            ->modify('first day of this month')
            ->modify('+1 month')
            ->format('Y-m-01');
        $response = $this->startAudit($csrfToken, '10', $periodKey);
        $runCount = (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_runs')->fetchColumn();
        $workCount = (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn();

        self::assertSame(
            [422, 0, 0],
            [$response->getStatusCode(), $runCount, $workCount],
            sprintf(
                'Expected HTTP 422 and no persisted state; got HTTP %d, runs=%d, Work=%d.',
                $response->getStatusCode(),
                $runCount,
                $workCount,
            ),
        );
    }

    public function testCompanyAdminStartsTenantBoundAuditAndInitialWorkAtomically(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCompanyUserAndAccount($pdo);
        $csrfToken = $this->authenticate();

        $periodKey = SalesAuditWindow::lastClosedPeriodKey(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $formMonth = substr($periodKey, 0, 7);
        $response = $this->startAudit($csrfToken, '10', $formMonth);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/sales', $response->getHeaderLine('Location'));

        $run = $pdo->query(
            'SELECT id,company_id,account_id,period_key,contract_version,status FROM sales_audit_runs'
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($run);
        self::assertSame('1', (string) $run['company_id']);
        self::assertSame('10', (string) $run['account_id']);
        self::assertSame($periodKey, $run['period_key']);
        self::assertSame('seller-search-v1', $run['contract_version']);
        self::assertSame('capturing', $run['status']);

        $work = $pdo->query(
            "SELECT company_id,account_id,scope_key,type,resource_key,payload_json,status FROM work_items WHERE type='sales.audit'"
        )->fetch(PDO::FETCH_ASSOC);
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

    public function testDuplicateActiveStartReturnsConflictWithoutCreatingMoreState(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCompanyUserAndAccount($pdo);
        $csrfToken = $this->authenticate();

        $periodKey = SalesAuditWindow::lastClosedPeriodKey(new DateTimeImmutable('now', new DateTimeZone('UTC')));
        self::assertSame(303, $this->startAudit($csrfToken, '10', $periodKey)->getStatusCode());
        $response = $this->startAudit($csrfToken, '10', $periodKey);

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('auditoría activa', (string) $response->getBody());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_runs')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn());
    }

    public function testCompanyMemberCannotStartAudit(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCompanyUserAndAccount($pdo, 'member');
        $csrfToken = $this->authenticate();

        $response = $this->startAudit($csrfToken, '10', '2026-10-01');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_runs')->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn());
    }

    public function testInvalidCsrfCannotStartAudit(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCompanyUserAndAccount($pdo);
        $this->authenticate();

        $response = $this->startAudit('wrong-token', '10', '2026-10-01');

        self::assertSame(419, $response->getStatusCode());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_runs')->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn());
    }

    public function testAccountOutsideSelectedCompanyCannotStartAudit(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCompanyUserAndAccount($pdo);
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (2,'Other Company','other-company')");
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (20,2,'700000020','MCO','Other Seller','connected')"
        );
        $csrfToken = $this->authenticate();

        $response = $this->startAudit($csrfToken, '20', '2026-10-01');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM sales_audit_runs')->fetchColumn());
        self::assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM work_items WHERE type='sales.audit'")->fetchColumn());
    }

    public function testSalesPageExposesAuditFormOnlyToCompanyAdmin(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCompanyUserAndAccount($pdo);
        $hash = (new PasswordService())->hash('secret');
        $stmt = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (2,'audit-member@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,2,'member')");

        $this->authenticate();
        $adminResponse = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales'),
        );
        $adminHtml = (string) $adminResponse->getBody();
        self::assertSame(200, $adminResponse->getStatusCode());
        self::assertStringContainsString('action="/sales/audits"', $adminHtml);
        self::assertStringContainsString('Main Seller', $adminHtml);
        self::assertStringContainsString('name="csrf_token"', $adminHtml);
        $lastClosedMonth = substr(
            SalesAuditWindow::lastClosedPeriodKey(new DateTimeImmutable('now', new DateTimeZone('UTC'))),
            0,
            7,
        );
        self::assertStringContainsString('type="month" name="period_key"', $adminHtml);
        self::assertStringContainsString('max="' . $lastClosedMonth . '"', $adminHtml);
        self::assertStringContainsString('value="' . $lastClosedMonth . '"', $adminHtml);

        $_SESSION['user_id'] = 2;
        $memberResponse = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/sales'),
        );
        self::assertSame(200, $memberResponse->getStatusCode());
        self::assertStringNotContainsString('action="/sales/audits"', (string) $memberResponse->getBody());
    }

    private function seedCompanyUserAndAccount(PDO $pdo, string $role = 'admin'): void
    {
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Audit Company','audit-company')");
        $stmt = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'audit-user@example.test',?,'active')");
        $stmt->execute([$hash]);
        $membership = $pdo->prepare('INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,?)');
        $membership->execute([$role]);
        $pdo->exec(
            "INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (10,1,'700000010','MCO','Main Seller','connected')"
        );
    }

    private function authenticate(int $userId = 1): string
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['company_id'] = 1;
        $_SESSION['csrf_token'] = str_repeat('a', 64);

        return $_SESSION['csrf_token'];
    }

    private function startAudit(string $csrfToken, string $accountId, string $periodKey): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/sales/audits')
            ->withParsedBody([
                'csrf_token' => $csrfToken,
                'account_id' => $accountId,
                'period_key' => $periodKey,
            ]);

        return Bootstrap::create()->handle($request);
    }
}
