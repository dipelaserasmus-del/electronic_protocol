<?php

namespace App\Support\Mail;

use ZBateson\MailMimeParser\Message;

/**
 * The one place that turns a raw e-mail into a mail-mime-parser Message (inbox page, registration, attachments).
 *
 * Before parsing, text that some mail programs (Horde webmail, owner's examples 2026-11-16) split in the middle of a
 * Greek letter is joined again, in the message's headers and in the headers of every MIME part:
 *  - adjacent RFC 2047 encoded words (sender names, subjects, `name="=?…?="` parameters): EncodedWords::rejoin;
 *  - RFC 2231 parameter continuations (`filename*0*=utf-8''%CE; filename*1*=%91…`): one `filename*=` parameter.
 * Decoding the pieces one by one gave "??" (sender names, attachment names). Only the parsed copy changes; the raw
 * e-mail that is cached and stored with the protocol stays byte for byte as received.
 */
final class MailParser
{
    public static function parse(string $raw): Message
    {
        return Message::from(self::normalize($raw), false);
    }

    /** The raw e-mail with split encoded words and parameter continuations joined in all its header blocks. */
    public static function normalize(string $raw): string
    {
        // nothing to join (also skips the line-by-line walk for most large e-mails)
        if (! str_contains($raw, '=?') && ! preg_match('/\*0\*?\s*=/', $raw)) {
            return $raw;
        }

        $out = '';
        $block = '';          // header block being collected
        $inHeaders = true;    // the message starts with its headers
        $boundaries = [];
        foreach (preg_split('/(?<=\n)/', $raw) as $line) {
            if ($inHeaders) {
                if (trim($line, "\r\n") === '') {                 // empty line: end of this header block
                    $out .= self::normalizeHeaders($block).$line;
                    foreach (self::boundaries($block) as $b) {
                        $boundaries['--'.$b] = true;
                    }
                    $block = '';
                    $inHeaders = false;
                } else {
                    $block .= $line;
                }
                continue;
            }
            $out .= $line;
            // a part starts after "--boundary" (not after the closing "--boundary--")
            if (str_starts_with($line, '--') && isset($boundaries[rtrim($line, " \t\r\n")])) {
                $inHeaders = true;
            }
        }

        return $out.$block;   // headers without a body
    }

    /** One header block: each header (with its continuation lines) normalized on its own. */
    private static function normalizeHeaders(string $block): string
    {
        if (! str_contains($block, '=?') && ! preg_match('/\*0\*?\s*=/', $block)) {
            return $block;
        }
        $eol = str_contains($block, "\r\n") ? "\r\n" : "\n";
        $fields = preg_split('/(?<=\n)(?![ \t])/', $block);
        foreach ($fields as &$field) {
            $end = substr($field, strlen(rtrim($field, "\r\n")));
            $text = rtrim($field, "\r\n");
            if (preg_match('/\*0\*?\s*=/', $text)) {
                $text = self::joinContinuations($text);
            }
            if (str_contains($text, '=?')) {
                $text = EncodedWords::rejoin($text);
            }
            $field = str_replace("\r\n", $eol, $text).$end;
        }

        return implode('', $fields);
    }

    /** @return list<string> multipart boundaries declared in a header block */
    private static function boundaries(string $block): array
    {
        preg_match_all('/boundary\s*=\s*(?:"([^"]+)"|([^\s;"]+))/i', $block, $m, PREG_SET_ORDER);

        return array_map(fn (array $x) => $x[1] !== '' ? $x[1] : $x[2], $m);
    }

    /**
     * RFC 2231 continuations of a parameter (name*0*=…; name*1*=…) → one extended parameter name*=charset''…,
     * so the bytes are decoded together. Sections without "*" are plain text and are percent-encoded on the way.
     */
    private static function joinContinuations(string $field): string
    {
        $pattern = '/;\s*([A-Za-z0-9_.-]+)\*(\d+)(\*?)\s*=\s*("[^"]*"|[^;\s]*)/';
        preg_match_all($pattern, $field, $all, PREG_SET_ORDER);
        $params = [];
        foreach ($all as [, $name, $index, $extended, $value]) {
            $params[strtolower($name)][(int) $index] = [$extended === '*', trim($value, '"'), $name];
        }

        foreach ($params as $key => $sections) {
            ksort($sections);
            if (! isset($sections[0])) {
                continue;
            }
            [$firstExtended, $firstValue, $name] = $sections[0];
            $prefix = "utf-8''";
            if ($firstExtended && substr_count($firstValue, "'") >= 2) {
                $cut = strpos($firstValue, "'", strpos($firstValue, "'") + 1) + 1;
                $prefix = substr($firstValue, 0, $cut);
                $sections[0][1] = substr($firstValue, $cut);
            }
            $encoded = '';
            foreach ($sections as [$extended, $value]) {
                $encoded .= $extended ? $value : rawurlencode($value);
            }
            // remove the sections of this parameter, add the joined one
            $field = preg_replace_callback($pattern, fn (array $m) => strtolower($m[1]) === $key ? '' : $m[0], $field);
            $field = rtrim($field, " \t;\r\n").";\r\n ".$name.'*='.$prefix.$encoded;
        }

        return $field;
    }
}
