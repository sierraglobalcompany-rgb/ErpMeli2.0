<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Client;

use DateTimeImmutable;
use RuntimeException;

final class MeliRateLimitException extends RuntimeException
{
    public readonly int $status;

    public function __construct(
        public readonly DateTimeImmutable $retryAt,
        public readonly ?string $requestId,
        string $message = 'Mercado Libre request is rate limited.',
    ) {
        $this->status = 429;
        parent::__construct($message);
    }
}
