<?php

namespace App\Support\Mail;

/**
 * RFC 2047 encoded words ("=?charset?B|Q?data?=") that belong together.
 *
 * Mail programs split a long header into several encoded words; some (Horde webmail, owner's example 2026-11-16)
 * split *inside* a multi-byte character, e.g. the 2 bytes of a Greek "ί" end up in two words. Decoding each word on
 * its own then gives two invalid halves, shown as "??". RFC 2047 forbids that split, but the fix is cheap and safe:
 * adjacent words (only whitespace between them, RFC 2047 §6.2) with the same charset are decoded together, as one
 * byte string, and converted to UTF-8 once.
 */
final class EncodedWords
{
    private const WORD = '=\?([^?\s]+)\?([bBqQ])\?([^?\s]*)\?=';

    /** Charset assumed for bytes that are not valid in their declared charset. Superset of ISO-8859-7 for letters. */
    public const LEGACY_CHARSET = 'WINDOWS-1253';

    /** Decodes every run of adjacent encoded words in $text to UTF-8; other text is left as it is. */
    public static function decode(string $text): string
    {
        if (! str_contains($text, '=?')) {
            return $text;
        }

        return preg_replace_callback('/'.self::WORD.'(?:\s*'.self::WORD.')*/', fn (array $m) => self::decodeRun($m[0]), $text);
    }

    /**
     * The same header with every run of two or more adjacent encoded words replaced by UTF-8 words that each hold
     * whole characters, so that any decoder (also mail-mime-parser) reads it correctly. Single words, plain text and
     * headers without encoded words are returned unchanged.
     */
    public static function rejoin(string $header): string
    {
        if (! str_contains($header, '=?')) {
            return $header;
        }

        return preg_replace_callback('/'.self::WORD.'(?:\s*'.self::WORD.')+/', function (array $m) {
            $words = [];
            foreach (mb_str_split(self::decodeRun($m[0]), 15) as $chunk) {   // ≤ 60 bytes → words ≤ 75 characters
                $words[] = '=?UTF-8?B?'.base64_encode($chunk).'?=';
            }

            return implode("\r\n ", $words);
        }, $header);
    }

    /** One run of adjacent words: decode the bytes of consecutive words with the same charset together. */
    private static function decodeRun(string $run): string
    {
        preg_match_all('/'.self::WORD.'/', $run, $words, PREG_SET_ORDER);
        $out = '';
        $charset = null;
        $bytes = '';
        foreach ($words as [, $wordCharset, $encoding, $data]) {
            $wordCharset = strtoupper(preg_replace('/\*.*$/', '', $wordCharset));   // RFC 2231 language suffix
            $decoded = strtoupper($encoding) === 'B'
                ? (string) base64_decode($data, false)
                : quoted_printable_decode(str_replace('_', ' ', $data));
            if ($charset !== null && $wordCharset !== $charset) {
                $out .= self::toUtf8($bytes, $charset);
                $bytes = '';
            }
            $charset = $wordCharset;
            $bytes .= $decoded;
        }

        return $out.self::toUtf8($bytes, (string) $charset);
    }

    /** Bytes in $charset → UTF-8, never "?" for characters that exist (unknown charsets: UTF-8, else legacy Greek). */
    public static function toUtf8(string $bytes, string $charset): string
    {
        if ($bytes === '') {
            return '';
        }
        $charset = strtoupper($charset);
        if (in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII'], true)) {
            return mb_check_encoding($bytes, 'UTF-8') ? $bytes : self::legacy($bytes);
        }
        $out = @iconv($charset, 'UTF-8//IGNORE', $bytes);
        if ($out === false || $out === '') {
            try {
                $out = mb_convert_encoding($bytes, 'UTF-8', $charset);
            } catch (\ValueError) {
                $out = false;   // charset unknown to both converters
            }
        }
        if ($out === false || $out === '') {
            $out = mb_check_encoding($bytes, 'UTF-8') ? $bytes : self::legacy($bytes);
        }

        return $out;
    }

    private static function legacy(string $bytes): string
    {
        $out = @iconv(self::LEGACY_CHARSET, 'UTF-8//IGNORE', $bytes);

        return $out === false ? mb_convert_encoding($bytes, 'UTF-8', 'ISO-8859-7') : $out;
    }
}
