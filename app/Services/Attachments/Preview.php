<?php

namespace App\Services\Attachments;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use Symfony\Component\HttpFoundation\Response;

/**
 * R4c: previewing a file inside the application (the large modal with ‹ ›, resources/modern/preview.js).
 *
 * PDFs and images are shown as the download gives them (SafeFile: inline, with their extension's type). Text, Word
 * (docx, odt) and spreadsheets (xlsx, xls, ods) are converted here into one HTML page with the libraries the
 * application already uses (PhpWord, PhpSpreadsheet): no new code in the browser. That page is served with a sandbox
 * (no scripts, no forms, nothing loaded from anywhere: only inline styles and embedded images), and the modal's frame
 * is sandboxed too, so a crafted document can never act in the application. The layout is approximate (said in the
 * modal); spreadsheets show their first MAX_ROWS rows and MAX_COLS columns, formulas with the values the file saved
 * (never calculated here).
 */
final class Preview
{
    public const IMAGES = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    /** extension => how it is converted */
    public const CONVERTED = [
        'txt' => 'text', 'csv' => 'text',
        'docx' => 'word', 'odt' => 'word',
        'xlsx' => 'sheet', 'xls' => 'sheet', 'ods' => 'sheet',
        'eml' => 'mail',   // R4q
    ];

    /** R4q: e-mails are read for their headers and text, not converted as a whole: a larger file is still shown */
    public const MAX_MAIL_BYTES = 41943040;

    public const MAX_ROWS = 500;
    public const MAX_COLS = 50;

    /** text files: only the start is shown */
    public const MAX_TEXT_BYTES = 1048576;

    private const CSP = "sandbox; default-src 'none'; img-src data:; style-src 'unsafe-inline'; font-src data:";

    /** 'pdf' | 'image' | 'text' | 'word' | 'sheet', or null when this type is not previewed */
    public static function kind(?string $name): ?string
    {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        return match (true) {
            $ext === 'pdf' => 'pdf',
            in_array($ext, self::IMAGES, true) => 'image',
            default => self::CONVERTED[$ext] ?? null,
        };
    }

    /** The kinds shown by this application's converter (the others are shown as the download gives them). */
    public static function converted(?string $kind): bool
    {
        return in_array($kind, ['text', 'word', 'sheet', 'mail'], true);
    }

    /** The largest file converted (config attachments.preview_max_kb) */
    public static function maxBytes(): int
    {
        return (int) config('attachments.preview_max_kb', 10240) * 1024;
    }

    /** The preview page of a file on disk, named $name (its extension decides the converter). */
    public function response(string $path, string $name): Response
    {
        $kind = self::kind($name);
        if (! self::converted($kind)) {
            return self::page(self::notice('Αυτό το είδος αρχείου δεν εμφανίζεται εδώ.'));
        }
        if (! is_file($path)) {
            return self::page(self::notice('Το αρχείο δεν βρέθηκε.'));
        }
        if (filesize($path) > ($kind === 'mail' ? max(self::maxBytes(), self::MAX_MAIL_BYTES) : self::maxBytes())) {
            return self::page(self::notice('Το αρχείο είναι πολύ μεγάλο για προεπισκόπηση. Κατεβάστε το για να το δείτε.'));
        }
        try {
            $html = match ($kind) {
                'text' => self::text($path),
                'word' => self::word($path, strtolower(pathinfo($name, PATHINFO_EXTENSION))),
                'sheet' => self::sheet($path, strtolower(pathinfo($name, PATHINFO_EXTENSION))),
                'mail' => self::mail($path),
            };
        } catch (\Throwable $e) {
            report($e);
            $html = self::notice('Δεν ήταν δυνατή η προεπισκόπηση αυτού του αρχείου. Κατεβάστε το για να το δείτε.');
        }
        return self::page($html);
    }

    /** The same for content in memory (an e-mail's attachment) */
    public function responseFor(string $content, string $name): Response
    {
        $tmp = tempnam(sys_get_temp_dir(), 'preview');
        try {
            file_put_contents($tmp, $content);
            return $this->response($tmp, $name);
        } finally {
            @unlink($tmp);
        }
    }

    /** A complete page with the sandbox headers */
    public static function page(string $html): Response
    {
        if (! preg_match('~<html[\s>]~i', $html)) {
            $html = '<!DOCTYPE html><html lang="el"><head><meta charset="utf-8"><style>'
                . 'body{font-family:system-ui,"Segoe UI",Roboto,Arial,sans-serif;margin:16px;color:#212529;background:#fff}'
                . 'pre{white-space:pre-wrap;word-break:break-word;font:13px/1.45 ui-monospace,Consolas,monospace;margin:0}'
                . '.notice{color:#6c757d;font-size:15px;margin-top:40px;text-align:center}.cut{color:#6c757d;font-style:italic;margin-top:12px}'
                . '</style></head><body>' . $html . '</body></html>';
        }
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => self::CSP,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function notice(string $text): string
    {
        return '<p class="notice">' . e($text) . '</p>';
    }

    /** Plain text, escaped; Greek files of older programs (Windows-1253 / ISO-8859-7) read as such */
    private static function text(string $path): string
    {
        $size = filesize($path);
        $text = (string) file_get_contents($path, false, null, 0, self::MAX_TEXT_BYTES);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if ($size > self::MAX_TEXT_BYTES) {
            $text = self::wholeCharacters($text);   // the read may end inside a UTF-8 character (review of R4c)
        }
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = (string) @iconv('WINDOWS-1253', 'UTF-8//IGNORE', $text);   // Greek files of older programs
        }
        $cut = $size > self::MAX_TEXT_BYTES ? '<p class="cut">… εμφανίζεται μόνο η αρχή του αρχείου.</p>' : '';
        return '<pre>' . e($text) . '</pre>' . $cut;
    }

