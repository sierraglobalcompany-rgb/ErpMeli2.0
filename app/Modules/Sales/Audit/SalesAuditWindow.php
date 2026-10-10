<?php

declare(strict_types=1);

namespace App\Modules\Sales\Audit;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class SalesAuditWindow
{
    private const SUPPORTED_SITE_ID = 'MCO';
    private const BUSINESS_TIMEZONE = 'America/Bogota';

    private function __construct(
        public readonly string $periodKey,
        public readonly DateTimeImmutable $canonicalStartUtc,
        public readonly DateTimeImmutable $canonicalEndUtc,
        public readonly DateTimeImmutable $remoteFromUtc,
        public readonly DateTimeImmutable $remoteToUtc,
    ) {
    }

    public static function forSitePeriod(string $siteId, string $periodKey): self
    {
        if ($siteId !== self::SUPPORTED_SITE_ID) {
            throw new InvalidArgumentException('Unsupported Mercado Libre site for historical sales audit.');
        }

        if (preg_match('/^[0-9]{4}-[0-9]{2}-01$/D', $periodKey) !== 1) {
            throw new InvalidArgumentException('Invalid sales audit period key.');
        }

        $businessTimezone = new DateTimeZone(self::BUSINESS_TIMEZONE);
        $periodStartLocal = DateTimeImmutable::createFromFormat('!Y-m-d', $periodKey, $businessTimezone);
        if (!$periodStartLocal instanceof DateTimeImmutable || $periodStartLocal->format('Y-m-d') !== $periodKey) {
            throw new InvalidArgumentException('Invalid sales audit period key.');
        }

        $periodEndLocal = $periodStartLocal->modify('+1 month');
        $utc = new DateTimeZone('UTC');
        $canonicalStartUtc = $periodStartLocal->setTimezone($utc);
        $canonicalEndUtc = $periodEndLocal->setTimezone($utc);

        return new self(
            $periodKey,
            $canonicalStartUtc,
            $canonicalEndUtc,
            $canonicalStartUtc->modify('-1 hour'),
            $canonicalEndUtc->modify('+1 hour'),
        );
    }

    public function isClosedAt(DateTimeImmutable $referenceNow): bool
    {
        return $this->periodKey <= self::lastClosedPeriodKey($referenceNow);
    }

    public static function lastClosedPeriodKey(DateTimeImmutable $referenceNow): string
    {
        $businessTimezone = new DateTimeZone(self::BUSINESS_TIMEZONE);
        $businessMonthStart = $referenceNow
            ->setTimezone($businessTimezone)
            ->modify('first day of this month')
            ->setTime(0, 0);

        return $businessMonthStart->modify('-1 month')->format('Y-m') . '-01';
    }
}
