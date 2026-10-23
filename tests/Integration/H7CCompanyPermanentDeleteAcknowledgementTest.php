<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Security\Csrf;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class H7CCompanyPermanentDeleteAcknowledgementTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDatabase::reset();
        $auth = new AuthService($this->pdo);
        $auth->bootstrapAdmin('Empresa H7C Ack', 'empresa-h7c-ack', 'h7c-ack@example.test', 'test-h7c-ack-password');
        self::assertTrue($auth->login('h7c-ack@example.test', 'test-h7c-ack-password'));
    }

    /** @param array<string,mixed> $body */
    private function request(string $method, string $path, array $body = []): ResponseInterface
    {
        $body += ['csrf_token' => (new Csrf())->token()];

        return Bootstrap::create()->handle(
            (new ServerRequestFactory())
                ->createServerRequest($method, $path)
                ->withParsedBody($body)
        );
    }

    public function testPermanentDeleteRequiresServerSideAcknowledgement(): void
    {
        self::assertSame(303, $this->request('POST', '/system/companies/1/archive')->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());

        $missing = $this->request('POST', '/system/companies/1/delete');
        self::assertSame(422, $missing->getStatusCode());
        self::assertStringContainsString('Confirma', (string) $missing->getBody());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM company_users')->fetchColumn());

        $invalid = $this->request('POST', '/system/companies/1/delete', ['confirm_permanent_delete' => '0']);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());

        $confirmed = $this->request('POST', '/system/companies/1/delete', ['confirm_permanent_delete' => '1']);
        self::assertSame(303, $confirmed->getStatusCode());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM company_users')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }
}
