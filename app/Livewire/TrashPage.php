<?php

namespace App\Livewire;

use App\Models\Attachment;
use App\Models\User;
use App\Services\Attachments\Trash;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * R4b: "Κάδος" (administrators): the attachments moved to the trash, with the protocol they belonged to, who deleted
 * them and when, and when they will be deleted for good. Restore (to a protocol that still exists) or delete now.
 */
class TrashPage extends Component
{
    use WithPagination;
    use \App\Livewire\Concerns\PagesWithinRange;

    protected string $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        abort_unless(auth()->user()?->role?->role === 'Διαχειριστής', 403);
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

    public function render(Trash $trash)
    {
        return view('livewire.trash-page', [
            'items' => $this->pageOf(Attachment::onlyTrashed()->with('protocol')->orderByDesc('deleted_at')->orderByDesc('id'), 50),
            'total' => Attachment::onlyTrashed()->count(),
            'totalSize' => (int) Attachment::onlyTrashed()->sum('size'),
            'days' => $trash->retentionDays(),
            'trash' => $trash,
            'names' => User::pluck('name', 'id'),
        ]);
    }
}
