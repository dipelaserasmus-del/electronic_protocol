<?php

namespace App\Services\Folders;

use App\Models\Keepvalue;
use App\Support\FolderCode;

/**
 * D07: the owner's folder glossary (Φάκελοι αρχείου, 2026-11-19) in database/data/folders.json — the full description
 * of every folder, and the folder merges (Φ.43 → Φ.46). Applied with `php artisan folders:glossary` (preview) and
 * `--apply`, through FolderCatalog's import: only the description is written; Διατήρηση and remarks stay as they are.
 * Folders that are not in the glossary (Φ.37–Φ.42, the school units) are never changed. docs/FOLDERS.md is the same list
 * to read (`--docs` writes it; a test checks it is up to date).
 */
final class FolderGlossary
{
    public static function path(): string
    {
        return database_path('data/folders.json');
    }

    /** @return array{folders: list<array{code: string, describe: string}>, merges: list<array{from: string, into: string, note?: string}>} */
    public static function load(?string $path = null): array
    {
        $data = json_decode((string) file_get_contents($path ?? self::path()), true, flags: JSON_THROW_ON_ERROR);

        return ['folders' => $data['folders'] ?? [], 'merges' => $data['merges'] ?? []];
    }

    /**
     * The glossary as import rows. A code that is written differently in the list (FolderCode: "Φ1.1" = "Φ.1.1",
     * case and accents do not count) takes the list's spelling, so the existing folder is updated, not duplicated.
     *
     * @return array{records: list<array{0: int, 1: list<string>}>, respelled: array<string, string>} respelled: glossary code => list code
     */
    public static function records(array $glossary): array
    {
        $byKey = [];
        foreach (Keepvalue::pluck('fakelos') as $code) {
            $byKey[FolderCode::key((string) $code)] ??= (string) $code;
        }
        $records = [];
        $respelled = [];
        foreach ($glossary['folders'] as $i => $f) {
            $code = $f['code'];
            $listed = $byKey[FolderCode::key($code)] ?? null;
            if ($listed !== null && mb_strtolower($listed) !== mb_strtolower($code)) {
                $respelled[$code] = $listed;
                $code = $listed;
            }
            $records[] = [$i + 1, [$code, $f['describe']]];
        }

        return ['records' => $records, 'respelled' => $respelled];
    }

    /** docs/FOLDERS.md */
    public static function markdown(array $glossary): string
    {
        $md = "# Φάκελοι αρχείου (folder glossary)\n\n"
            ."Generated from [`database/data/folders.json`](../database/data/folders.json) by `php artisan folders:glossary --docs`;\n"
            ."edit the JSON, not this file. The owner's official list (2026-11-19). Apply it to a database with\n"
            ."`php artisan folders:glossary` (preview) and `--apply`. Φ.37–Φ.42 (school units) are kept as they are in the database.\n\n"
            ."**Codes (owner, 2026-11-20):** the dot after Φ is only for readability and never counts: the old app wrote both\n"
            ."`Φ.2` and `Φ12`, and `Φ12` = `Φ.12` = `φ12` in search, in the folder picker and in a merge. The dots *inside* the\n"
            ."number do count: `Φ.1.1` is not `Φ.11`.\n\n"
            ."| Κωδικός | Περιγραφή |\n|---|---|\n";
        foreach ($glossary['folders'] as $f) {
            $md .= '| '.$f['code'].' | '.str_replace('|', '\\|', $f['describe'])." |\n";
        }
        if ($glossary['merges']) {
            $md .= "\n## Συγχωνεύσεις (merges)\n\nApplied by the same command; **every later data migration must apply them too.**\n\n"
                ."| Από | Σε | Σημείωση |\n|---|---|---|\n";
            foreach ($glossary['merges'] as $m) {
                $md .= '| '.$m['from'].' | '.$m['into'].' | '.str_replace('|', '\\|', $m['note'] ?? '')." |\n";
            }
        }

        return $md;
    }
}
