<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class OAuthHttpCallbackGuardTest extends TestCase
{
    public function testInvalidCallbackStateIsRejectedAndConsumesOriginalTenantBindingBeforeRemoteExchange(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Original','original'),(2,'Other','other')");
        $stmt = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'oauth-guard@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin'),(2,1,'admin')");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $previousClientId = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=123456789');
        $_ENV['MELI_CLIENT_ID'] = '123456789';

        try {
            $start = (new ServerRequestFactory())
                ->createServerRequest('GET', '/oauth/mercadolibre/connect');
            $startResponse = Bootstrap::create()->handle($start);
            self::assertSame(302, $startResponse->getStatusCode());
            self::assertSame(1, $_SESSION['meli_oauth_company_id'] ?? null);

            // Simulate a legitimate company switch in another tab before Mercado Libre redirects back.
            $_SESSION['company_id'] = 2;

            $callback = (new ServerRequestFactory())
                ->createServerRequest(
                    'GET',
                    '/oauth/mercadolibre/callback?code=one-use-code&state=wrong-state',
                );
            $response = Bootstrap::create()->handle($callback);

            self::assertSame(400, $response->getStatusCode());
            self::assertArrayNotHasKey('meli_oauth', $_SESSION);
            self::assertArrayNotHasKey('meli_oauth_company_id', $_SESSION);
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM meli_accounts')->fetchColumn());
        } finally {
            unset($_SESSION['meli_oauth'], $_SESSION['meli_oauth_company_id']);
            $this->restoreClientId($previousClientId);
        }
    }

    public function testCallbackRejectsUserWhoLostAdminRoleBeforeRemoteExchange(): void
    {
        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'OAuth Admin Company','oauth-admin-company')");
        $stmt = $pdo->prepare("INSERT INTO users(id,email,password_hash,status) VALUES (1,'oauth-role@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $previousClientId = getenv('MELI_CLIENT_ID');
        putenv('MELI_CLIENT_ID=123456789');
        $_ENV['MELI_CLIENT_ID'] = '123456789';

        try {
            $startResponse = Bootstrap::create()->handle(
                (new ServerRequestFactory())->createServerRequest('GET', '/oauth/mercadolibre/connect'),
            );
            self::assertSame(302, $startResponse->getStatusCode());
            $state = $_SESSION['meli_oauth']['state'] ?? null;
            self::assertIsString($state);
            self::assertNotSame('', $state);

            $pdo->exec("UPDATE company_users SET role='member' WHERE company_id=1 AND user_id=1");

            $response = Bootstrap::create()->handle(
                (new ServerRequestFactory())->createServerRequest(
                    'GET',
                    '/oauth/mercadolibre/callback?code=one-use-code&state=' . rawurlencode($state),
                ),
            );

            self::assertSame(403, $response->getStatusCode());
            self::assertArrayNotHasKey('meli_oauth', $_SESSION);
            self::assertArrayNotHasKey('meli_oauth_company_id', $_SESSION);
            self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM meli_accounts')->fetchColumn());
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
