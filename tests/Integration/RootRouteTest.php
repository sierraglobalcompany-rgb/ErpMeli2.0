<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class RootRouteTest extends TestCase
{
    public function testGuestRootRedirectsToLoginInsteadOfForbidden(): void
    {
        TestDatabase::resetSession();
        TestDatabase::reset();

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/')
        );

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/login', $response->getHeaderLine('Location'));
    }

    public function testSharedStylesheetIsServedWithoutAuthentication(): void
    {
        TestDatabase::resetSession();
        TestDatabase::reset();

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest('GET', '/assets/app.css')
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertStringStartsWith('text/css', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('--accent:', (string) $response->getBody());
    }
}
