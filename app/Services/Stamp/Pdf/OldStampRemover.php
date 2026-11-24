<?php

namespace App\Services\Stamp\Pdf;

/**
 * R4g: the stamp of the old tool (markpdf, before R4e/R4f) taken out of a PDF.
 *
 * That tool rewrote the whole file (Producer "UniDoc") and added to the end of every page's drawing exactly
 *     q /GS0 gs 1 0 0 1 X Y cm W 0 0 H 0 0 cm /Img1 Do Q
 * where Img1 is one image of W × H pixels (it drew one pixel per point), the same image on every page. Only a file that
 * shows this on every page, with that one image, counts as stamped by it; the drawing is then given back without that
 * last block (as an incremental update: the bytes before stay as they are). The result looks like the file before the
 * stamp; its bytes are those of the old tool's rewrite, not of the file first uploaded (that is gone).
 */
final class OldStampRemover
{
    private const TAIL = '~\s*q\s+/(GS\d+)\s+gs\s+1(?:\.0+)?\s+0(?:\.0+)?\s+0(?:\.0+)?\s+1(?:\.0+)?\s+[-\d.]+\s+[-\d.]+\s+cm\s+([\d.]+)\s+0(?:\.0+)?\s+0(?:\.0+)?\s+([\d.]+)\s+0(?:\.0+)?\s+0(?:\.0+)?\s+cm\s+/(Img\d+)\s+Do\s+Q\s*$~';

    /**
     * What is in the file: ['stamped' => bool, 'reason' => string, 'pages' => int]
     */
    public function analyse(string $pdf): array
    {
        try {
            $reader = new PdfReader($pdf);
            if ($reader->encrypted()) {
                return ['stamped' => false, 'reason' => 'κρυπτογραφημένο', 'pages' => 0];
            }
            $found = $this->find($reader);
            return ['stamped' => $found !== null, 'reason' => $found === null ? $this->why : 'σφραγίδα του παλιού εργαλείου', 'pages' => count($reader->pages())];
        } catch (PdfException $e) {
            return ['stamped' => false, 'reason' => 'δεν διαβάζεται (' . $e->getMessage() . ')', 'pages' => 0];
        }
    }

    /** The file without the old stamp; PdfException when it does not carry exactly that stamp */
    public function remove(string $pdf): string
    {
        $reader = new PdfReader($pdf);
        if ($reader->encrypted()) {
            throw new PdfException('encrypted');
        }
        $found = $this->find($reader) ?? throw new PdfException($this->why);
        $next = $reader->size();
        $objects = [];
        foreach ($found as [$num, $page, $content, $gs, $img]) {
            $streamNum = $next++;
            $data = gzcompress($content, 9);
            $objects[$streamNum] = [0, PdfParser::dict(['Length' => strlen($data), 'Filter' => new PdfName('FlateDecode')]) . "\nstream\n$data\nendstream"];
            $resources = $reader->resolve($page['Resources'] ?? []);
            $resources = is_array($resources) ? $resources : [];
            foreach (['XObject' => $img, 'ExtGState' => $gs] as $kind => $name) {
                $list = $reader->resolve($resources[$kind] ?? null);
                if (is_array($list) && ! preg_match('~/' . preg_quote($name, '~') . '\b~', $content)) {
                    unset($list[$name]);   // no longer used by the page
                    $resources[$kind] = $list ?: [PdfParser::EMPTY_DICT => true];
                }
            }
            $page['Resources'] = $resources;
            $page['Contents'] = new PdfRef($streamNum);
            unset($page[PdfParser::EMPTY_DICT]);
            $objects[$num] = [$reader->generation($num), PdfParser::dict($page)];
        }
        return (new IncrementalUpdate())->append($pdf, $reader, $objects, $next, "%EP-UNSTAMP");
    }

    private string $why = '';

    /** @return list<array{0: int, 1: array, 2: string, 3: string, 4: string}>|null page, dict, drawing without the stamp, GS, image */
    private function find(PdfReader $reader): ?array
    {
        $producer = $reader->resolve(($info = $reader->resolve($reader->trailer['Info'] ?? null)) ? ($info['Producer'] ?? null) : null);
        if (! $producer instanceof PdfString || stripos($producer->bytes, 'unidoc') === false) {
            $this->why = 'δεν το έγραψε το παλιό εργαλείο σφραγίδας';
            return null;
        }
        $found = [];
        $image = null;
        foreach ($reader->pages() as [$num, $page]) {
            $data = '';
            $contents = $reader->resolve($page['Contents'] ?? []);
            foreach ($contents instanceof PdfStream ? [$contents] : (is_array($contents) ? $contents : []) as $ref) {
                $s = $reader->resolve($ref);
                if (! $s instanceof PdfStream) {
                    $this->why = 'σελίδα χωρίς σχέδιο';
                    return null;
                }
                $data .= $reader->decode($s) . "\n";
            }
            if (! preg_match(self::TAIL, $data, $m, PREG_OFFSET_CAPTURE)) {
                $this->why = 'δεν βρέθηκε η σφραγίδα σε όλες τις σελίδες';
                return null;
            }
            [$gs, $w, $h, $img] = [$m[1][0], (float) $m[2][0], (float) $m[3][0], $m[4][0]];
            $resources = $reader->resolve($page['Resources'] ?? []);
            $xobjects = is_array($resources) ? $reader->resolve($resources['XObject'] ?? null) : null;
            $ref = is_array($xobjects) ? ($xobjects[$img] ?? null) : null;
            $object = $reader->resolve($ref);
            $same = $ref instanceof PdfRef && ($image === null || $image === $ref->num);
            if (! $object instanceof PdfStream || ! $same
                || abs((float) ($object->dict['Width'] ?? 0) - $w) > 0.01 || abs((float) ($object->dict['Height'] ?? 0) - $h) > 0.01) {
                $this->why = 'η εικόνα στο τέλος δεν είναι η σφραγίδα';
                return null;
            }
            $image = $ref->num;
            $found[] = [$num, $page, substr($data, 0, $m[0][1]) . "\n", $gs, $img];
        }
        return $found;
    }
}
