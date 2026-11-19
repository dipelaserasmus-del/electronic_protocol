<?php

namespace App\Services;

use App\Models\Protocol;
use Carbon\Carbon;

/**
 * R2f (owner, 2026-11-19): protocol dates that look like typing mistakes.
 *
 * Protocols are numbered in sequence, so as the numbers go up the dates never go back. A protocol whose date breaks
 * that order (e.g. 20/11 among protocols of 10/11) was probably given a wrong date. A date after today is suspect too.
 * Both are warnings: the user may set the date they want.
 *
 * Which protocols are out of order: the fewest whose removal leaves the dates in order (the complement of the longest
 * non-decreasing run of dates, by number). A single odd protocol is the one out of step with both neighbours; a new day
 * starting between two numbers is normal; the first and last protocol of a year, and several wrong ones in a row, are
 * judged the same way. Each year is checked on its own (numbering restarts every year).
 */
final class DateOrder
{
    public const ORDER = 'order';
    public const FUTURE = 'future';

    /**
     * The flagged protocols of these years.
     *
     * @param  iterable<int>  $years
     * @return array<int, array{kinds: list<string>, prev: ?array{num: int, date: Carbon}, next: ?array{num: int, date: Carbon}}>
     *         by protocol id
     */
    public function flags(iterable $years, ?Carbon $today = null): array
    {
        $today ??= Carbon::today();
        $flags = [];
        foreach (array_unique(array_map('intval', is_array($years) ? $years : iterator_to_array($years))) as $year) {
            $rows = $this->rows($year);
            $kept = self::inOrder(array_map(fn ($r) => (int) $r['date']->format('Ymd'), $rows));
            foreach ($rows as $i => $r) {
                $kinds = [];
                if (! isset($kept[$i])) {
                    $kinds[] = self::ORDER;
                }
                if ($r['date']->greaterThan($today)) {
                    $kinds[] = self::FUTURE;
                }
                if ($kinds) {
                    $flags[$r['id']] = ['kinds' => $kinds,
                        'prev' => $i > 0 ? ['num' => $rows[$i - 1]['num'], 'date' => $rows[$i - 1]['date']] : null,
                        'next' => $i < count($rows) - 1 ? ['num' => $rows[$i + 1]['num'], 'date' => $rows[$i + 1]['date']] : null];
                }
            }
        }

        return $flags;
    }

    /**
     * One date being typed in the form, against the protocols around its number: the nearest before and after it
     * whose dates are in order (so one wrongly dated neighbour does not hide the mistake, or cause a false one).
     * $number null: a new protocol numbered on save, so it comes after the last one of the year.
     *
     * @return array{kinds: list<string>, prev: ?array{num: int, date: Carbon}, next: ?array{num: int, date: Carbon}}|null
     */
    public function check(Carbon $date, int $year, ?int $number, ?int $exceptId = null, ?Carbon $today = null): ?array
    {
        $today ??= Carbon::today();
        $rows = $this->rows($year, $exceptId);
        $kept = self::inOrder(array_map(fn ($r) => (int) $r['date']->format('Ymd'), $rows));
        $prev = $next = null;
        foreach ($rows as $i => $r) {
            if (! isset($kept[$i])) {
                continue;
            }
            if ($number === null || $r['num'] < $number) {
                $prev = $r;
            } elseif ($r['num'] > $number && $next === null) {
                $next = $r;
            }
        }
        $prev = $prev ? ['num' => $prev['num'], 'date' => $prev['date']] : null;
        $next = $next ? ['num' => $next['num'], 'date' => $next['date']] : null;

        $kinds = [];
        if (($prev && $date->lessThan($prev['date'])) || ($next && $date->greaterThan($next['date']))) {
            $kinds[] = self::ORDER;
        }
        if ($date->greaterThan($today)) {
            $kinds[] = self::FUTURE;
        }

        return $kinds ? ['kinds' => $kinds, 'prev' => $prev, 'next' => $next] : null;
    }

    /** @return list<array{id: int, num: int, date: Carbon}> the dated protocols of a year, by number */
    private function rows(int $year, ?int $exceptId = null): array
    {
        return Protocol::whereEtos($year)->whereNotNull('protocoldate')->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->orderBy('protocolnum')->get(['id', 'protocolnum', 'protocoldate'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'num' => (int) $p->protocolnum, 'date' => $p->dateOf('protocoldate')])
            ->filter(fn ($r) => $r['date'] !== null)->values()->all();
    }

    /** The explanation shown with the ⚠ (list tooltip and form warning). */
    public static function message(array $flag): string
    {
        $parts = [];
        if (in_array(self::ORDER, $flag['kinds'], true)) {
            $around = array_filter([
                $flag['prev'] ? 'ο προηγούμενος ('.$flag['prev']['num'].') έχει '.$flag['prev']['date']->format('d/m/Y') : null,
                $flag['next'] ? 'ο επόμενος ('.$flag['next']['num'].') '.$flag['next']['date']->format('d/m/Y') : null,
            ]);
            $parts[] = 'Η ημερομηνία δεν ταιριάζει με τη σειρά των αριθμών: '.implode(' και ', $around).'. Μήπως γράφτηκε λάθος;';
        }
        if (in_array(self::FUTURE, $flag['kinds'], true)) {
            $parts[] = 'Η ημερομηνία είναι μετά τη σημερινή.';
        }

        return implode(' ', $parts);
    }

    /**
     * Positions (keys) of a longest non-decreasing subsequence of $values. Patience sorting, O(n log n); among equally
     * long ones it keeps the later values, so a wrong date early in a run is the one left out.
     *
     * @param  list<int>  $values
     * @return array<int, true>
     */
    private static function inOrder(array $values): array
    {
        $tails = [];   // position of the last value of the best run of each length
        $prev = [];
        foreach ($values as $i => $v) {
            // the first run whose last value is greater than $v (upper bound: equal values may follow each other)
            [$lo, $hi] = [0, count($tails)];
            while ($lo < $hi) {
                $mid = intdiv($lo + $hi, 2);
                if ($values[$tails[$mid]] <= $v) {
                    $lo = $mid + 1;
                } else {
                    $hi = $mid;
                }
            }
            $prev[$i] = $lo > 0 ? $tails[$lo - 1] : null;
            $tails[$lo] = $i;
        }
        $kept = [];
        for ($i = $tails ? end($tails) : null; $i !== null; $i = $prev[$i]) {
            $kept[$i] = true;
        }

        return $kept;
    }
}
