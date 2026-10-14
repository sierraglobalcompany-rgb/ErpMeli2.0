<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class MeliAccountHardDeleteUsageHistoryTest extends TestCase
{
    public function testHardDeleteRejectsOAuthAccountUsageHistory(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'admin','Admin','admin@example.test','unused','active')");
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(10,'Empresa A','empresa-a')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(10,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(307,10,'3007','MCO','DELETE TEST','disabled')");
        $pdo->exec("INSERT INTO api_usage_daily(usage_date,scope_key,operation_key,requests) VALUES(UTC_DATE(),'account:307','oauth.token',1)");

        $_SESSION = [
            'user_id' => 1,
            'company_id' => 10,
            'role' => 'admin',
            'display_name' => 'Admin',
            'csrf_token' => str_repeat('c', 64),
        ];

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/mercadolibre/accounts/307/delete')
            ->withParsedBody(['csrf_token' => str_repeat('c', 64)]);
        $response = Bootstrap::create()->handle($request);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM meli_accounts WHERE id=307')->fetchColumn());
        self::assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM api_usage_daily WHERE scope_key='account:307'")->fetchColumn());
    }
}
