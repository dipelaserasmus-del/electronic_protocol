<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * N04: rules of the date input (<x-date-input>), the server-side twin of resources/modern/date-input.js.
 * Both are checked against the same cases (tests/fixtures/date-input-cases.json).
 *
 * expand(): "9" → day 9 of the current month and year; "9/4" → 9 April this year; "9/4/25" or "9/4/2025";
 * separators / . - or space; digits only: dd, ddmm, ddmmyy, ddmmyyyy. Returns dd/mm/yyyy, "" for empty input,
 * null when it isn't a real date.
 */
final class DateInput
{
    public const FORMAT = 'd/m/Y';

    public static function expand(string $text, ?CarbonImmutable $today = null): ?string
    {
        $today ??= CarbonImmutable::today();
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (preg_match('/^\d+$/', $text)) {
            $parts = match (strlen($text)) {
                1, 2 => [$text],
                4 => [substr($text, 0, 2), substr($text, 2, 2)],
                6 => [substr($text, 0, 2), substr($text, 2, 2), substr($text, 4, 2)],
                8 => [substr($text, 0, 2), substr($text, 2, 2), substr($text, 4, 4)],
                default => null,
            };
        } else {
            $parts = preg_match('~^\d{1,2}([/.\- ]\d{1,2}([/.\- ](\d{2}|\d{4}))?)?$~', $text)
                ? preg_split('~[/.\- ]~', $text) : null;
        }
        if ($parts === null) {
            return null;
        }
        $day = (int) $parts[0];
        $month = isset($parts[1]) ? (int) $parts[1] : $today->month;
        $year = isset($parts[2]) ? (int) $parts[2] : $today->year;
        if ($year < 100) {
            $year += 2000;
        }
        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%02d/%02d/%04d', $day, $month, $year);
    }

    /** ◀ / ▶ buttons: one day earlier/later; from today when the field is empty or not a date. */
    public static function shift(string $text, int $days, ?CarbonImmutable $today = null): string
    {
        $today ??= CarbonImmutable::today();
        $current = self::expand($text, $today);
        $date = $current ? CarbonImmutable::createFromFormat('!'.self::FORMAT, $current) : $today;

        return $date->addDays($days)->format(self::FORMAT);
    }
}
