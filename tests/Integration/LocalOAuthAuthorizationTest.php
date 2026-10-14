<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class LocalOAuthAuthorizationTest extends TestCase
{
    /** @var array<string,string|false> */
    private array $before = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['APP_ENV','APP_URL','MELI_CLIENT_ID','MELI_CLIENT_SECRET','MELI_LOCAL_SIM_BASE_URL','MELI_TEST_BASE_URL'] as $key) {
            $this->before[$key] = getenv($key);
        }

        $pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('secret');
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Local OAuth Company','local-oauth-company')");
        $stmt = $pdo->prepare("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES (1,'local-oauth','Local OAuth','local-oauth@example.test',?,'active')");
        $stmt->execute([$hash]);
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES (1,1,'admin')");

        $_SESSION['user_id'] = 1;
        $_SESSION['company_id'] = 1;

        $this->setEnv('APP_ENV', 'local');
        $this->setEnv('APP_URL', 'http://localhost');
        $this->setEnv('MELI_CLIENT_ID', 'synthetic-client');
        $this->setEnv('MELI_CLIENT_SECRET', 'synthetic-secret');
        $this->clearEnv('MELI_LOCAL_SIM_BASE_URL');
        $this->clearEnv('MELI_TEST_BASE_URL');
    }

    protected function tearDown(): void
    {
        unset($_SESSION['meli_oauth'], $_SESSION['meli_oauth_company_id']);
        foreach ($this->before as $key => $value) {
            if ($value === false) {
                $this->clearEnv($key);
            } else {
                $this->setEnv($key, $value);
            }
        }
        parent::tearDown();
    }

    public function testLocalModeWithoutSimulatorFailsClosedBeforeOAuthState(): void
    {
        $response = $this->connect();

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('meli_oauth', $_SESSION);
        self::assertArrayNotHasKey('meli_oauth_company_id', $_SESSION);
    }

    public function testLocalModeDoesNotTreatTestBaseUrlAsSimulatorConfiguration(): void
    {
        $this->setEnv('MELI_TEST_BASE_URL', 'http://127.0.0.1:18080');

        $response = $this->connect();

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('meli_oauth', $_SESSION);
        self::assertArrayNotHasKey('meli_oauth_company_id', $_SESSION);
    }

    public function testLocalModeUsesLiteralLoopbackSimulatorAuthorizationEndpoint(): void
    {
        $this->setEnv('MELI_LOCAL_SIM_BASE_URL', 'http://127.0.0.1:18080');

        $response = $this->connect();
        self::assertSame(302, $response->getStatusCode());

        $parts = parse_url($response->getHeaderLine('Location'));
        self::assertIsArray($parts);
        self::assertSame('http', $parts['scheme'] ?? null);
        self::assertSame('127.0.0.1', $parts['host'] ?? null);
        self::assertSame(18080, $parts['port'] ?? null);
        self::assertSame('/authorization', $parts['path'] ?? null);

        parse_str((string) ($parts['query'] ?? ''), $query);
        self::assertSame('code', $query['response_type'] ?? null);
        self::assertSame('synthetic-client', $query['client_id'] ?? null);
        self::assertSame('http://localhost/oauth/mercadolibre/callback', $query['redirect_uri'] ?? null);
        self::assertSame('S256', $query['code_challenge_method'] ?? null);
        self::assertNotSame('', (string) ($query['state'] ?? ''));
        self::assertNotSame('', (string) ($query['code_challenge'] ?? ''));
        self::assertSame(1, $_SESSION['meli_oauth_company_id'] ?? null);
    }

    public function testLocalModeRejectsNonLiteralLoopbackSimulator(): void
    {
        foreach (['http://localhost:18080', 'http://192.168.1.5:18080', 'https://127.0.0.1:18080'] as $invalid) {
            unset($_SESSION['meli_oauth'], $_SESSION['meli_oauth_company_id']);
            $this->setEnv('MELI_LOCAL_SIM_BASE_URL', $invalid);
            $response = $this->connect();
            self::assertSame(503, $response->getStatusCode(), $invalid);
            self::assertSame('', $response->getHeaderLine('Location'), $invalid);
            self::assertArrayNotHasKey('meli_oauth', $_SESSION, $invalid);
        }
    }

    public function testProductionIgnoresLocalSimulatorOverride(): void
    {
        $this->setEnv('APP_ENV', 'production');
        $this->setEnv('APP_URL', 'https://erp.example.test');
        $this->setEnv('MELI_LOCAL_SIM_BASE_URL', 'http://127.0.0.1:18080');

        $response = $this->connect();
        self::assertSame(302, $response->getStatusCode());
        $parts = parse_url($response->getHeaderLine('Location'));
        self::assertIsArray($parts);
        self::assertSame('https', $parts['scheme'] ?? null);
        self::assertSame('auth.mercadolibre.com.co', $parts['host'] ?? null);
        self::assertSame('/authorization', $parts['path'] ?? null);
    }

    private function connect(): \Psr\Http\Message\ResponseInterface
    {
        return Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/oauth/mercadolibre/connect'),
        );
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }

    private function clearEnv(string $key): void
    {
        putenv($key);
        unset($_ENV[$key]);
    }
}
