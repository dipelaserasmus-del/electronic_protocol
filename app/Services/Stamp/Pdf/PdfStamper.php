<?php

namespace App\Services\Stamp\Pdf;

/**
 * R4e: puts the stamp on a PDF as an incremental update: the original bytes stay as they are and new objects are
 * appended after them (the image, a drawing per page, the changed pages, a new cross-reference section). So:
 * - the stamp has its exact size in mm, from a 600 dpi image (the old tool drew one pixel per point);
 * - it can go on the first page only;
 * - it sits at the corner of the page as shown (/Rotate and /CropBox respected), upright;
 * - the original is recoverable: everything after MARK can be cut off (R4g).
 * Encrypted PDFs and PDFs this reader cannot follow throw PdfException (the caller keeps the file as it is).
 */
final class PdfStamper
{
    /** written before the update, so an added stamp can be recognised (and removed) */
    public const MARK = '%EP-STAMP';

    private const NAME = 'EPStamp';

    /**
     * @param \GdImage $image the stamp, true colour with transparency
     * @param array{0: float, 1: float} $sizeMm its printed size
     * @param string $corner top-right | top-left | bottom-right | bottom-left
     * @param array{0: float, 1: float} $offsetMm distance from the corner (side, top/bottom)
     */
    public function stamp(string $pdf, \GdImage $image, array $sizeMm, string $corner, array $offsetMm, bool $firstPageOnly): string
    {
        $reader = new PdfReader($pdf);
        if ($reader->encrypted()) {
            throw new PdfException('encrypted');
        }
        $pages = $reader->pages();
        if ($firstPageOnly) {
            $pages = array_slice($pages, 0, 1);
        }

        $next = $reader->size();
        $objects = [];   // number => [generation, body]
        [$rgb, $alpha, $w, $h] = self::pixels($image);
        $maskNum = $next++;
        $imageNum = $next++;
        $objects[$maskNum] = [0, self::stream(['Type' => new PdfName('XObject'), 'Subtype' => new PdfName('Image'),
            'Width' => $w, 'Height' => $h, 'ColorSpace' => new PdfName('DeviceGray'), 'BitsPerComponent' => 8,
            'Filter' => new PdfName('FlateDecode')], gzcompress($alpha, 9))];
        $objects[$imageNum] = [0, self::stream(['Type' => new PdfName('XObject'), 'Subtype' => new PdfName('Image'),
            'Width' => $w, 'Height' => $h, 'ColorSpace' => new PdfName('DeviceRGB'), 'BitsPerComponent' => 8,
            'Interpolate' => true, 'SMask' => new PdfRef($maskNum), 'Filter' => new PdfName('FlateDecode')], gzcompress($rgb, 9))];
        $saveNum = $next++;
        $objects[$saveNum] = [0, self::stream([], 'q')];   // the page's own drawing between q … Q, ours after it

        $mm = 72 / 25.4;
        [$sw, $sh] = [$sizeMm[0] * $mm, $sizeMm[1] * $mm];
        [$ox, $oy] = [$offsetMm[0] * $mm, $offsetMm[1] * $mm];
        foreach ($pages as [$num, $page, $inherited]) {
            $box = self::box($reader, $page['CropBox'] ?? $inherited['CropBox'] ?? $page['MediaBox'] ?? $inherited['MediaBox'] ?? null);
            $rotate = (((int) $reader->resolve($page['Rotate'] ?? $inherited['Rotate'] ?? 0)) % 360 + 360) % 360;
            [$x0, $y0, $x1, $y1] = $box;
            [$bw, $bh] = [$x1 - $x0, $y1 - $y0];
            [$dw, $dh] = $rotate % 180 ? [$bh, $bw] : [$bw, $bh];   // the page as shown
            $u = str_contains($corner, 'right') ? $dw - $ox - $sw : $ox;
            $v = str_contains($corner, 'top') ? $dh - $oy - $sh : $oy;
            // shown (u, v) → the page's own coordinates
            $map = fn (float $u, float $v) => match ($rotate) {
                90 => [$x0 + $bw - $v, $y0 + $u],
                180 => [$x0 + $bw - $u, $y0 + $bh - $v],
                270 => [$x0 + $v, $y0 + $bh - $u],
                default => [$x0 + $u, $y0 + $v],
            };
            [$ex, $ey] = $map($u, $v);
            [$ax, $ay] = $map($u + $sw, $v);
            [$cx, $cy] = $map($u, $v + $sh);
            $matrix = array_map([PdfParser::class, 'number'], [$ax - $ex, $ay - $ey, $cx - $ex, $cy - $ey, $ex, $ey]);
            $drawNum = $next++;
            $objects[$drawNum] = [0, self::stream([], "Q q " . implode(' ', $matrix) . ' cm /' . self::NAME . ' Do Q')];

            // the page: its drawing wrapped, our image among its resources
            $contents = $reader->resolve($page['Contents'] ?? null);
            $contents = match (true) {
                $contents === null => [],
                $page['Contents'] instanceof PdfRef && ! is_array($contents) => [$page['Contents']],
                is_array($contents) && array_is_list($contents) => $contents,
                default => throw new PdfException('bad /Contents'),
            };
            $resources = $reader->resolve($page['Resources'] ?? $inherited['Resources'] ?? []);
            $resources = is_array($resources) ? $resources : [];
            $xobjects = $reader->resolve($resources['XObject'] ?? []);
            $xobjects = is_array($xobjects) ? $xobjects : [];
            unset($resources[PdfParser::EMPTY_DICT], $xobjects[PdfParser::EMPTY_DICT]);
            $xobjects[self::NAME] = new PdfRef($imageNum);
            $resources['XObject'] = $xobjects;
            $page['Resources'] = $resources;
            $page['Contents'] = [new PdfRef($saveNum), ...$contents, new PdfRef($drawNum)];
            unset($page[PdfParser::EMPTY_DICT]);
            $objects[$num] = [$reader->generation($num), PdfParser::dict($page)];
        }

        return (new IncrementalUpdate())->append($pdf, $reader, $objects, $next, self::MARK);
    }

