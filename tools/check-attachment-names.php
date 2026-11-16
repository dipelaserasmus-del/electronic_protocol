<?php
/**
 * F08: counts attachment files whose names do not fit Linux's limit of 255 bytes per file name.
 *
 * Windows (NTFS) allows 255 *characters*; a Greek letter takes 2 bytes in UTF-8 and an emoji 4, so a name that is
 * fine on the old Windows server can be refused on Linux (the Docker production stack, also Docker Desktop on WSL 2).
 * Run it on the attachments folder BEFORE copying the files to the new server. New attachments are already shortened
 * by the app (App\Support\FileNames).
 *
 * Standalone (no Laravel), PHP 8.1 or newer, works on the old XAMPP server:
 *   php tools/check-attachment-names.php C:\xampp\htdocs\protocol\storage\app\arxeio
 *   php tools/check-attachment-names.php <folder> --list      also lists the files (keep that list to yourself)
 *   ./dev.sh real check-names                                   the same, on local-data/storage/app
 *
 * Exit code: 0 when every name fits, 1 when some don't, 2 on wrong usage.
 */

$args = array_slice($argv, 1);
$list = in_array('--list', $args, true);
$max = 255;   // --max-bytes=N: only for the tests (Linux itself can't hold a longer name)
foreach ($args as $a) {
    if (preg_match('/^--max-bytes=(\d+)$/', $a, $m)) {
        $max = (int) $m[1];
    }
}
$dir = array_values(array_filter($args, fn ($a) => ! str_starts_with($a, '--')))[0] ?? null;
if ($dir === null || ! is_dir($dir)) {
    fwrite(STDERR, "usage: php check-attachment-names.php <attachments folder> [--list]\n");
    exit(2);
}

$files = 0;
$tooLong = [];
$longest = 0;
$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
foreach ($items as $item) {
    $bytes = strlen($item->getFilename());   // PHP reads names as UTF-8, also on Windows (PHP 7.1+)
    $longest = max($longest, $bytes);
    if ($item->isFile()) {
        $files++;
    }
    if ($bytes > $max) {
        $tooLong[] = substr($item->getPathname(), strlen(rtrim($dir, '/\\')) + 1);
    }
}

echo "Files checked:            $files\n";
echo "Names over $max bytes:     ".count($tooLong)."\n";
echo "Longest name:             $longest bytes\n";
if ($tooLong && $list) {
    echo "\n".implode("\n", $tooLong)."\n";
} elseif ($tooLong) {
    echo "Add --list to see them (the list shows file names: keep it to yourself).\n";
}

exit($tooLong ? 1 : 0);
