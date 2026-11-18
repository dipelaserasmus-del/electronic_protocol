{{-- R6c: expired attachments ("Εκκαθάριση"). Rules: App\Services\Attachments\Expired. --}}
<div class="archive-page">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Εκκαθάριση αρχείου</h1>
        @if ($total > 0)
            <x-pill-actions class="flex-grow-0">
                <x-pill :href="url('/expired')" target="_blank" id="expired-print"><i class="bi bi-printer me-1" aria-hidden="true"></i>Εκτύπωση λίστας</x-pill>
                <x-pill variant="danger" wire:click="trashExpired" id="expired-trash"
                        data-confirm="Να μεταφερθούν στον κάδο τα {{ $total }} αρχεία που έληξαν;">Μεταφορά στον κάδο</x-pill>
            </x-pill-actions>
        @endif
    </div>
    <x-notice type="info" id="archive-summary">
        @if ($total)
            {{ $total }} {{ $total === 1 ? 'συνημμένο έχει' : 'συνημμένα έχουν' }} περάσει τον χρόνο διατήρησης του φακέλου τους.
            Η μεταφορά στον κάδο δεν τα σβήνει ακόμη:
            @if ($days > 0) μένουν εκεί {{ $days }} ημέρες. @else μένουν εκεί μέχρι να τον αδειάσετε. @endif
        @else
            Κανένα συνημμένο δεν έχει περάσει τον χρόνο διατήρησης.
        @endif
        Στον <a href="{{ url('/trash') }}">κάδο</a>: {{ $trashCount }}.
    </x-notice>

    @if ($total)
        <div class="card shadow-sm">
            <table class="table table-sm align-middle mb-0 table-sticky" id="expired-table">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Φάκελος</th>
                        <th scope="col">Πρωτόκολλο</th>
                        <th scope="col">Αρχείο</th>
                        <th scope="col" class="d-none d-md-table-cell">Έληξε</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $a)
                        <tr wire:key="x-{{ $a->id }}" class="expired-row">
                            <td class="text-nowrap small">{{ $a->protocol?->fakelos }}</td>
                            <td class="small">
                                @if ($a->protocol)
                                    <a href="{{ url('/home/'.$a->protocol->id) }}">{{ $a->protocol->protocolnum }}/{{ $a->protocol->etos }}</a>
                                    <div class="text-body-secondary text-break">{{ $a->protocol->thema }}</div>
                                @endif
                            </td>
                            <td class="text-break small">{{ $a->name ?: 'ΑΔΑ '.$a->ada }}</td>
                            <td class="small d-none d-md-table-cell text-nowrap">{{ $a->expiresDate()?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $items->links() }}</div>
    @endif
</div>
