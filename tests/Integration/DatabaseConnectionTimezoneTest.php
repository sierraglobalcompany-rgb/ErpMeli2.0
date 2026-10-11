<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

final class DatabaseConnectionTimezoneTest extends TestCase
{
    public function testConnectionForcesMariaDbSessionTimezoneToUtc(): void
    {
        $pdo = TestDatabase::reset();

        self::assertSame(
            '+00:00',
            (string) $pdo->query('SELECT @@session.time_zone')->fetchColumn(),
        );
    }
}
