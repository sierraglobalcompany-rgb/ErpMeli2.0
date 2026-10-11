<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use PDOException;

final class MeliResponseCleanupException extends PDOException
{
    public function __construct(
        public readonly MeliClientResponse $response,
        PDOException $previous,
    ) {
        parent::__construct('Mercado Libre response cleanup failed after a successful response.', 0, $previous);
    }
}
