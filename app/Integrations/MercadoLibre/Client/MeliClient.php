<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use App\Integrations\MercadoLibre\Transport\MeliTransport;
use App\Integrations\MercadoLibre\Transport\MeliTransportResponse;
use App\Modules\Settings\SystemSettingsRepository;
use InvalidArgumentException;
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
    ) {
    }

    /** @param array<string,string> $headers */
    public function request(
        string $operationKey,
        ?string $accessToken = null,
        ?string $body = null,
        array $headers = [],
    ): MeliTransportResponse {
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

        return $this->transport->send(
            $operation['method'],
            rtrim($this->baseUrl, '/') . $operation['path'],
            $headers,
            $body,
        );
    }
}
