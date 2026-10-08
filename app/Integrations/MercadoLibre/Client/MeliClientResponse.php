<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

final readonly class MeliClientResponse
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public int $status,
        public array $data,
        public ?string $requestId,
    ) {
    }
}
