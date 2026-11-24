<?php

namespace Tests\Support;

/**
 * R4e: small PDFs of the kinds real files use, for the stamper's tests: a classic cross-reference table, or a
 * cross-reference stream (PNG predictor 12) with the page objects inside an object stream; pages with /Rotate,
 * resources inherited from the page tree or given by reference, /Contents as an array.
 */
final class PdfBuilder
{
    /**
     * @param list<array{rotate?: int, box?: list<float>, crop?: list<float>, text?: string}> $pages
     */
    public static function make(array $pages = [[]], bool $xrefStream = false): string
    {
        $objects = [];   // number => body (null: placed in the object stream)
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Font << /F1 4 0 R >> >>';   // shared resources, inherited from the page tree
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $kids = [];
        $n = 5;
        $inStream = [];
        foreach ($pages as $i => $p) {
            $pageNum = $n++;
            $c1 = $n++;
            $c2 = $n++;
            $kids[] = "$pageNum 0 R";
            $text = $p['text'] ?? 'Page ' . ($i + 1);
            $objects[$c1] = self::stream("BT /F1 24 Tf 72 700 Td ($text) Tj ET");
            $objects[$c2] = self::stream('0 0 1 rg 72 600 200 50 re f');
            $dict = '<< /Type /Page /Parent 2 0 R /Contents [' . "$c1 0 R $c2 0 R]";
            if (isset($p['box'])) {
                $dict .= ' /MediaBox [' . implode(' ', $p['box']) . ']';
            }
            if (isset($p['crop'])) {
                $dict .= ' /CropBox [' . implode(' ', $p['crop']) . ']';
            }
            if (isset($p['rotate'])) {
                $dict .= ' /Rotate ' . $p['rotate'];
            }
            if ($i === 1) {
                $dict .= ' /Resources 3 0 R';   // by reference, the second page
            }
            $objects[$pageNum] = $dict . ' >>';
            if ($xrefStream) {
                $inStream[] = $pageNum;
            }
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($pages) . ' /Resources 3 0 R /MediaBox [0 0 595 842] >>';
        if ($xrefStream) {
            $inStream = array_merge([1, 2, 3, 4], $inStream);
        }
        ksort($objects);

        $pdf = "%PDF-1.7\n%\xE2\xE3\xCF\xD3\n";
        $entries = [];   // number => [type, a, b]
        $objStmNum = $n++;
        if ($inStream) {
            $header = '';
            $body = '';
            foreach ($inStream as $k => $num) {
                $header .= "$num " . strlen($body) . ' ';
                $body .= $objects[$num] . "\n";
                $entries[$num] = [2, $objStmNum, $k];
            }
            $data = gzcompress($header . $body);
            $entries[$objStmNum] = [1, strlen($pdf), 0];
            $pdf .= "$objStmNum 0 obj\n<< /Type /ObjStm /N " . count($inStream) . ' /First ' . strlen($header) . ' /Filter /FlateDecode /Length ' . strlen($data) . " >>\nstream\n$data\nendstream\nendobj\n";
        }
        foreach ($objects as $num => $body) {
            if (in_array($num, $inStream, true)) {
                continue;
            }
            $entries[$num] = [1, strlen($pdf), 0];
            $pdf .= "$num 0 obj\n$body\nendobj\n";
        }
        $size = max(array_keys($entries)) + 2;
        if ($xrefStream) {
            $xrefNum = $size - 1;
            $entries[$xrefNum] = [1, strlen($pdf), 0];
            $rows = '';
            $previous = str_repeat("\0", 7);
            for ($i = 0; $i < $size; $i++) {
                [$t, $a, $b] = $entries[$i] ?? [0, 0, 65535];
                $row = chr($t) . pack('N', $a) . pack('n', $b);
                $up = '';
                for ($j = 0; $j < 7; $j++) {
                    $up .= chr((ord($row[$j]) - ord($previous[$j])) & 0xFF);   // PNG "Up" filter
                }
                $rows .= "\x02" . $up;
                $previous = $row;
            }
            $data = gzcompress($rows);
            $at = strlen($pdf);
            $pdf .= "$xrefNum 0 obj\n<< /Type /XRef /Size $size /W [1 4 2] /Root 1 0 R /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 7 >> /Length " . strlen($data) . " >>\nstream\n$data\nendstream\nendobj\n";
            return $pdf . "startxref\n$at\n%%EOF\n";
        }
        $at = strlen($pdf);
        $pdf .= "xref\n0 $size\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= isset($entries[$i]) ? sprintf("%010d 00000 n \n", $entries[$i][1]) : "0000000000 65535 f \n";
        }
        return $pdf . "trailer\n<< /Size $size /Root 1 0 R >>\nstartxref\n$at\n%%EOF\n";
    }

    private static function stream(string $data): string
    {
        return '<< /Length ' . strlen($data) . " >>\nstream\n$data\nendstream";
    }
}
