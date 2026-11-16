{{-- R1b: the protocol form. Rules and saving: App\Livewire\ProtocolForm → App\Services\Protocols\ProtocolWriter. --}}
@php
    $tone = ['new' => 'primary', 'edit' => 'success', 'readonly' => 'secondary', 'admin' => 'danger'][$access->tone] ?? 'primary';
    $e = fn (string $f) => $errors->first($f) ?: null;
@endphp
<form wire:submit="save" class="protocol-form" id="protocol-form" novalidate>
    <div class="card shadow-sm mb-3 border-{{ $tone }}">
        <div class="card-header bg-{{ $tone }}-subtle d-flex flex-wrap align-items-center gap-3">
            <h1 class="h4 mb-0 me-auto">{{ $title }}</h1>
            @if ($creator)
                <small class="text-body-secondary">Τελευταία αλλαγή: {{ $creator->name }}, {{ $protocol->updated_at?->format('d/m/Y H:i') }}</small>
            @endif
        </div>
        <div class="card-body">
            {{-- number, year, date: TAB from the number goes straight to the date (owner) --}}
            <div class="row g-3 align-items-start">
                <div class="col-6 col-md-2">
                    <label for="f-etos" class="form-label small fw-semibold mb-1">Έτος</label>
                    <input id="f-etos" class="form-control text-center @if ($e('etos')) is-invalid @endif" inputmode="numeric"
                           value="{{ $form['etos'] }}" wire:model.live.debounce.400ms="form.etos" @disabled(! $access->number) tabindex="-1">
                    @if ($e('etos'))<div class="invalid-feedback">{{ $e('etos') }}</div>@endif
                </div>
                <div class="col-6 col-md-3">
                    <label for="f-protocolnum" class="form-label small fw-semibold mb-1">Αριθμός πρωτοκόλλου</label>
                    @if ($access->number)
                        <input id="f-protocolnum" class="form-control form-control-lg text-center fw-bold @if ($e('protocolnum') || in_array($numberStatus[0] ?? '', ['taken', 'invalid'], true)) is-invalid @elseif (($numberStatus[0] ?? '') === 'free') is-valid @endif"
                               inputmode="numeric" autocomplete="off" value="{{ $form['protocolnum'] }}" wire:model.live.debounce.400ms="form.protocolnum"
                               @if ($nextNumber) placeholder="{{ $nextNumber }}" @endif autofocus>
                        <div class="small mt-1" id="number-status" aria-live="polite">
                            @switch($numberStatus[0] ?? null)
                                @case('auto')
                                    <span class="text-body-secondary">Κενό: ο επόμενος ελεύθερος κατά την αποθήκευση ({{ $nextNumber }}).</span>
                                    @break
                                @case('free')
                                    <span class="text-success">✔ Ελεύθερος</span>
                                    @break
                                @case('invalid')
                                    <span class="text-danger">Γράψτε θετικό ακέραιο αριθμό.</span>
                                    @break
                                @case('taken')
                                    <span class="text-danger">✖ Δεσμευμένος από το
                                        <a href="{{ url('/home/'.$numberStatus[1]->id) }}" target="_blank">{{ $numberStatus[1]->protocolnum }}/{{ $numberStatus[1]->etos }}</a></span>
                                    @break
                                @case('placeholder')
                                    <span class="text-warning-emphasis">Κρατημένος ως «ΕΛΕΥΘΕΡΟ»
                                        (<a href="{{ url('/home/'.$numberStatus[1]->id) }}" target="_blank">{{ $numberStatus[1]->protocolnum }}/{{ $numberStatus[1]->etos }}</a>)</span>
                                    <div class="form-check mt-1">
                                        <input class="form-check-input" type="checkbox" id="f-replace" wire:model="replacePlaceholder">
                                        <label class="form-check-label" for="f-replace">Το νέο πρωτόκολλο παίρνει τη θέση του</label>
                                    </div>
                                    @break
                            @endswitch
                        </div>
                    @else
                        <div class="form-control form-control-lg text-center fw-bold bg-body-secondary" id="f-protocolnum">
                            {{ $protocol->exists ? $protocol->protocolnum : '—' }}
                        </div>
                        @unless ($protocol->exists)
                            <div class="small mt-1 text-body-secondary">Δίνεται κατά την αποθήκευση (επόμενος: {{ $nextNumber }}).</div>
                        @endunless
                    @endif
                    @if ($e('protocolnum'))<div class="text-danger small">{{ $e('protocolnum') }}</div>@endif
                    @if ($e('replace_placeholder'))<div class="text-danger small">{{ $e('replace_placeholder') }}</div>@endif
                </div>
                <div class="col-12 col-md-7">
                    <x-date-input name="protocoldate" label="Ημερομηνία παραλαβής" :value="$form['protocoldate']"
                                  wire:model.change="form.protocoldate" id="f-protocoldate" :readonly="! $access->date"
                                  :help="$access->date ? null : 'Η σημερινή, όπως ορίζουν οι ρυθμίσεις.'" />
                    @if ($e('protocoldate'))<div class="text-danger small">{{ $e('protocoldate') }}</div>@endif
                </div>
            </div>

            @if ($access->secondsLeft > 0)
                <x-notice type="info" class="mt-2">Μπορείτε να αλλάξετε το πρωτόκολλο για ακόμη {{ (int) ceil($access->secondsLeft / 60) }} λεπτά.</x-notice>
            @endif

            <x-pill-actions class="mt-3">
                @if ($access->canSave)
                    <x-pill type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save,uploads">Αποθήκευση</x-pill>
                @endif
                @if ($protocol->exists && $access->canAssign && ! $access->canSave)
                    <x-pill variant="primary" wire:click="assignOnly">Ανάθεση</x-pill>
                @endif
                @if ($canComplete && ! $protocol->diekp_date)
                    <x-pill variant="success" wire:click="markDone">Διεκπεραιώθηκε</x-pill>
                @endif
                <x-pill :href="url('/home')">Νέο</x-pill>
                @if ($protocol->exists)
                    <x-pill :href="url('/home/'.$protocol->id.'/1')">Αντιγραφή ως νέο</x-pill>
                    <x-pill :href="url('/receipt/'.$protocol->id)" target="_blank">Απόδειξη</x-pill>
                    <x-pill :href="url('/goto/'.$protocol->etos.'/'.$protocol->protocolnum.'/b')" title="Προηγούμενο">◀ Προηγούμενο</x-pill>
                    <x-pill :href="url('/goto/'.$protocol->etos.'/'.$protocol->protocolnum.'/f')" title="Επόμενο">Επόμενο ▶</x-pill>
                @endif
                <x-pill :href="url('/home/list')">Λίστα</x-pill>
                @if ($access->canDelete)
                    <x-pill variant="danger" wire:click="deleteProtocol"
                            data-confirm="Να διαγραφεί οριστικά το πρωτόκολλο {{ $protocol->protocolnum }}/{{ $protocol->etos }} και τα συνημμένα του;">Διαγραφή</x-pill>
                @endif
            </x-pill-actions>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            {{-- general --}}
            <fieldset class="card shadow-sm mb-3" @disabled(! $access->head)>
                <legend class="card-header h6 mb-0 w-100">Γενικά</legend>
                <div class="card-body">
                    <div class="mb-2">
                        <label for="f-fakelos" class="form-label small fw-semibold mb-1">Φάκελος</label>
                        <select id="f-fakelos" class="form-select @if ($e('fakelos')) is-invalid @endif" wire:model.live="form.fakelos">
                            <option value="">—</option>
                            @foreach ($folders as $folder)
                                <option value="{{ $folder->fakelos }}" @selected($folder->fakelos === $form['fakelos'])>{{ $folder->fakelos }}{{ $folder->describe ? ' · '.$folder->describe : '' }}</option>
                            @endforeach
                            @if ($form['fakelos'] !== '' && ! $folders->contains('fakelos', $form['fakelos']))
                                <option value="{{ $form['fakelos'] }}" selected>{{ $form['fakelos'] }}</option>
                            @endif
                        </select>
                        @if ($e('fakelos'))<div class="invalid-feedback">{{ $e('fakelos') }}</div>@endif
                    </div>
                    <x-form.text name="thema" :value="$form['thema']" live label="Θέμα" rows="2" :error="$e('thema')" :placeholder="$suggestedSubject">
                        @if ($suggestedSubject)
                            <div class="form-text" id="subject-from-file">Αν μείνει κενό, θέμα θα γίνει το όνομα του αρχείου. Κάντε κλικ στο πεδίο για να το αλλάξετε.</div>
                        @endif
                        @if ($sameSubject)
                            <x-notice type="warning" class="mt-1" id="notice-thema">Υπάρχει ήδη πρωτόκολλο με το ίδιο θέμα:
                                @foreach ($sameSubject as $p)<a href="{{ url('/home/'.$p->id) }}" target="_blank">{{ $p->protocolnum }}/{{ $p->etos }}</a>@if (! $loop->last), @endif @endforeach
                            </x-notice>
                        @endif
                    </x-form.text>
                    <div class="row g-2">
                        <x-form.text class="col-md-6" name="sxetiko" :value="$form['sxetiko']" label="Σχετικοί αριθμοί" :error="$e('sxetiko')" help="π.χ. 12/2026, 40/2025" />
                        <x-form.text class="col-md-6" name="keywords" :value="$form['keywords']" label="Λέξεις κλειδιά" list="suggest-keywords" :error="$e('keywords')" />
                    </div>
                    <x-form.text name="paratiriseis" :value="$form['paratiriseis']" label="Παρατηρήσεις" rows="2" :error="$e('paratiriseis')" />
                </div>
            </fieldset>

            {{-- incoming --}}
            <fieldset class="card shadow-sm mb-3 border-success-subtle" @disabled(! $access->in)>
                <legend class="card-header h6 mb-0 w-100 bg-success-subtle">Εισερχόμενο</legend>
                <div class="card-body">
                    <div class="row g-2">
                        <x-form.text class="col-md-5" name="in_num" :value="$form['in_num']" live label="Αριθμός εισερχομένου" :error="$e('in_num')" />
                        <div class="col-md-7 mb-2">
                            <x-date-input name="in_date" label="Ημ/νία εισερχομένου" :value="$form['in_date']" id="f-in_date"
                                          wire:model.live.change="form.in_date" :readonly="! $access->in" help="" />
                            @if ($e('in_date'))<div class="text-danger small">{{ $e('in_date') }}</div>@endif
                        </div>
                    </div>
                    @if ($sameIncoming)
                        <x-notice type="warning" id="notice-in_num">Υπάρχει ήδη πρωτόκολλο με τον ίδιο αριθμό και ημερομηνία εισερχομένου:
                            @foreach ($sameIncoming as $p)<a href="{{ url('/home/'.$p->id) }}" target="_blank">{{ $p->protocolnum }}/{{ $p->etos }}</a>@if (! $loop->last), @endif @endforeach
                        </x-notice>
                    @endif
                    <div class="row g-2">
                        <x-form.text class="col-md-6" name="in_topos_ekdosis" :value="$form['in_topos_ekdosis']" label="Τόπος έκδοσης" list="suggest-in_topos_ekdosis" :error="$e('in_topos_ekdosis')" />
                        <x-form.text class="col-md-6" name="in_paraliptis" :value="$form['in_paraliptis']" label="Παραλήπτης" list="suggest-in_paraliptis" :error="$e('in_paraliptis')" />
                    </div>
                    <x-form.text name="in_arxi_ekdosis" :value="$form['in_arxi_ekdosis']" label="Αρχή έκδοσης" list="suggest-in_arxi_ekdosis" :error="$e('in_arxi_ekdosis')" />
                    <x-form.text name="in_perilipsi" :value="$form['in_perilipsi']" label="Περίληψη" rows="2" :error="$e('in_perilipsi')" />
                </div>
            </fieldset>
        </div>

        <div class="col-12 col-xl-6">
            {{-- assignment --}}
            <fieldset class="card shadow-sm mb-3">
                <legend class="card-header h6 mb-0 w-100">Ανάθεση</legend>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <label for="f-handle" class="form-label small fw-semibold mb-1">Διεκπεραίωση</label>
                            <select id="f-handle" multiple size="4" class="form-select @if ($e('diekperaiosi')) is-invalid @endif"
                                    wire:model="handle" @disabled(! $access->canAssign)>
                                @foreach ($users as $u)<option value="{{ $u->id }}" @selected(in_array((string) $u->id, $handle, true))>{{ $u->name }}</option>@endforeach
                            </select>
                            @if ($e('diekperaiosi'))<div class="invalid-feedback">{{ $e('diekperaiosi') }}</div>@endif
                        </div>
                        <div class="col-md-6 mb-2">
                            <label for="f-inform" class="form-label small fw-semibold mb-1">Ενημέρωση</label>
                            <select id="f-inform" multiple size="4" class="form-select" wire:model="inform" @disabled(! $access->canAssign)>
                                @foreach ($users as $u)<option value="{{ $u->id }}" @selected(in_array((string) $u->id, $inform, true))>{{ $u->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    @if ($access->canAssign)<div class="form-text mb-2">Ctrl + κλικ για περισσότερους χρήστες.</div>@endif
                    <div class="row g-2">
                        <div class="col-md-6 mb-2">
                            <x-date-input name="diekp_eos" label="Διεκπεραίωση έως" :value="$form['diekp_eos']" id="f-diekp_eos"
                                          wire:model.change="form.diekp_eos" :readonly="! $access->canAssign" help="" />
                            @if ($e('diekp_eos'))<div class="text-danger small">{{ $e('diekp_eos') }}</div>@endif
                        </div>
                        <div class="col-md-6 mb-2">
                            <x-date-input name="diekp_date" label="Διεκπεραιώθηκε την" :value="$form['diekp_date']" id="f-diekp_date"
                                          wire:model.change="form.diekp_date" :readonly="! $access->doneDate" help="" />
                            @if ($e('diekp_date'))<div class="text-danger small">{{ $e('diekp_date') }}</div>@endif
                        </div>
                    </div>
                </div>
            </fieldset>

            {{-- outgoing --}}
            <fieldset class="card shadow-sm mb-3 border-info-subtle" @disabled(! $access->out)>
                <legend class="card-header h6 mb-0 w-100 bg-info-subtle">Εξερχόμενο</legend>
                <div class="card-body">
                    <div class="row g-2">
                        <x-form.text class="col-md-6" name="out_to" :value="$form['out_to']" label="Απευθύνεται σε" list="suggest-out_to" :error="$e('out_to')" />
                        <div class="col-md-6 mb-2">
                            <x-date-input name="out_date" label="Ημ/νία εξερχομένου" :value="$form['out_date']" id="f-out_date"
                                          wire:model.change="form.out_date" :readonly="! $access->out" help="" />
                            @if ($e('out_date'))<div class="text-danger small">{{ $e('out_date') }}</div>@endif
                        </div>
                    </div>
                    <x-form.text name="out_perilipsi" :value="$form['out_perilipsi']" label="Περίληψη" rows="2" :error="$e('out_perilipsi')" />
                </div>
            </fieldset>

            {{-- attachments (R1c): open on new and copy, files can be dropped anywhere on the window --}}
            <section class="card shadow-sm mb-3" id="attachments" aria-labelledby="attachments-title"
                @if ($access->canSave)
                     x-data="{ over: false, depth: 0, progress: null,
                               hasFiles(e) { return [...(e.dataTransfer?.types || [])].includes('Files') } }"
                     x-on:dragenter.window="if (hasFiles($event)) { $event.preventDefault(); depth++; over = true }"
                     x-on:dragover.window="if (hasFiles($event)) $event.preventDefault()"
                     x-on:dragleave.window="if (hasFiles($event)) { depth = Math.max(0, depth - 1); if (! depth) over = false }"
                     x-on:drop.window="if (hasFiles($event)) { $event.preventDefault(); depth = 0; over = false;
                         progress = 0;
                         $wire.uploadMultiple('picked', $event.dataTransfer.files, () => progress = null, () => progress = null, (e) => progress = e.detail.progress) }"
                     x-on:livewire-upload-start="progress = 0" x-on:livewire-upload-progress="progress = $event.detail.progress"
                     x-on:livewire-upload-finish="progress = null" x-on:livewire-upload-error="progress = null"
                @endif>
                <h2 class="card-header h6 mb-0 d-flex align-items-center gap-2" id="attachments-title">
                    Συνημμένα
                    <span class="badge rounded-pill text-bg-secondary">{{ count($attachments) + count((array) $uploads) }}</span>
                </h2>
                <div class="card-body">
                    @forelse ($attachments as $a)
                        <div class="d-flex flex-wrap align-items-center gap-2 border-bottom py-1 attachment-row" wire:key="att-{{ $a->id }}">
                            <x-file-badge :name="$a->name" :size="$a->size" :mime="$a->mimeType" />
                            @if ($a->savedPath)
                                <a href="{{ url('/download/'.$a->id) }}" target="_blank" class="text-break me-auto">{{ $a->name }}</a>
                            @else
                                <span class="me-auto text-body-secondary">{{ $a->name ?: 'Μόνο ΑΔΑ' }}</span>
                            @endif
                            @if ($a->ada)
                                @if ($diavgeiaUrl)<a href="{{ $diavgeiaUrl . rawurlencode($a->ada) }}" target="_blank" class="badge text-bg-light">ΑΔΑ {{ $a->ada }}</a>
                                @else<span class="badge text-bg-light">ΑΔΑ {{ $a->ada }}</span>@endif
                            @endif
                            @if ($access->canSave)
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" wire:click="removeAttachment({{ $a->id }})"
                                        data-confirm="Να μεταφερθεί στον κάδο το «{{ $a->name ?: $a->ada }}»;" title="Στον κάδο" aria-label="Στον κάδο">✕</button>
                            @endif
                        </div>
                    @empty
                        @unless ($access->canSave)<p class="text-body-secondary small mb-0">Δεν υπάρχουν συνημμένα.</p>@endunless
                    @endforelse

                    @if ($access->canSave)
                        @foreach ((array) $uploads as $i => $file)
                            <div class="d-flex flex-wrap align-items-center gap-2 border-bottom py-1 upload-row" wire:key="upload-{{ $i }}-{{ $file->getFilename() }}">
                                <x-file-badge :name="$file->getClientOriginalName()" :size="$file->getSize()" />
                                <span class="me-auto text-break">{{ $file->getClientOriginalName() }}
                                    <small class="text-body-secondary">(θα αποθηκευτεί με το πρωτόκολλο)</small></span>
                                <input class="form-control form-control-sm w-auto" placeholder="ΑΔΑ" aria-label="ΑΔΑ του {{ $file->getClientOriginalName() }}"
                                       wire:model="uploadAdas.{{ $i }}" autocomplete="off">
                                <button type="button" class="btn btn-sm btn-link" wire:click="removeUpload({{ $i }})">αφαίρεση</button>
                            </div>
                        @endforeach

                        <label for="f-uploads" class="dropzone d-block border border-2 border-dashed rounded-3 text-center p-3 mt-2 text-body-secondary"
                               :class="over && 'border-primary bg-primary-subtle'">
                            <span class="fs-4 d-block" aria-hidden="true">📎</span>
                            Σύρετε αρχεία οπουδήποτε στο παράθυρο ή <span class="text-primary text-decoration-underline">επιλέξτε</span>
                            <input id="f-uploads" type="file" multiple class="visually-hidden" wire:model="picked">
                        </label>
                        <div class="progress mt-2" role="progressbar" aria-label="Ανέβασμα αρχείων" x-show="progress !== null" x-cloak
                             :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" :style="'width: ' + progress + '%'" x-text="progress + '%'"></div>
                        </div>
                        @error('picked.*')<div class="text-danger small">{{ $message }}</div>@enderror
                        @if ($e('ada'))<div class="text-danger small">{{ $e('ada') }}</div>@endif

                        <div class="row g-2 mt-1">
                            <div class="col-md-6">
                                <label for="f-ada" class="form-label small fw-semibold mb-1">ΑΔΑ (Διαύγεια, χωρίς αρχείο)</label>
                                <input id="f-ada" class="form-control" wire:model="newAda" autocomplete="off">
                            </div>
                            <div class="col-md-6">
                                <label for="f-keep" class="form-label small fw-semibold mb-1">Διατήρηση</label>
                                <select id="f-keep" class="form-select" wire:model="form.keep">
                                    <option value="">—</option>
                                    @foreach ($keepYears as $y)<option value="{{ $y }}" @selected((string) $y === $form['keep'])>{{ $y }} {{ $y == 1 ? 'έτος' : 'έτη' }}</option>@endforeach
                                    @foreach ($keepWords as $w)<option value="{{ $w }}" @selected((string) $w === $form['keep'])>{{ $w }}</option>@endforeach
                                </select>
                            </div>
                        </div>
                        @if ($putStamp)
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="f-nostamp" wire:model="noStamp">
                                <label class="form-check-label" for="f-nostamp">Χωρίς σφραγίδα αριθμού στα αρχεία</label>
                            </div>
                        @endif

                        {{-- whole-window drop target, shown while files are dragged over the page --}}
                        <div class="drop-overlay position-fixed top-0 start-0 w-100 h-100"
                             x-show="over" x-cloak x-transition.opacity aria-hidden="true">
                            <div class="bg-body rounded-4 shadow p-4 fs-4 border border-primary border-2">📎 Αφήστε τα αρχεία για επισύναψη</div>
                        </div>
                    @endif
                </div>
            </section>

            @if (! $protocol->exists && $access->canSave)
                <fieldset class="card shadow-sm mb-3">
                    <legend class="card-header h6 mb-0 w-100">Απόδειξη παραλαβής</legend>
                    <div class="card-body">
                        <x-form.text name="reply_to_email" :value="$form['reply_to_email']" type="email" label="Αποστολή απόδειξης στο email" :error="$e('reply_to_email')" />
                    </div>
                </fieldset>
            @endif
        </div>
    </div>

    @if ($access->canSave)
        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary rounded-pill px-4" wire:loading.attr="disabled" wire:target="save,uploads">Αποθήκευση</button>
        </div>
    @endif
</form>
