<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Install\WebInstaller;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class WebInstallerApplicationUrlDefaultTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        TestDatabase::resetSession();
        $this->root = sys_get_temp_dir() . '/erp-install-app-url-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/storage/private', 0700, true);
        mkdir($this->root . '/vendor');
        touch($this->root . '/vendor/autoload.php');
        $_SESSION['installer'] = ['stage' => 2, 'data' => []];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/storage/private/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->root . '/storage/private');
        rmdir($this->root . '/storage');
        unlink($this->root . '/vendor/autoload.php');
        rmdir($this->root . '/vendor');
        rmdir($this->root);
        $_SESSION = [];
    }

    public function testApplicationStepPrefillsUrlFromCurrentHttpsRequest(): void
    {
        $app = (new WebInstaller($this->root))->app();
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://erp.example.test:8443/install');

        $response = $app->handle($request);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            'name="APP_URL" type="text" value="https://erp.example.test:8443"',
            $html
        );
    }

    public function testStoredApplicationUrlTakesPrecedenceOverDetectedRequestUrl(): void
    {
        $_SESSION['installer']['data']['APP_URL'] = 'https://saved.example.test';
        $app = (new WebInstaller($this->root))->app();
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://erp.example.test/install');

        $response = $app->handle($request);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString(
            'name="APP_URL" type="text" value="https://saved.example.test"',
            $html
        );
    }
}
