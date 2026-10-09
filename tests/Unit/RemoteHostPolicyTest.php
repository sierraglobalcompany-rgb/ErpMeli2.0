<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\MercadoLibre\Transport\RemoteHostPolicy;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RemoteHostPolicyTest extends TestCase
{
    public function testBlocksRealMercadoLibreHostInTest(): void
    {
        $this->expectException(RuntimeException::class);
        (new RemoteHostPolicy())->assertAllowed('https://api.mercadolibre.com/orders/1', 'test');
    }

    public function testBlocksRealMercadoLibreHostInLocal(): void
    {
        $this->expectException(RuntimeException::class);
        (new RemoteHostPolicy())->assertAllowed('https://api.mercadolibre.com/orders/1', 'local');
    }

    public function testBlocksRealMercadoLibreHostForUnknownEnvironment(): void
    {
        $this->expectException(RuntimeException::class);
        (new RemoteHostPolicy())->assertAllowed('https://api.mercadolibre.com/orders/1', 'prod');
    }

    public function testDoesNotBlockProductionByItself(): void
    {
        (new RemoteHostPolicy())->assertAllowed('https://api.mercadolibre.com/orders/1', 'production');
        self::assertTrue(true);
    }
}
