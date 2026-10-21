<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Security\Csrf;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class UserManagementTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        (new AuthService($this->pdo))->bootstrapAdmin(
            'Main Company', 'main-company', 'owner@example.test', 'owner-password-123', 'Owner', 'owner'
        );
        self::assertTrue((new AuthService($this->pdo))->login('owner', 'owner-password-123'));
    }

    public function testOnlyDbProvenSystemAdminCanManageUsers(): void
    {
        self::assertSame(200, $this->request('GET', '/system/users')->getStatusCode());
        $this->pdo->exec('UPDATE users SET is_system_admin=0 WHERE id=1');
        $_SESSION['is_system_admin'] = true;
        self::assertSame(403, $this->request('GET', '/system/users')->getStatusCode());
        self::assertSame(403, $this->request('POST', '/system/users', [
            'username'=>'blocked','display_name'=>'Blocked','email'=>'blocked@example.test',
            'password'=>'blocked-password-123','csrf_token'=>(new Csrf())->token(),
        ])->getStatusCode());
    }

    public function testCreateUserAndDistinctRolesAcrossCompanies(): void
    {
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(2,'Second','second')");
        $csrf = (new Csrf())->token();
        $created = $this->request('POST', '/system/users', [
            'username'=>'worker','display_name'=>'Worker','email'=>'worker@example.test',
            'password'=>'worker-password-123','company_id'=>'1','role'=>'operator','csrf_token'=>$csrf,
        ]);
        self::assertSame(303, $created->getStatusCode());
        $userId = (int) $this->pdo->query("SELECT id FROM users WHERE username='worker'")->fetchColumn();
        self::assertGreaterThan(1, $userId);
        self::assertSame('operator', $this->membershipRole(1, $userId));

        self::assertSame(303, $this->request('POST', '/system/users/'.$userId.'/membership', [
            'company_id'=>'2','role'=>'viewer','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame('operator', $this->membershipRole(1, $userId));
        self::assertSame('viewer', $this->membershipRole(2, $userId));

        (new AuthService($this->pdo))->logout();
        self::assertTrue((new AuthService($this->pdo))->login('worker', 'worker-password-123'));
        self::assertArrayNotHasKey('company_id', $_SESSION);
        self::assertSame(303, $this->request('POST', '/company/select', [
            'company_id'=>2,'csrf_token'=>(new Csrf())->token(),
        ])->getStatusCode());
        self::assertSame('viewer', $_SESSION['role']);
        self::assertSame(403, $this->request('POST', '/catalog', [
            'internal_sku'=>'NOPE','name'=>'Nope','csrf_token'=>(new Csrf())->token(),
        ])->getStatusCode());
    }

    public function testDuplicateCreateRollsBackWithoutPartialMembership(): void
    {
        $csrf = (new Csrf())->token();
        $beforeUsers = (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $beforeMemberships = (int) $this->pdo->query('SELECT COUNT(*) FROM company_users')->fetchColumn();
        $response = $this->request('POST', '/system/users', [
            'username'=>'owner','display_name'=>'Duplicate','email'=>'other@example.test',
            'password'=>'duplicate-password-123','company_id'=>'1','role'=>'operator','csrf_token'=>$csrf,
        ]);
        self::assertSame(409, $response->getStatusCode());
        self::assertSame($beforeUsers, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        self::assertSame($beforeMemberships, (int) $this->pdo->query('SELECT COUNT(*) FROM company_users')->fetchColumn());
    }

    public function testSystemAdminRevocationRequiresExplicitHumanConfirmationWithoutChangingDbOrSession(): void
    {
        $csrf = (new Csrf())->token();
        self::assertSame(303, $this->request('POST', '/system/users', [
            'username'=>'second-admin','display_name'=>'Second Admin','email'=>'second-admin@example.test',
            'password'=>'second-admin-password-123','is_system_admin'=>'1','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM users WHERE status='active' AND is_system_admin=1")->fetchColumn());

        $adminBefore = (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id=1')->fetchColumn();
        $membershipsBefore = $this->pdo->query('SELECT company_id,role FROM company_users WHERE user_id=1 ORDER BY company_id')->fetchAll(\PDO::FETCH_ASSOC);
        $sessionKeys = ['is_system_admin','company_id','role','user_id'];
        $sessionBefore = array_intersect_key($_SESSION, array_flip($sessionKeys));

        $response = $this->request('POST', '/system/users/1/system-admin', [
            'enabled'=>'0','csrf_token'=>$csrf,
        ]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertSame($adminBefore, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id=1')->fetchColumn());
        self::assertSame($membershipsBefore, $this->pdo->query('SELECT company_id,role FROM company_users WHERE user_id=1 ORDER BY company_id')->fetchAll(\PDO::FETCH_ASSOC));
        self::assertSame($sessionBefore, array_intersect_key($_SESSION, array_flip($sessionKeys)));
    }

    public function testCannotRemoveLastActiveSystemAdmin(): void
    {
        $csrf = (new Csrf())->token();
        self::assertSame(409, $this->request('POST', '/system/users/1/system-admin', [
            'enabled'=>'0','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id=1')->fetchColumn());
        self::assertSame(409, $this->request('POST', '/system/users/1/status', [
            'status'=>'disabled','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame('active', $this->pdo->query('SELECT status FROM users WHERE id=1')->fetchColumn());
    }

    public function testSecondSystemAdminAllowsSafeDelegation(): void
    {
        $csrf = (new Csrf())->token();
        self::assertSame(303, $this->request('POST', '/system/users', [
            'username'=>'second-admin','display_name'=>'Second Admin','email'=>'second-admin@example.test',
            'password'=>'second-admin-password-123','is_system_admin'=>'1','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(303, $this->request('POST', '/system/users/1/system-admin', [
            'enabled'=>'0','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id=1')->fetchColumn());
        self::assertArrayNotHasKey('is_system_admin', $_SESSION);
    }

    public function testCannotDowngradeRemoveOrDisableLastActiveCompanyAdmin(): void
    {
        $csrf = (new Csrf())->token();
        foreach ([
            ['/system/users/1/membership', ['company_id'=>'1','role'=>'operator']],
            ['/system/users/1/remove-membership', ['company_id'=>'1']],
            ['/system/users/1/status', ['status'=>'disabled']],
        ] as [$path, $body]) {
            self::assertSame(409, $this->request('POST', $path, $body + ['csrf_token'=>$csrf])->getStatusCode());
        }
        self::assertSame('admin', $this->membershipRole(1, 1));
        self::assertSame('active', $this->pdo->query('SELECT status FROM users WHERE id=1')->fetchColumn());
    }

    public function testAnotherActiveAdminAllowsMembershipChange(): void
    {
        $hash = password_hash('other-admin-password-123', PASSWORD_DEFAULT);
        $insert = $this->pdo->prepare("INSERT INTO users(username,display_name,email,password_hash,status) VALUES('other-admin','Other Admin','other-admin@example.test',?,'active')");
        $insert->execute([$hash]);
        $otherId = (int) $this->pdo->lastInsertId();
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,$otherId,'admin')");
        $response = $this->request('POST', '/system/users/1/membership', [
            'company_id'=>'1','role'=>'viewer','csrf_token'=>(new Csrf())->token(),
        ]);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('viewer', $this->membershipRole(1, 1));
        self::assertSame('viewer', $_SESSION['role']);
    }

    public function testPasswordResetChangesLoginCredentialWithoutExposingHash(): void
    {
        $csrf = (new Csrf())->token();
        self::assertSame(303, $this->request('POST', '/system/users', [
            'username'=>'reset-me','display_name'=>'Reset Me','email'=>'reset@example.test',
            'password'=>'original-password-123','company_id'=>'1','role'=>'viewer','csrf_token'=>$csrf,
        ])->getStatusCode());
        $id = (int) $this->pdo->query("SELECT id FROM users WHERE username='reset-me'")->fetchColumn();
        self::assertSame(303, $this->request('POST', '/system/users/'.$id.'/password', [
            'password'=>'replacement-password-123','csrf_token'=>$csrf,
        ])->getStatusCode());
        $hash = (string) $this->pdo->query('SELECT password_hash FROM users WHERE id='.$id)->fetchColumn();
        self::assertNotSame('replacement-password-123', $hash);
        (new AuthService($this->pdo))->logout();
        self::assertFalse((new AuthService($this->pdo))->login('reset-me', 'original-password-123'));
        self::assertTrue((new AuthService($this->pdo))->login('reset-me', 'replacement-password-123'));
    }

    public function testDisabledUserCannotLoginAndThereIsNoHardDeleteRoute(): void
    {
        $this->pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(20,'disabled','Disabled','disabled@example.test','x','disabled')");
        self::assertFalse((new AuthService($this->pdo))->login('disabled', 'anything-at-all-123'));
        self::assertSame(404, $this->request('POST', '/system/users/20/delete', [
            'csrf_token'=>(new Csrf())->token(),
        ])->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE id=20')->fetchColumn());
    }

    private function membershipRole(int $companyId, int $userId): string
    {
        $statement = $this->pdo->prepare('SELECT role FROM company_users WHERE company_id=? AND user_id=?');
        $statement->execute([$companyId, $userId]);
        return (string) $statement->fetchColumn();
    }

    /** @param array<string,mixed> $body */
    private function request(string $method, string $path, array $body = []): ResponseInterface
    {
        return Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest($method, $path)->withParsedBody($body)
        );
    }
}
