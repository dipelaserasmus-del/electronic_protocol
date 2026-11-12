<?php

namespace App\Services;

use App\Config;
use App\Protocol;
use Carbon\Carbon;

/**
 * A02: finds protocol numbers that are missing (e.g. 999 and 1001 exist, 1000 does not).
 *
 * Scope (settings, page "Ρυθμίσεις"):
 *  - gapCheckMonths      look only at protocols dated in the last N months (default 6)
 *  - gapCheckFromNumber  ignore numbers below this one in the current year (empty = no lower limit)
 * Numbering restarts every year, so each year in the window is checked separately.
 */
class NumberGaps
{
    public const DEFAULT_MONTHS = 6;

    /**
     * @return array<int, int[]>  year => missing numbers (ascending); years without gaps are omitted
     */
    public function find(?Carbon $today = null): array
    {
        $today ??= Carbon::now();
        $months = (int) (Config::getConfigValueOf('gapCheckMonths') ?: self::DEFAULT_MONTHS);
        $from = (int) Config::getConfigValueOf('gapCheckFromNumber');
        $windowStart = (int) $today->copy()->subMonthsNoOverflow($months)->format('Ymd');

        $result = [];
        for ($year = (int) substr((string) $windowStart, 0, 4); $year <= $today->year; $year++) {
            $numbers = Protocol::whereEtos($year)->orderBy('protocolnum')->pluck('protocolnum')->map(fn ($n) => (int) $n)->all();
            if (count($numbers) < 2) {
                continue;
            }
            // lowest number dated inside the window (protocols before it are outside the check)
            $lower = (int) Protocol::whereEtos($year)->where('protocoldate', '>=', $windowStart)->min('protocolnum');
            if (!$lower) {
                continue;
            }
            if ($year === $today->year && $from > $lower) {
                $lower = $from;
            }
            $upper = end($numbers);
            if ($lower >= $upper) {
                continue;   // start number at/after the last entry: nothing between existing numbers
            }
            $missing = array_values(array_diff(range($lower, $upper), $numbers));
            if ($missing) {
                $result[$year] = $missing;
            }
        }
        return $result;
    }

    /** "37, 88, 100–105" */
    public static function compact(array $numbers): string
    {
        $out = [];
        $start = $prev = null;
        foreach ($numbers as $n) {
            if ($prev !== null && $n === $prev + 1) {
                $prev = $n;
                continue;
            }
            if ($start !== null) {
                $out[] = $start === $prev ? (string) $start : "{$start}–{$prev}";
            }
            $start = $prev = $n;
        }
        if ($start !== null) {
            $out[] = $start === $prev ? (string) $start : "{$start}–{$prev}";
        }
        return implode(', ', $out);
    }
}
