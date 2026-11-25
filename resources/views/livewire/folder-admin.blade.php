{{-- R6b: folders, Διατήρηση and automatic assignments. Rules: App\Services\Folders\FolderCatalog. --}}
<div class="folder-admin">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Φάκελοι και διατήρηση</h1>
        @if ($isAdmin)
            <x-pill-actions class="flex-grow-0">
                <x-pill variant="primary" wire:click="create" id="folder-new"><i class="bi bi-folder-plus me-1" aria-hidden="true"></i>Νέος φάκελος</x-pill>
                <x-pill wire:click="openImport" id="folder-import-open"><i class="bi bi-table me-1" aria-hidden="true"></i>Εισαγωγή λίστας</x-pill>
            </x-pill-actions>
        @endif
    </div>

    @if ($isAdmin && $formOpen)
        @php $editingFolder = $editing ? $folders->firstWhere('id', $editing) ?? \App\Models\Keepvalue::find($editing) : null; @endphp
        <div class="card shadow-sm mb-3" id="folder-form">
            <div class="card-header fw-semibold">@if ($editing) Επεξεργασία: {{ $editingFolder?->fakelos }} @else Νέος φάκελος @endif</div>
            <form class="card-body" wire:submit="save">
                <div class="row g-2">
                    <x-form.text class="col-md-3" name="fakelos" label="Κωδικός" :value="$form['fakelos']" :error="$errors->first('form.fakelos')" max="255" help="π.χ. Φ.12 ή Φ.11.2" />
                    <x-form.text class="col-md-3" name="keep" label="Διατήρηση (έτη ή κείμενο)" :value="$form['keep']" :error="$errors->first('form.keep')" max="255"
                                 help="π.χ. 5, ή «Μόνιμα»" />
                    <x-form.text class="col-md-6" name="describe" label="Περιγραφή" rows="2" :value="$form['describe']" :error="$errors->first('form.describe')" />
                </div>
                <x-form.text name="remarks" label="Παρατηρήσεις" rows="2" :value="$form['remarks']" :error="$errors->first('form.remarks')" />

                <fieldset class="mb-2" id="folder-rules">
                    <legend class="small fw-semibold mb-1">Αυτόματη ανάθεση</legend>
                    <p class="small text-body-secondary mb-2">Κάθε νέο πρωτόκολλο σε αυτόν τον φάκελο (και κάθε πρωτόκολλο που μπαίνει σε αυτόν)
                        ανατίθεται σε όσους επιλέξετε, όποιος κι αν το καταχωρίσει. Οι χρήστες παίρνουν e-mail όπως σε κάθε ανάθεση.</p>
                    <div class="row g-2">
                        @foreach (['d' => 'για Διεκπεραίωση', 'e' => 'για Ενημέρωση'] as $type => $label)
                            <div class="col-md-6">
                                <div class="small fw-semibold">{{ $label }}</div>
                                <div class="border rounded p-2 folder-users @error('form.'.$type) border-danger @enderror" style="max-height: 12rem; overflow-y: auto">
                                    @foreach ($activeUsers as $u)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="rule-{{ $type }}-{{ $u->id }}" value="{{ $u->id }}" wire:model="form.{{ $type }}">
                                            <label class="form-check-label small" for="rule-{{ $type }}-{{ $u->id }}">{{ $u->name }}@if (! $u->active) <span class="badge text-bg-secondary">ανενεργός: δεν παίρνει αναθέσεις</span>@endif</label>
                                        </div>
                                    @endforeach
                                </div>
                                @error('form.'.$type)<div class="small text-danger">{{ $message }}</div>@enderror
                            </div>
                        @endforeach
                    </div>
                    {{-- R6i (owner, 2026-11-25): the saved rules also to the protocols already in this folder; which ones is
                         asked each time; no e-mails --}}
                    @if ($editing && $savedRules && $editedCount)
                        <div class="border rounded-3 p-2 mt-2 bg-body-tertiary" id="folder-apply">
                            @if (! $applyOpen)
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" wire:click="openApply" id="folder-apply-open">
                                    <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>Εφαρμογή και {{ $editedCount === 1 ? 'στο 1 υπάρχον πρωτόκολλο' : 'στα '.$editedCount.' υπάρχοντα πρωτόκολλα' }} του φακέλου…</button>
                                <span class="small text-body-secondary ms-1">Η αυτόματη ανάθεση ισχύει μόνο για όσα αποθηκεύονται από εδώ και πέρα.</span>
                            @else
                                <div class="small fw-semibold mb-1">Σε ποια υπάρχοντα πρωτόκολλα του {{ $editingFolder?->fakelos }} να εφαρμοστεί η αυτόματη ανάθεση;</div>
                                @foreach (\App\Services\Folders\FolderCatalog::EXISTING_SCOPES as $scope => $label)
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="apply-scope" id="apply-{{ $scope }}" value="{{ $scope }}" wire:model.live="applyScope">
                                        <label class="form-check-label small" for="apply-{{ $scope }}">{{ $label }}
                                            <span class="text-body-secondary">({{ $existingCounts[$scope] ?? 0 }} {{ ($existingCounts[$scope] ?? 0) === 1 ? 'πρωτόκολλο αλλάζει' : 'πρωτόκολλα αλλάζουν' }})</span></label>
                                    </div>
                                @endforeach
                                <p class="small text-body-secondary mb-2 mt-1">Με τους κανόνες που είναι <strong>αποθηκευμένοι</strong> (αποθηκεύστε πρώτα μια αλλαγή τους).
                                    Όσες αναθέσεις υπάρχουν μένουν· οι χρήστες <strong>δεν</strong> παίρνουν e-mail. «Όσα δεν έχουν διεκπεραιωθεί»: χωρίς ημερομηνία
                                    «Διεκπεραιώθηκε», δηλαδή όσα θα φανούν στη λίστα «προς Διεκπεραίωση».</p>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-sm btn-primary rounded-pill" wire:click="applyToExisting" id="folder-apply-run"
                                            @disabled(! ($existingCounts[$applyScope] ?? 0))>Εφαρμογή σε {{ $existingCounts[$applyScope] ?? 0 }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" wire:click="closeApply">Άκυρο</button>
                                </div>
                            @endif
                        </div>
                    @endif
                </fieldset>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-pill" id="folder-save">Αποθήκευση</button>
                    <button type="button" class="btn btn-outline-secondary rounded-pill" wire:click="cancel">Ακύρωση</button>
                </div>
            </form>
        </div>
    @endif

    @if ($isAdmin && $importOpen)
        <div class="card shadow-sm mb-3" id="folder-import">
            <div class="card-header fw-semibold">Εισαγωγή λίστας φακέλων</div>
            <div class="card-body">
                <p class="small mb-2">Αντιγράψτε τις γραμμές από το λογιστικό φύλλο (Excel, LibreOffice) και επικολλήστε τις εδώ. Στήλες με
                    αυτή τη σειρά: <strong>Κωδικός</strong>, <strong>Περιγραφή</strong>, <strong>Διατήρηση</strong> (έτη ή κείμενο),
                    <strong>Παρατηρήσεις</strong>. Γίνεται και με «;» ανάμεσα. Οι φάκελοι που υπάρχουν παίρνουν τις τιμές της λίστας·
                    ένα κενό κελί αφήνει την τιμή τους όπως είναι.</p>
                <label for="import-text" class="visually-hidden">Λίστα φακέλων</label>
                <textarea id="import-text" class="form-control font-monospace small mb-2" rows="6" wire:model.blur="importText"
                          placeholder="Φ.1&#9;ΕΚΠΑΙΔΕΥΤΙΚΗ ΝΟΜΟΘΕΣΙΑ&#9;Μόνιμα"></textarea>
                <div class="d-flex gap-2 mb-2">
                    <button type="button" class="btn btn-outline-primary rounded-pill" wire:click="checkImport" id="import-check">Έλεγχος</button>
                    @if ($preview && collect($preview)->whereIn('status', ['new', 'update'])->isNotEmpty())
                        <button type="button" class="btn btn-primary rounded-pill" wire:click="runImport" id="import-run"
                                data-confirm="Να γίνει η εισαγωγή;">Εισαγωγή</button>
                    @endif
                    <button type="button" class="btn btn-outline-secondary rounded-pill" wire:click="closeImport">Κλείσιμο</button>
                </div>
                @if ($preview !== null)
                    @php $by = collect($preview)->countBy('status'); @endphp
                    <x-notice :type="($by['error'] ?? 0) ? 'warning' : 'info'" id="import-summary">
                        {{ $by['new'] ?? 0 }} νέοι, {{ $by['update'] ?? 0 }} αλλαγές, {{ $by['same'] ?? 0 }} ίδιοι,
                        {{ $by['error'] ?? 0 }} με λάθος (δεν περνούν).
                    </x-notice>
                    @if ($preview)
                        <div class="table-responsive" style="max-height: 20rem">
                            <table class="table table-sm small mb-0" id="import-preview">
                                <thead class="table-light"><tr><th>Γραμμή</th><th>Κωδικός</th><th>Περιγραφή</th><th>Διατήρηση</th><th>Κατάσταση</th></tr></thead>
                                <tbody>
                                    @foreach ($preview as $row)
                                        <tr @class(['table-danger' => $row['status'] === 'error', 'text-body-secondary' => $row['status'] === 'same'])>
                                            <td>{{ $row['line'] }}</td>
                                            <td class="text-nowrap">{{ $row['fakelos'] }}</td>
                                            <td class="text-break">{{ \Illuminate\Support\Str::limit((string) $row['describe'], 80) }}</td>
                                            <td>{{ $row['keep'] }}</td>
                                            <td>{{ ['new' => 'νέος', 'update' => 'αλλαγή', 'same' => 'ίδιος', 'error' => $row['error']][$row['status']] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2" role="search">
        <label for="folder-search" class="visually-hidden">Αναζήτηση φακέλου</label>
        <input id="folder-search" type="search" class="form-control form-control-sm w-auto" placeholder="Κωδικός ή λέξη"
               wire:model.live.debounce.250ms="search">
        <span class="small text-body-secondary ms-auto">{{ $folders->count() }} από {{ $total }} φακέλους@if ($withRules) · {{ $withRules }} με αυτόματη ανάθεση @endif</span>
    </div>

    <div class="card shadow-sm">
        <table class="table table-sm align-middle mb-0 table-sticky" id="folders-table">
            <thead class="table-light">
                <tr>
                    @php
                        // R6g (owner, 2026-11-25): Κωδικός and Πρωτ. sort the table (▲ / ▼), as the protocol list
                        [$sortNow, $dirNow] = [$sort, $dir];
                        // the top bar's search «φάκελος:…» (in quotes when the code has a space)
                        $folderSearch = fn (string $code) => url('/search?q='.urlencode('φάκελος:'.(preg_match('/\s/u', $code) ? '"'.$code.'"' : $code)));
                        $sortHead = function (string $column, string $label) use ($sortNow, $dirNow) {
                            $active = $sortNow === $column;
                            $arrow = $active ? ($dirNow === 'asc' ? ' ▲' : ' ▼') : '';
                            return '<button type="button" class="btn btn-link btn-sm p-0 text-reset fw-semibold text-decoration-none text-nowrap" data-sort="'.$column.'" wire:click="sortBy(\''.$column.'\')"'
                                .($active ? ' aria-sort="'.($dirNow === 'asc' ? 'ascending' : 'descending').'"' : '').'>'.e($label).$arrow.'</button>';
                        };
                    @endphp
                    <th scope="col">{!! $sortHead('code', 'Κωδικός') !!}</th>
                    <th scope="col">Περιγραφή</th>
                    <th scope="col">Διατήρηση</th>
                    <th scope="col" class="d-none d-lg-table-cell">Παρατηρήσεις</th>
                    <th scope="col" class="d-none d-md-table-cell">Αυτόματη ανάθεση</th>
                    <th scope="col" class="d-none d-md-table-cell text-end">{!! $sortHead('count', 'Πρωτ.') !!}</th>
                    @if ($isAdmin)<th scope="col" class="text-end"><span class="visually-hidden">Ενέργειες</span></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($folders as $f)
                    @php $n = $counts[mb_strtolower($f->fakelos)] ?? 0; @endphp
                    <tr wire:key="f-{{ $f->id }}" class="folder-row @if ($editing === $f->id) table-active @endif" data-code="{{ $f->fakelos }}">
                        {{-- R6g: the code (and the count) shows the protocols of this folder (the top bar's search: the results only) --}}
                        <td class="text-nowrap fw-semibold"><a href="{{ $folderSearch($f->fakelos) }}" class="text-decoration-none folder-search-link"
                               title="Τα πρωτόκολλα του φακέλου {{ $f->fakelos }}">{{ $f->fakelos }}</a></td>
                        <td class="text-break small" style="white-space: pre-line">{{ $f->describe }}</td>
                        <td class="small text-nowrap">{{ $f->keepLabel() }}</td>
                        <td class="d-none d-lg-table-cell text-break small text-body-secondary" style="white-space: pre-line">{{ $f->remarks }}</td>
                        <td class="d-none d-md-table-cell small">
                            @foreach ($f->assignments->sortBy('type') as $a)
                                <span class="badge rounded-pill {{ $a->type === 'd' ? 'text-bg-primary' : 'text-bg-info' }}"
                                      title="{{ $a->type === 'd' ? 'Διεκπεραίωση' : 'Ενημέρωση' }}">{{ $a->type === 'd' ? 'Δ' : 'Ε' }}: {{ $names[$a->user_id] ?? '—' }}</span>
                            @endforeach
                        </td>
                        <td class="d-none d-md-table-cell text-end small">
                            @if ($n)<a href="{{ $folderSearch($f->fakelos) }}">{{ $n }}</a>@else<span class="text-body-secondary">0</span>@endif
                        </td>
                        @if ($isAdmin)
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" wire:click="edit({{ $f->id }})">Επεξεργασία</button>
                                @if ($blocked = $catalog->cannotDelete($f, $n))
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" disabled title="{{ $blocked }}">Διαγραφή</button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" wire:click="delete({{ $f->id }})"
                                            data-confirm="Να διαγραφεί ο φάκελος {{ $f->fakelos }};">Διαγραφή</button>
                                @endif
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">Κανένας φάκελος δεν ταιριάζει.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
