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
    ];

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
        return in_array($kind, ['text', 'word', 'sheet'], true);
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
        if (filesize($path) > self::maxBytes()) {
            return self::page(self::notice('Το αρχείο είναι πολύ μεγάλο για προεπισκόπηση. Κατεβάστε το για να το δείτε.'));
        }
        try {
            $html = match ($kind) {
                'text' => self::text($path),
                'word' => self::word($path, strtolower(pathinfo($name, PATHINFO_EXTENSION))),
                'sheet' => self::sheet($path, strtolower(pathinfo($name, PATHINFO_EXTENSION))),
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
        if (! mb_check_encoding($text, 'UTF-8')) {
            // UTF-8 cut inside a character at the end is still UTF-8; anything else is taken as Greek Windows-1253
            $probe = $size > self::MAX_TEXT_BYTES ? substr($text, 0, -4) : $text;
            $text = mb_check_encoding($probe, 'UTF-8') ? mb_scrub($text, 'UTF-8') : (string) @iconv('WINDOWS-1253', 'UTF-8//IGNORE', $text);
        }
        $cut = $size > self::MAX_TEXT_BYTES ? '<p class="cut">… εμφανίζεται μόνο η αρχή του αρχείου.</p>' : '';
        return '<pre>' . e($text) . '</pre>' . $cut;
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
