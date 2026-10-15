<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Install\WebInstaller;
use App\Core\Security\Csrf;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class WebInstallerDatabaseDefaultsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        TestDatabase::resetSession();
        $this->root = sys_get_temp_dir() . '/erp-install-defaults-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/storage/private', 0700, true);
        mkdir($this->root . '/vendor');
        touch($this->root . '/vendor/autoload.php');
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

    public function testDatabaseStepPrefillsStandardHostAndPort(): void
    {
        $app = (new WebInstaller($this->root))->app();
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'http://localhost/install')
            ->withHeader('Origin', 'http://localhost')
            ->withParsedBody([
                'step' => 'server',
                'action' => 'continue',
                'csrf_token' => (new Csrf())->token(),
            ]);

        $response = $app->handle($request);
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('name="DB_HOST"', $html);
        self::assertStringContainsString('name="DB_HOST" type="text" value="localhost"', $html);
        self::assertStringContainsString('name="DB_PORT" type="text" value="3306"', $html);
    }
}
