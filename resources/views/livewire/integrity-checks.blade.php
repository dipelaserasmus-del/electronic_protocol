{{-- A01: «Έλεγχοι» (App\Livewire\IntegrityChecks; the rules: App\Services\Integrity\Checks). Only reads. --}}
<div class="integrity-checks">
    <h1 class="h4 mb-2">Έλεγχοι πρωτοκόλλου</h1>
    <p class="text-body-secondary small mb-3">
        Τι μοιάζει λάθος στα πρωτόκολλα με ημερομηνία μέσα στην περίοδο. Δεν αλλάζει τίποτα μόνο του: ανοίξτε ένα πρωτόκολλο
        για να το διορθώσετε, ή αφήστε το αν είναι σωστό.
    </p>

    <form wire:submit="check" class="card card-body shadow-sm mb-3" id="checks-range">
        <div class="row g-2 align-items-end">
            <div class="col-sm-6 col-lg-3">
                <x-date-input name="from" id="checks-from" label="Ημ/νία από" :value="$from" wire:model="from" help="" />
            </div>
            <div class="col-sm-6 col-lg-3">
                <x-date-input name="to" id="checks-to" label="Ημ/νία έως" :value="$to" wire:model="to" help="" />
            </div>
            <div class="col-lg-6 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" id="checks-run"><i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>Έλεγχος</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="range('days')" id="checks-days">{{ \App\Services\Integrity\Checks::DEFAULT_DAYS }} ημέρες</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="range('year')" id="checks-year">Φέτος</button>
            </div>
        </div>
    </form>

    @if ($results !== null)
        @if ($total === 0)
            <x-notice type="success" id="checks-summary">Κανένα πρόβλημα από {{ $from }} έως {{ $to }}.</x-notice>
        @else
            <x-notice type="warning" id="checks-summary">
                {{ $total }} {{ $total === 1 ? 'εύρημα' : 'ευρήματα' }} από {{ $from }} έως {{ $to }}.
            </x-notice>
        @endif

        @foreach ($results as $key => ['rule' => $rule, 'findings' => $findings])
            <section class="card shadow-sm mb-3 checks-rule" id="check-{{ $key }}" data-check="{{ $key }}" data-count="{{ count($findings) }}" wire:key="check-{{ $key }}">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <h2 class="h6 mb-0 me-auto">{{ $rule->title() }}</h2>
                    @if ($findings)
                        <span class="badge text-bg-warning rounded-pill">{{ count($findings) }}</span>
                    @else
                        <span class="badge text-bg-success rounded-pill"><i class="bi bi-check-lg" aria-hidden="true"></i> κανένα</span>
                    @endif
                </div>
                <div class="card-body py-2 small text-body-secondary">{{ $rule->explanation() }}</div>
                @if ($findings)
                    <ul class="list-group list-group-flush">
                        @foreach ($findings as $i => $f)
                            <li class="list-group-item d-flex flex-wrap align-items-center gap-2 checks-finding" wire:key="f-{{ $key }}-{{ $i }}">
                                <span class="fw-semibold text-nowrap checks-ref">
                                    @if ($f->protocolId)<a href="{{ url('/home/'.$f->protocolId) }}">{{ $f->ref }}</a>@else{{ $f->ref }}@endif
                                </span>
                                @if ($f->date)<span class="text-body-secondary text-nowrap">{{ $f->date->format('d/m/Y') }}</span>@endif
                                <span class="checks-message">{{ $f->message }}</span>
                                @if ($f->subject)<span class="text-body-secondary text-truncate checks-subject" style="max-width: 40rem">{{ $f->subject }}</span>@endif
                                @if ($key === 'gaps' && $canCreate && ($f->meta['year'] ?? null) == $formYear)
                                    <a href="{{ url('/home') }}?num={{ $f->meta['from'] }}" class="btn btn-sm btn-outline-success rounded-pill ms-auto checks-action">
                                        Καταχώριση με το {{ $f->meta['from'] }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    @endif
</div>
