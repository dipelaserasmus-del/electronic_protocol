<?php

namespace App\Livewire;

use App\Models\FolderAssignment;
use App\Models\Keepvalue;
use App\Models\User;
use App\Services\Folders\FolderCatalog;
use App\Support\FolderCode;
use App\Support\Roles;
use App\Support\TagList;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * R6b: "Φάκελοι και διατήρηση" at /keep. Everybody sees the folders (code, description, Διατήρηση, remarks, protocols
 * in it, automatic assignments); administrators add, change, delete and import them, and choose who gets a folder's
 * protocols automatically (applied on save, R1d). Rules: App\Services\Folders\FolderCatalog.
 */
class FolderAdmin extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** R6g (owner, 2026-11-25): the table sorted by Κωδικός ('code', the folders' own order) or Πρωτ. ('count') */
    #[Url(as: 'sort', except: 'code')]
    public string $sort = 'code';

    #[Url(as: 'dir', except: 'asc')]
    public string $dir = 'asc';

    /** a header pressed: that column ascending; pressed again: descending (a count starts from the most used) */
    public function sortBy(string $column): void
    {
        if (! in_array($column, ['code', 'count'], true)) {
            return;
        }
        $this->dir = $this->sort === $column ? ($this->dir === 'asc' ? 'desc' : 'asc') : ($column === 'count' ? 'desc' : 'asc');
        $this->sort = $column;
    }

    public ?int $editing = null;

    public bool $formOpen = false;

    public array $form = [];

    public bool $importOpen = false;

    public string $importText = '';

    /** @var list<array>|null rows of FolderCatalog::parseImport, shown before importing */
    public ?array $preview = null;

    public function mount(?int $keepvalue = null): void
    {
        $this->resetForm();
        if ($keepvalue && self::isAdmin() && Keepvalue::find($keepvalue)) {
            $this->edit($keepvalue);   // /keep/{id} opens that folder, as the old page did
        }
    }

    private static function isAdmin(): bool
    {
        return auth()->user()?->role?->role === Roles::ADMIN;
    }

    private static function authorizeAdmin(): void
    {
        abort_unless(self::isAdmin(), 403);
    }

    private function resetForm(): void
    {
        $this->form = ['fakelos' => '', 'keep' => '', 'describe' => '', 'remarks' => '', 'd' => [], 'e' => []];
        $this->editing = null;
        $this->resetErrorBag();
    }

    public function create(): void
    {
        self::authorizeAdmin();
        $this->resetForm();
        $this->importOpen = false;
        $this->formOpen = true;
    }

    public function edit(int $id): void
    {
        self::authorizeAdmin();
        $f = Keepvalue::with('assignments')->findOrFail($id);
        $this->resetErrorBag();
        $this->applyOpen = false;
        $this->editing = $f->id;
        $this->form = [
            'fakelos' => (string) $f->fakelos,
            'keep' => $f->keep ? (string) $f->keep : (string) $f->keep_alt,
            'describe' => (string) $f->describe,
            'remarks' => (string) $f->remarks,
            'd' => $f->assignments->where('type', 'd')->pluck('user_id')->map(fn ($id) => (string) $id)->values()->all(),
            'e' => $f->assignments->where('type', 'e')->pluck('user_id')->map(fn ($id) => (string) $id)->values()->all(),
        ];
        $this->importOpen = false;
        $this->formOpen = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->formOpen = false;
        $this->applyOpen = false;
    }

    /** R6i: the box «Εφαρμογή και στα υπάρχοντα πρωτόκολλα» of the folder being edited, and the protocols chosen */
    public bool $applyOpen = false;

    public string $applyScope = 'open';

    public function openApply(): void
    {
        self::authorizeAdmin();
        $this->applyOpen = $this->editing !== null;
        $this->applyScope = 'open';
    }

    public function closeApply(): void
    {
        $this->applyOpen = false;
    }

    /** R6i (owner, 2026-11-25): the saved rules of this folder to the protocols already in it, as chosen; no e-mails */
    public function applyToExisting(FolderCatalog $catalog): void
    {
        self::authorizeAdmin();
        $folder = $this->editing ? Keepvalue::find($this->editing) : null;
        if (! $folder || ! isset(FolderCatalog::EXISTING_SCOPES[$this->applyScope])) {
            return;
        }
        $n = $catalog->applyRulesToExisting($folder, $this->applyScope);
        $this->applyOpen = false;
        $this->dispatch('toast', message: $n
            ? "Η αυτόματη ανάθεση του {$folder->fakelos} εφαρμόστηκε σε $n ".($n === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα').' (χωρίς e-mail).'
            : 'Κανένα πρωτόκολλο δεν χρειαζόταν αλλαγή.', type: 'success');
    }

    public function save(FolderCatalog $catalog): void
    {
        self::authorizeAdmin();
        $folder = $this->editing ? Keepvalue::findOrFail($this->editing) : null;
        try {
            $saved = $catalog->save($folder, $this->form);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('form.'.($key === 'keep_alt' ? 'keep' : $key), $messages[0]);
            }
            return;
        }
        $this->dispatch('toast', message: $folder ? "Ο φάκελος {$saved->fakelos} ενημερώθηκε." : "Ο φάκελος {$saved->fakelos} προστέθηκε.", type: 'success');
        $this->cancel();
    }

    public function delete(int $id, FolderCatalog $catalog): void
    {
        self::authorizeAdmin();
        $f = Keepvalue::findOrFail($id);
        try {
            $catalog->delete($f);
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: "Ο φάκελος {$f->fakelos} δεν διαγράφηκε: ".$e->errors()['delete'][0], type: 'error');
            return;
        }
        if ($this->editing === $f->id) {
            $this->cancel();
        }
        $this->dispatch('toast', message: "Ο φάκελος {$f->fakelos} διαγράφηκε.", type: 'success');
    }

    public function openImport(): void
    {
        self::authorizeAdmin();
        $this->cancel();
        $this->importOpen = true;
        $this->preview = null;
    }

    public function closeImport(): void
    {
        $this->importOpen = false;
        $this->importText = '';
        $this->preview = null;
    }

    public function checkImport(FolderCatalog $catalog): void
    {
        self::authorizeAdmin();
        $this->preview = $catalog->parseImport($this->importText);
    }

    public function updatedImportText(): void
    {
        $this->preview = null;   // a changed list is checked again before importing
    }

    public function runImport(FolderCatalog $catalog): void
    {
        self::authorizeAdmin();
        $result = $catalog->import($this->importText);
        $this->closeImport();
        $this->dispatch('toast', message: "Εισαγωγή: {$result['created']} νέοι φάκελοι, {$result['updated']} ενημερώθηκαν.", type: 'success');
    }

    public function render(FolderCatalog $catalog)
    {
        $folders = Keepvalue::ordered()->with('assignments')->get();
        if (($q = trim($this->search)) !== '') {
            $folded = TagList::fold($q);
            $folders = $folders->filter(fn ($f) => FolderCode::startsWith($q, $f->fakelos)
                || str_contains(TagList::fold($f->describe.' '.$f->remarks.' '.$f->keep_alt), $folded))->values();
        }
        $counts = $catalog->protocolCounts();
        // R6g: by Πρωτ. (ties by code), or by code (the folders' own order, or reversed)
        $desc = $this->dir === 'desc';
        if ($this->sort === 'count') {
            $order = $folders->values()->map(fn ($f, $i) => [$counts[mb_strtolower($f->fakelos)] ?? 0, $i, $f]);
            $folders = $order->sort(fn ($a, $b) => ($desc ? $b[0] <=> $a[0] : $a[0] <=> $b[0]) ?: $a[1] <=> $b[1])->pluck(2)->values();
        } elseif ($desc) {
            $folders = $folders->reverse()->values();
        }
        $users = User::orderBy('name')->get(['id', 'name', 'active']);

        // R6i: the folder being edited: its saved rules and, with the box open, how many protocols each choice changes
        $editedFolder = $this->editing ? Keepvalue::find($this->editing) : null;
        $savedRules = $editedFolder ? \App\Support\FolderRules::for($editedFolder->fakelos) : [];

        return view('livewire.folder-admin', [
            'savedRules' => $savedRules,
            'existingCounts' => $this->applyOpen && $editedFolder && $savedRules ? $catalog->existingCounts($editedFolder) : null,
            'editedCount' => $editedFolder ? ($counts[mb_strtolower($editedFolder->fakelos)] ?? 0) : 0,
            'folders' => $folders,
            'total' => Keepvalue::count(),
            'counts' => $counts,
            'names' => $users->pluck('name', 'id'),
            // active users, and inactive ones still in the edited folder's rules (shown so they can be removed)
            'activeUsers' => $users->filter(fn ($u) => $u->active || in_array((string) $u->id, array_map('strval', array_merge($this->form['d'] ?? [], $this->form['e'] ?? [])), true))->values(),
            'isAdmin' => self::isAdmin(),
            'catalog' => $catalog,
            'withRules' => FolderAssignment::distinct()->count('keepvalue_id'),
        ]);
    }
}
