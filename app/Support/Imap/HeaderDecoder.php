<?php

namespace App\Support\Imap;

use Webklex\PHPIMAP\Decoder\HeaderDecoder as BaseHeaderDecoder;

/**
 * MIME header decoder that works without the (removed, unmaintained) PHP ext-imap.
 *
 * webklex/php-imap falls back to returning RFC 2047 encoded words undecoded when
 * ext-imap is missing. This decoder handles:
 *  - RFC 2047 encoded words (B and Q) in any iconv-supported charset (UTF-8, ISO-8859-7, Windows-1253 ...)
 *  - folded headers with several encoded words, also when a character is split between two of them (R5a)
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

        // R5a: adjacent encoded words are decoded together (a character may be split between two of them)
        return \App\Support\Mail\EncodedWords::decode($text);
    }
}
