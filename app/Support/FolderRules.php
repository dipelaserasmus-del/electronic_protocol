<?php

namespace App\Support;

use App\Models\FolderAssignment;
use App\Models\Keepvalue;
use App\Models\User;

/**
 * R1d: the automatic assignments of a folder (R6b, App\Models\FolderAssignment) added to a protocol's assignments.
 *
 * Applied by ProtocolWriter when a protocol is registered in a folder with rules, or moved into one, whoever saves it
 * (also writers and the inbox, who cannot assign by hand). Assignments already there stay; a user is added only once:
 * διεκπεραίωση takes the place of ενημέρωση for the same user (it includes being informed). Inactive users are skipped.
 */
final class FolderRules
{
    /** @return list<array{user_id: int, type: string}> the folder's active rules ([] for no folder / no rules) */
    public static function for(?string $folder): array
    {
        if ($folder === null || trim($folder) === '') {
            return [];
        }
        $id = Keepvalue::where('fakelos', $folder)->value('id');
        if (! $id) {
            return [];
        }
        $active = User::whereNotNull('active')->pluck('id')->map(fn ($id) => (int) $id)->all();

        return FolderAssignment::where('keepvalue_id', $id)->orderBy('type')->orderBy('id')->get()
            ->filter(fn ($r) => in_array((int) $r->user_id, $active, true))
            ->map(fn ($r) => ['user_id' => (int) $r->user_id, 'type' => $r->type])->values()->all();
    }

    /**
     * @param  list<array{user_id: int, type: string}>  $assignments
     * @param  list<array{user_id: int, type: string}>  $rules
     * @return array{0: list<array{user_id: int, type: string}>, 1: list<array{user_id: int, type: string}>} the merged
     *         assignments, and the rules that were added
     */
    public static function merge(array $assignments, array $rules): array
    {
        $added = [];
        foreach ($rules as $rule) {
            $uid = (int) $rule['user_id'];
            $has = fn (string $type) => collect($assignments)->contains(fn ($a) => (int) $a['user_id'] === $uid && $a['type'] === $type);
            if ($has(Assignments::HANDLE) || ($rule['type'] === Assignments::INFORM && $has(Assignments::INFORM))) {
                continue;
            }
            if ($rule['type'] === Assignments::HANDLE) {
                $assignments = array_values(array_filter($assignments, fn ($a) => ! ((int) $a['user_id'] === $uid && $a['type'] === Assignments::INFORM)));
            }
            $assignments[] = ['user_id' => $uid, 'type' => $rule['type']];
            $added[] = ['user_id' => $uid, 'type' => $rule['type']];
        }

        return [$assignments, $added];
    }

    /** "Αυτόματη ανάθεση (Φ.12): Διεκπεραίωση: Α, Β · Ενημέρωση: Γ" */
    public static function describe(string $folder, array $added): string
    {
        $names = User::whereIn('id', array_column($added, 'user_id'))->pluck('name', 'id');
        $parts = [];
        foreach ([Assignments::HANDLE => 'Διεκπεραίωση', Assignments::INFORM => 'Ενημέρωση'] as $type => $label) {
            $who = collect($added)->where('type', $type)->map(fn ($a) => $names[$a['user_id']] ?? '—')->all();
            if ($who) {
                $parts[] = $label.': '.implode(', ', $who);
            }
        }

        return "Αυτόματη ανάθεση ($folder): ".implode(' · ', $parts);
    }
}
