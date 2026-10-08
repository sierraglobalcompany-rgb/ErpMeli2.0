<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use RuntimeException;

final class MeliApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $requestId,
        public readonly ?string $errorCode = null,
    ) {
        parent::__construct('Mercado Libre request failed with HTTP ' . $status . '.');
    }
}
