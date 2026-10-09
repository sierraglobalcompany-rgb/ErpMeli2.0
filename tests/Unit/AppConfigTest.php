<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config\AppConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AppConfigTest extends TestCase
{
    public function testUnknownEnvironmentIsRejectedFailClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported APP_ENV.');

        AppConfig::fromEnvironment([
            'APP_ENV' => 'prod',
            'APP_URL' => 'https://erpmeli.bodegadigitalmedellin.com',
        ]);
    }
}
