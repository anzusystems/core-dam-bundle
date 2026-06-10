<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use AnzuSystems\CoreDamBundle\App;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class DateTimeHelper
{
    private const string DB_DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Parse "{h}h{m}m" string (e.g. '1h', '30m', '2h30m') to DateInterval; null if invalid/zero.
     */
    public static function parseDurationToInterval(string $duration): ?DateInterval
    {
        if (App::EMPTY_STRING === $duration || 1 !== preg_match('/^(?:(\d+)h)?(?:(\d+)m)?$/', $duration, $matches)) {
            return null;
        }

        $hours = (int) ($matches[1] ?? 0);
        $minutes = (int) ($matches[2] ?? 0);

        if (App::ZERO === $hours && App::ZERO === $minutes) {
            return null;
        }

        return new DateInterval(sprintf('PT%dH%dM', $hours, $minutes));
    }

    public static function datetimeOrNull(
        ?string $dateTimeString,
        string $format = self::DB_DATE_TIME_FORMAT,
        ?DateTimeZone $timezone = null,
    ): ?DateTimeImmutable {
        if (empty($dateTimeString)) {
            return null;
        }

        $dateTimeImmutable = DateTimeImmutable::createFromFormat($format, $dateTimeString, $timezone);
        if ($dateTimeImmutable instanceof DateTimeImmutable) {
            return $dateTimeImmutable;
        }

        return null;
    }
}
