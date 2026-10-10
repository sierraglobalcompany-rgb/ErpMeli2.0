<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

final class PlatformCompatibilityContractTest extends TestCase
{
    public function testComposerDeclaresSupportedPhpRangeAndRuntimeExtensions(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode(
            (string) file_get_contents($root . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertSame('>=8.3 <8.6', $composer['require']['php'] ?? null);
        self::assertArrayHasKey('ext-phar', $composer['require'] ?? []);
        self::assertArrayHasKey('ext-zlib', $composer['require'] ?? []);
    }
}
