<?php

namespace App\Support\Mail;

use ZBateson\MailMimeParser\Message;

/**
 * The one place that turns a raw e-mail into a mail-mime-parser Message (inbox page, registration, attachments).
 *
 * Before parsing, adjacent encoded words in the message's own headers are rejoined (EncodedWords::rejoin), so a
 * sender name split inside a Greek letter is read correctly instead of "??". Only the parsed copy changes; the raw
 * e-mail that is cached and stored with the protocol stays byte for byte as received.
 */
final class MailParser
{
    public static function parse(string $raw): Message
    {
        return Message::from(self::normalize($raw), false);
    }

    /** The raw e-mail with the encoded words of its top-level headers rejoined. */
    public static function normalize(string $raw): string
    {
        if (! preg_match('/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE)) {
            return $raw;   // headers only
        }
        $end = $m[0][1];
        $headers = substr($raw, 0, $end);
        if (! str_contains($headers, '=?')) {
            return $raw;
        }
        $eol = str_contains($m[0][0], "\r") ? "\r\n" : "\n";
        // one header per entry (continuation lines start with whitespace)
        $fields = preg_split('/\r?\n(?![ \t])/', $headers);
        foreach ($fields as &$field) {
            if (str_contains($field, '=?')) {
                $field = str_replace("\r\n", $eol, EncodedWords::rejoin($field));
            }
        }

        return implode($eol, $fields).substr($raw, $end);
    }
}
