<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class BillingDisabledAccountHumanLabelTest extends TestCase
{
    public function testBillingHistoryKeepsHumanNicknameForDisabledAccountWithoutChangingMachineQuery(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Billing UI','billing-ui')");
        $pdo->exec("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'billing-ui@example.test','Billing UI','billing-ui@example.test','unused','active')");
        $pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");
        $pdo->exec("INSERT INTO meli_accounts(id,company_id,external_user_id,site_id,nickname,status) VALUES(10,1,'100','MCO','Cuenta histórica','disabled')");
        $pdo->exec("INSERT INTO billing_periods(id,company_id,account_id,period_key,document_type,cursor_last_id,sync_state,last_synced_at) VALUES(1,1,10,'2026-09-01','BILL','101','caught_up','2026-10-15 20:00:00')");
        $_SESSION = ['user_id' => 1, 'company_id' => 1, 'role' => 'admin'];

        $response = Bootstrap::create()->handle((new ServerRequestFactory())->createServerRequest('GET', '/billing'));

        self::assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();

        self::assertStringContainsString('data-label="Cuenta">Cuenta histórica</td>', $html);
        self::assertStringNotContainsString('data-label="Cuenta">100</td>', $html);
        self::assertStringContainsString('account_id=10', $html);
    }
}
