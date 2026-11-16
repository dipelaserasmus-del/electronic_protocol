{{-- R4b: the attachments' trash. Rules: App\Services\Attachments\Trash. --}}
<div class="trash-page">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Κάδος συνημμένων</h1>
        @if ($total > 0)
            <x-pill-actions class="flex-grow-0">
                <x-pill variant="danger" wire:click="purgeAll" data-confirm="Να διαγραφούν οριστικά όλα τα {{ $total }} αρχεία του κάδου;">Άδειασμα κάδου</x-pill>
            </x-pill-actions>
        @endif
    </div>
    <x-notice type="info" id="trash-retention">
        {{ $total }} {{ $total === 1 ? 'αρχείο' : 'αρχεία' }}, {{ \App\Support\FileBadge::size($totalSize) }}.
        @if ($days > 0)
            Κάθε αρχείο μένει στον κάδο {{ $days }} ημέρες και μετά διαγράφεται οριστικά.
        @else
            Τα αρχεία μένουν στον κάδο μέχρι να τον αδειάσετε.
        @endif
        Η διάρκεια αλλάζει στις <a href="{{ url('/settings') }}">Ρυθμίσεις</a>.
    </x-notice>

    <div class="card shadow-sm">
        <table class="table table-sm align-middle mb-0 table-sticky">
            <thead class="table-light">
                <tr>
                    <th scope="col">Αρχείο</th>
                    <th scope="col">Πρωτόκολλο</th>
                    <th scope="col" class="d-none d-md-table-cell">Διαγραφή</th>
                    <th scope="col" class="d-none d-md-table-cell">Οριστική διαγραφή</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Ενέργειες</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $a)
                    @php $until = $trash->purgeDate($a); @endphp
                    <tr wire:key="t-{{ $a->id }}" class="trash-row">
                        <td>
                            <x-file-badge :name="$a->name" :size="$a->size" :mime="$a->mimeType" />
                            @if ($a->storedFilePath())
                                <a href="{{ url('/trash/'.$a->id.'/download') }}" target="_blank" class="text-break">{{ $a->name ?: $a->ada }}</a>
                            @else
                                <span class="text-break">{{ $a->name ?: $a->ada }}</span> <small class="text-danger">(το αρχείο λείπει)</small>
                            @endif
                        </td>
                        <td class="small">
                            @if ($a->protocol)
                                <a href="{{ url('/home/'.$a->protocol->id) }}">{{ $a->protocol->protocolnum }}/{{ $a->protocol->etos }}</a>
                                <div class="text-body-secondary text-break">{{ $a->protocol->thema }}</div>
                            @else
                                <span class="text-body-secondary">{{ $a->protocol_label ?: 'πρωτόκολλο #'.$a->protocol_id }}</span>
                                <div><span class="badge text-bg-secondary">το πρωτόκολλο διαγράφηκε</span></div>
                            @endif
                        </td>
                        <td class="small d-none d-md-table-cell">
                            {{ $a->deleted_at?->format('d/m/Y H:i') }}
                            <div class="text-body-secondary">{{ $a->deleted_by ? ($names[$a->deleted_by] ?? '—') : '—' }}</div>
                        </td>
                        <td class="small d-none d-md-table-cell">{{ $until ? $until->format('d/m/Y') : 'χειροκίνητα' }}</td>
                        <td class="text-end text-nowrap">
                            @if ($a->protocol)
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" wire:click="restore({{ $a->id }})">Επαναφορά</button>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" wire:click="purge({{ $a->id }})"
                                    data-confirm="Να διαγραφεί οριστικά το «{{ $a->name ?: $a->ada }}»;">Οριστική διαγραφή</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">Ο κάδος είναι άδειος.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
</div>
