<?php

namespace App\Services\Folders;

use App\Models\Attachment;
use App\Models\FolderAssignment;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Services\Protocols\ProtocolWriter;
use App\Support\FileNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * D07: one folder becomes part of another (owner, 2026-11-19: Φ.43 → Φ.46; list in database/data/folders.json, "merges").
 *
 * - The protocols of the old folder take the new code (their updated_at is kept: this is not a user's change).
 * - Their attachment files directly in arxeio/<old>/ (or trash/<old>/ for attachments in the trash) move to the new
 *   folder's directory, as a change of folder on the form moves them (ProtocolWriter::moveFiles); a name that is
 *   already taken there becomes "name (2).ext", nothing is overwritten. Retention stays as each attachment recorded it.
 * - The old folder's automatic assignments join the new folder's (διεκπεραίωση wins over ενημέρωση), then the old
 *   folder is removed from the list.
 * - Safe to run again: once merged there is nothing left to do. The same merge must be applied by any later data
 *   migration (owner, 2026-11-19).
 */
final class FolderMerge
{
    /**
     * @return array{from: string, into: string, protocols: int, files: int, rules: int, fromListed: bool,
     *               intoListed: bool, done: bool, subfolders: list<string>, error: ?string}
     */
    public function plan(string $from, string $into): array
    {
        $codes = $this->codes($from);
        $old = Keepvalue::whereFakelos($from)->first();
        $plan = [
            'from' => $from, 'into' => $into,
            'protocols' => count($codes),
            'files' => $codes === [] ? 0 : $this->attachments($codes, $into)->count(),
            'rules' => $old ? FolderAssignment::where('keepvalue_id', $old->id)->count() : 0,
            'fromListed' => $old !== null,
            'intoListed' => Keepvalue::whereFakelos($into)->exists(),
            'done' => $old === null && $codes === [],
            // sub-folders (Φ.43.1 …) are separate folders: not merged, only named so the owner can decide
            'subfolders' => Keepvalue::pluck('fakelos')->filter(fn ($c) => str_starts_with(mb_strtolower((string) $c), mb_strtolower($from).'.'))
                ->values()->all(),
            'error' => null,
        ];
        if (mb_strtolower($from) === mb_strtolower($into)) {
            $plan['error'] = 'Ο φάκελος δεν συγχωνεύεται με τον εαυτό του.';
        } elseif (! $plan['done'] && ! $plan['intoListed']) {
            $plan['error'] = "Ο φάκελος $into δεν υπάρχει στη λίστα.";
        }

        return $plan;
    }

    /**
     * @return array{protocols: int, moved: int, renamed: int, missing: int, failed: int, rules: int}
     * @throws \RuntimeException when plan() has an error
     */
    public function apply(string $from, string $into): array
    {
        $plan = $this->plan($from, $into);
        if ($plan['error'] !== null) {
            throw new \RuntimeException($plan['error']);
        }
        $result = ['protocols' => 0, 'moved' => 0, 'renamed' => 0, 'missing' => 0, 'failed' => 0, 'rules' => 0];
        if ($plan['done']) {
            return $result;
        }

        $codes = $this->codes($from);   // read before the update: each protocol's code as written (case may differ)
        $files = $this->attachments($codes, $into);
        $ids = array_keys($codes);
        DB::transaction(function () use ($from, $into, $ids, &$result) {
            foreach (array_chunk($ids, 500) as $chunk) {
                $result['protocols'] += DB::table('protocols')->whereIn('id', $chunk)->update(['fakelos' => $into]);
            }
            $old = Keepvalue::whereFakelos($from)->first();
            if ($old) {
                $new = Keepvalue::whereFakelos($into)->firstOrFail();
                foreach (FolderAssignment::where('keepvalue_id', $old->id)->get() as $rule) {
                    $there = FolderAssignment::where('keepvalue_id', $new->id)->where('user_id', $rule->user_id)->first();
                    if (! $there) {
                        FolderAssignment::create(['keepvalue_id' => $new->id, 'user_id' => $rule->user_id, 'type' => $rule->type]);
                        $result['rules']++;
                    } elseif ($rule->type === 'd' && $there->type !== 'd') {
                        $there->update(['type' => 'd']);
                        $result['rules']++;
                    }
                }
                $old->delete();   // its own rules go with it (foreign key)
            }
        });

        // files after the commit, one by one: a row's path changes only once its file has moved
        foreach ($files as [$attachment, $newPath]) {
            $path = (string) $attachment->savedPath;
            if (! Storage::exists($path)) {
                $result['missing']++;
                DB::table('attachments')->where('id', $attachment->id)->update(['savedPath' => $newPath]);
                continue;
            }
            $free = FileNames::available($newPath);
            if (! Storage::move($path, $free)) {
                $result['failed']++;   // the row keeps the old path, where the file still is
                continue;
            }
            $result[$free === $newPath ? 'moved' : 'renamed']++;
            DB::table('attachments')->where('id', $attachment->id)->update(['savedPath' => $free]);
        }
        // the old folder's directories, if nothing is left in them
        foreach (array_unique(array_values($codes)) as $code) {
            foreach (['arxeio', 'trash'] as $root) {
                $dir = $root.'/'.$code;
                if (Storage::directoryExists($dir) && Storage::allFiles($dir) === [] && Storage::directories($dir) === []) {
                    Storage::deleteDirectory($dir);
                }
            }
        }

        return $result;
    }

    /** @return array<int, string> protocol id => its folder code as written, for the protocols of this folder */
    private function codes(string $from): array
    {
        return Protocol::where('fakelos', $from)->pluck('fakelos', 'id')->map(fn ($c) => (string) $c)->all();
    }

    /**
     * The attachments (in the trash too) of these protocols whose file is directly in their folder's directory,
     * each with its path in the new folder.
     *
     * @param  array<int, string>  $codes  protocol id => code
     * @return \Illuminate\Support\Collection<int, array{0: Attachment, 1: string}>
     */
    private function attachments(array $codes, string $into)
    {
        return Attachment::withTrashed()->whereIn('protocol_id', array_keys($codes))->orderBy('id')->get()
            ->map(fn ($a) => [$a, $this->target((string) $a->savedPath, $codes[$a->protocol_id], $into)])
            ->filter(fn ($pair) => $pair[1] !== (string) $pair[0]->savedPath)->values();
    }

    /** arxeio/<from>/name → arxeio/<into>/name (trash/ likewise); any other path (sub-folders too) is returned as it is */
    private function target(string $path, string $from, string $into): string
    {
        foreach ([ProtocolWriter::folderPath($from).'/' => ProtocolWriter::folderPath($into).'/', 'trash/'.$from.'/' => 'trash/'.$into.'/'] as $old => $new) {
            // case does not count (a protocol written "φ.43" may have files saved under arxeio/Φ.43/)
            if (mb_strtolower(substr($path, 0, strlen($old))) === mb_strtolower($old) && ! str_contains(substr($path, strlen($old)), '/')) {
                return $new.substr($path, strlen($old));
            }
        }

        return $path;
    }
}
