{{-- R7: what to print. Rules: App\Services\Printing\PrintRange; output: App\Http\Controllers\PrintController. --}}
{{-- the links are built from the fields at the moment of the click (review of R7: a value typed just before the click
     was not yet on the server, so the old range was printed or exported) --}}
<div class="print-page" x-data="{
        link(base, xls) {
            const p = new URLSearchParams();
            for (const [key, id] of [['etosForMany', 'p-year'], ['aponum', 'p-from'], ['eosnum', 'p-to'], ['apoProtocolDate', 'p-from-date'], ['eosProtocolDate', 'p-to-date']]) {
                const v = (document.getElementById(id)?.value || '').trim();
                if (v !== '') p.set(key, v);
            }
            if (xls) p.set('target', 'xls');
            return base + '?' + p.toString();
        }
    }">
    <h1 class="h4 mb-3">Εκτύπωση</h1>
    <div class="card shadow-sm" style="max-width: 46rem">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <li class="nav-item"><button type="button" role="tab" id="mode-book" class="nav-link @if ($mode === 'book') active @endif" aria-selected="{{ $mode === 'book' ? 'true' : 'false' }}" wire:click="setMode('book')"><i class="bi bi-journal-text me-1" aria-hidden="true"></i>Βιβλίο πρωτοκόλλου</button></li>
                @if ($mayPrintAttachments)
                    <li class="nav-item"><button type="button" role="tab" id="mode-attachments" class="nav-link @if ($mode === 'attachments') active @endif" aria-selected="{{ $mode === 'attachments' ? 'true' : 'false' }}" wire:click="setMode('attachments')"><i class="bi bi-paperclip me-1" aria-hidden="true"></i>Συνημμένα αρχεία</button></li>
                @endif
            </ul>
        </div>
        <div class="card-body">
            <p class="small text-body-secondary">
                @if ($mode === 'book')
                    Το βιβλίο με τις 13 στήλες του, για τα πρωτόκολλα που διαλέγετε. Και σε Excel.
                @else
                    Τα πρωτόκολλα με συνημμένα, κάθε αρχείο με τον χρόνο διατήρησης και τη λήξη του.
                @endif
            </p>
            <div class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label for="p-year" class="form-label small fw-semibold mb-1">Έτος</label>
                    <input id="p-year" class="form-control" inputmode="numeric" wire:model.live.debounce.400ms="year" placeholder="όλα">
                </div>
                <div class="col-sm-4">
                    <label for="p-from" class="form-label small fw-semibold mb-1">Αριθμοί</label>
                    <div class="input-group">
                        <input id="p-from" class="form-control" inputmode="numeric" wire:model.live.debounce.400ms="from" placeholder="από" aria-label="Από αριθμό">
                        <input id="p-to" class="form-control" inputmode="numeric" wire:model.live.debounce.400ms="to" placeholder="έως" aria-label="Έως αριθμό">
                    </div>
                </div>
                <div class="col-sm-5">
                    <label for="p-from-date" class="form-label small fw-semibold mb-1">Ημερομηνίες παραλαβής</label>
                    <div class="input-group">
                        <input id="p-from-date" class="form-control" wire:model.live.debounce.600ms="fromDate" placeholder="από (ηη/μμ/εεεε)" aria-label="Από ημερομηνία">
                        <input id="p-to-date" class="form-control" wire:model.live.debounce.600ms="toDate" placeholder="έως" aria-label="Έως ημερομηνία">
                    </div>
                </div>
            </div>

            <div class="mt-3" id="print-summary" aria-live="polite">
                @if ($range->ignored)
                    <x-notice type="warning">Δεν ισχύουν και δεν λαμβάνονται υπόψη: {!! implode(', ', $range->ignored) !!}.</x-notice>
                @endif
                @if ($count === null)
                    <x-notice type="info">Συμπληρώστε έτος, αριθμούς ή ημερομηνίες.</x-notice>
                @elseif ($count === 0)
                    <x-notice type="warning">Κανένα πρωτόκολλο @if ($mode === 'attachments') με συνημμένα @endif δεν ταιριάζει.</x-notice>
                @else
                    <x-notice :type="$count > $limit ? 'warning' : 'success'">
                        {{ $count }} {{ $count === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα' }}@if ($mode === 'attachments') με συνημμένα @endif ({{ $range->describe() }}).
                        @if ($count > $limit) Εκτυπώνονται τα πρώτα {{ $limit }} (όριο στις Ρυθμίσεις): χωρίστε τους αριθμούς σε κομμάτια. @endif
                    </x-notice>
                @endif
            </div>

            <div class="d-flex flex-wrap gap-2">
                @if ($printUrl && $count)
                    <a class="btn btn-primary rounded-pill" href="{{ $printUrl }}" target="_blank" id="print-open"
                       data-base="{{ url($mode === 'attachments' ? '/printedAttachments' : '/printed') }}" x-on:click="$el.href = link($el.dataset.base, false)"><i class="bi bi-printer me-1" aria-hidden="true"></i>Εκτύπωση</a>
                    @if ($xlsUrl)
                        <a class="btn btn-outline-success rounded-pill" href="{{ $xlsUrl }}" id="print-xls"
                           data-base="{{ url('/printed') }}" x-on:click="$el.href = link($el.dataset.base, true)"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>Excel</a>
                    @endif
                @else
                    <button type="button" class="btn btn-primary rounded-pill" disabled>Εκτύπωση</button>
                @endif
            </div>
        </div>
    </div>
</div>
