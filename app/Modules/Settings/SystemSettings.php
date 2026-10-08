<?php

declare(strict_types=1);

namespace App\Modules\Settings;

final readonly class SystemSettings
{
    public function __construct(
        public bool $automationEnabled,
        public bool $meliWritesEnabled,
        public bool $debugEnabled,
        public int $debugRetentionDays,
        public int $debugMaxMb,
    ) {
    }
}
