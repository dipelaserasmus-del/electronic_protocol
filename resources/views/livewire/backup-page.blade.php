{{-- R6c: copies of the database. Rules: App\Services\DatabaseBackup (B10). --}}
<div class="backup-page">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Αντίγραφα ασφαλείας</h1>
        <x-pill-actions class="flex-grow-0">
            <x-pill variant="primary" wire:click="create" wire:loading.attr="disabled" wire:target="create" id="backup-create">
                <span wire:loading.remove wire:target="create"><i class="bi bi-database-down me-1" aria-hidden="true"></i>Νέο αντίγραφο</span>
                <span wire:loading wire:target="create">Δημιουργία…</span>
            </x-pill>
        </x-pill-actions>
    </div>
    <x-notice type="info">
        Το αντίγραφο περιέχει τη <strong>βάση δεδομένων</strong> (πρωτόκολλα, χρήστες, ρυθμίσεις), όχι τα συνημμένα αρχεία.
        Φυλάσσεται στον διακομιστή (<code class="text-break">{{ $dir }}</code>): κατεβάστε το και κρατήστε το και σε άλλο μέσο.
    </x-notice>
    <div class="card shadow-sm">
        <table class="table table-sm align-middle mb-0" id="backups-table">
            <thead class="table-light">
                <tr><th scope="col">Αρχείο</th><th scope="col">Δημιουργήθηκε</th><th scope="col" class="text-end">Μέγεθος</th><th scope="col" class="text-end"><span class="visually-hidden">Ενέργειες</span></th></tr>
            </thead>
            <tbody>
                @forelse ($files as $f)
                    <tr wire:key="b-{{ $f['name'] }}" class="backup-row" data-name="{{ $f['name'] }}">
                        <td class="text-break"><a href="{{ url('/downloadBackup/'.$f['name']) }}">{{ $f['name'] }}</a></td>
                        <td class="small text-nowrap">{{ \Carbon\Carbon::createFromTimestamp($f['time'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                        <td class="small text-end text-nowrap">{{ \App\Support\FileBadge::size($f['size']) }}</td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ url('/downloadBackup/'.$f['name']) }}">Λήψη</a>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" wire:click="delete('{{ $f['name'] }}')"
                                    data-confirm="Να διαγραφεί το {{ $f['name'] }};">Διαγραφή</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-body-secondary py-4">Δεν υπάρχει αντίγραφο ακόμη.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
