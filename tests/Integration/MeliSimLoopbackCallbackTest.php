<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Integrations\MercadoLibre\Transport\CurlMeliTransport;
use App\Integrations\MercadoLibre\Transport\RemoteHostPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MeliSimLoopbackCallbackTest extends TestCase
{
    /** @var resource|null */
    private $server = null;
    private ?string $directory = null;

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        if ($this->directory !== null) {
            foreach (['requests.jsonl','stdout.txt','stderr.txt','pkce-challenge.txt','authorization.json'] as $name) {
                $path = $this->directory . '/' . $name;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($this->directory)) {
                rmdir($this->directory);
            }
            $this->directory = null;
        }
        parent::tearDown();
    }

    public function testAuthorizationCodeAcceptsLoopbackCallbackWithLocalPort(): void
    {
        $verifier = str_repeat('a', 43);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $baseUrl = $this->startServer($challenge);

        $transport = new CurlMeliTransport(new RemoteHostPolicy(), 'test');
        $response = $transport->send(
            'POST',
            $baseUrl . '/oauth/token',
            ['Content-Type'=>'application/x-www-form-urlencoded'],
            http_build_query([
                'grant_type'=>'authorization_code',
                'client_id'=>'synthetic-client',
                'client_secret'=>'synthetic-secret',
                'code'=>'synthetic-one-use-code',
                'redirect_uri'=>'http://127.0.0.1:18082/oauth/mercadolibre/callback',
                'code_verifier'=>$verifier,
            ], '', '&', PHP_QUERY_RFC3986),
        );

        self::assertSame(200, $response->status);
        $trace = file($this->directory . '/requests.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($trace);
        self::assertCount(1, $trace);
        $record = json_decode($trace[0], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('oauth.token', $record['operation'] ?? null);
        self::assertTrue($record['matched'] ?? false);
    }

    private function startServer(string $challenge): string
    {
        if (getenv('APP_ENV') !== 'test') {
            throw new RuntimeException('MLSIM_TEST_ENV_REQUIRED');
        }

        $router = dirname(__DIR__) . '/Support/MeliSim/router.php';
        self::assertFileExists($router);
        $this->directory = sys_get_temp_dir() . '/erp-meli2-mlsim-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->directory, 0700));
        self::assertNotFalse(file_put_contents($this->directory . '/requests.jsonl', ''));
        self::assertNotFalse(file_put_contents($this->directory . '/pkce-challenge.txt', $challenge));

        $reservation = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        self::assertIsResource($reservation, $error);
        $address = stream_socket_get_name($reservation, false);
        self::assertIsString($address);
        fclose($reservation);

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
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $deadline = microtime(true) + 3.0;
        do {
            if (!proc_get_status($this->server)['running']) {
                break;
            }
            $socket = @stream_socket_client('tcp://' . $address, $connectErrno, $connectError, 0.05);
            if (is_resource($socket)) {
                fclose($socket);
                return 'http://' . $address;
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('MLSIM_OWNED_SERVER_START_FAILED: ' . (string) @file_get_contents($this->directory . '/stderr.txt'));
    }
}
