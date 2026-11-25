<?php

namespace App\Services\Logs;

/**
 * R6k (owner, 2026-11-25): personal data taken out of log text for an export («Απόκρυψη προσωπικών στοιχείων», on by
 * default). The log is not meant to hold personal data, but some entries do:
 * - e-mail addresses (failed e-mails: the recipient; mail server and other error messages) → «***@domain»;
 * - the values of the fields a failed e-mail logs (subject, recipient, sender, name, …) → «…»;
 * - the values inside a database error's query (a protocol's subject, a sender, a file name) → '…'.
 * What is left (times, levels, the errors themselves, files and lines of the code) is what a developer needs.
 */
final class LogMask
{
    /** JSON / array keys whose values are personal (a failed e-mail's context, a protocol's fields) */
    private const KEYS = ['to', 'cc', 'bcc', 'from', 'reply_to', 'subject', 'name', 'email', 'thema', 'username',
        'in_arxi_ekdosis', 'in_paraliptis', 'out_to', 'paratiriseis', 'filename', 'file_name'];

    public static function text(string $text): string
    {
        // the values of personal fields: "subject":"…" (JSON) and 'subject' => '…' / subject: … (as PHP and Laravel print them)
        $keys = implode('|', array_map('preg_quote', self::KEYS));
        $text = preg_replace('/("(?:' . $keys . ')"\s*:\s*)"(?:[^"\\\\]|\\\\.)*"/u', '$1"…"', $text) ?? $text;
        $text = preg_replace('/(\'(?:' . $keys . ')\'\s*=>\s*)\'(?:[^\'\\\\]|\\\\.)*\'/u', '$1\'…\'', $text) ?? $text;
        // a database error's query: every quoted value in it (SQL: … ) or in its bindings
        $text = preg_replace_callback('/SQL: .*?(?=\)\s*(?:\{|$|\n)|$)/mu', fn ($m) => preg_replace("/'(?:[^'\\\\]|\\\\.)*'/u", "'…'", $m[0]), $text) ?? $text;
        // e-mail addresses anywhere: the name goes, the domain stays (it says which mail server)
        return preg_replace('/[A-Za-z0-9._%+\-]+@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/u', '***@$1', $text) ?? $text;
    }
}
