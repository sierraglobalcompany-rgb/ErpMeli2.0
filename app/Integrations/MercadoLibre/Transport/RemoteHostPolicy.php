<?php

declare(strict_types=1);

namespace App\Integrations\MercadoLibre\Transport;

use RuntimeException;

final class RemoteHostPolicy
{
    public function assertAllowed(string $url, string $appEnv): void
    {
        if (!in_array($appEnv, ['local', 'test'], true)) {
            return;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($host === 'api.mercadolibre.com') {
            throw new RuntimeException('Real Mercado Libre HTTP is blocked in local/test.');
        }
    }
}
