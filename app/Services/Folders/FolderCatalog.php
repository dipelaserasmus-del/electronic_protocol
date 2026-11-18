<?php

namespace App\Services\Folders;

use App\Models\FolderAssignment;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * R6b: the folders (Φ., table `keepvalues`): add, change, delete, import a list, and their automatic assignments
 * (App\Models\FolderAssignment, applied on save by R1d). Page: App\Livewire\FolderAdmin.
 *
 * - A folder's code is written in protocols as text, so a code **in use** is not renamed or deleted (protocols would
 *   keep a code that is in no list); add a new folder instead.
 * - "Διατήρηση" is years (`keep`) or a text such as "Μόνιμα" (`keep_alt`), as before.
 */
final class FolderCatalog
{
    /** The old page's rule: "Φ." then a letter or digit; letters, digits, space, dot, dash, underscore */
    public const CODE_PATTERN = '/^Φ\.\w[\w ._-]*$/u';

    public const CODE_MESSAGE = 'Ο κωδικός ξεκινά με «Φ.» και έναν αριθμό ή γράμμα (π.χ. Φ.1, Φ.11.2). Επιτρέπονται γράμματα, αριθμοί, κενό, τελεία, παύλα και κάτω παύλα.';

    /**
     * @param  array{fakelos?: string, keep?: string|int|null, describe?: string|null, remarks?: string|null, d?: array, e?: array}  $data
     *         keep: a whole number of years, or a text (stored in keep_alt)
     * @throws ValidationException (keys: fakelos, keep, describe, remarks, d, e)
     */
    public function save(?Keepvalue $folder, array $data): Keepvalue
    {
        $creating = $folder === null || ! $folder->exists;
        $clean = $this->clean($data);
        Validator::make($clean, [
            'fakelos' => ['required', 'string', 'max:255', 'regex:'.self::CODE_PATTERN,
                Rule::unique('keepvalues', 'fakelos')->ignore($creating ? null : $folder->id)],
            'keep' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'keep_alt' => ['nullable', 'string', 'max:255'],
            'describe' => ['nullable', 'string', 'max:'.Keepvalue::TEXT_MAX],
            'remarks' => ['nullable', 'string', 'max:'.Keepvalue::TEXT_MAX],
        ], [
            'fakelos.regex' => self::CODE_MESSAGE,
            'fakelos.unique' => 'Υπάρχει ήδη φάκελος με αυτόν τον κωδικό.',
        ], [
            'fakelos' => 'Κωδικός', 'keep' => 'Διατήρηση', 'keep_alt' => 'Διατήρηση', 'describe' => 'Περιγραφή', 'remarks' => 'Παρατηρήσεις',
        ])->validate();

        if (! $creating && $clean['fakelos'] !== $folder->fakelos && ($n = $this->protocolCount($folder->fakelos)) > 0) {
            throw ValidationException::withMessages(['fakelos' => "Ο κωδικός χρησιμοποιείται σε $n ".($n === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα')
                .' και δεν αλλάζει. Για άλλον κωδικό προσθέστε νέο φάκελο.']);
        }

        $d = $this->userIds($data['d'] ?? [], 'd');
        $e = $this->userIds($data['e'] ?? [], 'e');

        return DB::transaction(function () use ($folder, $creating, $clean, $d, $e) {
            $folder = $creating ? new Keepvalue() : $folder;
            $folder->fill($clean)->save();
            $this->setAssignments($folder, $d, $e);

            return $folder;
        });
    }

    /** @throws ValidationException (key: delete) */
    public function delete(Keepvalue $folder): void
    {
        if ($reason = $this->cannotDelete($folder)) {
            throw ValidationException::withMessages(['delete' => $reason]);
        }
        $folder->delete();   // its automatic assignments go with it (foreign key)
    }

    public function cannotDelete(Keepvalue $folder, ?int $count = null): ?string
    {
        $n = $count ?? $this->protocolCount($folder->fakelos);

        return $n > 0 ? "Χρησιμοποιείται σε $n ".($n === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα').'.' : null;
    }

    public function protocolCount(string $code): int
    {
        return Protocol::where('fakelos', $code)->count();
    }

    /** @return array<string, int> code => protocols */
    public function protocolCounts(): array
    {
        return Protocol::query()->whereNotNull('fakelos')->where('fakelos', '!=', '')
            ->groupBy('fakelos')->selectRaw('fakelos, COUNT(*) AS n')->pluck('n', 'fakelos')
            ->mapWithKeys(fn ($n, $code) => [mb_strtolower((string) $code) => (int) $n])->all();
    }

    /**
     * Reads a pasted list (from a spreadsheet: one folder per row, cells separated by Tab, or by ";"):
     * code, description, Διατήρηση (years or text), remarks. A first row that is not a folder (a heading) is skipped.
     *
     * @return list<array{line: int, fakelos: string, describe: ?string, keep: ?string, remarks: ?string, status: string, error: ?string}>
     *         status: new, update, same, error
     */
    public function parseImport(string $text): array
    {
        $rows = [];
        $seen = [];
        $existing = Keepvalue::all()->keyBy(fn ($f) => mb_strtolower($f->fakelos));
        $lines = preg_split('/\R/u', $text) ?: [];
        foreach ($lines as $i => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map(fn ($c) => trim($c, " \t\"'\u{00A0}"), str_contains($line, "\t") ? explode("\t", $line) : explode(';', $line));
            $code = $this->normaliseCode($cells[0] ?? '');
            if ($rows === [] && ! preg_match(self::CODE_PATTERN, $code) && ! preg_match('/^Φ/u', $code)) {
                continue;   // a heading such as "Κωδικός;Περιγραφή"
            }
            $row = [
                'line' => $i + 1, 'fakelos' => $code,
                'describe' => ($cells[1] ?? '') !== '' ? $cells[1] : null,
                'keep' => ($cells[2] ?? '') !== '' ? $cells[2] : null,
                'remarks' => ($cells[3] ?? '') !== '' ? $cells[3] : null,
                'status' => 'new', 'error' => null,
            ];
            $key = mb_strtolower($code);
            $old = $existing[$key] ?? null;
            try {
                $clean = $this->clean($row);
                Validator::make($clean, [
                    'fakelos' => ['required', 'max:255', 'regex:'.self::CODE_PATTERN],
                    'keep' => ['nullable', 'integer', 'min:1', 'max:1000'],
                    'keep_alt' => ['nullable', 'max:255'],
                    'describe' => ['nullable', 'max:'.Keepvalue::TEXT_MAX],
                    'remarks' => ['nullable', 'max:'.Keepvalue::TEXT_MAX],
                ], ['fakelos.regex' => 'Ο κωδικός πρέπει να ξεκινά με «Φ.»'], ['fakelos' => 'Κωδικός', 'keep_alt' => 'Διατήρηση'])->validate();
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['fakelos' => 'Ο ίδιος κωδικός υπάρχει και στη γραμμή '.$seen[$key].'.']);
                }
                if ($old) {
                    $changes = array_filter($this->importValues($clean), fn ($v, $k) => (string) $v !== (string) $old->$k, ARRAY_FILTER_USE_BOTH);
                    $row['status'] = $changes ? 'update' : 'same';
                }
            } catch (ValidationException $e) {
                $row['status'] = 'error';
                $row['error'] = collect($e->errors())->flatten()->first();
            }
            $seen[$key] ??= $i + 1;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Writes the rows of parseImport (only new and update). An existing folder takes the cells given; an empty cell
     * leaves its value as it was. Automatic assignments are not touched.
     *
     * @return array{created: int, updated: int}
     */
    public function import(string $text): array
    {
        $created = $updated = 0;
        DB::transaction(function () use ($text, &$created, &$updated) {
            foreach ($this->parseImport($text) as $row) {
                if (! in_array($row['status'], ['new', 'update'], true)) {
                    continue;
                }
                $values = $this->importValues($this->clean($row));
                $folder = Keepvalue::where('fakelos', $row['fakelos'])->first();
                if ($folder) {
                    $folder->fill($values)->save();
                    $updated++;
                } else {
                    Keepvalue::create($values);
                    $created++;
                }
            }
        });

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param  list<int|string>  $d  users for διεκπεραίωση
     * @param  list<int|string>  $e  users for ενημέρωση
     */
    public function setAssignments(Keepvalue $folder, array $d, array $e): void
    {
        FolderAssignment::where('keepvalue_id', $folder->id)->delete();
        foreach (['d' => $d, 'e' => array_diff($e, $d)] as $type => $ids) {   // διεκπεραίωση includes ενημέρωση
            foreach (array_unique(array_map('intval', $ids)) as $id) {
                FolderAssignment::create(['keepvalue_id' => $folder->id, 'user_id' => $id, 'type' => $type]);
            }
        }
    }

    /** "φ.1 " → "Φ.1", "Φ 1" → "Φ.1", "Φ1" → "Φ.1", "f.1" → "Φ.1": codes as typed or pasted, as the folders are written */
    public function normaliseCode(string $code): string
    {
        $code = trim(preg_replace('/\s+/u', ' ', $code) ?? '');
        if (preg_match('/^[ΦφFf]\s*\.?\s*(?=[\p{L}\p{N}])/u', $code, $m)) {
            $code = 'Φ.'.mb_substr($code, mb_strlen($m[0]));
        }

        return $code;
    }

    /** @return list<int> active users among the ids, else a validation error */
    private function userIds(array $ids, string $key): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $known = User::whereIn('id', $ids)->whereNotNull('active')->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($known) !== count($ids)) {
            throw ValidationException::withMessages([$key => 'Επιλέξτε μόνο ενεργούς χρήστες.']);
        }

        return $known;
    }

    /** @return array{fakelos: string, keep: ?int, keep_alt: ?string, describe: ?string, remarks: ?string} */
    private function clean(array $data): array
    {
        $text = fn ($v) => ($v = trim(str_replace("\r\n", "\n", (string) $v))) === '' ? null : $v;
        $keep = $text($data['keep'] ?? null);
        $years = $keep !== null && preg_match('/^\d+$/', $keep) ? (int) $keep : null;
        if ($keep !== null && $years === null && preg_match('/^(\d+)\s*(έτη|έτος|χρόνια|χρόνος)$/iu', $keep, $m)) {
            $years = (int) $m[1];   // "10 έτη" → 10
        }

        return [
            'fakelos' => $this->normaliseCode((string) ($data['fakelos'] ?? '')),
            'keep' => $years,
            'keep_alt' => $years === null ? $keep : null,
            'describe' => $text($data['describe'] ?? null),
            'remarks' => $text($data['remarks'] ?? null),
        ];
    }

    /** The values an import writes: only the cells that were given */
    private function importValues(array $clean): array
    {
        $values = ['fakelos' => $clean['fakelos']];
        if ($clean['keep'] !== null || $clean['keep_alt'] !== null) {
            $values['keep'] = $clean['keep'];
            $values['keep_alt'] = $clean['keep_alt'];
        }
        foreach (['describe', 'remarks'] as $k) {
            if ($clean[$k] !== null) {
                $values[$k] = $clean[$k];
            }
        }

        return $values;
    }
}
