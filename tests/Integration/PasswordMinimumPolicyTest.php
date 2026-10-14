<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Install\WebInstaller;
use App\Core\Security\Csrf;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class PasswordMinimumPolicyTest extends TestCase
{
    private ?string $installerRoot = null;

    protected function tearDown(): void
    {
        if ($this->installerRoot !== null) {
            @unlink($this->installerRoot . '/vendor/autoload.php');
            @rmdir($this->installerRoot . '/vendor');
            foreach (glob($this->installerRoot . '/storage/private/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->installerRoot . '/storage/private');
            @rmdir($this->installerRoot . '/storage');
            @rmdir($this->installerRoot);
        }
        TestDatabase::resetSession();
    }

    public function testManagedUsersAcceptSixCharactersAndRejectFive(): void
    {
        $pdo = TestDatabase::reset();
        (new AuthService($pdo))->bootstrapAdmin(
            'Main Company',
            'main-company',
            'owner@example.test',
            'owner-password-123',
            'Owner',
            'owner',
        );
        self::assertTrue((new AuthService($pdo))->login('owner', 'owner-password-123'));
        $csrf = (new Csrf())->token();

        self::assertSame(303, $this->request('POST', '/system/users', [
            'username' => 'six-pass',
            'display_name' => 'Six Pass',
            'email' => 'six@example.test',
            'password' => 'abc123',
            'csrf_token' => $csrf,
        ])->getStatusCode());
        self::assertSame(422, $this->request('POST', '/system/users', [
            'username' => 'five-pass',
            'display_name' => 'Five Pass',
            'email' => 'five@example.test',
            'password' => 'abc12',
            'csrf_token' => $csrf,
        ])->getStatusCode());
    }

    public function testInstallerAcceptsSixCharactersAndRejectsFive(): void
    {
        TestDatabase::resetSession();
        $this->installerRoot = sys_get_temp_dir() . '/erp-password-policy-' . bin2hex(random_bytes(8));
        mkdir($this->installerRoot . '/storage/private', 0700, true);
        mkdir($this->installerRoot . '/vendor');
        touch($this->installerRoot . '/vendor/autoload.php');
        $app = (new WebInstaller($this->installerRoot))->app();
        $factory = new ServerRequestFactory();

        $_SESSION['installer'] = ['stage' => 4, 'data' => []];
        $six = $app->handle(
            $factory->createServerRequest('POST', 'http://localhost/install')
                ->withHeader('Origin', 'http://localhost')
                ->withParsedBody([
                    'step' => 'administrator',
                    'display_name' => 'Local Admin',
                    'username' => 'local-admin',
                    'email' => 'local@example.test',
                    'password' => 'abc123',
                    'confirm_password' => 'abc123',
                    'csrf_token' => (new Csrf())->token(),
                ])
        );
        self::assertSame(200, $six->getStatusCode());
        self::assertSame(5, $_SESSION['installer']['stage']);

        $_SESSION['installer'] = ['stage' => 4, 'data' => []];
        $five = $app->handle(
            $factory->createServerRequest('POST', 'http://localhost/install')
                ->withHeader('Origin', 'http://localhost')
                ->withParsedBody([
                    'step' => 'administrator',
                    'display_name' => 'Local Admin',
                    'username' => 'local-admin',
                    'email' => 'local@example.test',
                    'password' => 'abc12',
                    'confirm_password' => 'abc12',
                    'csrf_token' => (new Csrf())->token(),
                ])
        );
        self::assertSame(422, $five->getStatusCode());
        self::assertSame(4, $_SESSION['installer']['stage']);
    }

    /** @param array<string,mixed> $body */
    private function request(string $method, string $path, array $body = []): ResponseInterface
    {
        return Bootstrap::create()->handle(
            (new ServerRequestFactory())->createServerRequest($method, $path)->withParsedBody($body)
        );
    }
}
