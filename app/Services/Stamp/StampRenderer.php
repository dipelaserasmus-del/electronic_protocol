<?php

namespace App\Services\Stamp;

/**
 * R4e: draws a StampTemplate with GD: at 600 dpi for the files (sharp in print), lower for the editor's preview.
 * Sizes are real: the width in mm, text sizes in points, so the printed stamp measures what the template says.
 *
 * Fonts: the Arial Greek files the application already ships (public/fonts); a narrower look comes from "condense"
 * (the line is drawn and then narrowed), letter spacing from "spacing" (% of the text size).
 */
final class StampRenderer
{
    public const PRINT_DPI = 600;

    public const PREVIEW_DPI = 200;

    /** an emblem larger than this (e.g. 6000 × 6000) is not opened */
    public const MAX_EMBLEM_PIXELS = 36_000_000;

    public function regularFont(): string
    {
        return public_path('fonts/copyfonts.com_arial-greek.ttf');
    }

    public function boldFont(): string
    {
        return public_path('fonts/Arialbd.ttf');
    }

    /** The stamp as a true-colour image with transparency, $dpi pixels per inch */
    public function render(StampTemplate $t, array $values, int $dpi = self::PRINT_DPI): \GdImage
    {
        $mm = $dpi / 25.4;
        $width = max(8, (int) round($t->width * $mm));
        $pad = (int) round(($t->border ? 1.6 : 0.5) * $mm);
        $inner = max(4, $width - 2 * $pad);
        [$r, $g, $b] = $t->rgb();

        /** @var list<array{0: \GdImage, 1: string, 2: bool}> $blocks image, alignment, on opaque white */
        $blocks = [];
        if ($t->emblem && ($emblem = $this->emblem($t, $mm, $inner))) {
            $blocks[] = [$emblem, 'center', false];
        }
        foreach ($t->lines as $line) {
            $blocks[] = [$this->line($line, $values, $dpi, $inner, [$r, $g, $b]), $line['dots'] ? 'left' : $line['align'], self::opaque($line['text'])];
        }

        $gap = (int) round($t->gap * $mm);
        $height = 2 * $pad + array_sum(array_map(fn ($bl) => imagesy($bl[0]), $blocks)) + $gap * max(0, count($blocks) - 1);
        $height = max($height, 2 * $pad + 1);
        $img = self::canvas($width, $height);
        $y = $pad;
        $white = imagecolorallocatealpha($img, 255, 255, 255, 0);
        $margin = (int) round(0.35 * $mm);
        foreach ($blocks as [$block, $align, $opaque]) {
            $w = imagesx($block);
            $x = $pad + match ($align) { 'right' => $inner - $w, 'center' => intdiv($inner - $w, 2), default => 0 };
            if ($opaque) {
                // R4k: the number and the date on white, so nothing printed under the stamp hides them
                imagefilledrectangle($img, max(0, $x - $margin), max(0, $y - $margin), min($width - 1, $x + $w - 1 + $margin),
                    min($height - 1, $y + imagesy($block) - 1 + $margin), $white);
            }
            imagecopy($img, $block, $x, $y, 0, 0, $w, imagesy($block));
            $y += imagesy($block) + $gap;
        }
        if ($t->border) {
            $colour = imagecolorallocatealpha($img, $r, $g, $b, 0);
            $thick = max(1, (int) round(0.3 * $mm));
            imagesetthickness($img, 1);
            for ($i = 0; $i < $thick; $i++) {
                imagerectangle($img, $i, $i, $width - 1 - $i, $height - 1 - $i, $colour);
            }
        }
        return $img;
    }

    /**
     * R4k (owner, 2026-11-25): a line that shows the protocol's number, date or year is drawn on opaque white (text or
     * pictures under the stamp must never hide them); the other lines stay transparent
     */
    public static function opaque(string $text): bool
    {
        return (bool) preg_match('/\{(αριθμός|ημερομηνία|έτος)\}/u', $text);
    }

    /** PNG bytes (the editor's preview) */
    public function png(StampTemplate $t, array $values, int $dpi = self::PREVIEW_DPI): string
    {
        $img = $this->render($t, $values, $dpi);
        ob_start();
        imagepng($img);
        return (string) ob_get_clean();
    }

    /** Size in mm of the drawn stamp: [width, height] */
    public static function sizeMm(\GdImage $img, int $dpi): array
    {
        return [imagesx($img) * 25.4 / $dpi, imagesy($img) * 25.4 / $dpi];
    }

