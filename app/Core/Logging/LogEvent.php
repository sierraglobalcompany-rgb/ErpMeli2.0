<?php

declare(strict_types=1);

namespace App\Core\Logging;

use DateTimeImmutable;

final readonly class LogEvent
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public string $level,
        public string $event,
        public array $fields,
        public DateTimeImmutable $at,
    ) {
    }
}
