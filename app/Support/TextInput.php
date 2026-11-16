<?php

namespace App\Support;

use Normalizer;

/**
 * R1a: what every typed text goes through before it is stored. It removes only what is invisible or broken
 * and never changes what the user meant: `--`, quotes, `;`, `%`, `_`, `\`, `<`, `>`, `&`, Greek and emoji are stored
 * exactly as typed (queries are parameterised and pages escape output, so none of them is dangerous).
 *
 * Removed: invalid UTF-8 bytes, control characters (except tab and new line in multi-line fields), the byte-order mark
 * and zero-width spaces that come with copy-paste from Word/Outlook/PDF, spaces at the start and end.
 * Normalised: Unicode NFC (an accented letter typed as letter + accent becomes the single accented letter, so searches
 * find it), Windows line ends. Single-line fields get new lines replaced by a space. Empty → null.
 */
final class TextInput
{
    public static function clean(mixed $value, bool $multiline = false): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }
        $text = (string) $value;
        if (! mb_check_encoding($text, 'UTF-8')) {
            $previous = mb_substitute_character();
            mb_substitute_character('none');   // drop broken bytes instead of showing "?"
            $text = mb_scrub($text, 'UTF-8');
            mb_substitute_character($previous);
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        if (! $multiline) {
            $text = str_replace(["\n", "\t"], ' ', $text);
        }
        // C0/C1 controls except \t \n; BOM; zero-width space; word joiner. ZWJ (U+200D) stays: emoji sequences use it.
        $text = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}-\x{009F}\x{FEFF}\x{200B}\x{2060}]/u', '', $text) ?? '';
        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }
        // trim also no-break spaces (U+00A0) pasted from documents
        $text = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', $text) ?? '';

        return $text === '' ? null : $text;
    }

    /** A comma-separated list ("a, b,, c,") → "a, b, c" (keywords, related numbers); null when empty. */
    public static function list(mixed $value): ?string
    {
        $text = self::clean($value);
        if ($text === null) {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $text)), fn ($p) => $p !== ''));

        return $parts ? implode(', ', $parts) : null;
    }
}
