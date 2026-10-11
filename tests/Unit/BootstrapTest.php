<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Bootstrap;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

final class BootstrapTest extends TestCase
{
    public function testCreatesSlimApplication(): void
    {
        $app = Bootstrap::create();
        self::assertInstanceOf(\Slim\App::class, $app);
    }

    public function testHealthRouteReturnsMinimalJson(): void
    {
        $app = Bootstrap::create();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/health');
        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"ok":true}', (string) $response->getBody());
    }

    public function testStoragePathIsNotExposedAsApplicationRoute(): void
    {
        $app = Bootstrap::create();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/storage/debug/example.jsonl');
        $previousErrorLog = ini_get('error_log');
        $temporaryErrorLog = tempnam(sys_get_temp_dir(), 'erp2-bootstrap-');
        self::assertIsString($temporaryErrorLog);

        try {
            ini_set('error_log', $temporaryErrorLog);
            $response = $app->handle($request);
        } finally {
            ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
            @unlink($temporaryErrorLog);
        }

        self::assertSame(404, $response->getStatusCode());
    }
}
