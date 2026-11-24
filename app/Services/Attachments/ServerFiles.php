<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;

/**
 * D10: copies the attachment files of the old server into the app's storage, **by the database**: every attachment row
 * (also those in the trash) gets its file from the server's copy of storage/app, at the path the row says. Used by
 * `./dev.sh real load` (from local-data/server-files) and at the deployment (`php artisan attachments:import`).
 *
 * - The server folder is only read, never changed.
 * - A file already here with the same content is left alone; a different one is reported, never overwritten.
 * - The server was Windows: a path is found there whatever the case of its letters (a protocol written "φ.12" has its
 *   files under arxeio/Φ.12/); the copy goes to the path the row says, so the app finds it on Linux.
 * - Server files no row points to are only counted (and listed with --list), never copied (owner, 2026-11-24).
 * Counts are safe to show; the lists hold file names (private: printed only with --list, for the owner).
 */
final class ServerFiles
{
    /**
     * @return array{error: ?string, serverFiles: int, rows: int, copied: int, same: int, missing: int, conflicts: int,
     *   failed: int, orphans: int, lists: array{missing: list<string>, conflicts: list<string>, failed: list<string>, orphans: list<string>}}
     */
    public function import(string $source, bool $apply): array
    {
        $result = ['error' => null, 'serverFiles' => 0, 'rows' => 0, 'copied' => 0, 'same' => 0, 'missing' => 0,
            'conflicts' => 0, 'failed' => 0, 'orphans' => 0,
            'lists' => ['missing' => [], 'conflicts' => [], 'failed' => [], 'orphans' => []]];
        $root = realpath($source);
        if ($root === false || ! is_dir($root)) {
            return ['error' => "$source is not a folder."] + $result;
        }
        if (! is_dir($root.'/arxeio')) {
            return ['error' => "$source has no arxeio folder: give the server's storage/app folder (arxeio/ and trash/ are inside it)."] + $result;
        }
        $storage = realpath(Storage::path('')) ?: Storage::path('');
        if ($root === $storage || str_starts_with($root.'/', rtrim($storage, '/').'/') || str_starts_with($storage.'/', $root.'/')) {
            return ['error' => "$source is the app's own storage (or inside it): copy the server's files to a separate folder."] + $result;
        }

        // the server's files: exact path => true, and folded path (case, Unicode form) => exact paths
        $exact = [];
        $folded = [];
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($items as $item) {
            if (! $item->isFile()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            $exact[$rel] = true;
            $folded[self::fold($rel)][] = $rel;
        }
        $result['serverFiles'] = count($exact);
        $used = [];

        Attachment::withTrashed()->whereNotNull('savedPath')->where('savedPath', '!=', '')->orderBy('id')
            ->chunkById(500, function ($attachments) use (&$result, &$used, $exact, $folded, $root, $apply) {
                foreach ($attachments as $a) {
                    $result['rows']++;
                    [$target, $found] = $this->locate($a->fileCandidates(), $exact, $folded);
                    if ($found === null) {
                        $result['missing']++;
                        $result['lists']['missing'][] = (string) $a->savedPath;
                        continue;
                    }
                    $used[$found] = true;
                    $from = $root.'/'.$found;
                    $to = Storage::path($target);
                    if (is_file($to)) {
                        if (filesize($to) === filesize($from) && hash_file('sha256', $to) === hash_file('sha256', $from)) {
                            $result['same']++;
                        } else {
                            $result['conflicts']++;
                            $result['lists']['conflicts'][] = $target;
                        }
                        continue;
                    }
                    if ($apply && ! $this->copy($from, $to)) {
                        $result['failed']++;
                        $result['lists']['failed'][] = $target;
                        continue;
                    }
                    $result['copied']++;
                }
            });

        $orphans = array_keys(array_diff_key($exact, $used));
        sort($orphans);
        $result['orphans'] = count($orphans);
        $result['lists']['orphans'] = $orphans;

        return $result;
    }

    /**
     * The first candidate found on the server: exactly, else whatever the case of its letters (only when that is one
     * file). @param list<string> $candidates @return array{0: ?string, 1: ?string} [path here, path on the server]
     */
    private function locate(array $candidates, array $exact, array $folded): array
    {
        foreach ($candidates as $c) {
            if (isset($exact[$c])) {
                return [$c, $c];
            }
        }
        foreach ($candidates as $c) {
            $matches = $folded[self::fold($c)] ?? [];
            if (count($matches) === 1) {
                return [$c, $matches[0]];
            }
        }

        return [null, null];
    }

    private function copy(string $from, string $to): bool
    {
        $dir = dirname($to);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return false;
        }
        if (! @copy($from, $to)) {
            @unlink($to);   // never leave half a file

            return false;
        }
        @touch($to, (int) filemtime($from));   // keep the server's file date

        return true;
    }

    /** Windows paths: case does not count, and the same name may come in another Unicode form */
    private static function fold(string $path): string
    {
        $nfc = class_exists(\Normalizer::class) ? (\Normalizer::normalize($path, \Normalizer::FORM_C) ?: $path) : $path;

        return mb_strtolower($nfc);
    }
}
