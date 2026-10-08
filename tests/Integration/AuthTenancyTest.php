<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth\AuthService;
use App\Core\Auth\PasswordService;
use App\Core\Tenancy\CompanyContext;
use DomainException;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class AuthTenancyTest extends TestCase
{
    public function testValidPasswordLogsInAndInvalidPasswordDoesNot(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);

        $auth = new AuthService($pdo);

        self::assertFalse($auth->login('admin@example.test', 'wrong'));
        self::assertTrue($auth->login('admin@example.test', 'secret'));
        self::assertSame(1, $auth->userId());
        self::assertSame('admin', $auth->role());
    }

    public function testUserCannotSelectCompanyWithoutMembership(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedUser($pdo);
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (2,'Other','other')");

        $auth = new AuthService($pdo);
        self::assertTrue($auth->login('admin@example.test', 'secret'));

        $this->expectException(DomainException::class);
        (new CompanyContext($pdo))->select(2);
    }

    private function seedUser(\PDO $pdo): void
    {
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $stmt = $pdo->prepare(
            "INSERT INTO users(id,email,password_hash,status) VALUES (1,'admin@example.test',?,'active')"
        );
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");
    }
}
