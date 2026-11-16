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

    /** Longest name shown in the app (attachments.name is VARCHAR(255) characters). */
    public const MAX_CHARS = 200;

    /** attachments.savedPath is VARCHAR(255) characters and holds "<folder>/<stored name>". */
    public const MAX_PATH_CHARS = 255;

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

        return self::shorten($name, self::MAX_CHARS);   // review finding: keep the extension
    }

    /** $name in at most $chars characters, the extension kept. */
    private static function shorten(string $name, int $chars): string
    {
        if (mb_strlen($name) <= $chars) {
            return $name;
        }
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $extension = ($extension !== '' && mb_strlen($extension) <= 10) ? '.'.$extension : '';
        $base = $extension === '' ? $name : mb_substr($name, 0, -mb_strlen($extension));

        return rtrim(mb_substr($base, 0, max(1, $chars - mb_strlen($extension))), ' .-').$extension;
    }

    /**
     * $prefix + $name, shortened so that it fits in MAX_BYTES bytes and, together with "$dir/", in the
     * MAX_PATH_CHARS-character savedPath column (review finding: folders can be long). The extension is kept and a
     * character is never cut in half.
     */
    public static function fit(string $prefix, string $name, string $dir = ''): string
    {
        $chars = self::MAX_PATH_CHARS - ($dir === '' ? 0 : mb_strlen(rtrim($dir, '/')) + 1);
        $full = $prefix.$name;
        if (strlen($full) <= self::MAX_BYTES && mb_strlen($full) <= $chars) {
            return $full;
        }
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $extension = ($extension !== '' && strlen($extension) <= 10) ? '.'.$extension : '';
        $base = $extension === '' ? $name : substr($name, 0, -strlen($extension));

        $bytes = self::MAX_BYTES - strlen($prefix) - strlen($extension);
        $chars -= mb_strlen($prefix) + mb_strlen($extension);
        $base = mb_strcut($base, 0, max(0, $bytes), 'UTF-8');                 // bytes, whole characters
        $base = rtrim(mb_substr($base, 0, max(0, $chars)), ' .-');

        return $prefix.($base === '' ? self::FALLBACK : $base).$extension;
    }
}
