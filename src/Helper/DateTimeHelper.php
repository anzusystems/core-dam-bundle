<?php

declare(strict_types=1);

namespace AnzuSystems\CoreDamBundle\Helper;

use DateTimeImmutable;
use DateTimeZone;

final class DateTimeHelper
{
    private const string DB_DATE_TIME_FORMAT = 'Y-m-d H:i:s';

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
