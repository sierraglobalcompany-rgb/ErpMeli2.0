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


    public function testOmittedNumericFieldsUseExplicitDefaults(): void
    {
        $pdo = TestDatabase::reset();
        $auth = new AuthService($pdo);
        $auth->bootstrapAdmin(
            'Settings Admin',
            'settings-admin',
            'settings-defaults@example.test',
            'test-settings-defaults-password',
        );
        self::assertTrue($auth->login('settings-defaults@example.test', 'test-settings-defaults-password'));

        $response = Bootstrap::create()->handle(
            (new ServerRequestFactory())
                ->createServerRequest('POST', '/settings/system')
                ->withParsedBody([
                    'csrf_token' => (new Csrf())->token(),
                    'automation_enabled' => '1',
                    'debug_enabled' => '1',
                ])
        );
        $settings = (new SystemSettingsRepository($pdo))->get();

        self::assertSame(303, $response->getStatusCode());
        self::assertTrue($settings->automationEnabled);
        self::assertTrue($settings->debugEnabled);
        self::assertSame(7, $settings->debugRetentionDays);
        self::assertSame(100, $settings->debugMaxMb);
        self::assertFalse($settings->meliWritesEnabled);
    }

    public static function invalidSettings(): iterable
    {
        yield 'unsupported retention' => [['debug_retention_days' => '5']];
        yield 'debug cap below minimum' => [['debug_max_mb' => '9']];
        yield 'nonnumeric retention' => [['debug_retention_days' => 'abc']];
        yield 'zero retention' => [['debug_retention_days' => '0']];
        yield 'blank retention' => [['debug_retention_days' => '']];
        yield 'decimal retention' => [['debug_retention_days' => '3.5']];
        yield 'scientific retention' => [['debug_retention_days' => '1e3']];
        yield 'null retention' => [['debug_retention_days' => null]];
        yield 'array retention' => [['debug_retention_days' => ['7']]];
        yield 'boolean retention' => [['debug_retention_days' => true]];
        yield 'nonnumeric debug cap' => [['debug_max_mb' => 'abc']];
        yield 'zero debug cap' => [['debug_max_mb' => '0']];
        yield 'blank debug cap' => [['debug_max_mb' => '']];
        yield 'decimal debug cap' => [['debug_max_mb' => '10.5']];
        yield 'scientific debug cap' => [['debug_max_mb' => '1e3']];
        yield 'null debug cap' => [['debug_max_mb' => null]];
        yield 'array debug cap' => [['debug_max_mb' => ['100']]];
        yield 'boolean debug cap' => [['debug_max_mb' => true]];
    }
}
