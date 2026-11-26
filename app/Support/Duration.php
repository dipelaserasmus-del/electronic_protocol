<?php

namespace App\Support;

/**
 * R1o (owner, 2026-11-26): a length of time for people: «2 μέρες, 4 ώρες, 3 λεπτά» instead of «3123 λεπτά». Rounded
 * up to the minute (what is left is never shown as less than it is); parts that are 0 are left out.
 */
final class Duration
{
    private const UNITS = [[86400, 'μέρα', 'μέρες'], [3600, 'ώρα', 'ώρες'], [60, 'λεπτό', 'λεπτά']];

    public static function text(int $seconds): string
    {
        $left = (int) ceil(max(0, $seconds) / 60) * 60;
        $parts = [];
        foreach (self::UNITS as [$size, $one, $many]) {
            $n = intdiv($left, $size);
            $left -= $n * $size;
            if ($n > 0) {
                $parts[] = $n.' '.($n === 1 ? $one : $many);
            }
        }
        return $parts ? implode(', ', $parts) : '0 λεπτά';
    }
}
