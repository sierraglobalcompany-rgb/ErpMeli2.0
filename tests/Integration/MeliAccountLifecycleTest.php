<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliAccountLifecycleTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = TestDatabase::reset();
        $this->pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'admin','Admin','admin@example.test','unused','active')");
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(10,'Empresa A','empresa-a'),(20,'Empresa B','empresa-b')");
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(10,1,'admin')");
        $this->pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES
            (101,10,'1001','MCO','TIENDA UNO','connected'),
            (201,20,'2001','MCO','OTRA EMPRESA','connected')");
        $this->pdo->exec("INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at) VALUES
            (101,'ACCESS','REFRESH',DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR)),
            (201,'OTHER_ACCESS','OTHER_REFRESH',DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR))");
        $_SESSION = [
            'user_id' => 1,
            'company_id' => 10,
            'role' => 'admin',
            'display_name' => 'Admin',
            'csrf_token' => str_repeat('c', 64),
        ];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testDisconnectDeletesTokenDisablesAccountAndPreservesTerminalWork(): void
    {
        $this->insertWork(101, 'done');

        $response = $this->post('/mercadolibre/accounts/101/disconnect');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/mercadolibre/accounts', $response->getHeaderLine('Location'));
        self::assertSame('disabled', $this->accountStatus(101));
        self::assertSame(0, $this->tokenCount(101));
        self::assertSame(1, $this->workCount(101));
        self::assertSame('done', $this->workStatus(101));
    }

    public function testDisconnectRejectsPendingAndRunningWorkWithoutMutation(): void
    {
        foreach (['pending', 'running'] as $status) {
            $this->pdo->exec('DELETE FROM work_items WHERE account_id=101');
            $this->insertWork(101, $status);

            $response = $this->post('/mercadolibre/accounts/101/disconnect');

            self::assertSame(409, $response->getStatusCode(), $status);
            self::assertSame('connected', $this->accountStatus(101), $status);
            self::assertSame(1, $this->tokenCount(101), $status);
        }
    }

    public function testDisconnectIsCompanyScopedAndCsrfProtected(): void
    {
        self::assertSame(404, $this->post('/mercadolibre/accounts/201/disconnect')->getStatusCode());
        self::assertSame('connected', $this->accountStatus(201));
        self::assertSame(1, $this->tokenCount(201));

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/mercadolibre/accounts/101/disconnect')
            ->withParsedBody(['csrf_token' => 'wrong']);
        $response = Bootstrap::create()->handle($request);
        self::assertSame(419, $response->getStatusCode());
        self::assertSame('connected', $this->accountStatus(101));
        self::assertSame(1, $this->tokenCount(101));
    }

    private function post(string $path): ResponseInterface
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', $path)
            ->withParsedBody(['csrf_token' => str_repeat('c', 64)]);
        return Bootstrap::create()->handle($request);
    }

    private function insertWork(int $accountId, string $status): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO work_items(company_id,account_id,scope_key,type,resource_key,dedupe_key,status,available_at,claim_token,claimed_at,finished_at) "
            . "VALUES(10,?,?,'order.sync','9001',?, ?,UTC_TIMESTAMP(6),?,?,?)"
        );
        $running = $status === 'running';
        $terminal = in_array($status, ['done','failed'], true);
        $statement->execute([
            $accountId,
            'company:10:account:' . $accountId,
            hash('sha256', 'l7:' . $accountId . ':' . $status),
            $status,
            $running ? str_repeat('a', 32) : null,
            $running ? gmdate('Y-m-d H:i:s') : null,
            $terminal ? gmdate('Y-m-d H:i:s') : null,
        ]);
    }

    private function accountStatus(int $id): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM meli_accounts WHERE id=?');
        $statement->execute([$id]);
        return (string) $statement->fetchColumn();
    }

    private function tokenCount(int $id): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM meli_tokens WHERE account_id=?');
        $statement->execute([$id]);
        return (int) $statement->fetchColumn();
    }

    private function workCount(int $id): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM work_items WHERE account_id=?');
        $statement->execute([$id]);
        return (int) $statement->fetchColumn();
    }

    private function workStatus(int $id): string
    {
        $statement = $this->pdo->prepare('SELECT status FROM work_items WHERE account_id=? ORDER BY id DESC LIMIT 1');
        $statement->execute([$id]);
        return (string) $statement->fetchColumn();
    }
}
