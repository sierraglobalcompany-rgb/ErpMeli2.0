<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use App\Core\Logging\DebugRecorder;
use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Modules\Settings\SystemSettingsRepository;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use Throwable;

final class MeliClient
{
    private ?int $lastDispatchAtNs = null;

    /**
     * @param array<string,array{
     *     method:string,
     *     path:string,
     *     family:string,
     *     classification:string,
     *     official_doc_url:string,
     *     verified_at:string
     * }> $operations
     */
    public function __construct(
        private readonly MeliTransport $transport,
        private readonly SystemSettingsRepository $settings,
        private readonly array $operations,
        private readonly string $baseUrl,
        private readonly ?ApiUsageRecorder $usageRecorder = null,
        private readonly ?MeliCooldownRepository $cooldowns = null,
        private readonly int $minRequestIntervalMs = 2000,
        private readonly ?DebugRecorder $debugRecorder = null,
    ) {
        if ($this->minRequestIntervalMs < 0) {
            throw new InvalidArgumentException('Mercado Libre minimum request interval cannot be negative.');
        }
    }

    /**
     * @param array<string,string> $headers
     * @param array<string,string> $pathParams
     * @param array<string,string|int> $queryParams
     */
    public function request(
        string $operationKey,
        ?string $accessToken = null,
        ?string $body = null,
        array $headers = [],
        string $scopeKey = 'app',
        int $resourceCount = 1,
        array $pathParams = [],
        array $queryParams = [],
    ): MeliClientResponse {
        $operation = $this->operations[$operationKey] ?? null;
        if ($operation === null) {
            throw new InvalidArgumentException('Unknown Mercado Libre operation: ' . $operationKey);
        }

        if ($operation['classification'] === 'WRITE' && !$this->settings->get()->meliWritesEnabled) {
            throw new RuntimeException('Remote Mercado Libre writes are disabled.');
        }

        $path = $this->resolvePath($operation['path'], $pathParams);
        $path = $this->appendQuery($path, $queryParams);
        $cooldownKey = 'app:' . $operation['family'];
        $activeCooldown = $this->cooldowns?->activeUntil($cooldownKey);
        if ($activeCooldown instanceof DateTimeImmutable) {
            throw new MeliRateLimitException(
                $activeCooldown,
                null,
                'Mercado Libre operation is cooling down.',
            );
        }

        if ($accessToken !== null) {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        // Pace only requests that are actually about to cross the transport boundary.
        // This is a conservative ERP2 default, not an official Mercado Libre quota.
        $this->pacePhysicalRequest();

        $startedAt = hrtime(true);
        try {
            $response = $this->transport->send(
                $operation['method'],
                rtrim($this->baseUrl, '/') . $path,
                $headers,
                $body,
            );
        } catch (Throwable $exception) {
            $durationMs = max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
            $this->recordDebugHttp($operationKey, null, 'transport_error', $durationMs, null);
            throw $exception;
        }

        $durationMs = max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
        $requestId = $this->header($response->headers, 'x-request-id');

        if ($response->status === 429) {
            $this->recordUsage($scopeKey, $operationKey, $resourceCount, 'rate_limited', $durationMs);
            $this->recordDebugHttp($operationKey, 429, 'rate_limited', $durationMs, $requestId);
            $retryAfter = $this->retryAfterSeconds($this->header($response->headers, 'retry-after'));
            $retryAt = $this->cooldowns?->register429($cooldownKey, $retryAfter)
                ?? $this->fallbackRetryAt($retryAfter);

            throw new MeliRateLimitException($retryAt, $requestId);
        }

        $this->cooldowns?->clear($cooldownKey);

        if ($response->status < 200 || $response->status >= 300) {
            $outcome = $response->status >= 500 ? 'server_error' : 'client_error';
            $this->recordUsage($scopeKey, $operationKey, $resourceCount, $outcome, $durationMs);
            $this->recordDebugHttp($operationKey, $response->status, $outcome, $durationMs, $requestId);

            throw new MeliApiException(
                $response->status,
                $requestId,
                $this->safeRemoteErrorCode($response->body),
            );
        }

        try {
            if ($response->body === '') {
                $data = [];
            } else {
                $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($decoded)) {
                    throw new JsonException('Expected JSON object.');
                }
                /** @var array<string,mixed> $data */
                $data = $decoded;
            }
        } catch (JsonException $exception) {
            $this->recordUsage($scopeKey, $operationKey, $resourceCount, 'server_error', $durationMs);
            $this->recordDebugHttp($operationKey, $response->status, 'server_error', $durationMs, $requestId);
            throw new RuntimeException('Mercado Libre returned invalid JSON.', 0, $exception);
        }

        $this->recordUsage($scopeKey, $operationKey, $resourceCount, 'success', $durationMs);
        $this->recordDebugHttp($operationKey, $response->status, 'success', $durationMs, $requestId);

        return new MeliClientResponse($response->status, $data, $requestId);
    }

