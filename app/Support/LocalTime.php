<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * Forms and tables show wall-clock time in the content time zone (WIB by default);
 * the database stores UTC. This is the only place that converts between the two.
 */
final class LocalTime
{
    public const INPUT_FORMAT = 'Y-m-d\TH:i';

    public static function zone(): DateTimeZone
    {
        return new DateTimeZone(config('content.timezone'));
    }

    public static function abbreviation(): string
    {
        return CarbonImmutable::now(self::zone())->format('T');
    }

    /** "2026-12-01T19:00" (local) to a UTC instant for storing. */
    public static function fromInput(?string $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('!'.self::INPUT_FORMAT, $value, self::zone())
            ->setTimezone(config('app.timezone'));
    }

    /** A stored instant to the "2026-12-01T19:00" string form fields use. */
    public static function toInput(?CarbonInterface $date): ?string
    {
        return $date?->copy()->setTimezone(self::zone())->format(self::INPUT_FORMAT);
    }

    /** "Tue, 1 Dec 2026 · 19:00" */
    public static function label(?CarbonInterface $date): ?string
    {
        if ($date === null) {
            return null;
        }

        $local = $date->copy()->setTimezone(self::zone());

        return $local->format('D, j M Y').' · '.$local->format('H:i');
    }

    /** Start of a local calendar day ("2026-12-01") as a UTC instant. */
    public static function startOfDay(string $ymd): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $ymd, self::zone())->setTimezone(config('app.timezone'));
    }

    public static function endOfDay(string $ymd): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d', $ymd, self::zone())->endOfDay()->setTimezone(config('app.timezone'));
    }
}
