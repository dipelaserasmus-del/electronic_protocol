<?php

namespace App\Support;

/**
 * R1h: how a typed folder code is compared with the folders' codes (owner, 2026-11-18). Case and accents never count;
 * dots and spaces between the letters and the number are optional ("φ11" = "Φ.11" = "Φ11"); dots inside the number
 * count ("φ11" is not "Φ1.1" or "Φ.1.1"). A number typed alone is compared with the number part ("12" = "Φ12").
 * The browser twin is folderKey() in resources/modern/tags.js; both follow tests/fixtures/folder-code-cases.json.
 */
final class FolderCode
{
    /** "Φ.1.1" → "φ1.1", "Φ 22.1" → "φ22.1", "12" → "12" */
    public static function key(string $code): string
    {
        $code = preg_replace('/\s+/u', '', TagList::fold($code)) ?? '';
        // the symbols between the leading letters and the number do not count
        return preg_replace('/^(\p{L}*)[^\p{L}\p{N}]+(?=\p{N})/u', '$1', $code) ?? $code;
    }

    /** The key without its leading letters: "Φ.12" → "12", "12" → "12" */
    public static function number(string $code): string
    {
        return preg_replace('/^\p{L}+/u', '', self::key($code)) ?? '';
    }

    /** Is the typed text this folder's code? "φ11" → Φ.11 and Φ11; "12" → Φ12 and Φ.12 */
    public static function same(string $typed, string $code): bool
    {
        $key = self::key($typed);
        if ($key === '') {
            return false;
        }
        return preg_match('/^\p{N}/u', $key) ? self::number($code) === $key : self::key($code) === $key;
    }

    /** Does the code start with the typed text, the same way? "φ1" → Φ1, Φ1.1, Φ10; "12" → Φ12, Φ12.3, Φ120 */
    public static function startsWith(string $typed, string $code): bool
    {
        $key = self::key($typed);
        if ($key === '') {
            return false;
        }
        return str_starts_with(preg_match('/^\p{N}/u', $key) ? self::number($code) : self::key($code), $key);
    }
}
