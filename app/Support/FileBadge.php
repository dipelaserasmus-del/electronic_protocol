<?php

namespace App\Support;

/** N04: file-type label and readable size for <x-file-badge> (attachments, e-mail parts). */
final class FileBadge
{
    /** [label, Bootstrap colour] by extension (or MIME type when the name has none). */
    public static function kind(?string $name, ?string $mime = null): array
    {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        $byMime = ['application/pdf' => 'pdf', 'message/rfc822' => 'eml', 'text/html' => 'html'];
        if ($ext === '' && $mime) {
            $ext = $byMime[strtolower($mime)] ?? (str_starts_with((string) $mime, 'image/') ? 'png' : '');
        }

        return match (true) {
            $ext === 'pdf' => ['PDF', 'danger'],
            in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true) => ['DOC', 'primary'],
            in_array($ext, ['xls', 'xlsx', 'ods', 'csv'], true) => ['XLS', 'success'],
            in_array($ext, ['ppt', 'pptx', 'odp'], true) => ['PPT', 'warning'],
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'tif', 'tiff', 'bmp', 'webp'], true) => ['IMG', 'info'],
            in_array($ext, ['zip', 'rar', '7z', 'gz'], true) => ['ZIP', 'secondary'],
            in_array($ext, ['eml', 'msg'], true) => ['EML', 'dark'],
            in_array($ext, ['html', 'htm'], true) => ['HTML', 'dark'],
            $ext === 'dat' => ['DAT', 'secondary'],
            default => [$ext !== '' && strlen($ext) <= 4 ? strtoupper($ext) : 'ΑΡΧΕΙΟ', 'secondary'],
        };
    }

    /** R2b: "Πρόσκληση_σε_ημερ….pdf": at most $max letters, keeping the extension. */
    public static function shortName(?string $name, int $max = 24): string
    {
        $name = (string) $name;
        if (mb_strlen($name) <= $max) {
            return $name;
        }
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $ext = $ext !== '' && mb_strlen($ext) <= 5 ? '.' . $ext : '';
        $stem = mb_substr($name, 0, mb_strlen($name) - mb_strlen($ext));
        return rtrim(mb_substr($stem, 0, max(1, $max - mb_strlen($ext) - 1))) . '…' . $ext;
    }

    /** 0 B, 950 B, 12 KB, 1,2 MB, 3,4 GB (Greek decimal comma). */
    public static function size(?int $bytes): string
    {
        if ($bytes === null) {
            return '';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        $decimals = ($i >= 2 && $value < 10) ? 1 : 0;

        return number_format($value, $decimals, ',', '.').' '.$units[$i];
    }
}
