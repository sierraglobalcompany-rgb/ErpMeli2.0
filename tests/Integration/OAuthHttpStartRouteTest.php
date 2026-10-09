<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class OAuthHttpStartRouteTest extends TestCase
{
    public function testAuthenticatedCompanyAdminStartsTenantBoundPkceAuthorization(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'OAuth Route Company','oauth-route-company')");
        $stmt = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'oauth@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $previousClientId = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=123456789');
        $_ENV['MELI_CLIENT_ID'] = '123456789';

        try {
            $request = (new ServerRequestFactory())
                ->createServerRequest('GET', '/oauth/mercadolibre/connect');
            $response = Bootstrap::create()->handle($request);

            self::assertSame(302, $response->getStatusCode());
            $location = $response->getHeaderLine('Location');
            self::assertNotSame('', $location);

            $parts = parse_url($location);
            self::assertIsArray($parts);
            self::assertSame('https', $parts['scheme'] ?? null);
            self::assertSame('auth.mercadolibre.com.co', $parts['host'] ?? null);
            self::assertSame('/authorization', $parts['path'] ?? null);

            parse_str((string) ($parts['query'] ?? ''), $query);
            self::assertSame('code', $query['response_type'] ?? null);
            self::assertSame('123456789', $query['client_id'] ?? null);
            self::assertSame(
                'http://localhost/oauth/mercadolibre/callback',
                $query['redirect_uri'] ?? null,
            );
            self::assertSame('S256', $query['code_challenge_method'] ?? null);
            self::assertNotSame('', (string) ($query['state'] ?? ''));
            self::assertNotSame('', (string) ($query['code_challenge'] ?? ''));

            self::assertSame(1, $_SESSION['meli_oauth_company_id'] ?? null);
            self::assertSame($query['state'] ?? null, $_SESSION['meli_oauth']['state'] ?? null);
        } finally {
            unset($_SESSION['meli_oauth'], $_SESSION['meli_oauth_company_id']);
            $this->restoreClientId($previousClientId);
        }
    }

    public function testAuthenticatedCompanyMemberCannotStartOAuthAuthorization(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'OAuth Member Company','oauth-member-company')");
        $stmt = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'oauth-member@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'member')");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $previousClientId = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=123456789');
        $_ENV['MELI_CLIENT_ID'] = '123456789';

        try {
            $response = Bootstrap::create()->handle(
                (new ServerRequestFactory())->createServerRequest('GET', '/oauth/mercadolibre/connect'),
            );

            self::assertSame(403, $response->getStatusCode());
            self::assertArrayNotHasKey('meli_oauth', $_SESSION);
            self::assertArrayNotHasKey('meli_oauth_company_id', $_SESSION);
        } finally {
            unset($_SESSION['meli_oauth'], $_SESSION['meli_oauth_company_id']);
            $this->restoreClientId($previousClientId);
        }
    }

    private function restoreClientId(string|false $previousClientId): void
    {
        if ($previousClientId === false) {
            putenv('MELI_CLIENT_ID');
            unset($_ENV['MELI_CLIENT_ID']);
            return;
        }

        putenv('MELI_CLIENT_ID=' . $previousClientId);
        $_ENV['MELI_CLIENT_ID'] = $previousClientId;
    }
}
