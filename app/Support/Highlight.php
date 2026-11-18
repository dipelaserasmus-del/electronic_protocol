<?php

namespace App\Support;

/**
 * R1a: a stored value as safe HTML with the search term marked. The value and the term are escaped first, so a
 * subject like `<script>…` or `a & b` is shown as typed and never runs (search results printed both raw before).
 * R3a: the match ignores case, accents and final ς, like the search itself ("εκκρεμοτητα" marks "ΕΚΚΡΕΜΌΤΗΤΑ").
 * Several terms can be given; each is marked wherever it occurs.
 */
final class Highlight
{
    /** @param string|list<string>|null $terms */
    public static function html(mixed $text, mixed $terms): string
    {
        $text = is_scalar($text) ? (string) $text : '';
        $terms = array_values(array_filter(array_map(
            fn ($t) => TagList::fold(trim((string) $t)),
            is_array($terms) ? $terms : (is_scalar($terms) ? [$terms] : []),
        ), fn ($t) => $t !== ''));
        if ($text === '' || ! $terms) {
            return e($text);
        }

        // fold the text one character at a time, remembering which character each folded one came from
        $chars = mb_str_split($text);
        $folded = [];
        $from = [];
        foreach ($chars as $i => $ch) {
            foreach (mb_str_split(TagList::fold($ch)) as $f) {
                $folded[] = $f;
                $from[] = $i;
            }
        }
        $haystack = implode('', $folded);
        $marked = array_fill(0, count($chars), false);
        foreach ($terms as $term) {
            $len = mb_strlen($term);
            for ($at = mb_strpos($haystack, $term); $at !== false; $at = mb_strpos($haystack, $term, $at + 1)) {
                for ($k = $at; $k < $at + $len; $k++) {
                    $marked[$from[$k]] = true;
                }
            }
        }

        $out = '';
        foreach ($chars as $i => $ch) {
            if ($marked[$i] && ! ($marked[$i - 1] ?? false)) {
                $out .= '<mark><strong>';
            }
            $out .= e($ch);
            if ($marked[$i] && ! ($marked[$i + 1] ?? false)) {
                $out .= '</strong></mark>';
            }
        }

        return $out;
    }
}
