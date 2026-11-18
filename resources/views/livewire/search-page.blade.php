{{-- R3a: the advanced search screen. Rules: App\Livewire\SearchPage, App\Services\Search\ProtocolSearch. --}}
@php
    $e = fn (string $f) => $errors->first($f);
    $moreFields = array_diff(array_keys($textFields), $mainText);
    $moreUsed = array_filter(array_intersect_key($text, array_flip($moreFields))) || $empty || $inFrom || $inTo || $outFrom || $outTo;
    $field = function (string $name, string $label, ?string $list = null, ?string $help = null) use ($text, $e) {
        return view('search.field', compact('name', 'label', 'list', 'help') + ['value' => $text[$name] ?? '', 'error' => $e('text.'.$name)]);
    };
@endphp
<div class="search-page">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Αναζήτηση πρωτοκόλλων</h1>
        <x-pill-actions class="flex-grow-0">
            <x-pill :href="url('/home/list')">Λίστα</x-pill>
            <x-pill :href="url('/home')" variant="primary">Νέο</x-pill>
        </x-pill-actions>
    </div>

    {{-- Enter in any field searches (the folder picker: when its list is closed) --}}
    <form wire:submit="search" id="search-form" class="mb-3" autocomplete="off">
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Αριθμός και ημερομηνία</div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-7">
                                <label for="s-num" class="form-label small fw-semibold mb-1">Αριθμός πρωτοκόλλου</label>
                                <input id="s-num" class="form-control @if ($e('num')) is-invalid @endif" wire:model="num" value="{{ $num }}" inputmode="numeric" placeholder="π.χ. 125 ή 120-130">
                                @if ($e('num'))<div class="invalid-feedback">{{ $e('num') }}</div>@endif
                            </div>
                            <div class="col-5">
                                <label for="s-year" class="form-label small fw-semibold mb-1">Έτος</label>
                                <input id="s-year" class="form-control @if ($e('year')) is-invalid @endif" wire:model="year" value="{{ $year }}" inputmode="numeric" maxlength="4" placeholder="όλα">
                                @if ($e('year'))<div class="invalid-feedback">{{ $e('year') }}</div>@else<div class="form-text">Κενό: όλα τα έτη.</div>@endif
                            </div>
                            <div class="col-sm-6 col-lg-12">
                                <x-date-input name="from" id="s-from" label="Ημ/νία από" :value="$from" wire:model="from" help="" />
                            </div>
                            <div class="col-sm-6 col-lg-12">
                                <x-date-input name="to" id="s-to" label="Ημ/νία έως" :value="$to" wire:model="to" help="" />
                            </div>
                        </div>

                        {{-- owner, 2026-11-18: protocols on hold have no folder or ΕΚΚΡΕΜΟΤΗΤΑ in the subject --}}
                        <div class="border rounded p-2 mt-3 bg-body-tertiary" id="pending-box">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="s-pending" wire:model="pending">
                                <label class="form-check-label" for="s-pending">Μόνο εκκρεμότητες</label>
                            </div>
                            <div class="small text-body-secondary mb-2">Πρωτόκολλα χωρίς φάκελο ή με «ΕΚΚΡΕΜΟΤΗΤΑ» στο θέμα.</div>
                            <button type="button" class="btn btn-sm btn-outline-warning rounded-pill" id="pending-check" wire:click="pendingCheck">
                                <x-icon name="warning" /> Έλεγχος εκκρεμοτήτων για το έτος / τις ημερομηνίες
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header fw-semibold">Περιεχόμενο <small class="fw-normal text-body-secondary">· μέρος του κειμένου, χωρίς τόνους / κεφαλαία</small></div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-6">
                                {{-- R1f's folder picker, with «Όλοι» and «Χωρίς φάκελο» first; code typed with or without dots --}}
                                <div class="mb-2 position-relative" x-on:click.outside="close()"
                                     x-data="folderPicker({ model: 'folder', live: false, submit: true, choices: [['', 'Όλοι οι φάκελοι'], ['{{ \App\Services\Search\SearchCriteria::NO_FOLDER }}', 'Χωρίς φάκελο']] })">
                                    <label for="s-folder" class="form-label small fw-semibold mb-1">Φάκελος</label>
                                    <input id="s-folder" class="form-control folder-input" autocomplete="off" data-combobox
                                           role="combobox" aria-autocomplete="list" aria-controls="s-folder-list" :aria-expanded="open"
                                           :value="open ? query : label()" :placeholder="open ? 'Γράψτε κωδικό ή λέξη της περιγραφής' : 'Όλοι οι φάκελοι'"
                                           x-on:focus="show()" x-on:click="show()" x-on:input="input($event)" x-on:keydown="key($event)"
                                           x-on:blur="setTimeout(() => { if (! $root.contains(document.activeElement)) close() }, 150)">
                                    <ul class="list-group shadow folder-list" id="s-folder-list" role="listbox" x-show="open" x-cloak>
                                        <template x-for="(f, n) in items" :key="'f' + f[0]">
                                            <li class="list-group-item list-group-item-action py-1" role="option" :aria-selected="n === active"
                                                :class="{ active: n === active, 'fst-italic': isChoice(f) }" :data-folder-active="n === active ? '' : null" :title="f[1]"
                                                x-on:mousedown.prevent="choose(f)" x-on:mousemove="active = n">
                                                <span class="fw-semibold" x-show="! isChoice(f)" x-html="mark(f[0])"></span>
                                                <span :class="isChoice(f) ? '' : 'folder-describe'" x-html="mark(f[1])"></span>
                                            </li>
                                        </template>
                                        <li class="list-group-item text-body-secondary py-1" x-show="! items.length">Κανένας φάκελος δεν ταιριάζει.</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">{{ $field('thema', 'Θέμα') }}</div>
                            <div class="col-md-6">{{ $field('in_arxi_ekdosis', 'Από (αρχή έκδοσης)', 'suggest-in_arxi_ekdosis') }}</div>
                            <div class="col-md-6">{{ $field('out_to', 'Προς (απευθύνεται)', 'suggest-out_to') }}</div>
                            <div class="col-md-4">{{ $field('in_num', 'Αρ. εισερχομένου') }}</div>
                            <div class="col-md-4">{{ $field('sxetiko', 'Σχετικοί αριθμοί', null, 'π.χ. 12 ή 12/2026') }}</div>
                            <div class="col-md-4">{{ $field('keywords', 'Λέξεις κλειδιά') }}</div>
                            <div class="col-md-6">
                                <label for="s-assignee" class="form-label small fw-semibold mb-1">Ανάθεση (διεκπεραίωση)</label>
                                <select id="s-assignee" class="form-select" wire:model="assignee">
                                    <option value="">Οποιοσδήποτε</option>
                                    <option value="none">Κανένας (χωρίς ανάθεση)</option>
                                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- rarely used fields, open when one of them is filled or when the user left them open --}}
        <div class="card shadow-sm mt-3" id="more-fields"
             x-data="{ more: {{ $moreUsed ? 'true' : '(() => { try { return localStorage.getItem(\'search.more\') === \'1\' } catch (e) { return false } })()' }} }"
             x-init="$watch('more', v => { try { localStorage.setItem('search.more', v ? '1' : '0') } catch (e) {} })">
            <button type="button" class="card-header btn text-start fw-semibold d-flex align-items-center gap-2 border-0" x-on:click="more = ! more"
                    :aria-expanded="more" aria-controls="more-fields-body" id="more-toggle">
                <span x-text="more ? '▾' : '▸'"></span> Περισσότερα πεδία
                <small class="fw-normal text-body-secondary">τόπος, παραλήπτης, περιλήψεις, παρατηρήσεις, συνημμένα, άλλες ημερομηνίες, κενά πεδία</small>
            </button>
            <div class="card-body" id="more-fields-body" x-show="more" x-cloak>
                <div class="row g-2">
                    <div class="col-md-4">{{ $field('in_topos_ekdosis', 'Τόπος έκδοσης', 'suggest-in_topos_ekdosis') }}</div>
                    <div class="col-md-4">{{ $field('in_paraliptis', 'Παραλήπτης', 'suggest-in_paraliptis') }}</div>
                    <div class="col-md-4">{{ $field('paratiriseis', 'Παρατηρήσεις') }}</div>
                    <div class="col-md-6">{{ $field('in_perilipsi', 'Περίληψη εισερχομένου') }}</div>
                    <div class="col-md-6">{{ $field('out_perilipsi', 'Περίληψη εξερχομένου') }}</div>
                    <div class="col-md-6">{{ $field('name', 'Όνομα συνημμένου') }}</div>
                    <div class="col-md-6">{{ $field('ada', 'ΑΔΑ') }}</div>
                    <div class="col-md-6 col-xl-3"><x-date-input name="inFrom" id="s-in-from" label="Ημ/νία εισερχ. από" :value="$inFrom" wire:model="inFrom" help="" /></div>
                    <div class="col-md-6 col-xl-3"><x-date-input name="inTo" id="s-in-to" label="Ημ/νία εισερχ. έως" :value="$inTo" wire:model="inTo" help="" /></div>
                    <div class="col-md-6 col-xl-3"><x-date-input name="outFrom" id="s-out-from" label="Ημ/νία εξερχ. από" :value="$outFrom" wire:model="outFrom" help="" /></div>
                    <div class="col-md-6 col-xl-3"><x-date-input name="outTo" id="s-out-to" label="Ημ/νία εξερχ. έως" :value="$outTo" wire:model="outTo" help="" /></div>
                </div>
                <fieldset class="mt-2" id="empty-fields">
                    <legend class="form-label small fw-semibold mb-1">Να είναι κενό το πεδίο</legend>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($emptyFields as $key => $label)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="s-empty-{{ $key }}" value="{{ $key }}" wire:model="empty">
                                <label class="form-check-label small" for="s-empty-{{ $key }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">Όνομα συνημμένου / ΑΔΑ: πρωτόκολλα με συνημμένο χωρίς αυτό.</div>
                </fieldset>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
            <button type="submit" class="btn btn-primary rounded-pill px-4" id="search-submit">Αναζήτηση</button>
            <button type="button" class="btn btn-outline-secondary rounded-pill" wire:click="clear" id="search-clear">Καθαρισμός</button>
            <span class="small text-body-secondary align-self-center" wire:loading.delay>αναζήτηση…</span>
        </div>
    </form>

    @if ($searched && $criteria && $criteria->isEmpty())
        <div class="alert alert-info" id="search-empty">Συμπληρώστε τουλάχιστον ένα πεδίο (ή το έτος) και πατήστε Enter.</div>
    @elseif ($protocols)
        @php $pendingOnly = \App\Livewire\SearchPage::onlyPendingRange($criteria); @endphp
        @if ($protocols->total() === 0 && $pendingOnly)
            {{-- owner, 2026-11-18: say clearly that nothing is pending in that range --}}
            <div class="alert alert-success d-flex align-items-center gap-2" id="no-pending" role="status">
                <x-icon name="check" />
                <div><strong>Καμία εκκρεμότητα {{ $this->rangeText($criteria) }}.</strong>
                    Όλα τα πρωτόκολλα έχουν φάκελο και κανένα θέμα δεν γράφει «ΕΚΚΡΕΜΟΤΗΤΑ».</div>
            </div>
        @elseif ($criteria->pending && $protocols->total() > 0)
            <div class="alert alert-warning py-2" id="pending-count" role="status">
                <x-icon name="warning" /> <strong>{{ $protocols->total() }} {{ $protocols->total() === 1 ? 'εκκρεμότητα' : 'εκκρεμότητες' }}</strong>
                {{ $this->rangeText($criteria) }}: χωρίς φάκελο ή με «ΕΚΚΡΕΜΟΤΗΤΑ» στο θέμα.
            </div>
        @endif
        @include('search.results', ['hideEmpty' => $pendingOnly, 'protocols' => $protocols, 'terms' => $terms, 'names' => $names, 'folders' => $folders, 'showPending' => $criteria->pending])
    @endif
</div>
