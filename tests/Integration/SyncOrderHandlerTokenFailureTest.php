<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Auth\OAuthRefreshService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Sales\SyncOrder\SyncOrderHandler;
use App\Modules\Settings\SystemSettingsRepository;
use App\Work\WorkRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class SyncOrderHandlerTokenFailureTest extends TestCase
{
    public function testAttentionAccountFailsCurrentWorkWithoutThrowingOrCallingMeli(): void
    {
        $pdo = TestDatabase::reset();
        $cipher = new TokenCipher('sync-order-token-failure-key');
        [$companyId, $accountId] = $this->seedAttentionAccount($pdo, $cipher);
        $transport = new NoOrderHttpTransport();

        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            minRequestIntervalMs: 0,
        );
        $tokens = new OAuthRefreshService(
            $pdo,
            Connection::fromConfig(TestDatabase::config()),
            $client,
            $cipher,
            '123456789',
            'client-secret',
            'erp_meli2.oauth.account',
        );
        $work = new WorkRepository($pdo);
        $handler = new SyncOrderHandler($work, $client, $tokens);

        $work->enqueue(
            $companyId,
            $accountId,
            'company:' . $companyId . ':account:' . $accountId,
            'order.sync',
            '200000000002',
            'order.sync:200000000002',
            ['order_id' => '200000000002'],
        );
        $claim = $work->claimNext();
        self::assertIsArray($claim);

        try {
            $completed = $handler->syncCurrentClaim(
                $claim['id'],
                $claim['claim_token'],
                $companyId,
                $accountId,
                '200000000002',
                new DateTimeImmutable('2026-10-09T01:35:00+00:00'),
            );
        } catch (RuntimeException $exception) {
            self::fail('OAuth attention must become a terminal work outcome, not escape the handler: ' . $exception->getMessage());
        }

        self::assertFalse($completed);
        self::assertCount(0, $transport->requests, 'An attention account must not perform OAuth or order HTTP.');

        $row = $pdo->query(
            'SELECT status, claim_token, last_error_code FROM work_items WHERE id=' . (int) $claim['id']
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame('failed', $row['status']);
        self::assertNull($row['claim_token']);
        self::assertSame('meli_oauth_attention', $row['last_error_code']);
    }

    /** @return array{0:int,1:int} */
    private function seedAttentionAccount(PDO $pdo, TokenCipher $cipher): array
    {
        $pdo->exec("INSERT INTO companies(name,slug) VALUES ('Order Token Guard','order-token-guard')");
        $companyId = (int) $pdo->lastInsertId();

        $account = $pdo->prepare(
            "INSERT INTO meli_accounts(company_id,external_user_id,site_id,nickname,status) "
            . "VALUES (:company_id,'700000003','MCO','Attention Seller','attention')"
        );
        $account->execute(['company_id' => $companyId]);
        $accountId = (int) $pdo->lastInsertId();

        $tokens = $pdo->prepare(
            'INSERT INTO meli_tokens(account_id,access_token_cipher,refresh_token_cipher,expires_at,refresh_version) '
            . 'VALUES (:account_id,:access_token,:refresh_token,:expires_at,0)'
        );
        $tokens->execute([
            'account_id' => $accountId,
            'access_token' => $cipher->encrypt('expired-access'),
            'refresh_token' => $cipher->encrypt('must-not-reuse-refresh'),
            'expires_at' => '2026-10-09 01:00:00.000000',
        ]);

        return [$companyId, $accountId];
    }
}

final class NoOrderHttpTransport implements MeliTransport
{
    /** @var list<array{method:string,url:string,headers:array<string,string>,body:?string}> */
    public array $requests = [];

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = compact('method', 'url', 'headers', 'body');
        throw new RuntimeException('HTTP must not be reached for an attention account.');
    }
}
