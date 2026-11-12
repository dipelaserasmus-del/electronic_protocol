<?php

namespace App\Support\Imap;

use Webklex\PHPIMAP\Decoder\HeaderDecoder as BaseHeaderDecoder;

/**
 * MIME header decoder that works without the (removed, unmaintained) PHP ext-imap.
 *
 * webklex/php-imap falls back to returning RFC 2047 encoded words undecoded when
 * ext-imap is missing. This decoder handles:
 *  - RFC 2047 encoded words (B and Q) in any iconv-supported charset (UTF-8, ISO-8859-7, Windows-1253 ...)
 *  - folded headers with several encoded words
 *  - raw 8-bit headers: valid UTF-8 is kept, anything else is treated as Windows-1253 (Greek legacy)
 * It never replaces characters with "?" (the c-client/imap_utf8 behaviour behind the "??" symptom).
 */
class HeaderDecoder extends BaseHeaderDecoder
{
    /** Charset assumed for raw 8-bit bytes that are not valid UTF-8. Superset of ISO-8859-7 for letters. */
    public const LEGACY_CHARSET = 'WINDOWS-1253';

    public function mimeHeaderDecode(string $text): array
    {
        return [(object) ['charset' => 'UTF-8', 'text' => self::toUtf8($text)]];
    }

    public static function toUtf8(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Raw (unencoded) 8-bit bytes that are not UTF-8: interpret as legacy Greek first.
        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @iconv(self::LEGACY_CHARSET, 'UTF-8//IGNORE', $text);
            $text = $converted === false ? mb_convert_encoding($text, 'UTF-8', 'ISO-8859-7') : $converted;
        }

        if (!str_contains($text, '=?')) {
            return $text;
        }

        // Whitespace between adjacent encoded words must be removed (RFC 2047 §6.2)
        $text = preg_replace('/\?=\s+=\?/', '?==?', $text);

        return preg_replace_callback(
            '/=\?([^?]+)\?([bBqQ])\?([^?]*)\?=/',
            function (array $m) {
                [$all, $charset, $enc, $data] = $m;
                $charset = preg_replace('/\*.*$/', '', $charset);       // RFC 2231 language suffix
                $bytes = strtoupper($enc) === 'B'
                    ? base64_decode($data, false)
                    : quoted_printable_decode(str_replace('_', ' ', $data));
                if ($bytes === false) {
                    return $all;
                }
                $charset = strtoupper($charset);
                if (in_array($charset, ['UTF-8', 'UTF8', 'US-ASCII'], true)) {
                    return mb_check_encoding($bytes, 'UTF-8') ? $bytes : mb_convert_encoding($bytes, 'UTF-8', 'UTF-8');
                }
                $out = @iconv($charset, 'UTF-8//IGNORE', $bytes);
                if ($out === false || $out === '') {
                    $out = @mb_convert_encoding($bytes, 'UTF-8', $charset) ?: $bytes;
                }
                return $out;
            },
            $text
        );
    }
}
