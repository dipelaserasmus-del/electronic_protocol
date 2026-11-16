<?php

namespace App\Services\Protocols;

use App\Models\Protocol;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Yasapurnama\DocumentWatermark\WatermarkFactory;

/**
 * The "Αρ.Πρ." stamp put on stored attachments (setting putStamp), moved here from ProtocolController (R1a).
 *
 * Before, the stamp image and the files being stamped were written to the web server's working folder under fixed
 * names (arPr.png, toStamp.pdf, stamped.pdf): two users saving at the same moment overwrote each other's files, and
 * the stamp image could be downloaded from the public folder. Now each save uses its own temporary files.
 */
final class Stamp
{
    /** Extensions the watermark library can stamp; other files are stored as they are. */
    public const STAMPABLE = ['pdf', 'docx', 'odt'];

    private ?string $image = null;

    /** Draw the stamp for this protocol. Returns false (no stamping) when it can't be drawn. */
    public function prepare(Protocol $protocol): bool
    {
        $this->cleanup();
        $date = $protocol->dateOf('protocoldate')?->format('d-m-Y') ?? Carbon::now()->format('d-m-Y');
        $txt = \App\Models\Config::getConfigValueOf('ipiresiasName') . "\nΑρ.Πρ: {$protocol->protocolnum} / $date";
        $font = config('stamp.font');
        $size = config('stamp.font_size');
        try {
            $box = imagettfbbox($size, 0, $font, $txt);
            $w = abs($box[4] - $box[0]) + 10;
            $h = abs($box[5] - $box[1]) + 10;
            $img = imagecreate($w, $h);
            $text = imagecolorallocate($img, config('stamp.txtR'), config('stamp.txtG'), config('stamp.txtB'));
            $border = imagecolorallocate($img, config('stamp.brdR'), config('stamp.brdG'), config('stamp.brdB'));
            $bg = imagecolorallocate($img, config('stamp.bgR'), config('stamp.bgG'), config('stamp.bgB'));
            if (config('stamp.transparent')) {
                imagecolortransparent($img, $bg);
            }
            imagefilledrectangle($img, 0, 0, $w, $h, $bg);
            imagerectangle($img, 0, 0, $w - 1, $h - 1, $border);
            imagettftext($img, $size, 0, 5, 5 + $size, $text, $font, $txt);
            $path = $this->tempPath('png');
            imagepng($img, $path);
            imagedestroy($img);
            $this->image = $path;
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    public function ready(): bool
    {
        return $this->image !== null;
    }

    /**
     * Stamp a stored file (default disk) in place. A file type the library can't handle, or any failure, leaves the
     * stored file exactly as it was.
     */
    public function apply(string $savedPath): void
    {
        $ext = strtolower(pathinfo($savedPath, PATHINFO_EXTENSION));
        if (! $this->ready() || ! in_array($ext, self::STAMPABLE, true)) {
            return;
        }
        $in = $this->tempPath($ext);
        $out = $this->tempPath($ext);
        try {
            copy(Storage::path($savedPath), $in);
            $w = WatermarkFactory::load($in)->outputFile($out)->setImage($this->image)->opacity(config('stamp.opacity'));
            $w = config('stamp.section') == 'header' ? $w->sectionHeader() : $w->sectionFooter();
            $w = config('stamp.align') == 'left'
                ? $w->alignLeft(config('stamp.x'), config('stamp.y'))
                : $w->alignRight(config('stamp.x'), config('stamp.y'));
            if (config('stamp.onlyFirstPage')) {
                $w = $w->onlyFirstPage();
            }
            $w->generate();
            if (is_file($out) && filesize($out) > 0) {
                Storage::put($savedPath, file_get_contents($out));
            }
        } catch (\Throwable $e) {
            report($e);
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    public function cleanup(): void
    {
        if ($this->image !== null) {
            @unlink($this->image);
            $this->image = null;
        }
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    private function tempPath(string $ext): string
    {
        return sys_get_temp_dir() . '/ep-stamp-' . bin2hex(random_bytes(8)) . '.' . $ext;
    }
}
