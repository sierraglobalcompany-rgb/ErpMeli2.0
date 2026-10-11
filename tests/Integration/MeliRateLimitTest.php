<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database\Connection;
use App\Integrations\MercadoLibre\Client\ApiUsageRecorder;
use App\Integrations\MercadoLibre\Client\MeliApiException;
use App\Integrations\MercadoLibre\Client\MeliClient;
use App\Integrations\MercadoLibre\Client\MeliCooldownRepository;
use App\Integrations\MercadoLibre\Client\MeliRateLimitException;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use DateTimeImmutable;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabase;

final class MeliRateLimitTest extends TestCase
{
    public function testRetryAfterCreatesCooldownAndDoesNotRetryPhysicalHttp(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RateLimitQueueTransport([
            new MeliTransportResponse(429, ['retry-after' => '20', 'x-request-id' => 'req-429'], '{"error":"rate_limit"}'),
            new MeliTransportResponse(200, [], '{"id":1}'),
        ]);
        $client = $this->client($pdo, $transport);
        $before = new DateTimeImmutable('now UTC');

        try {
            $client->request('users.me', 'token', scopeKey: 'account:7');
            self::fail('429 must produce a rate-limit exception.');
        } catch (MeliRateLimitException $exception) {
            $after = new DateTimeImmutable('now UTC');
            self::assertSame(429, $exception->status);
            self::assertSame('req-429', $exception->requestId);
            self::assertGreaterThanOrEqual($before->modify('+20 seconds')->getTimestamp(), $exception->retryAt->getTimestamp());
            self::assertLessThanOrEqual($after->modify('+22 seconds')->getTimestamp(), $exception->retryAt->getTimestamp());
        }

        self::assertCount(1, $transport->requests, 'MeliClient must not retry a 429 inline.');

        try {
            $client->request('users.me', 'token', scopeKey: 'account:7');
            self::fail('An active cooldown must block before physical HTTP.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame('Mercado Libre operation is cooling down.', $exception->getMessage());
        }
        self::assertCount(1, $transport->requests);
    }

    public function testMissingRetryAfterUsesBoundedBackoffWithJitter(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RateLimitQueueTransport([
            new MeliTransportResponse(429, [], '{}'),
        ]);
        $client = $this->client($pdo, $transport);
        $before = new DateTimeImmutable('now UTC');

        try {
            $client->request('users.me', 'token', scopeKey: 'account:7');
            self::fail('429 must produce a rate-limit exception.');
        } catch (MeliRateLimitException $exception) {
            $after = new DateTimeImmutable('now UTC');
            self::assertGreaterThanOrEqual(
                $before->modify('+15 seconds')->getTimestamp(),
                $exception->retryAt->getTimestamp(),
            );
            self::assertLessThanOrEqual(
                $after->modify('+17 seconds')->getTimestamp(),
                $exception->retryAt->getTimestamp(),
            );
        }

        self::assertCount(1, $transport->requests);
    }

    public function testCooldownIsFamilyScopedInsteadOfFreezingAllMercadoLibreTraffic(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RateLimitQueueTransport([
            new MeliTransportResponse(200, [], '{"ok":true}'),
        ]);
        $cooldowns = new MeliCooldownRepository($pdo);
        $cooldowns->register429('app:billing', 300);

        $operations = $this->operations();
        $operations['billing.test'] = [
            'method' => 'GET',
            'path' => '/billing/test',
            'family' => 'billing',
            'classification' => 'READ',
            'official_doc_url' => 'https://developers.mercadolibre.com.co/',
            'verified_at' => '2026-10-08',
        ];
        $client = new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $operations,
            'https://api.mercadolibre.com',
            new ApiUsageRecorder($pdo),
            $cooldowns,
        );

        $response = $client->request('users.me', 'token', scopeKey: 'account:7');
        self::assertSame(200, $response->status);
        self::assertCount(1, $transport->requests, 'Billing cooldown must not block users family.');

        try {
            $client->request('billing.test', 'token', scopeKey: 'account:7');
            self::fail('Billing family should still be blocked.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame('Mercado Libre operation is cooling down.', $exception->getMessage());
        }
        self::assertCount(1, $transport->requests);
    }

    #[DataProvider('lateResponses')]
    public function testLateResponsePreservesNewerDurableCooldown(int $status, string $body): void
    {
        $pdo = TestDatabase::reset();
        $other = Connection::fromConfig(TestDatabase::config());
        self::assertNotSame($pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), $other->query('SELECT CONNECTION_ID()')->fetchColumn());
        $deadline = null;
        $transport = new RateLimitQueueTransport(
            [new MeliTransportResponse($status, [], $body)],
            function () use ($other, &$deadline): void {
                (new MeliCooldownRepository($other))->register429('app:users', 3600);
                $deadline = $other->query("SELECT blocked_until FROM meli_cooldowns WHERE cooldown_key='app:users'")->fetchColumn();
            },
        );
        $this->assertResponseOutcome($this->client($pdo, $transport), $status, $body);
        self::assertIsString($deadline);
        self::assertSame($deadline, $pdo->query("SELECT blocked_until FROM meli_cooldowns WHERE cooldown_key='app:users'")->fetchColumn());
        try {
            $this->client($other, $transport)->request('users.me', 'token');
            self::fail('The newer cooldown must block another physical request.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame($deadline, $exception->retryAt->format('Y-m-d H:i:s.u'));
        }
        self::assertCount(1, $transport->requests);
    }

    /** @return array<string,array{int,string}> */
    public static function lateResponses(): array
    {
        return [
            'valid 200' => [200, '{"id":1}'],
            'terminal 403' => [403, '{"error":"forbidden"}'],
            'invalid JSON 200' => [200, '{'],
        ];
    }

    public function testShort429ReturnsTheLongerDurableDeadline(): void
    {
        $pdo = TestDatabase::reset();
        $deadline = (new DateTimeImmutable('+1 hour UTC'))->format('Y-m-d H:i:s.u');
        $this->seedCooldown($pdo, $deadline);
        $retryAt = (new MeliCooldownRepository($pdo))->register429('app:users', 1);
        self::assertSame($deadline, $pdo->query("SELECT blocked_until FROM meli_cooldowns WHERE cooldown_key='app:users'")->fetchColumn());
        self::assertSame($deadline, $retryAt->format('Y-m-d H:i:s.u'));
    }

    public function testClient429EmitsEffectiveDeadlinePersistedWhileRequestWasInFlight(): void
    {
        $pdo = TestDatabase::reset();
        $other = Connection::fromConfig(TestDatabase::config());
        $deadline = (new DateTimeImmutable('+1 hour UTC'))->format('Y-m-d H:i:s.u');
        $transport = new RateLimitQueueTransport(
            [new MeliTransportResponse(429, ['retry-after' => '1'], '{}')],
            fn () => $this->seedCooldown($other, $deadline),
        );
        try {
            $this->client($pdo, $transport)->request('users.me', 'token');
            self::fail('A physical 429 must defer using the durable deadline.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame($deadline, $exception->retryAt->format('Y-m-d H:i:s.u'));
            self::assertSame($deadline, $pdo->query("SELECT blocked_until FROM meli_cooldowns WHERE cooldown_key='app:users'")->fetchColumn());
        }
        self::assertCount(1, $transport->requests);
    }

    public function testConcurrent429ProcessesIncrementTwiceWithoutShorteningDeadline(): void
    {
        $pdo = TestDatabase::reset();
        $deadline = (new DateTimeImmutable('+1 hour UTC'))->format('Y-m-d H:i:s.u');
        $this->seedCooldown($pdo, $deadline);
        $processes = [];
        $pdo->beginTransaction();
        $pdo->query("SELECT cooldown_key FROM meli_cooldowns WHERE cooldown_key='app:users' FOR UPDATE")->fetchColumn();
        $script = <<<'PHP'
require getcwd() . '/vendor/autoload.php';
$pdo = \App\Core\Database\Connection::fromConfig(\Tests\Support\TestDatabase::config());
echo $pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), "\n";
flush();
$retryAt = (new \App\Integrations\MercadoLibre\Client\MeliCooldownRepository($pdo))->register429('app:users', 1);
echo $retryAt->format('Y-m-d H:i:s.u'), "\n";
PHP;
        try {
            for ($i = 0; $i < 2; $i++) {
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, '-r', $script],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    dirname(__DIR__, 2),
                );
                self::assertIsResource($process);
                fclose($pipes[0]);
                $processes[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2]];
            }
            $ids = [];
            foreach ($processes as $child) {
                $line = fgets($child['out']);
                self::assertIsString($line);
                self::assertMatchesRegularExpression('/^[0-9]+$/D', trim($line));
                $ids[] = (int) trim($line);
            }
            self::assertNotSame($ids[0], $ids[1]);
            self::assertNotContains((int) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), $ids);
            $overlap = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID IN (?, ?) '
                . "AND INFO LIKE '%INSERT INTO meli_cooldowns%'",
            );
            $until = hrtime(true) + 5_000_000_000;
            do {
                $overlap->execute($ids);
                $waiting = (int) $overlap->fetchColumn();
                if ($waiting === 2) {
                    break;
                }
                usleep(10_000);
            } while (hrtime(true) < $until);
            self::assertSame(2, $waiting, 'Both independent mutations must overlap behind the test row-lock barrier.');
            $pdo->commit();
            $returned = [];
            while (($child = array_shift($processes)) !== null) {
                $returned[] = trim((string) stream_get_contents($child['out']));
                $stderr = stream_get_contents($child['err']);
                fclose($child['out']);
                fclose($child['err']);
                self::assertSame(0, proc_close($child['process']), (string) $stderr);
                self::assertSame('', $stderr);
            }
            self::assertSame(2, (int) $pdo->query("SELECT consecutive_429 FROM meli_cooldowns WHERE cooldown_key='app:users'")->fetchColumn());
            self::assertSame($deadline, $pdo->query("SELECT blocked_until FROM meli_cooldowns WHERE cooldown_key='app:users'")->fetchColumn());
            self::assertSame([$deadline, $deadline], $returned);
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($processes as $child) {
                proc_terminate($child['process']);
                fclose($child['out']);
                fclose($child['err']);
                proc_close($child['process']);
            }
        }
    }

    public function testIndependentConnectionBlocksBeforeTransportUsingDurableDeadline(): void
    {
        $pdo = TestDatabase::reset();
        $retryAt = (new MeliCooldownRepository($pdo))->register429('app:users', 60);
        $other = Connection::fromConfig(TestDatabase::config());
        self::assertNotSame($pdo->query('SELECT CONNECTION_ID()')->fetchColumn(), $other->query('SELECT CONNECTION_ID()')->fetchColumn());
        $transport = new RateLimitQueueTransport([]);
        try {
            $this->client($other, $transport)->request('users.me', 'token');
            self::fail('A new connection must respect durable protection.');
        } catch (MeliRateLimitException $exception) {
            self::assertSame($retryAt->format('Y-m-d H:i:s.u'), $exception->retryAt->format('Y-m-d H:i:s.u'));
        }
        self::assertCount(0, $transport->requests);
    }

    public function testCooldownReadDatabaseFailureEscapesBeforeTransport(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec('DROP TABLE meli_cooldowns');
        $transport = new RateLimitQueueTransport([]);
        try {
            $this->client($pdo, $transport)->request('users.me', 'token');
            self::fail('Missing safety persistence must not allow dispatch.');
        } catch (PDOException $exception) {
            self::assertSame('42S02', $exception->getCode());
        }
        self::assertCount(0, $transport->requests);
    }

    public function testCooldownWriteDatabaseFailureDoesNotFallbackOrPermitAnotherRequest(): void
    {
        $pdo = TestDatabase::reset();
        $transport = new RateLimitQueueTransport(
            [new MeliTransportResponse(429, ['retry-after' => '20'], '{}')],
            fn () => $pdo->exec('DROP TABLE meli_cooldowns'),
        );
        $client = $this->client($pdo, $transport);
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $client->request('users.me', 'token');
                self::fail('Failed safety persistence must propagate, not fall back to an unrestricted request.');
            } catch (PDOException $exception) {
                self::assertSame('42S02', $exception->getCode());
            }
        }
        self::assertCount(1, $transport->requests, 'The initial 429 happened before the DB failure; no subsequent HTTP is allowed.');
    }

    public function testValidatedSuccessCleansExpiredCooldownAndResetsBackoff(): void
    {
        $pdo = TestDatabase::reset();
        $this->seedCooldown($pdo, (new DateTimeImmutable('-1 hour UTC'))->format('Y-m-d H:i:s.u'), 6);
        $transport = new RateLimitQueueTransport([new MeliTransportResponse(200, [], '{"id":1}')]);
        self::assertSame(200, $this->client($pdo, $transport)->request('users.me', 'token')->status);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM meli_cooldowns')->fetchColumn());
        $before = new DateTimeImmutable('now UTC');
        $retryAt = (new MeliCooldownRepository($pdo))->register429('app:users', null);
        self::assertSame(1, (int) $pdo->query('SELECT consecutive_429 FROM meli_cooldowns')->fetchColumn());
        self::assertGreaterThanOrEqual($before->modify('+15 seconds')->getTimestamp(), $retryAt->getTimestamp());
        self::assertLessThanOrEqual((new DateTimeImmutable('now UTC'))->modify('+17 seconds')->getTimestamp(), $retryAt->getTimestamp());
    }

    #[DataProvider('failedResponses')]
    public function testFailedResponseDoesNotCleanExpiredCooldown(int $status, string $body): void
    {
        $pdo = TestDatabase::reset();
        $deadline = (new DateTimeImmutable('-1 hour UTC'))->format('Y-m-d H:i:s.u');
        $this->seedCooldown($pdo, $deadline, 6);
        $transport = new RateLimitQueueTransport([new MeliTransportResponse($status, [], $body)]);
        $this->assertResponseOutcome($this->client($pdo, $transport), $status, $body);
        self::assertSame($deadline, $pdo->query('SELECT blocked_until FROM meli_cooldowns')->fetchColumn());
        self::assertSame(6, (int) $pdo->query('SELECT consecutive_429 FROM meli_cooldowns')->fetchColumn());
    }

    /** @return array<string,array{int,string}> */
    public static function failedResponses(): array
    {
        return ['denied 403' => [403, '{"error":"forbidden"}'], 'invalid JSON' => [200, '{']];
    }

    public function testFallbackStepsFollowIncrementedCounterAndSaturateSafely(): void
    {
        $pdo = TestDatabase::reset();
        $repo = new MeliCooldownRepository($pdo);
        foreach ([15, 30, 60, 120, 300, 900, 900] as $index => $seconds) {
            $pdo->exec("UPDATE meli_cooldowns SET blocked_until=UTC_TIMESTAMP(6)-INTERVAL 1 HOUR");
            $before = new DateTimeImmutable('now UTC');
            $retryAt = $repo->register429('app:users', null);
            self::assertSame($index + 1, (int) $pdo->query('SELECT consecutive_429 FROM meli_cooldowns')->fetchColumn());
            self::assertGreaterThanOrEqual($before->modify('+' . $seconds . ' seconds')->getTimestamp(), $retryAt->getTimestamp());
            self::assertLessThanOrEqual((new DateTimeImmutable('now UTC'))->modify('+' . ($seconds + 2) . ' seconds')->getTimestamp(), $retryAt->getTimestamp());
        }
        $pdo->exec('UPDATE meli_cooldowns SET consecutive_429=65535');
        $repo->register429('app:users', 1);
        self::assertSame(65535, (int) $pdo->query('SELECT consecutive_429 FROM meli_cooldowns')->fetchColumn());
    }

    private function assertResponseOutcome(MeliClient $client, int $status, string $body): void
    {
        try {
            $response = $client->request('users.me', 'token');
            self::assertSame(200, $status);
            self::assertSame(200, $response->status);
            self::assertSame(['id' => 1], $response->data);
        } catch (MeliApiException $exception) {
            self::assertSame(403, $status);
            self::assertSame(403, $exception->status);
            self::assertSame('forbidden', $exception->errorCode);
        } catch (RuntimeException $exception) {
            self::assertSame('{', $body);
            self::assertSame('Mercado Libre returned invalid JSON.', $exception->getMessage());
        }
    }

    private function seedCooldown(PDO $pdo, string $deadline, int $count = 0): void
    {
        $statement = $pdo->prepare('INSERT INTO meli_cooldowns (cooldown_key, blocked_until, consecutive_429) VALUES (?, ?, ?)');
        $statement->execute(['app:users', $deadline, $count]);
    }

    private function client(PDO $pdo, RateLimitQueueTransport $transport): MeliClient
    {
        return new MeliClient(
            $transport,
            new SystemSettingsRepository($pdo),
            $this->operations(),
            'https://api.mercadolibre.com',
            new ApiUsageRecorder($pdo),
            new MeliCooldownRepository($pdo),
        );
    }

    /** @return array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> */
    private function operations(): array
    {
        /** @var array<string,array{method:string,path:string,family:string,classification:string,official_doc_url:string,verified_at:string}> $operations */
        $operations = require dirname(__DIR__, 2) . '/config/meli_operations.php';
        return $operations;
    }
}

final class RateLimitQueueTransport implements MeliTransport
{
    /** @var list<MeliTransportResponse> */
    private array $responses;

    /** @var list<string> */
    public array $requests = [];

    /** @param list<MeliTransportResponse> $responses */
    public function __construct(array $responses, private readonly ?\Closure $onSend = null)
    {
        $this->responses = $responses;
    }

    /** @param array<string,string> $headers */
    public function send(string $method, string $url, array $headers, ?string $body): MeliTransportResponse
    {
        $this->requests[] = $method . ' ' . $url;
        if ($this->onSend !== null) {
            ($this->onSend)();
        }
        $response = array_shift($this->responses);
        if (!$response instanceof MeliTransportResponse) {
            throw new RuntimeException('No fake response queued.');
        }
        return $response;
    }
}