    /** $bytes without an incomplete UTF-8 character at its end (a lead byte without all its continuation bytes) */
    public static function wholeCharacters(string $bytes): string
    {
        $len = strlen($bytes);
        for ($back = 1; $back <= min(3, $len); $back++) {
            $byte = ord($bytes[$len - $back]);
            if (($byte & 0xC0) === 0x80) {
                continue;   // a continuation byte: look further back for its lead byte
            }
            $need = $byte >= 0xF0 ? 4 : ($byte >= 0xE0 ? 3 : ($byte >= 0xC0 ? 2 : 1));
            return $need > $back ? substr($bytes, 0, $len - $back) : $bytes;
        }
        return $bytes;
    }

    /**
     * R4q (owner, 2026-11-27): an e-mail file (.eml, e.g. the e-mail a protocol was registered from) as an e-mail program
     * shows it: Από, Προς, Κοιν., date, Θέμα and the names of its files, then its HTML (or its text). The HTML stays in
     * this sandboxed page: no scripts, forms or plugins, and nothing loaded from the internet (remote pictures and
     * trackers stay blank); only its <style> and body are kept, without <meta>, <base>, <link> and embedded frames.
     */
    private static function mail(string $path): string
    {
        $mail = \App\Support\Mail\MailParser::parse((string) file_get_contents($path));
        $s = \App\Support\Mail\MailContent::summary($mail);
        $date = $s['date'] ? (new \DateTimeImmutable($s['date']))->setTimezone(\App\Services\Mail\EmailRegistration::zone())->format('d/m/Y H:i') : '';
        $files = implode(', ', array_map(fn ($a) => e($a['name']) . ' <span style="color:#6c757d">(' . e(\App\Support\FileBadge::size($a['size'])) . ')</span>', $s['attachments']));
        $rows = array_filter([
            'Από' => e(\App\Support\Mail\MailContent::sender($s)),
            'Προς' => e(implode(', ', $s['to'])),
            'Κοιν.' => e(implode(', ', $s['cc'])),
            'Ημερομηνία' => e($date),
            'Θέμα' => '<strong>' . e($s['subject'] !== '' ? $s['subject'] : '(χωρίς θέμα)') . '</strong>',
            'Συνημμένα' => $files,
        ], fn ($v) => $v !== '');
        $head = '<table style="border-collapse:collapse;margin:0 0 12px;font:14px/1.45 system-ui,\'Segoe UI\',Roboto,Arial,sans-serif;color:#212529">';
        foreach ($rows as $label => $value) {
            $head .= '<tr><th style="text-align:left;vertical-align:top;padding:2px 12px 2px 0;color:#6c757d;font-weight:600;white-space:nowrap">'
                . $label . '</th><td style="padding:2px 0;overflow-wrap:anywhere">' . $value . '</td></tr>';
        }
        $head .= '</table><hr style="border:0;border-top:1px solid #dee2e6;margin:0 0 12px">';

        $html = \App\Support\Mail\MailContent::html($mail);
        if ($html === null || trim($html) === '') {
            return $head . '<pre>' . e(\App\Support\Mail\MailContent::text($mail)) . '</pre>';
        }
        preg_match_all('~<style\b[^>]*>.*?</style>~is', $html, $styles);
        $body = preg_match('~<body\b[^>]*>(.*)</body>~is', $html, $m) ? $m[1] : preg_replace('~<head\b.*?</head>~is', '', $html);
        // what the page's sandbox would block anyway, removed as well
        $body = preg_replace('~<(script|iframe|object|embed|frameset|frame|noscript|template)\b.*?</\1\s*>|<(script|iframe|object|embed|frame|meta|base|link)\b[^>]*>~is', '', (string) $body);
        $body = preg_replace('~</?(html|head|body)\b[^>]*>~i', '', (string) $body);
        return implode('', $styles[0]) . $head . '<div class="mail-body">' . $body . '</div>';
    }

    /** Word (docx) or OpenDocument text (odt) → HTML (PhpWord) */
    private static function word(string $path, string $ext): string
    {
        $reader = \PhpOffice\PhpWord\IOFactory::createReader($ext === 'odt' ? 'ODText' : 'Word2007');
        $document = $reader->load($path);
        // PhpWord writes text as it is unless told to escape it: a document's "<script>" must stay text
        $escaping = \PhpOffice\PhpWord\Settings::isOutputEscapingEnabled();
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);
        try {
            return (new \PhpOffice\PhpWord\Writer\HTML($document))->getContent();
        } finally {
            \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled($escaping);
        }
    }

    /** A spreadsheet → HTML tables, one per sheet (PhpSpreadsheet), limited to MAX_ROWS × MAX_COLS, values as stored */
    private static function sheet(string $path, string $ext): string
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader(match ($ext) {
            'xls' => 'Xls', 'ods' => 'Ods', default => 'Xlsx',
        });
        $reader->setReadFilter(new class implements IReadFilter {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= Preview::MAX_ROWS
                    && \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($columnAddress) <= Preview::MAX_COLS;
            }
        });
        $book = $reader->load($path);
        // formulas: the value the file saved (what its program showed last); calculated here never
        foreach ($book->getAllSheets() as $sheet) {
            foreach ($sheet->getCellCollection()->getCoordinates() as $coordinate) {
                $cell = $sheet->getCell($coordinate);
                if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA && ($saved = $cell->getOldCalculatedValue()) !== null) {
                    $cell->setValueExplicit($saved, is_numeric($saved) ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Html($book);
        $writer->writeAllSheets();
        $writer->setPreCalculateFormulas(false);   // shows the values saved in the file; nothing is calculated here
        $html = $writer->generateHtmlAll();
        $book->disconnectWorksheets();
        return $html;
    }
}
