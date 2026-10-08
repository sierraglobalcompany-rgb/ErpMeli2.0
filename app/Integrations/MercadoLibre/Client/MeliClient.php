<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Modules\Settings\SystemSettingsRepository;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class MeliClient
{
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
    ) {
    }

    /** @param array<string,string> $headers */
    public function request(
        string $operationKey,
        ?string $accessToken = null,
        ?string $body = null,
        array $headers = [],
        string $scopeKey = 'app',
        int $resourceCount = 1,
    ): MeliClientResponse {
        $operation = $this->operations[$operationKey] ?? null;
        if ($operation === null) {
            throw new InvalidArgumentException('Unknown Mercado Libre operation: ' . $operationKey);
        }

        if ($operation['classification'] === 'WRITE' && !$this->settings->get()->meliWritesEnabled) {
            throw new RuntimeException('Remote Mercado Libre writes are disabled.');
        }

        if ($accessToken !== null) {
            $headers['Authorization'] = 'Bearer ' . $accessToken;
        }

        $startedAt = hrtime(true);
        $response = $this->transport->send(
            $operation['method'],
            rtrim($this->baseUrl, '/') . $operation['path'],
            $headers,
            $body,
        );
        $durationMs = max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
        $requestId = $this->header($response->headers, 'x-request-id');

        if ($response->status < 200 || $response->status >= 300) {
            $outcome = match (true) {
                $response->status === 429 => 'rate_limited',
                $response->status >= 500 => 'server_error',
                default => 'client_error',
            };
            $this->recordUsage($scopeKey, $operationKey, $resourceCount, $outcome, $durationMs);

            throw new MeliApiException($response->status, $requestId);
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
            throw new RuntimeException('Mercado Libre returned invalid JSON.', 0, $exception);
        }

        $this->recordUsage($scopeKey, $operationKey, $resourceCount, 'success', $durationMs);

        return new MeliClientResponse($response->status, $data, $requestId);
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

    private function recordUsage(
        string $scopeKey,
        string $operationKey,
        int $resourceCount,
        string $outcome,
        int $durationMs,
    ): void {
        $this->usageRecorder?->record(
            $scopeKey,
            $operationKey,
            $resourceCount,
            $outcome,
            $durationMs,
        );
    }
}
