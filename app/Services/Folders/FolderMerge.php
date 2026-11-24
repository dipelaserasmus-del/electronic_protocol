<?php

namespace App\Services\Folders;

use App\Models\Attachment;
use App\Models\FolderAssignment;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Services\Attachments\Trash;
use App\Services\Protocols\ProtocolWriter;
use App\Support\FileNames;
use App\Support\FolderCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * D07: one folder becomes part of another (owner, 2026-11-19: Φ.43 → Φ.46; list in database/data/folders.json, "merges").
 *
 * - The protocols of the old folder take the new code (their updated_at is kept: this is not a user's change).
 * - Their attachment files directly in arxeio/<old>/ move to arxeio/<new>/, as a change of folder on the form moves
 *   them (ProtocolWriter::moveFiles); files in the trash move from trash/<old>/ to trash/<new>/ (found as the trash
 *   finds them: rows trashed before R4b still say arxeio/…) and their restore address (trashed_from) follows. A name
 *   already taken becomes "name (2).ext": nothing is overwritten. A file that is not found keeps its path (review of
 *   D07: the new path could belong to another attachment's file). Retention stays as each attachment recorded it.
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
        $old = $this->listed($from);
        $plan = [
            'from' => $from, 'into' => $into,
            'protocols' => count($codes),
            'files' => $codes === [] ? 0 : $this->attachments($codes, $this->intoCode($into))->count(),
            'rules' => $old->isEmpty() ? 0 : FolderAssignment::whereIn('keepvalue_id', $old->pluck('id'))->count(),
            'fromListed' => $old->isNotEmpty(),
            'intoListed' => $this->listed($into)->isNotEmpty(),
            'done' => $old->isEmpty() && $codes === [],
            // sub-folders (Φ.43.1 …) are separate folders: not merged, only named so the owner can decide
            'subfolders' => Keepvalue::pluck('fakelos')->filter(fn ($c) => str_starts_with(FolderCode::key((string) $c), FolderCode::key($from).'.'))
                ->values()->all(),
            'error' => null,
        ];
        if (FolderCode::key($from) === FolderCode::key($into)) {
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

        $into = $this->intoCode($into);   // as the list writes it ("Φ46" or "Φ.46")
        $codes = $this->codes($from);   // read before the update: each protocol's code as written (case, dot may differ)
        $files = $this->attachments($codes, $into);
        $ids = array_keys($codes);
        DB::transaction(function () use ($from, $into, $ids, &$result) {
            foreach (array_chunk($ids, 500) as $chunk) {
                $result['protocols'] += DB::table('protocols')->whereIn('id', $chunk)->update(['fakelos' => $into]);
            }
            $this->trashed($from, $into);   // R4h: protocols in the trash too
            $new = $this->listed($into)->first();
            foreach ($this->listed($from) as $old) {   // "Φ43" and "Φ.43" may both be in the list
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

        // files after the commit, one by one: a row changes only once its file has moved
        foreach ($files as $move) {
            [$attachment, $current, $target, $from] = $move;
            if ($current === null) {
                $result['missing']++;   // the row keeps its path (review of D07)
                continue;
            }
            $free = FileNames::available($target);
            if (! Storage::move($current, $free)) {
                $result['failed']++;   // the row still says where the file is
                continue;
            }
            $result[$free === $target ? 'moved' : 'renamed']++;
            $values = ['savedPath' => $free];
            if ($attachment->trashed()) {
                // restore puts it back under the new folder (rows trashed before R4b get the address they lacked)
                $values['trashed_from'] = $this->target($attachment->trashed_from ?: (string) $attachment->savedPath, $from, $into);
            }
            DB::table('attachments')->where('id', $attachment->id)->update($values);
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

    /**
     * R4h: protocols in the trash with that folder get the new code (also in their saved row), and their files in the
     * trash go back under the new folder when restored.
     */
    private function trashed(string $from, string $into): void
    {
        $key = FolderCode::key($from);
        foreach (\App\Models\TrashedProtocol::whereNotNull('fakelos')->get() as $t) {
            if (FolderCode::key((string) $t->fakelos) !== $key) {
                continue;
            }
            $old = (string) $t->fakelos;
            $data = $t->data;
            $data['protocol']['fakelos'] = $into;
            $t->update(['fakelos' => $into, 'data' => $data]);
            foreach ($t->attachments()->get() as $a) {
                $back = $a->trashed_from ?: (string) $a->savedPath;
                DB::table('attachments')->where('id', $a->id)->update(['trashed_from' => $this->target($back, $old, $into)]);
            }
        }
    }

    /**
     * @return array<int, string> protocol id => its folder code as written, for the protocols of this folder. Every
     *         spelling counts (owner, 2026-11-20: the old app wrote both "Φ.2" and "Φ12"; the dot after Φ is optional)
     */
    private function codes(string $from): array
    {
        $key = FolderCode::key($from);
        $spellings = Protocol::query()->whereNotNull('fakelos')->where('fakelos', '!=', '')->distinct()->pluck('fakelos')
            ->filter(fn ($c) => FolderCode::key((string) $c) === $key)->values()->all();

        return $spellings === [] ? [] : Protocol::whereIn('fakelos', $spellings)->pluck('fakelos', 'id')->map(fn ($c) => (string) $c)->all();
    }

    /** The list's folders with this code, however written ("Φ43" = "Φ.43") */
    private function listed(string $code)
    {
        $key = FolderCode::key($code);

        return Keepvalue::orderBy('id')->get()->filter(fn ($f) => FolderCode::key((string) $f->fakelos) === $key)->values();
    }

    /** The target's code as the list writes it, else as given */
    private function intoCode(string $into): string
    {
        return (string) ($this->listed($into)->first()?->fakelos ?? $into);
    }

    /**
     * The attachments (in the trash too) of these protocols whose file is directly in their folder's directory:
     * [attachment, where its file is now (null: not found), where it goes, the protocol's old code].
     *
     * @param  array<int, string>  $codes  protocol id => code
     * @return \Illuminate\Support\Collection<int, array{0: Attachment, 1: ?string, 2: string, 3: string}>
     */
    private function attachments(array $codes, string $into)
    {
        $trash = app(Trash::class);

        return Attachment::withTrashed()->whereIn('protocol_id', array_keys($codes))->orderBy('id')->get()
            ->map(function (Attachment $a) use ($codes, $into, $trash) {
                $from = $codes[$a->protocol_id];
                $path = (string) $a->savedPath;
                if ($a->trashed()) {
                    // where the trash keeps it: trash/… (rows trashed before R4b still say arxeio/…)
                    $logical = str_starts_with($path, 'trash/') ? $path : (preg_replace('~^arxeio/~', 'trash/', $path) ?? $path);
                    $target = $this->target($logical, $from, $into);

                    return $target === $logical ? null : [$a, $trash->fileInTrash($a), $target, $from];
                }
                $target = $this->target($path, $from, $into);

                return $target === $path ? null : [$a, Storage::exists($path) ? $path : null, $target, $from];
            })->filter()->values();
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
