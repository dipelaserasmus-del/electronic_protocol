<?php

namespace App\Support;

/**
 * R1f: a field holding a list as text ("12/2026, 40/2025" or "σχολείο, εκδρομή"), shown as tags in the form.
 * The stored value stays the same text: search, prints and old entries are unchanged.
 * - related numbers: commas, semicolons and spaces separate; a bare number gets the year ("12" → "12/2026");
 * - keywords: commas and semicolons separate (a keyword may have spaces); spaces collapsed.
 * No repeats, compared without case and accents ("Διαύγεια" = "διαυγεια").
 */
final class TagList
{
    private function __construct(private string $separators, private \Closure $normalize) {}

    public static function related(int $year): self
    {
        return new self('~[\s,;]+~u', fn (string $t) => ctype_digit($t) ? ((int) $t) . '/' . $year : $t);
    }

    public static function keywords(): self
    {
        return new self('~\s*[,;]+\s*~u', fn (string $t) => preg_replace('~\s+~u', ' ', trim($t)));
    }

    public static function for(string $field, int $year): self
    {
        return match ($field) {
            'sxetiko' => self::related($year),
            'keywords' => self::keywords(),
        };
    }

    /** @return list<string> */
    public function tokens(?string $value): array
    {
        $out = [];
        foreach (preg_split($this->separators, trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) as $t) {
            $t = ($this->normalize)($t);
            if ($t !== '') {
                $out[] = $t;
            }
        }
        return $out;
    }

    /** The value with what was typed (one or more tags) added at the end; no repeats. */
    public function add(?string $value, string $typed): string
    {
        return $this->splice($this->tokens($value), count($this->tokens($value)), 0, $typed);
    }

    /** The value with tag $index replaced by what was typed (nothing typed = removed); the tag keeps its place. */
    public function replace(?string $value, int $index, string $typed): string
    {
        $tokens = $this->tokens($value);
        if (! array_key_exists($index, $tokens)) {
            return implode(', ', $tokens);
        }
        return $this->splice($tokens, $index, 1, $typed);
    }

    public function remove(?string $value, int $index): string
    {
        return $this->replace($value, $index, '');
    }

    /** Lower case without accents (and final ς = σ), to compare and search Greek text. */
    public static function fold(string $text): string
    {
        $text = \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text;
        $text = preg_replace('~\p{Mn}+~u', '', $text);
        return str_replace('ς', 'σ', mb_strtolower($text, 'UTF-8'));
    }

    /** @param list<string> $tokens */
    private function splice(array $tokens, int $at, int $length, string $typed): string
    {
        $kept = $tokens;
        array_splice($kept, $at, $length);
        $seen = array_map(self::fold(...), $kept);
        $new = [];
        foreach ($this->tokens($typed) as $t) {
            if (! in_array(self::fold($t), $seen, true)) {
                $seen[] = self::fold($t);
                $new[] = $t;
            }
        }
        array_splice($tokens, $at, $length, $new);
        return implode(', ', $tokens);
    }
}
