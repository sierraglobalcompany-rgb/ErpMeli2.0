<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use InvalidArgumentException;
use PDO;

final class ApiUsageRecorder
{
    private const OUTCOMES = ['success', 'client_error', 'server_error', 'rate_limited'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(
        string $scopeKey,
        string $operationKey,
        int $resourceCount,
        string $outcome,
        int $durationMs,
    ): void {
        if (!in_array($outcome, self::OUTCOMES, true)) {
            throw new InvalidArgumentException('Unsupported Mercado Libre usage outcome.');
        }
        if ($resourceCount < 0 || $durationMs < 0) {
            throw new InvalidArgumentException('Mercado Libre usage counters cannot be negative.');
        }

        $successes = $outcome === 'success' ? 1 : 0;
        $clientErrors = $outcome === 'client_error' ? 1 : 0;
        $serverErrors = $outcome === 'server_error' ? 1 : 0;
        $rateLimited = $outcome === 'rate_limited' ? 1 : 0;

        $statement = $this->pdo->prepare(
            'INSERT INTO api_usage_daily '
            . '(usage_date, scope_key, operation_key, requests, resources, successes, client_errors, '
            . 'server_errors, rate_limited, duration_ms_sum, duration_ms_max) '
            . 'VALUES (UTC_DATE(), :scope_key, :operation_key, 1, :resources, :successes, :client_errors, '
            . ':server_errors, :rate_limited, :duration_ms, :duration_ms_max) '
            . 'ON DUPLICATE KEY UPDATE '
            . 'requests = requests + 1, '
            . 'resources = resources + VALUES(resources), '
            . 'successes = successes + VALUES(successes), '
            . 'client_errors = client_errors + VALUES(client_errors), '
            . 'server_errors = server_errors + VALUES(server_errors), '
            . 'rate_limited = rate_limited + VALUES(rate_limited), '
            . 'duration_ms_sum = duration_ms_sum + VALUES(duration_ms_sum), '
            . 'duration_ms_max = GREATEST(duration_ms_max, VALUES(duration_ms_max))'
        );
        $statement->execute([
            'scope_key' => $scopeKey,
            'operation_key' => $operationKey,
            'resources' => $resourceCount,
            'successes' => $successes,
            'client_errors' => $clientErrors,
            'server_errors' => $serverErrors,
            'rate_limited' => $rateLimited,
            'duration_ms' => $durationMs,
            'duration_ms_max' => $durationMs,
        ]);
    }
}
