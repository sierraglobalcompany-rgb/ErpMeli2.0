<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Database\Migrator;
use App\Core\Security\Csrf;
use App\Core\Tenancy\CompanyContext;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\TestDatabase;

final class CompanyManagementTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $config = TestDatabase::config();
        self::assertContains($config->dbHost, ['127.0.0.1', 'localhost']);
        self::assertStringEndsWith('_test', $config->dbName);
        $this->pdo = TestDatabase::reset();
        (new AuthService($this->pdo))->bootstrapAdmin('Main', 'main', 'owner@example.test', 'test-password-long');
        self::assertTrue((new AuthService($this->pdo))->login('owner@example.test', 'test-password-long'));
    }

    /** @param array<string,mixed> $body */
    private function request(string $method, string $path, array $body = []): ResponseInterface
    {
        if ($method === 'POST' && str_ends_with($path, '/delete')) {
            $body += ['confirm_permanent_delete'=>'1'];
        }
        $body += ['csrf_token'=>(new Csrf())->token()];
        return Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest($method, $path)->withParsedBody($body));
    }

    public function testBootstrapOwnsInstallationAuthority(): void
    {
        self::assertContains('is_system_admin', $this->pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN));
        self::assertSame(1, (int)$this->pdo->query('SELECT is_system_admin FROM users')->fetchColumn());
        self::assertSame('admin', $this->pdo->query('SELECT role FROM company_users')->fetchColumn());
    }

    public function testAnonymousCompanyManagementIsDenied(): void
    {
        $_SESSION = [];
        self::assertSame(403, $this->request('GET', '/system/companies')->getStatusCode());
    }

    public function testGlobalSettingsRejectsOrdinaryCompanyAdmin(): void
    {
        $columns = $this->pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('is_system_admin', $columns, true)) { $this->pdo->exec('UPDATE users SET is_system_admin=0'); }
        $_SESSION['is_system_admin'] = true; // A forged UI hint must not grant authority.
        foreach (['GET','POST'] as $method) {
            self::assertSame(403, $this->request($method, '/settings/system', ['automation_enabled'=>'1'])->getStatusCode());
        }
        foreach (['clear','export'] as $action) {
            self::assertSame(403, $this->request('POST', '/settings/system/debug/'.$action)->getStatusCode());
        }
        self::assertSame(0, (int)$this->pdo->query('SELECT automation_enabled FROM system_settings')->fetchColumn());
    }

    public function testSystemAuthorityIsDbBackedAndAllWritesRequireCsrf(): void
    {
        self::assertSame(200, $this->request('GET','/system/companies')->getStatusCode());
        foreach (['/system/companies','/system/companies/1/update','/system/companies/1/archive','/system/companies/1/reactivate','/system/companies/1/delete'] as $path) {
            self::assertSame(419,$this->request('POST',$path,['csrf_token'=>'bad'])->getStatusCode());
        }
        $this->pdo->exec('UPDATE users SET is_system_admin=0');
        $_SESSION['is_system_admin']=true;
        self::assertSame(403,$this->request('GET','/system/companies')->getStatusCode());
        self::assertSame(403,$this->request('POST','/system/companies',['name'=>'Denied','slug'=>'denied'])->getStatusCode());
        $this->pdo->exec("UPDATE users SET is_system_admin=1,status='disabled'");
        self::assertSame(403,$this->request('GET','/system/companies')->getStatusCode());
    }

    public function testCreateEditAndDuplicateAreAtomicAndDoNotSelectNewCompany(): void
    {
        $invalid=$this->request('POST','/system/companies',['name'=>'','slug'=>'bad slug']);
        self::assertSame(422,$invalid->getStatusCode());
        self::assertStringContainsString('minúsculas',(string)$invalid->getBody());
        self::assertSame(303,$this->request('POST','/system/companies',['name'=>'Second <Company>','slug'=>'second'])->getStatusCode());
        self::assertStringContainsString('Second &lt;Company&gt;',(string)$this->request('GET','/system/companies')->getBody());
        self::assertSame(1,$_SESSION['company_id']);
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());
        self::assertSame('admin',$this->pdo->query('SELECT role FROM company_users WHERE company_id=2 AND user_id=1')->fetchColumn());
        self::assertSame(409,$this->request('POST','/system/companies',['name'=>'Duplicate','slug'=>'second'])->getStatusCode());
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM company_users')->fetchColumn());
        self::assertSame(303,$this->request('POST','/system/companies/2/update',['name'=>'Renamed','slug'=>'renamed'])->getStatusCode());
        self::assertSame('Renamed',$this->pdo->query('SELECT name FROM companies WHERE id=2')->fetchColumn());
        self::assertSame(409,$this->request('POST','/system/companies/2/update',['name'=>'Duplicate','slug'=>'main'])->getStatusCode());
        self::assertSame('renamed',$this->pdo->query('SELECT slug FROM companies WHERE id=2')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }

    public function testArchivedCompanyLosesTenantAuthorityAndReactivationRestoresVisibility(): void
    {
        self::assertSame(303,$this->request('POST','/system/companies/1/archive')->getStatusCode());
        self::assertArrayNotHasKey('company_id',$_SESSION);
        self::assertArrayNotHasKey('role',$_SESSION);
        $context=new CompanyContext($this->pdo);
        self::assertSame([],$context->memberships());
        self::assertSame('/system/companies',$this->request('GET','/')->getHeaderLine('Location'));
        self::assertSame('/system/companies',$this->request('GET','/company/select')->getHeaderLine('Location'));
        self::assertSame(200,$this->request('GET','/settings/system')->getStatusCode());
        self::assertSame(403,$this->request('POST','/company/select',['company_id'=>1])->getStatusCode());
        // Another session retaining a stale selection fails in the existing root owner.
        $_SESSION['company_id']=1; $_SESSION['role']='admin';
        self::assertSame(403,$this->request('GET','/sales')->getStatusCode());
        self::assertArrayNotHasKey('company_id',$_SESSION);
        $_SESSION['company_id']=1; $_SESSION['role']='admin';
        self::assertSame('/system/companies',$this->request('GET','/')->getHeaderLine('Location'));
        self::assertSame(303,$this->request('POST','/system/companies/1/update',['name'=>'Archived edited','slug'=>'archived-edited'])->getStatusCode());
        self::assertSame(303,$this->request('POST','/system/companies/1/reactivate')->getStatusCode());
        self::assertArrayNotHasKey('company_id',$_SESSION);
        self::assertCount(1,$context->memberships());
        self::assertSame('/company/select',$this->request('GET','/')->getHeaderLine('Location'));
        $context->select(1); self::assertSame(1,$context->companyId());
    }

    public function testFailedMembershipInsertRollsBackCompanyCreation(): void
    {
        $this->pdo->exec("CREATE TRIGGER l2_membership_reject BEFORE INSERT ON company_users FOR EACH ROW SIGNAL SQLSTATE '23000' SET MESSAGE_TEXT='Synthetic membership rejection'");
        try {
            self::assertSame(409,$this->request('POST','/system/companies',['name'=>'Rejected','slug'=>'rejected'])->getStatusCode());
            self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());
            self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM company_users')->fetchColumn());
        } finally { $this->pdo->exec('DROP TRIGGER l2_membership_reject'); }
    }

    public function testZeroActiveMembershipLoginDependsOnInstallationAuthority(): void
    {
        $this->pdo->exec('UPDATE companies SET archived_at=UTC_TIMESTAMP(3)');
        $auth=new AuthService($this->pdo);
        self::assertTrue($auth->login('owner@example.test','test-password-long'));
        self::assertArrayNotHasKey('company_id',$_SESSION);
        self::assertSame(200,$this->request('GET','/system/companies')->getStatusCode());
        $this->pdo->exec('UPDATE users SET is_system_admin=0');
        self::assertFalse($auth->login('owner@example.test','test-password-long'));
        self::assertArrayNotHasKey('user_id',$_SESSION);
        $_SESSION=['user_id'=>1];
        self::assertSame(403,$this->request('GET','/')->getStatusCode());
        self::assertSame(403,$this->request('GET','/company/select')->getStatusCode());
        $auth->logout(); self::assertArrayNotHasKey('is_system_admin',$_SESSION);
    }

    #[DataProvider('archiveOwners')]
    public function testArchivePreservesOwnersAndBlocksOnlyLiveOnes(string $sql, bool $blocked): void
    {
        $this->pdo->exec($sql);
        $before=[];
        foreach (['work_items','meli_accounts','invoicing_service_keys','products'] as $table) {
            $before[$table]=$this->pdo->query('SELECT * FROM '.$table)->fetchAll();
        }
        self::assertSame($blocked?409:303,$this->request('POST','/system/companies/1/archive')->getStatusCode());
        self::assertSame($blocked,$this->pdo->query('SELECT archived_at FROM companies WHERE id=1')->fetchColumn()===null);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM company_users')->fetchColumn());
        // The action must not mutate any operational owner.
        foreach (['work_items','meli_accounts','invoicing_service_keys','products'] as $table) {
            self::assertSame($before[$table],$this->pdo->query('SELECT * FROM '.$table)->fetchAll());
        }
    }

    public static function archiveOwners(): iterable
    {
        foreach (['pending','running','done','failed'] as $status) {
            yield 'work '.$status=>["INSERT INTO work_items(company_id,scope_key,type,dedupe_key,status,available_at) VALUES(1,'company:1','test','test','$status',UTC_TIMESTAMP(6))",in_array($status,['pending','running'],true)];
        }
        foreach (['connected','attention','reauth_required','disabled'] as $status) {
            yield 'Meli '.$status=>["INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES(1,'100','MCO','$status')",$status!=='disabled'];
        }
        foreach (['active','revoked'] as $status) {
            yield 'bridge '.$status=>["INSERT INTO invoicing_service_keys(company_id,key_hash,scopes,status) VALUES(1,REPEAT('a',64),'sources:read','$status')",$status==='active'];
        }
        yield 'product history'=>["INSERT INTO products(company_id,internal_sku,name) VALUES(1,'old','Historical')",false];
    }

    public function testDeleteRequiresArchivedPristineSoleCreatorMembershipAndKeepsUser(): void
    {
        self::assertSame(409,$this->request('POST','/system/companies/1/delete')->getStatusCode());
        $this->request('POST','/system/companies/1/archive');
        self::assertStringContainsString('/system/companies/1/delete',(string)$this->request('GET','/system/companies')->getBody());
        self::assertSame(303,$this->request('POST','/system/companies/1/delete')->getStatusCode());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM company_users')->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertSame(200,$this->request('GET','/system/companies')->getStatusCode());
    }

    #[DataProvider('deleteResidues')]
    public function testDeletePreservesAnyPriorUse(string $sql): void
    {
        $this->pdo->exec('UPDATE companies SET archived_at=UTC_TIMESTAMP(3)');
        $this->pdo->exec($sql);
        $memberships=$this->pdo->query('SELECT * FROM company_users')->fetchAll();
        self::assertSame(409,$this->request('POST','/system/companies/1/delete')->getStatusCode());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());
        self::assertSame($memberships,$this->pdo->query('SELECT * FROM company_users')->fetchAll());
    }

    public static function deleteResidues(): iterable
    {
        yield 'disabled Meli'=>["INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES(1,'100','MCO','disabled')"];
        yield 'product'=>["INSERT INTO products(company_id,internal_sku,name) VALUES(1,'old','Historical')"];
        yield 'done work'=>["INSERT INTO work_items(company_id,scope_key,type,dedupe_key,status,available_at) VALUES(1,'company:1','test','test','done',UTC_TIMESTAMP(6))"];
        yield 'revoked key'=>["INSERT INTO invoicing_service_keys(company_id,key_hash,scopes,status) VALUES(1,REPEAT('a',64),'sources:read','revoked')"];
        yield 'company usage'=>["INSERT INTO api_usage_daily(usage_date,scope_key,operation_key) VALUES('2026-10-13','company:1','users.me')"];
        yield 'company account usage without root'=>["INSERT INTO api_usage_daily(usage_date,scope_key,operation_key) VALUES('2026-10-13','company:1:account:99','orders.get')"];
        yield 'extra membership'=>["INSERT INTO users(id,email,username,display_name,password_hash) VALUES(2,'other@example.test','other','Other','unused'); INSERT INTO company_users(company_id,user_id,role) VALUES(1,2,'member')"];
        yield 'sole role not admin'=>["UPDATE company_users SET role='member'"];
        yield 'sole foreign membership'=>["INSERT INTO users(id,email,username,display_name,password_hash) VALUES(2,'other@example.test','other','Other','unused'); DELETE FROM company_users; INSERT INTO company_users(company_id,user_id,role) VALUES(1,2,'admin')"];
        yield 'foreign FK'=>["CREATE TABLE l2_fk_probe(company_id BIGINT UNSIGNED, FOREIGN KEY(company_id) REFERENCES companies(id)); INSERT INTO l2_fk_probe VALUES(1)"];
    }

    public function testApplicationCooldownIsNotCompanyResidueAndIdsMatchExactly(): void
    {
        $this->pdo->exec("INSERT INTO api_usage_daily(usage_date,scope_key,operation_key) VALUES('2026-10-13','company:10','users.me'),('2026-10-13','company:10:account:99','orders.get')");
        $this->pdo->exec("INSERT INTO meli_cooldowns(cooldown_key,blocked_until) VALUES('app:oauth',UTC_TIMESTAMP(6))");
        $this->request('POST','/system/companies/1/archive');
        self::assertSame(303,$this->request('POST','/system/companies/1/delete')->getStatusCode());
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM meli_cooldowns')->fetchColumn());
    }

    public function testArchivedDisabledAccountWebhookCannotCreateLiveWork(): void
    {
        $this->pdo->exec("INSERT INTO meli_accounts(company_id,external_user_id,site_id,status) VALUES(1,'100','MCO','disabled')");
        self::assertSame(303,$this->request('POST','/system/companies/1/archive')->getStatusCode());
        $receiver=new \App\Modules\Sales\ReceiveOrderWebhook\OrderWebhookReceiver($this->pdo,new \App\Work\WorkRepository($this->pdo),'local-client');
        self::assertFalse($receiver->receive(['_id'=>'l2-archived','topic'=>'orders_v2','resource'=>'/orders/200000000001','user_id'=>'100','application_id'=>'local-client']));
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM work_items')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM webhook_events')->fetchColumn());
    }

    #[DataProvider('legacyShapes')]
    public function testMigrationPromotesOnlyUniqueLegacyCompanyAdmin(int $companies, int $admins, int $promoted): void
    {
        // Recreate the pre-010 schema in the disposable test DB; apply the actual migration once.
        $this->pdo->exec('ALTER TABLE users DROP COLUMN is_system_admin');
        $this->pdo->exec('ALTER TABLE companies DROP COLUMN archived_at');
        $this->pdo->exec("DELETE FROM schema_migrations WHERE version='010_company_management.sql'");
        if ($companies===2) { $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Second','second')"); }
        if ($admins===2) {
            $this->pdo->exec("INSERT INTO users(id,email,username,display_name,password_hash) VALUES(2,'other@example.test','other','Other','unused')");
            $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,2,'admin')");
        }
        if ($admins===0) { $this->pdo->exec("UPDATE company_users SET role='member'"); }
        if ($admins===3) {
            $this->pdo->exec("UPDATE company_users SET role='member'");
            $this->pdo->exec("INSERT INTO users(id,email,username,display_name,password_hash) VALUES(2,'unique@example.test','unique','Unique','unused')");
            $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,2,'admin')");
        }
        (new Migrator())->migrate($this->pdo,dirname(__DIR__,2).'/database/migrations');
        self::assertSame($promoted,(int)$this->pdo->query('SELECT SUM(is_system_admin) FROM users')->fetchColumn());
        if ($admins===3) { self::assertSame(1,(int)$this->pdo->query('SELECT is_system_admin FROM users WHERE id=2')->fetchColumn()); }
    }

    public static function legacyShapes(): iterable
    {
        yield 'unique'=>[1,1,1];
        yield 'two companies'=>[2,1,0];
        yield 'two admins'=>[1,2,0];
        yield 'no admins'=>[1,0,0];
        yield 'unique owner not first user'=>[1,3,1];
    }
}
