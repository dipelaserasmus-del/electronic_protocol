<?php

namespace App\Support;

use App\Models\Protocol;

/**
 * R1e: the "Σχετικοί αριθμοί" field ("12/2026, 40/2025"). Each reference is "number/year" or a bare number (= the
 * protocol's own year); commas, semicolons and spaces separate them. Anything else is kept as plain text.
 */
class RelatedNumbers
{
    /** @return list<string> the references as typed */
    public static function tokens(?string $value): array
    {
        return preg_split('~[\s,;]+~u', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);
    }

    /** Adds what was typed (one or more references) to the field's value; a bare number gets the year, no repeats. */
    public static function add(?string $value, string $typed, int $year): string
    {
        $tokens = self::tokens($value);
        foreach (self::tokens($typed) as $t) {
            $t = ctype_digit($t) ? ((int) $t) . '/' . $year : $t;
            if (! in_array($t, $tokens, true)) {
                $tokens[] = $t;
            }
        }
        return implode(', ', $tokens);
    }

    /** The field's value without its reference number $index (0 = first). */
    public static function remove(?string $value, int $index): string
    {
        $tokens = self::tokens($value);
        unset($tokens[$index]);
        return implode(', ', $tokens);
    }

    /**
     * Every reference in order; protocol = the existing protocol the user may open, else null.
     *
     * @return list<array{text: string, protocol: ?Protocol}>
     */
    public static function resolve(?string $value, int $year): array
    {
        $refs = [];
        foreach (self::tokens($value) as $text) {
            if (preg_match('~^(\d{1,9})(?:/(\d{4}))?$~', $text, $m)) {
                $refs[] = ['text' => $text, 'num' => (int) $m[1], 'year' => isset($m[2]) ? (int) $m[2] : $year];
            } else {
                $refs[] = ['text' => $text, 'num' => null, 'year' => null];
            }
        }
        $wanted = array_filter($refs, fn ($r) => $r['num'] !== null);
        $found = collect();
        if ($wanted) {
            $found = Protocol::query()
                ->where(function ($q) use ($wanted) {
                    foreach ($wanted as $r) {
                        $q->orWhere(fn ($q) => $q->where('etos', $r['year'])->where('protocolnum', $r['num']));
                    }
                })
                ->get()
                ->filter(fn ($p) => \Illuminate\Support\Facades\Gate::allows('view', $p))   // others' protocols stay plain text
                ->keyBy(fn ($p) => $p->protocolnum . '/' . $p->etos);
        }
        return array_map(fn ($r) => [
            'text' => $r['text'],
            'protocol' => $r['num'] !== null ? $found->get($r['num'] . '/' . $r['year']) : null,
        ], $refs);
    }
}
