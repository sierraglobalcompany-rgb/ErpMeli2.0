<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\PasswordService;
use App\Integrations\MercadoLibre\Auth\TokenCipher;
use App\Integrations\MercadoLibre\Transport\CurlMeliTransport;
use App\Integrations\MercadoLibre\Transport\RemoteHostPolicy;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class LocalOAuthMeliSimEndToEndTest extends TestCase
{
    /** @var resource|null */
    private $server = null;
    private ?string $directory = null;
    private string $baseUrl = '';
    /** @var array<string,string|false> */
    private array $before = [];
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['APP_ENV','APP_URL','APP_KEY','MELI_CLIENT_ID','MELI_CLIENT_SECRET','MELI_LOCAL_SIM_BASE_URL'] as $key) {
            $this->before[$key] = getenv($key);
        }

        $this->pdo = TestDatabase::reset();
        $hash = (new PasswordService())->hash('local-oauth-password');
        $this->pdo->exec("INSERT INTO companies(id,name,slug) VALUES(1,'Local OAuth','local-oauth')");
        $user = $this->pdo->prepare("INSERT INTO users(id,username,display_name,email,password_hash,status) VALUES(1,'local-oauth','Local OAuth','local@example.test',?,'active')");
        $user->execute([$hash]);
        $this->pdo->exec("INSERT INTO company_users(company_id,user_id,role) VALUES(1,1,'admin')");

        $this->startServer();
        $config = TestDatabase::config();
        $this->setEnv('APP_ENV', 'local');
        $this->setEnv('APP_URL', 'http://127.0.0.1:18082');
        $this->setEnv('APP_KEY', $config->appKey);
        $this->setEnv('MELI_CLIENT_ID', 'synthetic-client');
        $this->setEnv('MELI_CLIENT_SECRET', 'synthetic-secret');
        $this->setEnv('MELI_LOCAL_SIM_BASE_URL', $this->baseUrl);
        $_SESSION = ['user_id'=>1,'company_id'=>1];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        foreach ($this->before as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key]);
            } else {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
        $this->stopServer();
        parent::tearDown();
    }

    public function testHumanLocalAuthorizationUsesRealErpCallbackAndPersistsCanonicalSeller(): void
    {
        $factory = new ServerRequestFactory();
        $start = Bootstrap::create()->handle($factory->createServerRequest('GET', '/oauth/mercadolibre/connect'));
        self::assertSame(302, $start->getStatusCode());
        $authorizationUrl = $start->getHeaderLine('Location');
        self::assertStringStartsWith($this->baseUrl . '/authorization?', $authorizationUrl);

        $transport = new CurlMeliTransport(new RemoteHostPolicy(), 'test');
        $approval = $transport->send('GET', $authorizationUrl, [], null);
        self::assertSame(200, $approval->status);
        self::assertStringContainsString('Cuenta simulada Mercado Libre', $approval->body);
        self::assertStringContainsString('Seller: 100', $approval->body);
        self::assertStringContainsString('Site: MCO', $approval->body);
        self::assertSame(1, preg_match('/name="approval_token" value="([a-f0-9]{32})"/', $approval->body, $match));

        $approved = $transport->send(
            'POST',
            $this->baseUrl . '/authorization',
            ['Content-Type'=>'application/x-www-form-urlencoded'],
            http_build_query(['approval_token'=>$match[1]], '', '&', PHP_QUERY_RFC3986),
        );
        self::assertSame(302, $approved->status);
        $callbackUrl = $approved->headers['location'] ?? '';
        self::assertStringStartsWith('http://127.0.0.1:18082/oauth/mercadolibre/callback?', $callbackUrl);
        parse_str((string) parse_url($callbackUrl, PHP_URL_QUERY), $callbackQuery);
        self::assertSame('synthetic-one-use-code', $callbackQuery['code'] ?? null);
        self::assertSame($_SESSION['meli_oauth']['state'] ?? null, $callbackQuery['state'] ?? null);

        $callback = $factory->createServerRequest('GET', '/oauth/mercadolibre/callback')->withQueryParams($callbackQuery);
        $before = time();
        $response = Bootstrap::create()->handle($callback);
        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/sales', $response->getHeaderLine('Location'));
        self::assertArrayNotHasKey('meli_oauth', $_SESSION);
        self::assertArrayNotHasKey('meli_oauth_company_id', $_SESSION);

        $row = $this->pdo->query(
            'SELECT a.id,a.company_id,a.external_user_id,a.site_id,a.nickname,a.status,'
            . 't.access_token_cipher,t.refresh_token_cipher,t.expires_at '
            . 'FROM meli_accounts a JOIN meli_tokens t ON t.account_id=a.id'
        )->fetch(PDO::FETCH_ASSOC);
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['company_id']);
        self::assertSame('100', $row['external_user_id']);
        self::assertSame('MCO', $row['site_id']);
        self::assertSame('synthetic-seller', $row['nickname']);
        self::assertSame('connected', $row['status']);
        $cipher = new TokenCipher(TestDatabase::config()->appKey);
        self::assertSame('synthetic-new-access', $cipher->decrypt($row['access_token_cipher']));
        self::assertSame('synthetic-new-refresh', $cipher->decrypt($row['refresh_token_cipher']));
        self::assertGreaterThanOrEqual($before + 1200, (new DateTimeImmutable($row['expires_at']))->getTimestamp());

        $trace = $this->trace();
        self::assertSame(['oauth.token','users.me'], array_column($trace, 'operation'));
        self::assertSame(['POST','GET'], array_column($trace, 'method'));
        self::assertSame([true,true], array_column($trace, 'matched'));
        self::assertSame([1,2], array_column($trace, 'seq'));

        self::assertSame(400, Bootstrap::create()->handle($callback)->getStatusCode());
        self::assertCount(2, $this->trace(), 'Consumed callback must not exchange again.');

        $accountId = (int) $row['id'];
        $secondStart = Bootstrap::create()->handle($factory->createServerRequest('GET', '/oauth/mercadolibre/connect'));
        self::assertSame(302, $secondStart->getStatusCode());
        $secondApproval = $transport->send('GET', $secondStart->getHeaderLine('Location'), [], null);
        self::assertSame(200, $secondApproval->status);
        self::assertSame(1, preg_match('/name="approval_token" value="([a-f0-9]{32})"/', $secondApproval->body, $secondMatch));
        $secondApproved = $transport->send(
            'POST',
            $this->baseUrl . '/authorization',
            ['Content-Type'=>'application/x-www-form-urlencoded'],
            http_build_query(['approval_token'=>$secondMatch[1]], '', '&', PHP_QUERY_RFC3986),
        );
        self::assertSame(302, $secondApproved->status);
        parse_str((string) parse_url($secondApproved->headers['location'] ?? '', PHP_URL_QUERY), $secondCallbackQuery);
        $secondCallback = $factory->createServerRequest('GET', '/oauth/mercadolibre/callback')->withQueryParams($secondCallbackQuery);
        $secondResponse = Bootstrap::create()->handle($secondCallback);
        self::assertSame(303, $secondResponse->getStatusCode());
        self::assertSame($accountId, (int) $this->pdo->query('SELECT id FROM meli_accounts WHERE external_user_id=100')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM meli_accounts WHERE external_user_id=100')->fetchColumn());

        $trace = $this->trace();
        self::assertSame(['oauth.token','users.me','oauth.token','users.me'], array_column($trace, 'operation'));
        self::assertSame([true,true,true,true], array_column($trace, 'matched'));
        self::assertSame([1,2,3,4], array_column($trace, 'seq'));
    }

    /** @return list<array<string,mixed>> */
    private function trace(): array
    {
        if ($this->directory === null) { return []; }
        $lines = file($this->directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        return array_map(static fn(string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR), $lines);
    }

    private function startServer(): void
    {
        if (getenv('APP_ENV') !== 'test') {
            throw new RuntimeException('MLSIM_TEST_ENV_REQUIRED');
        }
        $router = dirname(__DIR__) . '/Support/MeliSim/router.php';
        self::assertFileExists($router);
        $this->directory = sys_get_temp_dir() . '/erp-meli2-mlsim-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
        file_put_contents($this->directory . '/requests.jsonl', '');

        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($reservation, $error);
        $address = stream_socket_get_name($reservation, false);
        self::assertIsString($address);
        fclose($reservation);
        $this->baseUrl = 'http://' . $address;
        self::assertSame('127.0.0.1', parse_url($this->baseUrl, PHP_URL_HOST));

        $environment = array_merge(
            array_filter([
                'SystemRoot'=>getenv('SystemRoot'),
                'WINDIR'=>getenv('WINDIR'),
                'TEMP'=>sys_get_temp_dir(),
                'TMP'=>sys_get_temp_dir(),
            ], static fn(mixed $value): bool => is_string($value)),
            ['APP_ENV'=>'test','MLSIM_SCENARIO'=>'OAUTH_CONNECT','MLSIM_ROOT'=>$this->directory],
        );
        $pipes = [];
        $this->server = proc_open(
            [PHP_BINARY, '-S', $address, $router],
            [0=>['pipe','r'],1=>['file',$this->directory . '/stdout.txt','w'],2=>['file',$this->directory . '/stderr.txt','w']],
            $pipes,
            dirname(__DIR__, 2),
            $environment,
            ['bypass_shell'=>true],
        );
        self::assertIsResource($this->server);
        if (isset($pipes[0]) && is_resource($pipes[0])) { fclose($pipes[0]); }

        $deadline = microtime(true) + 3.0;
        do {
            if (!proc_get_status($this->server)['running']) { break; }
            $socket = @stream_socket_client('tcp://' . $address, $connectErrno, $connectError, 0.05);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('MLSIM_OWNED_SERVER_START_FAILED: ' . (string) @file_get_contents($this->directory . '/stderr.txt'));
    }

    private function stopServer(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        if ($this->directory === null) { return; }
        $parent = realpath(dirname($this->directory));
        if ($parent !== realpath(sys_get_temp_dir())
            || preg_match('/^erp-meli2-mlsim-[a-f0-9]{16}$/D', basename($this->directory)) !== 1
            || is_link($this->directory)) {
            throw new RuntimeException('MLSIM_UNSAFE_CLEANUP');
        }
        foreach (['requests.jsonl','stdout.txt','stderr.txt','pkce-challenge.txt','authorization.json'] as $name) {
            $path = $this->directory . '/' . $name;
            if (is_file($path)) { unlink($path); }
        }
        if (is_dir($this->directory)) { rmdir($this->directory); }
        $this->directory = null;
    }

    private function setEnv(string $key, string $value): void
    {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}