    /** @param array<string,string> $pathParams */
    private function resolvePath(string $path, array $pathParams): string
    {
        if ($path === '/orders/{order_id}') {
            $orderId = $pathParams['order_id'] ?? null;
            if (!is_string($orderId) || preg_match('/^[0-9]{1,32}$/D', $orderId) !== 1) {
                throw new InvalidArgumentException('Invalid Mercado Libre order_id path parameter.');
            }
            if (count($pathParams) !== 1) {
                throw new InvalidArgumentException('Unexpected Mercado Libre path parameters.');
            }

            return '/orders/' . $orderId;
        }

        if ($pathParams !== []) {
            throw new InvalidArgumentException('Unexpected Mercado Libre path parameters.');
        }

        return $path;
    }

    /** @param array<string,string|int> $queryParams */
    private function appendQuery(string $path, array $queryParams): string
    {
        if ($queryParams === []) {
            return $path;
        }

        foreach ($queryParams as $key => $value) {
            if ($key === '' || (is_string($value) && $value === '')) {
                throw new InvalidArgumentException('Invalid Mercado Libre query parameter.');
            }
        }

        return $path . '?' . http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
    }

    private function pacePhysicalRequest(): void
    {
        if ($this->minRequestIntervalMs === 0) {
            $this->lastDispatchAtNs = hrtime(true);
            return;
        }

        $nowNs = hrtime(true);
        if ($this->lastDispatchAtNs !== null) {
            $minimumGapNs = $this->minRequestIntervalMs * 1_000_000;
            $remainingNs = $minimumGapNs - ($nowNs - $this->lastDispatchAtNs);

            if ($remainingNs > 0) {
                usleep((int) ceil($remainingNs / 1_000));
            }
        }

        // Set immediately before dispatch. A transport exception still counts as a physical attempt.
        $this->lastDispatchAtNs = hrtime(true);
    }

    /** @param array<string,string> $headers */
    private function header(array $headers, string $wanted): ?string
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === $wanted) {
                return $value;
            }
        }

        return null;
    }

    private function retryAfterSeconds(?string $header): ?int
    {
        if ($header === null) {
            return null;
        }

        $trimmed = trim($header);
        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }

    private function fallbackRetryAt(?int $retryAfterSeconds): DateTimeImmutable
    {
        $base = $retryAfterSeconds ?? 15;
        return new DateTimeImmutable(
            '+' . ($base + random_int(0, 2)) . ' seconds',
            new DateTimeZone('UTC'),
        );
    }

    private function safeRemoteErrorCode(string $body): ?string
    {
        if ($body === '') {
            return null;
        }

        try {
            $decoded = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($decoded)) {
            return null;
        }

        $error = $decoded['error'] ?? null;
        if (!is_string($error) || preg_match('/^[A-Za-z0-9._-]{1,80}$/D', $error) !== 1) {
            return null;
        }

        return $error;
    }

    private function recordDebugHttp(
        string $operationKey,
        ?int $httpStatus,
        string $outcome,
        int $durationMs,
        ?string $requestId,
    ): void {
        if ($this->debugRecorder === null) {
            return;
        }

        $fields = [
            'operation' => $operationKey,
            'outcome' => $outcome,
            'duration_ms' => $durationMs,
        ];
        if ($httpStatus !== null) {
            $fields['http_status'] = $httpStatus;
        }
        if ($requestId !== null && $requestId !== '') {
            $fields['request_id'] = $requestId;
        }

        try {
            $this->debugRecorder->record('meli.http', $fields);
        } catch (Throwable) {
            // Debug observability must never change Mercado Libre HTTP semantics.
        }
    }

    private function recordUsage(
        string $scopeKey,
        string $operationKey,
        int $resourceCount,
        string $outcome,
        int $durationMs,
    ): void {
        if ($this->usageRecorder === null) {
            return;
        }

        try {
            $this->usageRecorder->record(
                $scopeKey,
                $operationKey,
                $resourceCount,
                $outcome,
                $durationMs,
            );
        } catch (Throwable) {
            // Aggregate usage metrics are best-effort observability, never business truth.
        }
    }
}
