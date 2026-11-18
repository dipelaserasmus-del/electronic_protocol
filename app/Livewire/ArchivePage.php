<?php

namespace App\Livewire;

use App\Services\Attachments\Expired;
use App\Services\Attachments\Trash;
use App\Support\Roles;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * R6c: "Εκκαθάριση" (administrators): the attachments whose keeping time has passed, with their protocol and folder;
 * print the list (/expired) and move them all to the trash (R4b: restore or delete for good on /trash).
 * Before, the page only gave counts, and "delete" and "empty the trash" were plain links (GET, no CSRF check).
 */
class ArchivePage extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        self::authorizeAdmin();
    }

    private static function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role?->role === Roles::ADMIN, 403);
    }

    public function trashExpired(Expired $expired): void
    {
        self::authorizeAdmin();
        $n = $expired->trashAll(auth()->user());
        $this->resetPage();
        $this->dispatch('toast', message: $n ? "Μεταφέρθηκαν στον κάδο $n ".($n === 1 ? 'αρχείο' : 'αρχεία').'.' : 'Δεν υπάρχουν ληγμένα αρχεία.',
            type: 'success', url: $n ? url('/trash') : null, label: $n ? 'Άνοιγμα κάδου' : null);
    }

    public function render(Expired $expired, Trash $trash)
    {
        return view('livewire.archive-page', [
            'items' => $expired->query()->paginate(100),
            'total' => $expired->count(),
            'trashCount' => \App\Models\Attachment::onlyTrashed()->count(),
            'days' => $trash->retentionDays(),
        ]);
    }
}
