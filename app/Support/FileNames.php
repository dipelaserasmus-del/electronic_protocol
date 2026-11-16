<?php

namespace App\Support;

use Symfony\Component\Mime\MimeTypes;

/**
 * F08: names of stored attachment files.
 *
 * Linux (the Docker production stack, also on Windows via WSL 2) allows 255 *bytes* per file name; a Greek letter takes
 * 2 bytes and an emoji 4. Names used to be cut at 120 *characters*, so a long Greek name plus the "num-date_" prefix
 * did not fit and the file was not written. Stored names are now cut by bytes, on a character boundary, keeping the
 * extension. The name shown in the app (attachments.name) is not shortened beyond the column's size.
 */
final class FileNames
{
    /** Linux limit for one file name (ext4, also Docker volumes on WSL 2). */
    public const MAX_BYTES = 255;

    /** attachments.savedPath is VARCHAR(255) characters and also holds "arxeio/<folder>/". */
    public const MAX_CHARS = 200;

    /** Name shown for an attachment that has no usable name of its own. */
    public const FALLBACK = 'συνημμένο';

    /**
     * The name without characters that are illegal or unsafe in file names (as before: replaced by "-"). A name
     * without any usable character keeps its extension; a missing name becomes "συνημμένο" + the extension of its type.
     */
    public static function clean(?string $name, ?string $mimeType = null): string
    {
        $name = preg_replace(
            '~[<>:"/\\\\|?*]|[\x00-\x1F]|[\x7F\xA0\xAD]|[#\[\]@!$&\'()+,;=]|[{}^\~`]~u',
            '-',
            (string) $name
        ) ?? '';
        $name = trim($name);
        $dot = strrpos($name, '.');
        $extension = $dot === false ? '' : trim(substr($name, $dot + 1), ' -');
        $base = $dot === false ? $name : substr($name, 0, $dot);
        $name = ltrim($name, '.-');   // no ".", ".." or hidden files
        if (trim($base, ' .-') === '') {
            if ($extension === '' && $mimeType) {
                $extension = MimeTypes::getDefault()->getExtensions(strtolower($mimeType))[0] ?? '';
            }
            $name = self::FALLBACK.($extension !== '' ? '.'.$extension : '');
        }

        return mb_substr($name, 0, self::MAX_CHARS);
    }

    /**
     * $prefix + $name, shortened so that it fits in MAX_BYTES bytes and MAX_CHARS characters; the extension is kept and
     * a character is never cut in half.
     */
    public static function fit(string $prefix, string $name): string
    {
        $full = $prefix.$name;
        if (strlen($full) <= self::MAX_BYTES && mb_strlen($full) <= self::MAX_CHARS) {
            return $full;
        }
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $extension = ($extension !== '' && strlen($extension) <= 10) ? '.'.$extension : '';
        $base = $extension === '' ? $name : substr($name, 0, -strlen($extension));

        $bytes = self::MAX_BYTES - strlen($prefix) - strlen($extension);
        $chars = self::MAX_CHARS - mb_strlen($prefix) - mb_strlen($extension);
        $base = mb_strcut($base, 0, max(0, $bytes), 'UTF-8');                 // bytes, whole characters
        $base = rtrim(mb_substr($base, 0, max(0, $chars)), ' .-');

        return $prefix.($base === '' ? self::FALLBACK : $base).$extension;
    }
}
