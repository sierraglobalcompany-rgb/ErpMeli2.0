<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Bootstrap;
use App\Core\Auth\AuthService;
use App\Core\Security\Csrf;
use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tests\Support\TestDatabase;

final class SystemSettingsInvalidInputHttpRouteTest extends TestCase
{
    #[DataProvider('invalidSettings')]
    public function testInvalidOperationalSettingsReturn422WithoutMutation(array $override): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin(
            'Settings Admin',
            'settings-admin',
            'settings-invalid@example.test',
            'test-settings-invalid-password',
        );
        self::assertTrue($auth->login('settings-invalid@example.test', 'test-settings-invalid-password'));

        $csrf = new Csrf();
        $body = array_merge([
            'csrf_token' => $csrf->token(),
            'automation_enabled' => '1',
            'debug_enabled' => '1',
            'debug_retention_days' => '14',
            'debug_max_mb' => '200',
        ], $override);

        $before = (new SystemSettingsRepository($pdo))->get();
        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())
                ->createServerRequest('POST', '/settings/system')
                ->withParsedBody($body)
        );
        $after = (new SystemSettingsRepository($pdo))->get();

        self::assertSame(422, $response->getStatusCode());
        self::assertSame($before->automationEnabled, $after->automationEnabled);
        self::assertSame($before->debugEnabled, $after->debugEnabled);
        self::assertSame($before->debugRetentionDays, $after->debugRetentionDays);
        self::assertSame($before->debugMaxMb, $after->debugMaxMb);
        self::assertFalse($after->meliWritesEnabled);
    }

    public static function invalidSettings(): iterable
    {
        yield 'unsupported retention' => [['debug_retention_days' => '5']];
        yield 'debug cap below minimum' => [['debug_max_mb' => '9']];
        yield 'nonnumeric retention' => [['debug_retention_days' => 'abc']];
    }
}
