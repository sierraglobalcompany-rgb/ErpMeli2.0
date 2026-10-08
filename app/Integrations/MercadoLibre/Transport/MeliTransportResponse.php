<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Transport;

final readonly class MeliTransportResponse
{
    /** @param array<string,string> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
    }
}
