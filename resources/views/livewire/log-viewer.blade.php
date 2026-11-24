{{-- R6e: the log viewer (App\Livewire\LogViewer, App\Services\Logs\LogReader). Everything shown is escaped. --}}
@php
    $colour = ['emergency' => 'danger', 'alert' => 'danger', 'critical' => 'danger', 'error' => 'danger', 'warning' => 'warning',
               'notice' => 'info', 'info' => 'primary', 'debug' => 'secondary'];
    $at = array_search($day, $days, true);
@endphp
<div class="log-viewer" @if ($live) wire:poll.10s @endif>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 me-auto">Αρχεία καταγραφής</h1>
        <div class="input-group w-auto" role="group" aria-label="Ημέρα">
            <button type="button" class="btn btn-outline-secondary" wire:click="step(-1)" @disabled($at === false || $at === count($days) - 1)
                    title="Προηγούμενη ημέρα με καταγραφές" aria-label="Προηγούμενη ημέρα" id="log-prev">‹</button>
            <input type="date" class="form-control" wire:model.live="day" aria-label="Ημέρα" id="log-day"
                   @if ($days) min="{{ end($days) }}" max="{{ $days[0] }}" @endif>
            <button type="button" class="btn btn-outline-secondary" wire:click="step(1)" @disabled($at === false || $at === 0)
                    title="Επόμενη ημέρα με καταγραφές" aria-label="Επόμενη ημέρα" id="log-next">›</button>
        </div>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" role="switch" id="log-live" wire:model.live="live">
            <label class="form-check-label small" for="log-live">Ανανέωση κάθε 10″</label>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <div class="d-flex flex-wrap gap-1" role="group" aria-label="Επίπεδα" id="log-levels">
            @foreach ($allLevels as $level)
                <input type="checkbox" class="btn-check" id="lvl-{{ $level }}" value="{{ $level }}" wire:model.live="levels" autocomplete="off">
                <label class="btn btn-sm rounded-pill btn-outline-{{ $colour[$level] }}" for="lvl-{{ $level }}">{{ $level }}
                    <span class="badge rounded-pill text-bg-light">{{ $counts[$level] ?? 0 }}</span></label>
            @endforeach
        </div>
        <input type="search" class="form-control form-control-sm w-auto ms-auto" placeholder="Αναζήτηση" aria-label="Αναζήτηση στις καταγραφές"
               wire:model.live.debounce.400ms="q" id="log-search">
    </div>

    <div class="small text-body-secondary mb-2" id="log-summary">
        {{ $found }} από {{ $total }} καταγραφές στις {{ \Carbon\Carbon::parse($day)->format('d/m/Y') }}.
        @if ($truncated) <span class="text-warning-emphasis">Η ημέρα είναι πολύ μεγάλη: φαίνονται οι νεότερες καταγραφές της.</span> @endif
        @unless ($files) Δεν υπάρχουν αρχεία καταγραφής. @endunless
    </div>

    <div class="card shadow-sm">
        <div class="list-group list-group-flush log-entries" id="log-entries">
            @forelse ($entries as $e)
                @php $isOpen = in_array($e['id'], $open, true); @endphp
                <div class="list-group-item log-entry px-2 py-1" wire:key="log-{{ $e['id'] }}" data-level="{{ $e['level'] }}">
                    <button type="button" class="log-row btn btn-link text-reset text-decoration-none p-0 w-100 text-start d-flex gap-2 align-items-baseline"
                            wire:click="toggle(@js($e['id']))" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" @disabled($e['details'] === '')>
                        <span class="log-time font-monospace text-body-secondary">{{ $e['time'] }}</span>
                        <span class="badge text-bg-{{ $colour[$e['level']] ?? 'secondary' }} log-level">{{ $e['level'] }}</span>
                        <span class="log-message text-break flex-grow-1">{!! \App\Support\Highlight::html(mb_strimwidth($e['message'], 0, 600, '…'), $q) !!}</span>
                        @if ($e['details'] !== '')<i class="bi {{ $isOpen ? 'bi-chevron-up' : 'bi-chevron-down' }} text-body-secondary" aria-hidden="true"></i>@endif
                    </button>
                    @if ($isOpen)
                        <pre class="log-details mt-1 mb-1">{!! \App\Livewire\LogViewer::details($e['details'], $q) !!}</pre>
                        <div class="small text-body-secondary">{{ $e['file'] }} · {{ $e['channel'] }}</div>
                    @endif
                </div>
            @empty
                <div class="list-group-item text-center text-body-secondary py-4">Καμία καταγραφή.</div>
            @endforelse
        </div>
    </div>

    @if ($pages > 1)
        <nav class="d-flex justify-content-end align-items-center gap-2 mt-3" aria-label="Σελίδες" id="log-pages">
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="gotoPage({{ $page - 1 }})" @disabled($page <= 1)>‹</button>
            <span class="small">Σελίδα {{ $page }} από {{ $pages }}</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="gotoPage({{ $page + 1 }})" @disabled($page >= $pages)>›</button>
        </nav>
    @endif
</div>
