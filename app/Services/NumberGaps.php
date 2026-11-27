<?php

namespace App\Services;

use App\Models\Config;
use App\Models\Protocol;
use Carbon\Carbon;

/**
 * A02: finds protocol numbers that are missing (e.g. 999 and 1001 exist, 1000 does not).
 *
 * Scope (settings, page "Ρυθμίσεις"):
 *  - gapCheckMonths      look only at protocols dated in the last N months (default 6)
 *  - firstProtocolNum    "Αρ.Πρωτοκόλλου για ξεκίνημα": numbers below it in the current year are not checked
 *                        (R2c, owner 2026-11-17; was the separate setting gapCheckFromNumber)
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
        $from = (int) Config::getConfigValueOf('firstProtocolNum');
        $windowStart = (int) $today->copy()->subMonthsNoOverflow($months)->format('Ymd');

        $result = [];
        for ($year = (int) substr((string) $windowStart, 0, 4); $year <= $today->year; $year++) {
            $numbers = Protocol::whereEtos($year)->orderBy('protocolnum')->pluck('protocolnum')->map(fn ($n) => (int) $n)->all();
            if (count($numbers) < 2) {
                continue;
            }
            // lowest number dated inside the window (protocols before it are outside the check)
            $lower = (int) Protocol::whereEtos($year)->where('protocoldate', '>=', \Carbon\Carbon::createFromFormat('Ymd', (string) $windowStart)->toDateString())->min('protocolnum');   // D04: DATE column
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

    /**
     * A01: the missing numbers next to the protocols dated from $from to $to (the «Έλεγχοι» page). A run of missing
     * numbers counts when one of its two neighbours (the nearest existing number before and after it) is dated in the
     * range, so a gap just at the start or the end of the range is found too, and (review of A01) a gap between two
     * protocols dated elsewhere is not, even when the dates are out of number order. The numbering years (etos) are
     * those of the protocols dated in the range, which need not be the dates' years. The start number
     * (firstProtocolNum) applies to the current year, as in find().
     *
     * @return array<int, int[]>  year => missing numbers (ascending); years without gaps are omitted
     */
    public function between(\Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to, ?Carbon $today = null): array
    {
        $today ??= Carbon::now();
        $start = (int) Config::getConfigValueOf('firstProtocolNum');
        [$first, $last] = [$from->toDateString(), $to->toDateString()];
        $years = Protocol::whereBetween('protocoldate', [$first, $last])->distinct()->orderBy('etos')->pluck('etos')->map(fn ($y) => (int) $y);

        $result = [];
        foreach ($years as $year) {
            // number => 'Y-m-d' (raw column: D04 DATE), by number
            $dates = Protocol::whereEtos($year)->orderBy('protocolnum')->toBase()->pluck('protocoldate', 'protocolnum')->all();
            $inRange = fn ($date) => $date !== null && substr((string) $date, 0, 10) >= $first && substr((string) $date, 0, 10) <= $last;
            $missing = [];
            $prev = null;
            foreach ($dates as $num => $date) {
                $num = (int) $num;
                if ($prev !== null && $num > $prev + 1 && ($inRange($dates[$prev]) || $inRange($date))) {
                    $low = $year === $today->year ? max($prev + 1, $start) : $prev + 1;
                    array_push($missing, ...($low < $num ? range($low, $num - 1) : []));
                }
                $prev = $num;
            }
            if ($missing) {
                $result[$year] = $missing;
            }
        }

        return $result;
    }

    /**
     * R2c: the missing numbers as runs of consecutive numbers, for the placeholder rows of the list.
     *
     * @param  array<int, int[]>  $gaps  the result of find()
     * @return list<array{year: int, from: int, to: int}>
     */
    public static function runs(array $gaps): array
    {
        $runs = [];
        foreach ($gaps as $year => $numbers) {
            $start = $prev = null;
            foreach ($numbers as $n) {
                if ($prev !== null && $n === $prev + 1) {
                    $prev = $n;
                    continue;
                }
                if ($start !== null) {
                    $runs[] = ['year' => (int) $year, 'from' => $start, 'to' => $prev];
                }
                $start = $prev = $n;
            }
            if ($start !== null) {
                $runs[] = ['year' => (int) $year, 'from' => $start, 'to' => $prev];
            }
        }
        return $runs;
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
