<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Transport;

interface MeliTransport
{
    /** @param array<string,string> $headers */
    public function send(
        string $method,
        string $url,
        array $headers,
        ?string $body,
    ): MeliTransportResponse;
}
