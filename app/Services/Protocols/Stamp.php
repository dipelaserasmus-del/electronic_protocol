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
 * Each save uses its own temporary files (before R1a two users saving at once overwrote each other's).
 */
final class Stamp
{
    /** Extensions that get a stamp; other files are stored as they are. */
    public const STAMPABLE = ['pdf', 'docx', 'odt'];

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
            $stamped = $ext === 'pdf' ? $this->pdf(Storage::get($savedPath)) : $this->word(Storage::path($savedPath), $ext);
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
        $x = str_contains($t->corner, 'right') ? (int) round((210 - $t->x - $w) * $dpmm) : (int) round($t->x * $dpmm);
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
        $right = str_contains($t->corner, 'right');
        $top = str_contains($t->corner, 'top');
        $container->addImage($png, [
            'width' => round($w * $pt, 2), 'height' => round($h * $pt, 2),
            'wrappingStyle' => \PhpOffice\PhpWord\Style\Image::WRAPPING_STYLE_INFRONT,
            'positioning' => \PhpOffice\PhpWord\Style\Image::POSITION_ABSOLUTE,
            'posHorizontalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_PAGE,
            'posVerticalRel' => \PhpOffice\PhpWord\Style\Image::POSITION_RELATIVE_TO_PAGE,
            'posHorizontal' => $right ? \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_RIGHT : \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_LEFT,
            'posVertical' => $top ? \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_TOP : \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_BOTTOM,
            $right ? 'wrapDistanceRight' : 'wrapDistanceLeft' => round($t->x * $pt, 2),
            $top ? 'wrapDistanceTop' : 'wrapDistanceBottom' => round($t->y * $pt, 2),
        ]);
        if ($t->firstPageOnly) {
            $section->getStyle()->setTitlePg(true);   // a first-page header of its own
        }
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
