<?php

namespace App\Support;

/**
 * R1a: a stored value as safe HTML with the search term marked. The value and the term are escaped first, so a
 * subject like `<script>…` or `a & b` is shown as typed and never runs (search results printed both raw before).
 * Case-insensitive, Greek included.
 */
final class Highlight
{
    public static function html(mixed $text, mixed $term): string
    {
        $text = is_scalar($text) ? (string) $text : '';
        $term = is_scalar($term) ? trim((string) $term) : '';
        $safe = e($text);
        if ($term === '') {
            return $safe;
        }

        return preg_replace('/'.preg_quote(e($term), '/').'/iu', '<mark><strong>$0</strong></mark>', $safe) ?? $safe;
    }
}