    public static function canvas(int $w, int $h): \GdImage
    {
        $img = imagecreatetruecolor(max(1, $w), max(1, $h));
        imagealphablending($img, false);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);
        imagesavealpha($img, true);
        return $img;
    }

    private function emblem(StampTemplate $t, float $mm, int $inner): ?\GdImage
    {
        $path = $t->emblemFile ?? StampTemplate::emblemPath();
        $info = $path ? @getimagesize($path) : false;
        if (! $info || $info[0] * $info[1] > self::MAX_EMBLEM_PIXELS) {
            return null;   // not an image, or too large to open safely
        }
        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source) {
            return null;
        }
        $h = max(1, (int) round($t->emblemHeight * $mm));
        $w = max(1, (int) round(imagesx($source) * $h / max(1, imagesy($source))));
        if ($w > $inner) {
            [$w, $h] = [$inner, max(1, (int) round($h * $inner / $w))];
        }
        $img = self::canvas($w, $h);
        imagealphablending($img, false);
        imagecopyresampled($img, $source, 0, 0, 0, 0, $w, $h, imagesx($source), imagesy($source));
        if ($t->tint) {
            [$r, $g, $b] = $t->rgb();
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    $c = imagecolorat($img, $x, $y);
                    $alpha = (127 - (($c >> 24) & 0x7F)) / 127;
                    $light = ((($c >> 16) & 0xFF) * 0.299 + (($c >> 8) & 0xFF) * 0.587 + ($c & 0xFF) * 0.114) / 255;
                    $a = $alpha * (1 - $light);   // dark = ink, light = paper
                    imagesetpixel($img, $x, $y, imagecolorallocatealpha($img, $r, $g, $b, 127 - (int) round($a * 127)));
                }
            }
        }
        imagealphablending($img, true);
        return $img;
    }

    /** One line, as wide as it needs (field lines: the whole width), never wider than $inner */
    private function line(array $line, array $values, int $dpi, int $inner, array $rgb): \GdImage
    {
        $font = $line['bold'] ? $this->boldFont() : $this->regularFont();
        $gdSize = $line['size'] * $dpi / 96;   // GD's sizes are points at 96 dpi
        $spacing = $line['size'] * $line['spacing'] / 100 * $dpi / 72;
        $condense = $line['condense'] / 100;
        [$ascent, $descent] = $this->metrics($font, $gdSize);
        $height = (int) ceil(($ascent + $descent) * 1.08);
        $baseline = (int) round($ascent + ($height - $ascent - $descent) / 2);

        if (! $line['dots']) {
            $text = StampTemplate::fill($line['text'], $values);
            $natural = $this->text($text, $font, $gdSize, $spacing, $rgb, $height, $baseline);
            return $this->narrow($natural, $condense, $inner);
        }

        // a field line: the label, then the value over a dotted line to the end
        $text = $line['text'];
        $at = mb_strpos($text, '{');
        [$label, $value] = $at === false ? [$text, ''] : [rtrim(mb_substr($text, 0, $at)), StampTemplate::fill(mb_substr($text, $at), $values)];
        $img = self::canvas($inner, $height);
        $labelImg = $this->narrow($this->text($label, $font, $gdSize, $spacing, $rgb, $height, $baseline), $condense, $inner);
        imagecopy($img, $labelImg, 0, 0, 0, 0, imagesx($labelImg), $height);
        $from = imagesx($labelImg) + (int) round($gdSize * 0.3);
        $dot = max(1, (int) round($gdSize * 0.09));
        $step = max(2 * $dot + 1, (int) round($gdSize * 0.32));
        $colour = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 0);
        $dotY = $baseline;
        for ($x = $from + $dot; $x <= $inner - $dot; $x += $step) {
            imagefilledellipse($img, $x, $dotY, $dot * 2, $dot * 2, $colour);
        }
        if (trim($value) !== '' && $inner - $from > 4) {
            $valueImg = $this->narrow($this->text($value, $font, $gdSize, $spacing, $rgb, $height, $baseline), $condense, $inner - $from);
            $x = $from + intdiv($inner - $from - imagesx($valueImg), 2);
            imagecopy($img, $valueImg, $x, -(int) round($gdSize * 0.12), 0, 0, imagesx($valueImg), $height);   // just above the dots
        }
        return $img;
    }

    /** text drawn at its natural width */
    private function text(string $text, string $font, float $gdSize, float $spacing, array $rgb, int $height, int $baseline): \GdImage
    {
        $chars = $spacing > 0 ? mb_str_split($text) : [$text];
        $widths = array_map(fn ($c) => $this->advance($c, $font, $gdSize), $chars);
        $width = (int) ceil(array_sum($widths) + $spacing * max(0, count($chars) - 1)) + 2;
        $img = self::canvas($width, $height);
        $colour = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 0);
        $x = 1.0;
        foreach ($chars as $i => $c) {
            if ($c !== '') {
                imagettftext($img, $gdSize, 0, (int) round($x), $baseline, $colour, $font, $c);
            }
            $x += $widths[$i] + $spacing;
        }
        return $img;
    }

    /** narrowed to $factor of its width, and to $max at most */
    private function narrow(\GdImage $img, float $factor, int $max): \GdImage
    {
        $w = imagesx($img);
        $target = max(1, min($max, (int) round($w * $factor)));
        if ($target === $w) {
            return $img;
        }
        $out = self::canvas($target, imagesy($img));
        imagealphablending($out, false);
        imagecopyresampled($out, $img, 0, 0, 0, 0, $target, imagesy($img), $w, imagesy($img));
        imagealphablending($out, true);
        return $out;
    }

    private function advance(string $text, string $font, float $gdSize): float
    {
        if ($text === '') {
            return 0;
        }
        // the advance of "x" + text + "x" minus that of "xx" counts the spaces at the ends too
        $with = imagettfbbox($gdSize, 0, $font, 'x' . $text . 'x');
        $without = imagettfbbox($gdSize, 0, $font, 'xx');
        return max(0, ($with[2] - $with[0]) - ($without[2] - $without[0]));
    }

    /** [ascent, descent] in pixels */
    private function metrics(string $font, float $gdSize): array
    {
        $box = imagettfbbox($gdSize, 0, $font, 'ΆΪΫΕΛgjpqy|');
        return [max(1, -$box[7]), max(0, $box[1])];
    }
}
