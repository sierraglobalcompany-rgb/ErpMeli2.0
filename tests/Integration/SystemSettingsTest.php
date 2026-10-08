<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Settings\SystemSettingsRepository;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class SystemSettingsTest extends TestCase
{
    public function testFreshDefaultsAreSafe(): void
    {
        $pdo = TestDatabase::reset();
        $settings = (new SystemSettingsRepository($pdo))->get();

        self::assertFalse($settings->automationEnabled);
        self::assertFalse($settings->meliWritesEnabled);
        self::assertFalse($settings->debugEnabled);
        self::assertSame(7, $settings->debugRetentionDays);
        self::assertSame(100, $settings->debugMaxMb);
    }

    public function testCorruptWriteFlagFailsClosed(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec('UPDATE system_settings SET meli_writes_enabled = 2 WHERE id = 1');

        self::assertFalse((new SystemSettingsRepository($pdo))->get()->meliWritesEnabled);
    }

    public function testUpdatePersistsOnlyTypedValues(): void
    {
        $pdo = TestDatabase::reset();
        $pdo->exec("INSERT INTO companies(id,name,slug) VALUES (1,'Main','main')");
        $pdo->exec(
            "INSERT INTO users(id,email,password_hash,status)
             VALUES (1,'admin@example.test','unused','active')"
        );

        $repo = new SystemSettingsRepository($pdo);
        $repo->updateOperationalToggles(true, false, true, 14, 200, 1);

        $settings = $repo->get();
        self::assertTrue($settings->automationEnabled);
        self::assertFalse($settings->meliWritesEnabled);
        self::assertTrue($settings->debugEnabled);
        self::assertSame(14, $settings->debugRetentionDays);
        self::assertSame(200, $settings->debugMaxMb);
    }
}
