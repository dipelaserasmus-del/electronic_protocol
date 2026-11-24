<?php

namespace App\Livewire;

use App\Models\Attachment;
use App\Models\User;
use App\Models\TrashedProtocol;
use App\Services\Attachments\Trash;
use App\Services\Protocols\ProtocolTrash;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * R4b: "Κάδος" (administrators): the attachments moved to the trash, with the protocol they belonged to, who deleted
 * them and when, and when they will be deleted for good. Restore (to a protocol that still exists) or delete now.
 * R4h: a tab «Πρωτόκολλα» with the deleted protocols (App\Services\Protocols\ProtocolTrash). Συγγραφέας and
 * Αναθέτων open the trash too, but see only that tab and only the protocols they registered (they may restore them).
 */
class TrashPage extends Component
{
    use WithPagination;
    use \App\Livewire\Concerns\PagesWithinRange;

    protected string $paginationTheme = 'bootstrap';

    /** R4h: 'protocols' or 'files' (administrators only) */
    #[\Livewire\Attributes\Url]
    public string $tab = '';

    /** R4h: search in the trashed protocols (number, subject) */
    #[\Livewire\Attributes\Url(as: 'q')]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(ProtocolTrash::mayOpen(auth()->user()), 403);
        if (! $this->admin() || ! in_array($this->tab, ['protocols', 'files'], true)) {
            $this->tab = $this->admin() && $this->tab === '' ? 'files' : 'protocols';
        }
    }

    private function admin(): bool
    {
        return auth()->user()?->role?->role === 'Διαχειριστής';
    }

    public function show(string $tab): void
    {
        $this->tab = $this->admin() && $tab === 'files' ? 'files' : 'protocols';
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /** R4h: a deleted protocol back (original number if free, else a new one) */
    public function restoreProtocol(int $id, ProtocolTrash $trash): void
    {
        $t = TrashedProtocol::findOrFail($id);
        try {
            $r = $trash->restore(auth()->user(), $t);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
            return;
        }
        $p = $r['protocol'];
        $now = "{$p->protocolnum}/{$p->etos}";
        $message = $r['newNumber']
            ? "Το πρωτόκολλο επανήλθε με νέο αριθμό $now: ο αρχικός {$r['original']} έχει δοθεί σε άλλο πρωτόκολλο."
            : "Το πρωτόκολλο $now επανήλθε με τον αριθμό του.";
        $this->dispatch('toast', message: $message, type: $r['newNumber'] ? 'warning' : 'success',
            url: url('/home/'.$p->id), label: "Άνοιγμα πρωτοκόλλου $now");
    }

    /** R4h: a deleted protocol for good, with its files (administrators) */
    public function purgeProtocol(int $id, ProtocolTrash $trash): void
    {
        abort_unless(ProtocolTrash::mayPurge(auth()->user()), 403);
        $t = TrashedProtocol::findOrFail($id);
        $trash->purge($t);
        $this->dispatch('toast', message: "Το πρωτόκολλο {$t->label()} διαγράφηκε οριστικά.", type: 'success');
    }

    public function restore(int $id, Trash $trash): void
    {
        abort_unless(auth()->user()?->role?->role === 'Διαχειριστής', 403);
        $a = Attachment::onlyTrashed()->findOrFail($id);
        if (! $trash->restore($a)) {
            $this->dispatch('toast', message: 'Το πρωτόκολλο δεν υπάρχει πια: δεν γίνεται επαναφορά.', type: 'error');
            return;
        }
        // R1e: the notice links to the protocol the file went back to
        $p = $a->fresh()->protocol;
        $this->dispatch('toast', message: "Το «{$a->name}» επέστρεψε στο πρωτόκολλό του.", type: 'success',
            url: $p ? url('/home/'.$p->id) : null, label: $p ? "Άνοιγμα πρωτοκόλλου {$p->protocolnum}/{$p->etos}" : null);
    }

    public function purge(int $id, Trash $trash): void
    {
        abort_unless(auth()->user()?->role?->role === 'Διαχειριστής', 403);
        $a = Attachment::onlyTrashed()->findOrFail($id);
        $trash->purge($a);
        $this->dispatch('toast', message: "Το «{$a->name}» διαγράφηκε οριστικά.", type: 'success');
    }

    public function purgeAll(Trash $trash): void
    {
        abort_unless(auth()->user()?->role?->role === 'Διαχειριστής', 403);
        $n = 0;
        Attachment::onlyTrashed()->orderBy('id')->chunkById(200, function ($rows) use ($trash, &$n) {
            foreach ($rows as $a) {
                $trash->purge($a);
                $n++;
            }
        });
        $this->resetPage();
        $this->dispatch('toast', message: "Ο κάδος άδειασε ($n αρχεία).", type: 'success');
    }

    public function render(Trash $trash, ProtocolTrash $protocols)
    {
        $user = auth()->user();
        $visible = ProtocolTrash::visibleTo($user);
        if (($q = trim($this->search)) !== '') {
            $visible->where(fn ($w) => ctype_digit($q) ? $w->where('protocolnum', (int) $q) : $w->where('thema', 'like', \App\Services\Search\ProtocolSearch::like($q)));
        }
        if ($this->tab === 'protocols') {
            return view('livewire.trash-page', [
                'protocols' => $this->pageOf($visible->orderByDesc('deleted_at')->orderByDesc('id'), 50),
                'protocolTotal' => ProtocolTrash::visibleTo($user)->count(),
                'fileTotal' => $this->admin() ? Attachment::onlyTrashed()->count() : 0,
                'days' => $trash->retentionDays(),
                'protocolTrash' => $protocols,
                'names' => User::pluck('name', 'id'),
                'isAdmin' => $this->admin(),
            ]);
        }
        return view('livewire.trash-page', [
            'protocolTotal' => ProtocolTrash::visibleTo($user)->count(),
            'fileTotal' => Attachment::onlyTrashed()->count(),
            'isAdmin' => true,
            'items' => $this->pageOf(Attachment::onlyTrashed()->with('protocol')->orderByDesc('deleted_at')->orderByDesc('id'), 50),
            'total' => Attachment::onlyTrashed()->count(),
            'totalSize' => (int) Attachment::onlyTrashed()->sum('size'),
            'days' => $trash->retentionDays(),
            'trash' => $trash,
            'names' => User::pluck('name', 'id'),
        ]);
    }
}
