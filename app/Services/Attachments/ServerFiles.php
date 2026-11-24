<?php

namespace App\Services\Attachments;

use App\Models\Attachment;
use App\Models\Protocol;
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
 *   Those that are not attachments (placeholders, database backups, the copy tool's index, D11) are counted apart;
 *   for the others --list says what the database has for them (D11).
 * Counts are safe to show; the lists hold file names (private: printed only with --list, for the owner).
 */
final class ServerFiles
{
    /**
     * @return array{error: ?string, serverFiles: int, rows: int, copied: int, same: int, missing: int, conflicts: int,
     *   failed: int, orphans: int, newer: int, other: int, lists: array{missing: list<string>, conflicts: list<string>,
     *   failed: list<string>, orphans: list<string>, other: list<string>}}
     */
    public function import(string $source, bool $apply): array
    {
        $result = ['error' => null, 'serverFiles' => 0, 'rows' => 0, 'copied' => 0, 'same' => 0, 'missing' => 0,
            'conflicts' => 0, 'failed' => 0, 'orphans' => 0, 'newer' => 0, 'other' => 0,
            'lists' => ['missing' => [], 'conflicts' => [], 'failed' => [], 'orphans' => [], 'other' => []]];
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
        $other = array_values(array_filter($orphans, self::notAttachment(...)));
        $orphans = array_values(array_diff($orphans, $other));
        $result['other'] = count($other);
        $result['lists']['other'] = $other;
        $result['orphans'] = count($orphans);
        [$result['lists']['orphans'], $result['newer']] = $this->explain($orphans);

        return $result;
    }

    /**
     * The first candidate found on the server: exactly, else whatever the case of its letters (only when that is one
     * file); a later candidate only when the earlier one is not there at all. @param list<string> $candidates @return array{0: ?string, 1: ?string} [path here, path on the server]
     */
    private function locate(array $candidates, array $exact, array $folded): array
    {
        // candidate by candidate: their order counts (a trashed row's file in trash/ before a newer upload at the
        // row's own path, review of D10), so each one is tried exactly and then whatever the case before the next
        foreach ($candidates as $c) {
            if (isset($exact[$c])) {
                return [$c, $c];
            }
            $matches = $folded[self::fold($c)] ?? [];
            if (count($matches) === 1) {
                return [$c, $matches[0]];
            }
        }

        return [null, null];
    }

    /**
     * D11: a server file that is not an attachment: outside arxeio/ and trash/, hidden (".hist/" of the copy tool,
     * ".gitignore"), a placeholder ("dummy"), Windows' own files, the database backups, the old SQLite file. Files in
     * arxeio/emails/ and directly in arxeio/ can be attachments (protocols without a folder): only their names decide.
     */
    private static function notAttachment(string $path): bool
    {
        $parts = explode('/', $path);
        $name = mb_strtolower(end($parts));
        if (! in_array($parts[0], ['arxeio', 'trash'], true) || in_array($name, ['dummy', 'thumbs.db', 'desktop.ini'], true)) {
            return true;
        }
        foreach ($parts as $part) {
            if (str_starts_with($part, '.')) {
                return true;
            }
        }

        return str_starts_with($path, 'arxeio/backups/')
            || (count($parts) === 2 && str_starts_with($name, 'protocol.sqlite'));
    }

    /**
     * D11: for each orphan, what the database has: a row with the same name elsewhere, or its protocol (the stored
     * names start "<number>-<YYYYMMDD>_") not in the database (saved after the export) or holding other files.
     * @param list<string> $orphans @return array{0: list<string>, 1: int} [lines for --list, how many protocols are not in the database]
     */
    private function explain(array $orphans): array
    {
        if ($orphans === []) {
            return [[], 0];
        }
        $byName = [];
        foreach (Attachment::withTrashed()->whereNotNull('savedPath')->pluck('savedPath') as $p) {
            $byName[self::fold(basename((string) $p))][] = (string) $p;
        }
        $numbers = [];
        foreach ($orphans as $o) {
            if (preg_match('/^(\d+)-(\d{4})\d{4}_/u', basename($o), $m)) {
                $numbers[(int) $m[1]] = true;
            }
        }
        $protocols = [];
        foreach (array_chunk(array_keys($numbers), 500) as $chunk) {
            foreach (Protocol::whereIn('protocolnum', $chunk)->get(['protocolnum', 'etos', 'fakelos']) as $p) {
                $protocols[$p->protocolnum.'/'.$p->etos] = (string) $p->fakelos;
            }
        }

        $lines = [];
        $newer = 0;
        foreach ($orphans as $o) {
            $same = $byName[self::fold(basename($o))] ?? [];
            if ($same !== []) {
                $why = 'a row has this name at '.$same[0].(count($same) > 1 ? ' (and '.(count($same) - 1).' more)' : '');
            } elseif (preg_match('/^(\d+)-(\d{4})\d{4}_/u', basename($o), $m)) {
                $key = (int) $m[1].'/'.$m[2];
                if (! array_key_exists($key, $protocols)) {
                    $newer++;
                    $why = "protocol $key is not in the database (saved after the export?)";
                } else {
                    $why = "protocol $key (folder ".($protocols[$key] !== '' ? $protocols[$key] : 'none').') has no attachment with this name';
                }
            } else {
                $why = 'no protocol number in the name';
            }
            $lines[] = "$o  ← $why";
        }

        return [$lines, $newer];
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
