<?php

namespace App\Services\Stamp\Pdf;

/**
 * R4e/R4g: writes new or changed objects after a PDF's own bytes, with a cross-reference section of the same kind as
 * the file's (a table, or a stream) pointing back to the previous one (/Prev). The bytes before stay as they are.
 */
final class IncrementalUpdate
{
    /** the file with the objects and a new cross-reference section after it */
    /**
     * @param array<int, array{0: int, 1: string}> $objects number => [generation, body]
     * @param string $mark a comment line before the update (to recognise it)
     */
    public function append(string $pdf, PdfReader $reader, array $objects, int $size, string $mark): string
    {
        $out = $pdf;
        if (! str_ends_with($out, "\n")) {
            $out .= "\n";
        }
        $out .= $mark . "\n";
        $offsets = [];
        ksort($objects);
        foreach ($objects as $num => [$gen, $body]) {
            $offsets[$num] = [strlen($out), $gen];
            $out .= "$num $gen obj\n$body\nendobj\n";
        }
        $trailer = ['Size' => $size, 'Root' => $reader->trailer['Root'], 'Prev' => $reader->startxref];
        foreach (['Info', 'ID'] as $key) {
            if (isset($reader->trailer[$key])) {
                $trailer[$key] = $reader->trailer[$key];
            }
        }
        $xrefAt = strlen($out);
        if ($reader->xrefStream) {
            // a cross-reference stream (the file uses them): its own number is the last
            $xrefNum = $size;
            $trailer['Size'] = $size + 1;
            $offsets[$xrefNum] = [$xrefAt, 0];
            ksort($offsets);
            $index = [];
            $rows = '';
            foreach (self::runs(array_keys($offsets)) as [$start, $count]) {
                array_push($index, $start, $count);
                for ($n = $start; $n < $start + $count; $n++) {
                    $rows .= chr(1) . pack('N', $offsets[$n][0]) . pack('n', $offsets[$n][1]);
                }
            }
            $dict = ['Type' => new PdfName('XRef'), 'W' => [1, 4, 2], 'Index' => $index] + $trailer;
            $out .= "$xrefNum 0 obj\n" . PdfParser::dict($dict + ['Length' => strlen($rows)]) . "\nstream\n" . $rows . "\nendstream" . "\nendobj\n";
        } else {
            $out .= "xref\n";
            foreach (self::runs(array_keys($offsets)) as [$start, $count]) {
                $out .= "$start $count\n";
                for ($n = $start; $n < $start + $count; $n++) {
                    $out .= sprintf("%010d %05d n \n", $offsets[$n][0], $offsets[$n][1]);
                }
            }
            $out .= 'trailer' . "\n" . PdfParser::dict($trailer) . "\n";
        }
        return $out . "startxref\n$xrefAt\n%%EOF\n";
    }

    /** consecutive numbers grouped: [[start, count], …] */
    private static function runs(array $numbers): array
    {
        sort($numbers);
        $runs = [];
        foreach ($numbers as $n) {
            $last = count($runs) - 1;
            if ($last >= 0 && $runs[$last][0] + $runs[$last][1] === $n) {
                $runs[$last][1]++;
            } else {
                $runs[] = [$n, 1];
            }
        }
        return $runs;
    }

}
