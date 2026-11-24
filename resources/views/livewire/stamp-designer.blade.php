{{-- R4e: the stamp designer (App\Livewire\StampDesigner); drawn by App\Services\Stamp\StampRenderer --}}
@php
    $num = fn (string $model, string $limit, float $step = 0.5) => 'wire:model.live.debounce.400ms="'.$model.'" type="number" step="'.$step.'" min="'.$limits[$limit][0].'" max="'.$limits[$limit][1].'"';
    $scale = 1.6;   // px per mm on the page drawing
    [$sw, $sh] = $sizeMm;
    $left = str_contains($template->corner, 'right') ? 210 - $template->x - $sw : $template->x;
    $top = str_contains($template->corner, 'top') ? $template->y : 297 - $template->y - $sh;
@endphp
<div class="stamp-designer">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 me-auto">Σφραγίδα αρχείων</h1>
        <x-pill-actions class="flex-grow-0">
            <x-pill wire:click="resetDefaults" data-confirm="Να γίνει επαναφορά στην αρχική σφραγίδα; Αποθηκεύεται μόνο με «Αποθήκευση».">Αρχική σφραγίδα</x-pill>
            <x-pill variant="primary" wire:click="save" id="stamp-save">Αποθήκευση</x-pill>
        </x-pill-actions>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold">Γενικά</div>
                <div class="card-body row g-2">
                    <div class="col-sm-4">
                        <label class="form-label small fw-semibold mb-1" for="st-width">Πλάτος (mm)</label>
                        <input class="form-control" id="st-width" {!! $num('tpl.width', 'width', 1) !!}>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-semibold mb-1" for="st-color">Χρώμα</label>
                        <input class="form-control form-control-color w-100" id="st-color" type="color" wire:model.live="tpl.color">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small fw-semibold mb-1" for="st-gap">Απόσταση γραμμών (mm)</label>
                        <input class="form-control" id="st-gap" {!! $num('tpl.gap', 'gap', 0.1) !!}>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="st-border" wire:model.live="tpl.border">
                            <label class="form-check-label" for="st-border">Πλαίσιο γύρω από τη σφραγίδα</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mb-3" id="stamp-emblem">
                <div class="card-header fw-semibold">Έμβλημα <small class="fw-normal text-body-secondary">· στο πάνω μέρος, στο κέντρο</small></div>
                <div class="card-body row g-2 align-items-end">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold mb-1" for="st-emblem-file">{{ $hasEmblem ? 'Αλλαγή εικόνας' : 'Εικόνα' }}</label>
                        <input class="form-control @error('emblemUpload') is-invalid @enderror" id="st-emblem-file" type="file" accept="image/png,image/jpeg,image/webp" wire:model="emblemUpload">
                        @error('emblemUpload')<div class="invalid-feedback">{{ $message }}</div>@else
                        <div class="form-text">PNG υψηλής ανάλυσης, με διάφανο φόντο αν γίνεται.</div>@enderror
                    </div>
                    <div class="col-sm-3">
                        <label class="form-label small fw-semibold mb-1" for="st-emblem-h">Ύψος (mm)</label>
                        <input class="form-control" id="st-emblem-h" {!! $num('tpl.emblemHeight', 'emblem', 0.5) !!} @disabled(! $hasEmblem)>
                    </div>
                    <div class="col-sm-3">
                        @if ($hasEmblem)
                            <button type="button" class="btn btn-outline-danger rounded-pill w-100" wire:click="removeEmblem" id="st-emblem-remove">Αφαίρεση</button>
                        @endif
                    </div>
                    @if ($hasEmblem)
                        <div class="col-12 d-flex flex-wrap gap-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="st-emblem-show" wire:model.live="tpl.emblem">
                                <label class="form-check-label" for="st-emblem-show">Εμφάνιση εμβλήματος</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="st-tint" wire:model.live="tpl.tint">
                                <label class="form-check-label" for="st-tint">Στο χρώμα της σφραγίδας</label>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm mb-3" id="stamp-lines">
                <div class="card-header fw-semibold d-flex align-items-center">
                    <span class="me-auto">Γραμμές</span>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" wire:click="addLine" id="st-add-line"
                            @disabled(count($tpl['lines'] ?? []) >= $limits['lines'][1])><i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Γραμμή</button>
                </div>
                <div class="card-body">
                    <div class="small text-body-secondary mb-2">Πεδία: <code>{αριθμός}</code> <code>{ημερομηνία}</code> <code>{έτος}</code> <code>{υπηρεσία}</code>.
                        «Με τελείες»: η ετικέτα αριστερά και η τιμή πάνω σε διακεκομμένη γραμμή ως το τέλος (π.χ. <code>ΑΡΙΘΜ. ΠΡΩΤ. {αριθμός}</code>).</div>
                    @forelse ($tpl['lines'] ?? [] as $i => $line)
                        <div class="border rounded p-2 mb-2 stamp-line" wire:key="line-{{ $i }}">
                            <div class="d-flex gap-2 mb-2">
                                <input class="form-control" aria-label="Κείμενο γραμμής {{ $i + 1 }}" maxlength="120" placeholder="Κείμενο"
                                       wire:model.live.debounce.400ms="tpl.lines.{{ $i }}.text" data-line-text="{{ $i }}">
                                <div class="btn-group btn-group-sm flex-shrink-0">
                                    <button type="button" class="btn btn-outline-secondary" wire:click="moveLine({{ $i }}, -1)" @disabled($i === 0) title="Πάνω" aria-label="Πάνω"><i class="bi bi-arrow-up"></i></button>
                                    <button type="button" class="btn btn-outline-secondary" wire:click="moveLine({{ $i }}, 1)" @disabled($i === count($tpl['lines']) - 1) title="Κάτω" aria-label="Κάτω"><i class="bi bi-arrow-down"></i></button>
                                    <button type="button" class="btn btn-outline-danger" wire:click="removeLine({{ $i }})" title="Διαγραφή" aria-label="Διαγραφή γραμμής {{ $i + 1 }}"><i class="bi bi-x-lg"></i></button>
                                </div>
                            </div>
                            <div class="row g-2 small align-items-center">
                                <div class="col-6 col-md-2"><label class="form-label mb-0">Μέγεθος (pt)</label>
                                    <input class="form-control form-control-sm" {!! $num("tpl.lines.$i.size", 'size', 0.5) !!}></div>
                                <div class="col-6 col-md-3"><label class="form-label mb-0">Στοίχιση</label>
                                    <select class="form-select form-select-sm" wire:model.live="tpl.lines.{{ $i }}.align" @disabled($line['dots'] ?? false)>
                                        @foreach ($aligns as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                                    </select></div>
                                <div class="col-6 col-md-2"><label class="form-label mb-0">Αραίωση (%)</label>
                                    <input class="form-control form-control-sm" {!! $num("tpl.lines.$i.spacing", 'spacing', 1) !!}></div>
                                <div class="col-6 col-md-2"><label class="form-label mb-0">Πλάτος (%)</label>
                                    <input class="form-control form-control-sm" {!! $num("tpl.lines.$i.condense", 'condense', 1) !!}></div>
                                <div class="col-12 col-md-3 d-flex flex-column gap-1">
                                    <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="st-bold-{{ $i }}" wire:model.live="tpl.lines.{{ $i }}.bold">
                                        <label class="form-check-label" for="st-bold-{{ $i }}">Έντονα</label></div>
                                    <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="st-dots-{{ $i }}" wire:model.live="tpl.lines.{{ $i }}.dots">
                                        <label class="form-check-label" for="st-dots-{{ $i }}">Με τελείες</label></div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-body-secondary mb-0">Καμία γραμμή.</p>
                    @endforelse
                </div>
            </div>

            <div class="card shadow-sm mb-3" id="stamp-position">
                <div class="card-header fw-semibold">Θέση στη σελίδα</div>
                <div class="card-body row g-2">
                    <div class="col-sm-4"><label class="form-label small fw-semibold mb-1" for="st-corner">Γωνία</label>
                        <select class="form-select" id="st-corner" wire:model.live="tpl.corner">
                            @foreach ($corners as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select></div>
                    <div class="col-sm-4"><label class="form-label small fw-semibold mb-1" for="st-x">Από το πλάι (mm)</label>
                        <input class="form-control" id="st-x" {!! $num('tpl.x', 'x', 1) !!}></div>
                    <div class="col-sm-4"><label class="form-label small fw-semibold mb-1" for="st-y">Από {{ str_contains($template->corner, 'top') ? 'πάνω' : 'κάτω' }} (mm)</label>
                        <input class="form-control" id="st-y" {!! $num('tpl.y', 'y', 1) !!}></div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="st-first" wire:model.live="tpl.firstPageOnly">
                            <label class="form-check-label" for="st-first">Μόνο στην πρώτη σελίδα</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card shadow-sm stamp-preview-card">
                <div class="card-header fw-semibold">Προεπισκόπηση <small class="fw-normal text-body-secondary">· με παράδειγμα τιμών</small></div>
                <div class="card-body">
                    <div class="stamp-preview-box text-center p-3 mb-2 border rounded bg-white">
                        <img src="{{ $preview }}" alt="Η σφραγίδα" id="stamp-preview" style="width: {{ round($sw * 3.78 * 1.5) }}px; max-width: 100%">
                    </div>
                    <div class="small text-body-secondary mb-3" id="stamp-size">Μέγεθος: {{ number_format($sw, 1, ',', '') }} × {{ number_format($sh, 1, ',', '') }} mm (μεγεθυμένη ×1,5). Στα αρχεία τυπώνεται στα 600 dpi.</div>
                    <div class="small fw-semibold mb-1">Θέση σε σελίδα A4</div>
                    <div class="stamp-page mx-auto" style="width: {{ 210 * $scale }}px; height: {{ 297 * $scale }}px">
                        <img src="{{ $preview }}" alt="" class="stamp-on-page" id="stamp-on-page"
                             style="left: {{ round($left * $scale) }}px; top: {{ round($top * $scale) }}px; width: {{ round($sw * $scale) }}px">
                        <div class="stamp-page-lines" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
