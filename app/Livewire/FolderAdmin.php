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
        $users = User::orderBy('name')->get(['id', 'name', 'active']);

        return view('livewire.folder-admin', [
            'folders' => $folders,
            'total' => Keepvalue::count(),
            'counts' => $counts,
            'names' => $users->pluck('name', 'id'),
            'activeUsers' => $users->whereNotNull('active')->values(),
            'isAdmin' => self::isAdmin(),
            'catalog' => $catalog,
            'withRules' => FolderAssignment::distinct()->count('keepvalue_id'),
        ]);
    }
}
