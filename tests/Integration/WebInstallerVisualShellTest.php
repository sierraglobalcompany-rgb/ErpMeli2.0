<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Install\WebInstaller;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class WebInstallerVisualShellTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        TestDatabase::resetSession();
        $this->root = sys_get_temp_dir() . '/erp-install-visual-' . bin2hex(random_bytes(8));
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

    public function testInstallerUsesPolishedSetupShell(): void
    {
        $app = (new WebInstaller($this->root))->app();
        $response = $app->handle(
            (new ServerRequestFactory())->createServerRequest('GET', 'https://erp.example.test/install')
        );
        $html = (string) $response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('class="install-page"', $html);
        self::assertStringContainsString('class="install-shell"', $html);
        self::assertStringContainsString('class="install-brand"', $html);
        self::assertStringContainsString('class="install-card"', $html);
    }
}
