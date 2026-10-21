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

    public function testRemoveMembershipRequiresHumanConfirmationAndPreservesDbAndSession(): void
    {
        $targetId = $this->createManagedUser('membership-revoke-target', false);
        $csrf = (new Csrf())->token();
        $actor = $this->pdo->query(
            'SELECT u.status,u.is_system_admin,cu.role FROM users u '
            . 'INNER JOIN company_users cu ON cu.user_id=u.id AND cu.company_id=1 WHERE u.id=1'
        )->fetch(\PDO::FETCH_ASSOC);
        self::assertSame('active', $actor['status']);
        self::assertSame(1, (int) $actor['is_system_admin']);
        self::assertSame('admin', $actor['role']);
        self::assertSame('viewer', $this->membershipRole(1, $targetId));
        self::assertSame(1, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM company_users cu INNER JOIN users u ON u.id=cu.user_id "
            . "WHERE cu.company_id=1 AND cu.role='admin' AND u.status='active'"
        )->fetchColumn());

        $targetBefore = $this->managedMemberships($targetId);
        $allBefore = $this->pdo->query(
            'SELECT company_id,user_id,role FROM company_users ORDER BY company_id,user_id'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $sessionKeys = ['user_id','company_id','role','is_system_admin'];
        $sessionBefore = array_intersect_key($_SESSION, array_flip($sessionKeys));

        $response = $this->request('POST', '/system/users/'.$targetId.'/remove-membership', [
            'company_id'=>'1','csrf_token'=>$csrf,
        ]);

        $targetAfter = $this->managedMemberships($targetId);
        $allAfter = $this->pdo->query(
            'SELECT company_id,user_id,role FROM company_users ORDER BY company_id,user_id'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $sessionAfter = array_intersect_key($_SESSION, array_flip($sessionKeys));
        self::assertSame(422, $response->getStatusCode(), json_encode([
            'observed_status'=>$response->getStatusCode(),
            'observed_location'=>$response->getHeaderLine('Location'),
            'target_before'=>$targetBefore,
            'target_after'=>$targetAfter,
            'all_memberships_unchanged'=>$allBefore === $allAfter,
            'session_unchanged'=>$sessionBefore === $sessionAfter,
        ], JSON_THROW_ON_ERROR));
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertSame($targetBefore, $targetAfter);
        self::assertSame($allBefore, $allAfter);
        self::assertSame($sessionBefore, $sessionAfter);
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

    public function testInvalidSystemAdminRevocationAcknowledgementsDoNotChangeTarget(): void
    {
        $targetId = $this->createManagedUser('protected-admin', true);
        $csrf = (new Csrf())->token();
        $sessionKeys = ['is_system_admin','company_id','role','user_id'];
        $sessionBefore = array_intersect_key($_SESSION, array_flip($sessionKeys));
        $membershipsBefore = $this->managedMemberships($targetId);

        foreach (['0', 'true', true, '', ' 1 ', []] as $confirmation) {
            $response = $this->request('POST', '/system/users/'.$targetId.'/system-admin', [
                'enabled'=>'0','confirm_remove_system_admin'=>$confirmation,'csrf_token'=>$csrf,
            ]);
            self::assertSame(422, $response->getStatusCode());
            self::assertSame(1, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id='.$targetId)->fetchColumn());
            self::assertSame($membershipsBefore, $this->managedMemberships($targetId));
            self::assertSame($sessionBefore, array_intersect_key($_SESSION, array_flip($sessionKeys)));
        }

        $this->pdo->exec("UPDATE users SET status='disabled' WHERE id=".$targetId);
        self::assertSame(422, $this->request('POST', '/system/users/'.$targetId.'/system-admin', [
            'enabled'=>'0','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id='.$targetId)->fetchColumn());
        self::assertSame($membershipsBefore, $this->managedMemberships($targetId));
    }

    public function testSystemAdminEndpointPreservesAuthorizationCsrfAndInputErrorContracts(): void
    {
        $targetId = $this->createManagedUser('contract-admin', true);
        $csrf = (new Csrf())->token();

        self::assertSame(419, $this->request('POST', '/system/users/'.$targetId.'/system-admin', [
            'enabled'=>'1','csrf_token'=>'invalid-token',
        ])->getStatusCode());
        self::assertSame(404, $this->request('POST', '/system/users/999999/system-admin', [
            'enabled'=>'1','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(422, $this->request('POST', '/system/users/'.$targetId.'/system-admin', [
            'enabled'=>'enabled','csrf_token'=>$csrf,
        ])->getStatusCode());

        $this->pdo->exec('UPDATE users SET is_system_admin=0 WHERE id=1');
        $_SESSION['is_system_admin'] = true;
        self::assertSame(403, $this->request('POST', '/system/users/'.$targetId.'/system-admin', [
            'enabled'=>'1','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id='.$targetId)->fetchColumn());
    }

    public function testSystemAdminGrantAndIdempotentRevocationKeepCompanyMembership(): void
    {
        $targetId = $this->createManagedUser('ordinary-member', false);
        $csrf = (new Csrf())->token();
        self::assertSame('viewer', $this->membershipRole(1, $targetId));

        self::assertSame(303, $this->request('POST', '/system/users/'.$targetId.'/system-admin', [
            'enabled'=>'1','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id='.$targetId)->fetchColumn());
        self::assertSame(303, $this->request('POST', '/system/users/'.$targetId.'/system-admin', [
            'enabled'=>'0','confirm_remove_system_admin'=>'1','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id='.$targetId)->fetchColumn());
        self::assertSame(303, $this->request('POST', '/system/users/'.$targetId.'/system-admin', [
            'enabled'=>'0','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame('viewer', $this->membershipRole(1, $targetId));
    }

    public function testSystemAdminUiUsesClosedRevocationDisclosureAndDirectGrant(): void
    {
        $globalId = $this->createManagedUser('global-target', true);
        $ordinaryId = $this->createManagedUser('ordinary-target', false);
        $response = $this->request('GET', '/system/users');
        self::assertSame(200, $response->getStatusCode());

        $document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML((string) $response->getBody(), LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        self::assertTrue($loaded);
        $xpath = new \DOMXPath($document);

        foreach ([[$globalId, 'global-target'], [$ordinaryId, 'ordinary-target']] as [$id, $username]) {
            $card = $this->userCard($xpath, $username);
            $sections = $xpath->query('.//details[@data-user-section]', $card);
            self::assertNotFalse($sections);
            self::assertCount(3, $sections);
            self::assertSame(0, $xpath->query('.//form[ancestor::form]', $card)->length);
            $securityNode = $xpath->query('.//details[@data-user-section="security"]', $card)->item(0);
            self::assertInstanceOf(\DOMElement::class, $securityNode);
            self::assertFalse($securityNode->hasAttribute('open'));
            $html = $document->saveHTML($securityNode);
            self::assertIsString($html);

            if ($username === 'global-target') {
                $passwordPosition = strpos($html, '/password');
                $disclosurePosition = strpos($html, 'data-user-destructive-action="remove-system-admin"');
                self::assertIsInt($passwordPosition);
                self::assertIsInt($disclosurePosition);
                self::assertLessThan($disclosurePosition, $passwordPosition);
                $disclosures = $xpath->query('.//details[@data-user-destructive-action="remove-system-admin"]', $securityNode);
                self::assertNotFalse($disclosures);
                self::assertCount(1, $disclosures);
                $disclosure = $disclosures->item(0);
                self::assertInstanceOf(\DOMElement::class, $disclosure);
                self::assertFalse($disclosure->hasAttribute('open'));
                $summary = $xpath->query('./summary', $disclosure)->item(0);
                self::assertNotNull($summary);
                self::assertSame('Quitar administración del sistema', trim($summary->textContent));
                $copy = $xpath->query('./p', $disclosure)->item(0);
                self::assertNotNull($copy);
                self::assertStringContainsString('Sus accesos por empresa no se eliminan.', $copy->textContent);
                self::assertStringContainsString('perderá acceso a la administración global.', $copy->textContent);
                $form = $xpath->query('./form[@action="/system/users/'.$id.'/system-admin"]', $disclosure)->item(0);
                self::assertInstanceOf(\DOMElement::class, $form);
                $csrfInput = $xpath->query('./input[@name="csrf_token"]', $form)->item(0);
                self::assertNotNull($csrfInput);
                self::assertNotSame('', $csrfInput->getAttribute('value'));
                $enabledInput = $xpath->query('./input[@name="enabled"]', $form)->item(0);
                self::assertNotNull($enabledInput);
                self::assertSame('0', $enabledInput->getAttribute('value'));
                $checkbox = $xpath->query('.//input[@name="confirm_remove_system_admin"]', $form)->item(0);
                self::assertInstanceOf(\DOMElement::class, $checkbox);
                self::assertTrue($checkbox->hasAttribute('required'));
                self::assertFalse($checkbox->hasAttribute('checked'));
                $button = $xpath->query('./button', $form)->item(0);
                self::assertNotNull($button);
                self::assertSame('Confirmar retiro de administración', trim($button->textContent));
                $sibling = $disclosure->nextSibling;
                while ($sibling !== null && ! ($sibling instanceof \DOMElement)) {
                    $sibling = $sibling->nextSibling;
                }
                self::assertNull($sibling);
            } else {
                self::assertSame(0, $xpath->query('.//details[@data-user-destructive-action="remove-system-admin"]', $securityNode)->length);
                $form = $xpath->query('./form[@action="/system/users/'.$id.'/system-admin"]', $securityNode)->item(0);
                self::assertInstanceOf(\DOMElement::class, $form);
                $enabledInput = $xpath->query('./input[@name="enabled"]', $form)->item(0);
                self::assertNotNull($enabledInput);
                self::assertSame('1', $enabledInput->getAttribute('value'));
                self::assertSame(0, $xpath->query('.//input[@name="confirm_remove_system_admin"]', $form)->length);
                $button = $xpath->query('./button', $form)->item(0);
                self::assertNotNull($button);
                self::assertSame('Hacer Administrador del sistema', trim($button->textContent));
            }
        }
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
        $membershipsBefore = $this->managedMemberships(1);
        $sessionKeys = ['company_id','role','user_id'];
        $sessionBefore = array_intersect_key($_SESSION, array_flip($sessionKeys));
        self::assertSame(303, $this->request('POST', '/system/users', [
            'username'=>'second-admin','display_name'=>'Second Admin','email'=>'second-admin@example.test',
            'password'=>'second-admin-password-123','is_system_admin'=>'1','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(303, $this->request('POST', '/system/users/1/system-admin', [
            'enabled'=>'0','confirm_remove_system_admin'=>'1','csrf_token'=>$csrf,
        ])->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT is_system_admin FROM users WHERE id=1')->fetchColumn());
        self::assertArrayNotHasKey('is_system_admin', $_SESSION);
        self::assertSame($sessionBefore, array_intersect_key($_SESSION, array_flip($sessionKeys)));
        self::assertSame($membershipsBefore, $this->managedMemberships(1));
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

    private function createManagedUser(string $username, bool $systemAdmin): int
    {
        $body = [
            'username'=>$username,'display_name'=>$username,'email'=>$username.'@example.test',
            'password'=>$username.'-password-123','company_id'=>'1','role'=>'viewer','csrf_token'=>(new Csrf())->token(),
        ];
        if ($systemAdmin) {
            $body['is_system_admin'] = '1';
        }
        self::assertSame(303, $this->request('POST', '/system/users', $body)->getStatusCode());
        $statement = $this->pdo->prepare('SELECT id FROM users WHERE username=?');
        $statement->execute([$username]);
        $userId = (int) $statement->fetchColumn();
        self::assertGreaterThan(1, $userId);
        return $userId;
    }

    /** @return list<array<string,mixed>> */
    private function managedMemberships(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT company_id,role FROM company_users WHERE user_id=? ORDER BY company_id');
        $statement->execute([$userId]);
        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function userCard(\DOMXPath $xpath, string $username): \DOMElement
    {
        $cards = $xpath->query('//article[@data-admin-card="user"]');
        if ($cards === false) {
            self::fail('Could not query user cards.');
        }
        foreach ($cards as $card) {
            if (! $card instanceof \DOMElement) {
                continue;
            }
            $heading = $card->getElementsByTagName('h2')->item(0);
            if ($heading?->textContent === $username) {
                return $card;
            }
        }
        self::fail('User card not found: '.$username);
    }

    /** @param array<string,mixed> $body */
    private function request(string $method, string $path, array $body = []): ResponseInterface
    {
        return Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest($method, $path)->withParsedBody($body)
        );
    }
}
