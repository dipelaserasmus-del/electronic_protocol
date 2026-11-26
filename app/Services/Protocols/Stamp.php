<?php

namespace App\Services\Protocols;

use App\Models\Protocol;
use App\Services\Stamp\Pdf\PdfException;
use App\Services\Stamp\Pdf\PdfStamper;
use App\Services\Stamp\StampRenderer;
use App\Services\Stamp\StampTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * The stamp put on stored attachments (setting putStamp), moved here from ProtocolController (R1a).
 *
 * R4e: drawn from the editable template (StampTemplate, Ρυθμίσεις → Σφραγίδα) at 600 dpi and placed at its real size.
 * PDFs: App\Services\Stamp\Pdf\PdfStamper (an incremental update: first page or every page, the corner of the page as
 * shown). A PDF it cannot read falls back to the old tool (markpdf), with the stamp drawn on a canvas as wide as an A4
 * page so that it still has its size (that tool can only stamp every page). Encrypted PDFs are left as they are.
 * docx / odt: the image in the first section's header, sized in points.
 * R4o (owner, 2026-11-26): jpg / png: the stamp drawn onto the picture (GD), as if the picture were an A4 page (upright
 * or turned), a photo's EXIF orientation applied first; very large pictures are left as they are.
 * Each save uses its own temporary files (before R1a two users saving at once overwrote each other's).
 */
final class Stamp
{
    /** Extensions that get a stamp; other files are stored as they are. */
    public const STAMPABLE = ['pdf', 'docx', 'odt', 'jpg', 'jpeg', 'png'];

    /** R4o: pictures; above MAX_PIXELS they are not stamped (memory: about 5 bytes a pixel while drawing) */
    public const IMAGES = ['jpg', 'jpeg', 'png'];

    public const MAX_PIXELS = 30_000_000;

    private ?\GdImage $image = null;

    private ?StampTemplate $template = null;

    /** @var list<string> temporary files */
    private array $temp = [];

    /** Draw the stamp for this protocol. Returns false (no stamping) when it can't be drawn. */
    public function prepare(Protocol $protocol, ?StampTemplate $template = null): bool
    {
        $this->cleanup();
        try {
            $this->template = $template ?? StampTemplate::current();
            $this->image = (new StampRenderer())->render($this->template, StampTemplate::valuesFor($protocol), StampRenderer::PRINT_DPI);
        } catch (\Throwable $e) {
            report($e);
            $this->image = null;
            return false;
        }
        return true;
    }

    /** R4n: the places a user may choose for one file (attachments.stamp_place), shown in the preview */
    public const PLACES = ['top-left' => 'πάνω αριστερά', 'top-center' => 'πάνω στο κέντρο', 'top-right' => 'πάνω δεξιά'];

    /**
     * R4n (owner, 2026-11-26): this file's own place instead of the stamp's (when the usual place hides something). The
     * distance from the top stays the stamp's; top-center ignores the distance from the side.
     */
    public function placeAt(?string $place): void
    {
        if ($this->template !== null && isset(self::PLACES[$place ?? ''])) {
            $this->template = clone $this->template;
            $this->template->corner = $place;
        }
    }

    public function ready(): bool
    {
        return $this->image !== null;
    }

    /**
     * Stamp a stored file (default disk) in place. A file type that takes no stamp, or any failure, leaves the stored
     * file exactly as it was.
     */
    public function apply(string $savedPath): void
    {
        $ext = strtolower(pathinfo($savedPath, PATHINFO_EXTENSION));
        if (! $this->ready() || ! in_array($ext, self::STAMPABLE, true) || ! Storage::exists($savedPath)) {
            return;
        }
        try {
            $stamped = match (true) {
                $ext === 'pdf' => $this->pdf(Storage::get($savedPath)),
                in_array($ext, self::IMAGES, true) => $this->picture(Storage::path($savedPath), $ext),
                default => $this->word(Storage::path($savedPath), $ext),
            };
            if ($stamped !== null && $stamped !== '') {
                Storage::put($savedPath, $stamped);
            }
        } catch (\Throwable $e) {
            report($e);
        } finally {
            $this->removeTemp();
        }
    }

    /** the stamped PDF, or null to keep it as it is */
    public function pdf(string $pdf): ?string
    {
        [$w, $h] = StampRenderer::sizeMm($this->image, StampRenderer::PRINT_DPI);
        $t = $this->template;
        try {
            return (new PdfStamper())->stamp($pdf, $this->image, [$w, $h], $t->corner, [$t->x, $t->y], $t->firstPageOnly);
        } catch (PdfException $e) {
            if ($e->getMessage() === 'encrypted') {
                return null;
            }
            return $this->markpdf($pdf, $w, $h);
        }
    }

    /** the old tool: the stamp on an A4-wide transparent canvas, scaled by the tool to the page's width */
    private function markpdf(string $pdf, float $w, float $h): ?string
    {
        $t = $this->template;
        $dpmm = StampRenderer::PRINT_DPI / 25.4;
        $canvas = StampRenderer::canvas((int) round(210 * $dpmm), imagesy($this->image));
        $x = match (true) {
            str_contains($t->corner, 'center') => (int) round((210 - $w) / 2 * $dpmm),   // R4n
            str_contains($t->corner, 'right') => (int) round((210 - $t->x - $w) * $dpmm),
            default => (int) round($t->x * $dpmm),
        };
        imagecopy($canvas, $this->image, max(0, $x), 0, 0, 0, imagesx($this->image), imagesy($this->image));
        $png = $this->tempPath('png');
        imagepng($canvas, $png);
        [$in, $out] = [$this->tempPath('pdf'), $this->tempPath('pdf')];
        file_put_contents($in, $pdf);
        $y = round($t->y * 72 / 25.4, 2);
        $bin = realpath(base_path('vendor/yasapurnama/document-watermark/bin/markpdf_linux-amd64'));
        if (! $bin) {
            return null;
        }
        $cmd = [$bin, $in, $png, $out, '-w', '-y', (str_contains($t->corner, 'bottom') ? '-' : '') . $y, '-o', '1'];
        exec(implode(' ', array_map('escapeshellarg', $cmd)) . ' 2>&1', $output, $code);
        return $code === 0 && is_file($out) && filesize($out) > 0 ? file_get_contents($out) : null;
    }

    /**
     * R4o: jpg / png: the stamp drawn on the picture. A picture has pixels, not mm: it is taken as an A4 page (210 mm wide
     * when upright, 297 mm when wider than high), so the stamp has the same size as on a scanned PDF. A photo's EXIF
     * orientation is applied first (so the top is the top as shown); the copy is saved without EXIF. Null (left as it
     * is): unreadable, or more than MAX_PIXELS.
     */
    private function picture(string $path, string $ext): ?string
    {
        $info = @getimagesize($path);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }
        $png = $info[2] === IMAGETYPE_PNG;
        $img = $png ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
        if ($img === false) {
            return null;
        }
        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);   // a palette PNG: the stamp's colours would be lost
        }
        if (! $png && function_exists('exif_read_data')) {
            $turn = match ((int) (@exif_read_data($path)['Orientation'] ?? 1)) {
                3 => 180, 6 => 270, 8 => 90,   // imagerotate turns anticlockwise
                default => 0,
            };
            if ($turn !== 0) {
                $img = imagerotate($img, $turn, 0);
            }
        }
        [$pw, $ph] = [imagesx($img), imagesy($img)];
        $pxPerMm = $pw / ($pw > $ph ? 297 : 210);
        [$wMm, $hMm] = StampRenderer::sizeMm($this->image, StampRenderer::PRINT_DPI);
        [$sw, $sh] = [max(1, (int) round($wMm * $pxPerMm)), max(1, (int) round($hMm * $pxPerMm))];
        $t = $this->template;
        [$ox, $oy] = [(int) round($t->x * $pxPerMm), (int) round($t->y * $pxPerMm)];
        $x = match (true) {
            str_contains($t->corner, 'center') => intdiv($pw - $sw, 2),
            str_contains($t->corner, 'right') => $pw - $ox - $sw,
            default => $ox,
        };
        $y = str_contains($t->corner, 'bottom') ? $ph - $oy - $sh : $oy;
        if ($png) {
            imagealphablending($img, true);
            imagesavealpha($img, true);
        }
        imagecopyresampled($img, $this->image, $x, $y, 0, 0, $sw, $sh, imagesx($this->image), imagesy($this->image));
        ob_start();
        $png ? imagepng($img, null, 6) : imagejpeg($img, null, 92);
        return (string) ob_get_clean() ?: null;
    }

    /** docx / odt: the stamp in the header of the first section (every page, or the first page only) */
    private function word(string $path, string $ext): ?string
    {
        $t = $this->template;
        $png = $this->tempPath('png');
        imagepng($this->image, $png);
        [$w, $h] = StampRenderer::sizeMm($this->image, StampRenderer::PRINT_DPI);
        $pt = 72 / 25.4;
        $out = $this->tempPath($ext);
        $doc = \PhpOffice\PhpWord\IOFactory::load($path, $ext === 'odt' ? 'ODText' : 'Word2007');
        $section = $doc->getSections()[0] ?? null;
        if ($section === null) {
            return null;
        }
        $type = $t->firstPageOnly ? \PhpOffice\PhpWord\Element\Footer::FIRST : \PhpOffice\PhpWord\Element\Footer::AUTO;
        $container = str_contains($t->corner, 'top') ? $section->addHeader($type) : $section->addFooter($type);
        // placed from the page's own size (twips → points), the stamp's distance from the corner kept
        $style = $section->getStyle();
        [$pageW, $pageH] = [($style->getPageSizeW() ?: 11906) / 20, ($style->getPageSizeH() ?: 16838) / 20];
        $left = match (true) {
            str_contains($t->corner, 'center') => ($pageW - $w * $pt) / 2,   // R4n
            str_contains($t->corner, 'right') => $pageW - ($t->x + $w) * $pt,
            default => $t->x * $pt,
        };
        $top = str_contains($t->corner, 'top') ? $t->y * $pt : $pageH - ($t->y + $h) * $pt;
        $container->addImage($png, [
            'width' => round($w * $pt, 2), 'height' => round($h * $pt, 2),
            'wrappingStyle' => \PhpOffice\PhpWord\Style\Image::WRAPPING_STYLE_INFRONT,
            'positioning' => \PhpOffice\PhpWord\Style\Image::POSITION_ABSOLUTE,
            'posHorizontal' => \PhpOffice\PhpWord\Style\Image::POSITION_ABSOLUTE,
            'posVertical' => \PhpOffice\PhpWord\Style\Image::POSITION_ABSOLUTE,
            'posHorizontalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_PAGE,
            'posVerticalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_PAGE,
            'marginLeft' => round($left, 2), 'marginTop' => round($top, 2),
        ]);
        $doc->save($out, $ext === 'odt' ? 'ODText' : 'Word2007');
        return is_file($out) ? file_get_contents($out) : null;
    }

    public function cleanup(): void
    {
        $this->image = null;
        $this->template = null;
        $this->removeTemp();
    }

    public function __destruct()
    {
        $this->removeTemp();
    }

    private function removeTemp(): void
    {
        foreach ($this->temp as $f) {
            @unlink($f);
        }
        $this->temp = [];
    }

    private function tempPath(string $ext): string
    {
        return $this->temp[] = sys_get_temp_dir() . '/ep-stamp-' . bin2hex(random_bytes(8)) . '.' . $ext;
    }
}
