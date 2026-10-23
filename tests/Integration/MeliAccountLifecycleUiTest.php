<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliAccountLifecycleUiTest extends TestCase
{
    private string|false $clientBefore;
    private string|false $secretBefore;
    private mixed $envClientBefore;
    private mixed $envSecretBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientBefore = getenv('MELI_CLIENT_ID');
        $this->secretBefore = getenv('MELI_CLIENT_SECRET');
        $this->envClientBefore = $_ENV['MELI_CLIENT_ID'] ?? null;
        $this->envSecretBefore = $_ENV['MELI_CLIENT_SECRET'] ?? null;
        putenv('MELI_CLIENT_ID=test-client');
        putenv('MELI_CLIENT_SECRET=test-secret');
        $_ENV['MELI_CLIENT_ID'] = 'test-client';
        $_ENV['MELI_CLIENT_SECRET'] = 'test-secret';

        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'admin','Admin','admin@example.test','unused','active')");
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(10,'Empresa A','empresa-a')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(10,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES
            (101,10,'1001','MCO','CONECTADA','connected'),
            (102,10,'1002','MCO','ATENCION','attention'),
            (103,10,'1003','MCO','REAUTORIZAR','reauth_required'),
            (104,10,'1004','MCO','DESCONECTADA PRISTINA','disabled'),
            (105,10,'1005','MCO','DESCONECTADA CON HISTORIA','disabled')");
        $pdo->exec("INSERT INTO api_usage_daily(usage_date,scope_key,operation_key,requests) VALUES(UTC_DATE(),'account:105','oauth.token',1)");

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
        $this->restoreEnv('MELI_CLIENT_ID', $this->clientBefore, $this->envClientBefore);
        $this->restoreEnv('MELI_CLIENT_SECRET', $this->secretBefore, $this->envSecretBefore);
        parent::tearDown();
    }

    public function testLifecycleActionsMatchAccountStatusAndPristineDeleteRule(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/mercadolibre/accounts')
        );
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('action="/mercadolibre/accounts/101/disconnect"', $html);
        self::assertStringContainsString('action="/mercadolibre/accounts/102/reauthorize"', $html);
        self::assertStringContainsString('action="/mercadolibre/accounts/103/reauthorize"', $html);
        self::assertStringContainsString('action="/mercadolibre/accounts/104/reauthorize"', $html);
        self::assertStringContainsString('action="/mercadolibre/accounts/104/delete"', $html);
        self::assertStringNotContainsString('action="/mercadolibre/accounts/101/delete"', $html);
        self::assertStringNotContainsString('action="/mercadolibre/accounts/105/delete"', $html);
        self::assertStringContainsString('name="csrf_token"', $html);
        self::assertStringContainsString('value="' . str_repeat('c', 64) . '"', $html);
    }

    public function testDisconnectIsBehindNativeHumanDisclosureWithFactualImpact(): void
    {
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/mercadolibre/accounts')
        );
        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertSame(
            1,
            preg_match(
                '#<details[^>]*data-meli-destructive-action="disconnect"[^>]*>\\s*<summary>Desconectar cuenta</summary>.*?</details>#si',
                $html,
                $matches,
            ),
            'Desconectar no debe ser un POST directo: requiere un disclosure nativo antes de confirmar.'
        );

        $disclosure = $matches[0];
        self::assertStringContainsString('credenciales', $disclosure);
        self::assertStringContainsString('historial', $disclosure);
        self::assertStringContainsString('volver a autorizar', $disclosure);
        self::assertMatchesRegularExpression(
            '#<form[^>]*method="post"[^>]*action="/mercadolibre/accounts/101/disconnect"[^>]*>.*?name="csrf_token".*?<button[^>]*>Confirmar desconexión</button>.*?</form>#si',
            $disclosure,
            'El POST real y CSRF deben permanecer dentro de la confirmación.'
        );
        self::assertSame(
            1,
            preg_match_all('#action="/mercadolibre/accounts/101/disconnect"#', $html),
            'No debe quedar un segundo formulario de desconexión directa.'
        );
    }

    private function restoreEnv(string $key, string|false $getenvValue, mixed $envValue): void
    {
        putenv($getenvValue === false ? $key : $key . '=' . $getenvValue);
        if ($envValue === null) {
            unset($_ENV[$key]);
        } else {
            $_ENV[$key] = $envValue;
        }
    }
}