    private static function stream(array $dict, string $data): string
    {
        $dict['Length'] = strlen($data);
        return PdfParser::dict($dict) . "\nstream\n" . $data . "\nendstream";
    }

    /** [x0, y0, x1, y1] of a box (A4 when missing) */
    private static function box(PdfReader $reader, mixed $box): array
    {
        $box = $reader->resolve($box);
        if (! is_array($box) || count($box) !== 4) {
            return [0, 0, 595.28, 841.89];
        }
        $b = array_map(fn ($v) => (float) $reader->resolve($v), $box);
        return [min($b[0], $b[2]), min($b[1], $b[3]), max($b[0], $b[2]), max($b[1], $b[3])];
    }

    /** the image's colours and transparency, row by row from the top: [rgb, alpha, width, height] */
    private static function pixels(\GdImage $img): array
    {
        [$w, $h] = [imagesx($img), imagesy($img)];
        $rgb = '';
        $alpha = '';
        $ink = "\0\0\0";   // transparent pixels take the last ink colour: no dark fringes when the image is smoothed
        for ($y = 0; $y < $h; $y++) {
            $rowRgb = '';
            $rowAlpha = '';
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = 127 - (($c >> 24) & 0x7F);
                $rowAlpha .= chr((int) round($a * 255 / 127));
                if ($a > 0) {
                    $ink = chr(($c >> 16) & 0xFF) . chr(($c >> 8) & 0xFF) . chr($c & 0xFF);
                }
                $rowRgb .= $ink;
            }
            $rgb .= $rowRgb;
            $alpha .= $rowAlpha;
        }
        return [$rgb, $alpha, $w, $h];
    }
}
