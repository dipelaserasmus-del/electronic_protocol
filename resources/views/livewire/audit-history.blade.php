{{-- X02: the audit log (App\Livewire\AuditHistory; written by App\Services\Audit\AuditLog, X01). Only reads. --}}
@php use App\Services\Audit\AuditPresenter as P; @endphp
<div class="audit-history">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Ιστορικό ενεργειών</h1>
        <x-pill-actions class="flex-grow-0">
            <x-pill wire:click="export" id="history-export" title="Όσα δείχνει το φίλτρο, όλες οι σελίδες, για Excel">
                <i class="bi bi-filetype-csv me-1" aria-hidden="true"></i>Εξαγωγή CSV</x-pill>
        </x-pill-actions>
    </div>
    <p class="text-body-secondary small mb-3">
        Ποιος έκανε τι και πότε: πρωτόκολλα, συνημμένα, αναθέσεις, φάκελοι, χρήστες, ρυθμίσεις, συνδέσεις και λήψεις αρχείων.
        Δεν αλλάζει και δεν σβήνεται από την εφαρμογή.
        @if ($keepDays > 0) Κρατιούνται {{ $keepDays }} ημέρες. @else Κρατιούνται όλα. @endif
    </p>

    <form wire:submit="apply" class="card card-body shadow-sm mb-3" id="history-filters">
        <div class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-2">
                <label for="history-user" class="form-label small fw-semibold mb-1">Χρήστης</label>
                <select id="history-user" class="form-select" wire:model="user">
                    <option value="">Όλοι</option>
                    <option value="{{ \App\Livewire\AuditHistory::NOBODY }}">— κανένας (αυτόματα, αποτυχημένες συνδέσεις)</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username }})</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="history-action" class="form-label small fw-semibold mb-1">Ενέργεια</label>
                <select id="history-action" class="form-select" wire:model="action">
                    <option value="">Όλες</option>
                    @foreach (P::ACTIONS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-lg-2">
                <x-date-input name="from" id="history-from" label="Ημ/νία από" :value="$from" wire:model="from" help="" />
            </div>
            <div class="col-sm-6 col-lg-2">
                <x-date-input name="to" id="history-to" label="Ημ/νία έως" :value="$to" wire:model="to" help="" />
            </div>
            <div class="col-sm-6 col-lg-2">
                <label for="history-protocol" class="form-label small fw-semibold mb-1">Πρωτόκολλο</label>
                <input id="history-protocol" class="form-control @error('protocol') is-invalid @enderror" placeholder="π.χ. 125/2026"
                       wire:model="protocol" inputmode="numeric">
                @error('protocol')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary" id="history-apply"><i class="bi bi-search me-1" aria-hidden="true"></i>Αναζήτηση</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="clear" id="history-clear">Καθαρισμός</button>
            </div>
        </div>
    </form>

    @if ($protocolId)
        <p class="small mb-2" id="history-protocol-link">
            Πρωτόκολλο <a href="{{ url('/home/'.$protocolId) }}">{{ $protocol }}</a>: οι ενέργειες στο ίδιο, στα συνημμένα και στις αναθέσεις του.
        </p>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <span class="small text-body-secondary" id="history-count">{{ $entries->total() }} {{ $entries->total() === 1 ? 'εγγραφή' : 'εγγραφές' }}</span>
        <x-list-pager :paginator="$entries" position="top" class="ms-auto justify-content-end" />
    </div>
    <div class="card shadow-sm">
        <table class="table table-sm align-middle mb-0 table-sticky" id="history-table">
            <thead class="table-light">
                <tr>
                    <th scope="col">Πότε</th>
                    <th scope="col">Χρήστης</th>
                    <th scope="col">Ενέργεια</th>
                    <th scope="col">Αφορά</th>
                    <th scope="col">Αλλαγές</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $e)
                    <tr wire:key="h-{{ $e->id }}" class="history-row" data-action="{{ $e->action }}">
                        <td class="small text-nowrap">{{ $e->created_at->format('d/m/Y') }}
                            <div class="text-body-secondary">{{ $e->created_at->format('H:i:s') }}</div></td>
                        <td class="small">{{ $e->username ?? '—' }}
                            @if ($e->ip)<div class="text-body-secondary">{{ $e->ip }}</div>@endif</td>
                        <td><span class="badge text-bg-{{ P::COLOURS[$e->action] ?? 'secondary' }} history-action">{{ P::action($e->action) }}</span></td>
                        <td class="small history-subject">
                            @if ($e->protocol_id && $e->entity_type === 'protocol')
                                <a href="{{ url('/home/'.$e->protocol_id) }}">{{ P::subject($e) }}</a>
                            @else
                                {{ P::subject($e) }}
                                @if ($e->protocol_id)<div><a class="text-body-secondary" href="{{ url('/home/'.$e->protocol_id) }}">του πρωτοκόλλου</a></div>@endif
                            @endif
                            @if (($notes = P::notes($e)) !== '')<div class="text-body-secondary">{{ $notes }}</div>@endif
                        </td>
                        <td class="small history-changes">
                            @foreach (P::changes($e) as [$label, $old, $new])
                                <div class="text-break"><span class="fw-semibold">{{ $label }}:</span>
                                    @if ($e->action === 'updated' || $e->action === 'restored')<span class="text-body-secondary text-decoration-line-through">{{ $old }}</span> → @endif
                                    {{ $e->action === 'deleted' || $e->action === 'purged' || $e->action === 'trashed' ? $old : $new }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-4" id="history-empty">Καμία εγγραφή.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-list-pager :paginator="$entries" position="bottom" class="justify-content-end mt-2" />
</div>
