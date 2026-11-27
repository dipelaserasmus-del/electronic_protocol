{{-- R2: the protocol list. Query and rules: App\Livewire\ProtocolList. --}}
@php
    $currentField = $field;
    $currentSort = $sort;
    $sortHeader = function (string $column, string $label) use ($currentField, $currentSort) {
        $active = $currentField === $column;
        $arrow = $active ? ($currentSort === 'asc' ? ' ▲' : ' ▼') : '';
        return '<button type="button" class="btn btn-link btn-sm p-0 text-reset fw-semibold text-decoration-none text-nowrap" data-sort="'.$column.'" wire:click="sortBy(\''.$column.'\')"'
            .($active ? ' aria-sort="'.($currentSort === 'asc' ? 'ascending' : 'descending').'"' : '').'>'.e($label).$arrow.'</button>';
    };
@endphp
<div class="protocol-list" @if ($refreshSeconds > 0) wire:poll.{{ $refreshSeconds }}s @endif
     x-data="{ detailed: (() => { try { return localStorage.getItem('protocolList.detailed') === '1' } catch (e) { return false } })() }"
     x-init="$watch('detailed', v => { try { localStorage.setItem('protocolList.detailed', v ? '1' : '0') } catch (e) {} })">

    {{-- R2t (owner, 2026-11-27): the title, the gaps notice, the filters and the pager stay in place under the top bar while
         the list scrolls (the table's column titles stick right under them) --}}
    <div class="list-head" id="list-head">
    {{-- R2m (owner, 2026-11-25): no «Νέο» here (the top bar, always in view, has it); «Ανανέωση» is ↻ next to «Λίστα» in
         the top bar. R2l: no «Αναζήτηση» either (the search box has it). --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto" id="list-title">{{ $title }}</h1>
    </div>

    {{-- A02/R2c: missing numbers. A short line here; the numbers themselves are rows in the list (dashed) --}}
    @if (! empty($numberGaps))
        <div class="alert alert-warning py-2 d-flex flex-wrap align-items-center gap-2" id="numberGaps" role="alert">
            <strong>Κενά στην αρίθμηση:</strong>
            <span>{{ $gapCount }} {{ $gapCount === 1 ? 'αριθμός' : 'αριθμοί' }}
                @foreach ($numberGaps as $gapYear => $gapNumbers)
                    · έτος {{ $gapYear }}: {{ \App\Services\NumberGaps::compact(array_slice($gapNumbers, 0, 12)) }}@if (count($gapNumbers) > 12) …@endif
                @endforeach
            </span>
            <small class="text-body-secondary">
                @if ($gapRows) Φαίνονται στη λίστα σε πράσινες γραμμές «ΚΕΝΟ».
                @else Επιλέξτε «Όλα», ταξινόμηση κατά αριθμό, για να τα δείτε στη λίστα.
                @endif
                (έλεγχος {{ $gapFrom > 1 ? 'από τον αριθμό '.$gapFrom.', ' : '' }}τελευταίων {{ $gapMonths }} μηνών)
            </small>
        </div>
    @endif

    {{-- filters --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2" role="group" aria-label="Φίλτρα">
        @foreach (\App\Livewire\ProtocolList::FILTERS as $key => $label)
            <button type="button" wire:click="setFilter('{{ $key }}')" data-filter="{{ $key === '' ? 'all' : $key }}"
                    class="btn btn-sm rounded-pill {{ $filter === $key ? 'btn-primary' : 'btn-outline-primary' }}" title="{{ \App\Livewire\ProtocolList::FILTER_HELP[$key] }}"
                    @if ($filter === $key) aria-pressed="true" @endif>{{ $label }}</button>
        @endforeach
        @if ($canSeeOthers)
            <label for="list-who" class="visually-hidden">Χρήστης</label>
            {{-- R2b: under "Όλα" the list is everybody's unless a user is chosen; users with open handling
                 assignments (not yet "Διεκπεραιώθηκε") first, with their number --}}
            <select id="list-who" class="form-select form-select-sm w-auto" wire:model.live="who">
                @if ($filter === '')
                    <option value="me">Όλοι οι χρήστες</option>
                @else
                    <option value="me">Εγώ</option>
                    <option value="all">Όλοι οι χρήστες</option>
                @endif
                @if ($userGroups['open'])
                    <optgroup label="Με εκκρεμείς αναθέσεις">
                        @foreach ($userGroups['open'] as $u)<option value="{{ $u['id'] }}">{{ $u['name'] }} ({{ $u['open'] }})</option>@endforeach
                    </optgroup>
                @endif
                @if ($userGroups['rest'])
                    <optgroup label="Χωρίς εκκρεμείς αναθέσεις">
                        @foreach ($userGroups['rest'] as $u)<option value="{{ $u['id'] }}">{{ $u['name'] }}</option>@endforeach
                    </optgroup>
                @endif
            </select>
        @endif
        <div class="form-check form-switch ms-auto">
            <input class="form-check-input" type="checkbox" role="switch" id="list-detailed" x-model="detailed">
            <label class="form-check-label small" for="list-detailed">Αναλυτικά</label>
        </div>
        <span class="small text-body-secondary" wire:loading.delay>φόρτωση…</span>
    </div>

    {{-- R2e: the pager above the list too; both on the right (owner, 2026-11-19) --}}
    <x-list-pager :paginator="$protocols" position="top" class="mb-2 justify-content-end" />
    </div>

    <div class="card shadow-sm">
        {{-- R2i (owner, 2026-11-20): rows in alternating colours; fixed column widths; each row short (subject and sender in two
             lines, two files), ▾ shows all of it; Αναλυτικά opens every row --}}
        <table class="table table-hover table-sm align-middle mb-0 table-sticky protocol-table">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="text-nowrap col-number">{!! $sortHeader('protocolnum', 'Αριθμός') !!}<br>{!! $sortHeader('protocoldate', 'Ημ/νία') !!}</th>
                    <th scope="col" class="d-none d-md-table-cell col-folder">{!! $sortHeader('fakelos', 'Φάκελος') !!}</th>
                    <th scope="col">{!! $sortHeader('thema', 'Θέμα') !!}</th>
                    <th scope="col" class="d-none d-lg-table-cell col-from">{!! $sortHeader('in_arxi_ekdosis', 'Από') !!} / {!! $sortHeader('out_to', 'Προς') !!}</th>
                    <th scope="col" class="d-none d-md-table-cell col-assign">{!! $sortHeader('diekperaiosi', 'Ανάθεση') !!}<br>{!! $sortHeader('diekp_eos', 'Έως') !!}</th>
                    <th scope="col" class="d-none d-sm-table-cell col-files">Συνημμένα</th>
                    <th scope="col" class="text-end col-actions"><span class="visually-hidden">Ενέργειες</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($protocols as $p)
                    @php
                        $tone = \App\Livewire\ProtocolList::deadlineTone($p);
                    @endphp
                    @php
                        $dateFlag = $dateFlags[$p->id] ?? null;
                        $noFolder = trim((string) $p->fakelos) === '';   // R2n (owner, 2026-11-25): flagged, as a wrong date
                    @endphp
                    <tr wire:key="p-{{ $p->id }}" class="protocol-row @if ($dateFlag) date-flagged @endif @if ($noFolder) folder-flagged @endif" data-id="{{ $p->id }}"
                        x-data="{ open: false }" :class="{ 'row-open': open || detailed }">
                        <td class="text-nowrap">
                            <a href="{{ url('/home/'.$p->id) }}" class="fw-bold text-decoration-none stretched-link-row">{{ $p->protocolnum }}/{{ $p->etos }}</a>
                            <div class="small @if ($dateFlag) text-warning-emphasis fw-semibold @else text-body-secondary @endif">{{ $p->dateOf('protocoldate')?->format('d/m/Y') }}
                                @if ($dateFlag)
                                    {{-- R2f: above the row's link, so the tooltip opens without opening the protocol --}}
                                    @php $dateText = \App\Services\DateOrder::message($dateFlag, auth()->user()); @endphp
                                    <span class="date-flag position-relative" tabindex="0" role="img" aria-label="{{ $dateText }}"
                                          data-bs-title="{{ $dateText }}" data-kinds="{{ implode(' ', $dateFlag['kinds']) }}"
                                          x-data x-init="bootstrap.Tooltip.getOrCreateInstance($el, { placement: 'right', customClass: 'date-flag-tooltip' })"><i class="bi bi-exclamation-triangle-fill text-warning" aria-hidden="true"></i></span>
                                @endif
                                @if ($noFolder)
                                    {{-- R2n: narrow screens have no Φάκελος column: the warning here instead --}}
                                    <span class="folder-flag position-relative d-md-none" tabindex="0" role="img" aria-label="Χωρίς φάκελο"
                                          data-bs-title="Χωρίς φάκελο: το πρωτόκολλο δεν έχει φάκελο. Ανοίξτε το και επιλέξτε τον φάκελό του."
                                          x-data x-init="bootstrap.Tooltip.getOrCreateInstance($el, { placement: 'right', customClass: 'date-flag-tooltip' })"><i class="bi bi-folder-x text-warning" aria-hidden="true"></i></span>
                                @endif
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell small">
                            @if ($p->fakelos)<span title="{{ $folders[$p->fakelos] ?? '' }}">{{ $p->fakelos }}</span>
                            @else
                                {{-- R2n: no folder: a warning with its explanation (above the row's link, as R2f's) --}}
                                @php $folderText = 'Χωρίς φάκελο: το πρωτόκολλο δεν έχει φάκελο. Ανοίξτε το και επιλέξτε τον φάκελό του.'; @endphp
                                <span class="folder-flag position-relative text-warning-emphasis fw-semibold" tabindex="0" role="img" aria-label="{{ $folderText }}"
                                      data-bs-title="{{ $folderText }}"
                                      x-data x-init="bootstrap.Tooltip.getOrCreateInstance($el, { placement: 'right', customClass: 'date-flag-tooltip' })"><i class="bi bi-exclamation-triangle-fill text-warning" aria-hidden="true"></i> Χωρίς φάκελο</span>{{-- R2o (owner, 2026-11-25): in full --}}
                            @endif
                        </td>
                        <td>
                            <div class="text-break row-clamp">{{ $p->thema }}</div>
                            {{-- R2b: the detailed view shows everything a row has, not only the (often empty) notes --}}
                            <div x-show="detailed || open" x-cloak class="small text-body-secondary detailed">
                                @if ($p->fakelos && ($folders[$p->fakelos] ?? null))<div><span class="fw-semibold">Φάκελος:</span> {{ $p->fakelos }} · {{ $folders[$p->fakelos] }}</div>@endif
                                @if ($p->in_num || $p->in_arxi_ekdosis || $p->in_topos_ekdosis || $p->in_paraliptis)
                                    <div><span class="fw-semibold">Εισερχόμενο:</span>
                                        {{ collect([
                                            $p->in_num ? 'αρ. '.$p->in_num : null,
                                            $p->in_date ? $p->dateOf('in_date')?->format('d/m/Y') : null,
                                            $p->in_arxi_ekdosis, $p->in_topos_ekdosis,
                                            $p->in_paraliptis ? 'προς '.$p->in_paraliptis : null,
                                        ])->filter()->implode(' · ') }}</div>
                                @endif
                                @if ($p->in_perilipsi)<div><span class="fw-semibold">Περίληψη εισ.:</span> {{ $p->in_perilipsi }}</div>@endif
                                @if ($p->out_to || $p->out_date)
                                    <div><span class="fw-semibold">Εξερχόμενο:</span>
                                        {{ collect([$p->out_to, $p->out_date ? $p->dateOf('out_date')?->format('d/m/Y') : null])->filter()->implode(' · ') }}</div>
                                @endif
                                @if ($p->out_perilipsi)<div><span class="fw-semibold">Περίληψη εξ.:</span> {{ $p->out_perilipsi }}</div>@endif
                                @if ($p->keywords)<div><span class="fw-semibold">Λέξεις:</span> {{ $p->keywords }}</div>@endif
                                @if ($p->sxetiko)
                                    <div><span class="fw-semibold">Σχετικοί:</span>
                                        @foreach (array_map('trim', explode(',', $p->sxetiko)) as $ref)
                                            @if (preg_match('~^(\d+)/(\d{4})$~', $ref, $m))<a href="{{ url('/goto/'.$m[2].'/'.$m[1]) }}?find=1" class="position-relative z-2">{{ $ref }}</a>@else{{ $ref }}@endif@if (! $loop->last), @endif
                                        @endforeach
                                    </div>
                                @endif
                                @if ($p->paratiriseis)<div><span class="fw-semibold">Παρατηρήσεις:</span> {{ $p->paratiriseis }}</div>@endif
                                <div><span class="fw-semibold">Καταχώριση:</span> {{ $names[$p->user_id] ?? '—' }}@if ($p->created_at) · {{ $p->created_at->format('d/m/Y H:i') }}@endif</div>
                            </div>
                        </td>
                        {{-- R2k (owner, 2026-11-20): the sender / recipient only, one line each; the number and the dates in a
                             tooltip (they rarely fitted), and in full when the row is opened --}}
                        @php
                            // the whole name too: the cell shortens it with …; R2q (owner, 2026-11-25): one line each (the tooltip keeps them)
                            $inMore = collect([$p->in_arxi_ekdosis, $p->in_num ? 'Αρ. εισερχομένου: '.$p->in_num : null,
                                $p->in_date ? 'Ημ/νία: '.$p->dateOf('in_date')?->format('d/m/Y') : null])->filter()->implode("\n");
                            $outMore = collect([$p->out_to, $p->out_date ? 'Ημ/νία εξερχομένου: '.$p->dateOf('out_date')?->format('d/m/Y') : null])->filter()->implode("\n");
                        @endphp
                        <td class="d-none d-lg-table-cell small from-to">
                            @if ($p->in_arxi_ekdosis || $p->in_num)
                                <div class="from-to-line"><span @if ($inMore) class="from-to-more position-relative z-2" tabindex="0" data-bs-title="{{ $inMore }}"
                                      x-init="bootstrap.Tooltip.getOrCreateInstance($el, { placement: 'top', customClass: 'from-to-tooltip' })" @endif>← {{ $p->in_arxi_ekdosis ?: '—' }}</span>
                                    @if ($p->in_num)<span class="text-body-secondary" x-show="detailed || open" x-cloak>αρ. {{ $p->in_num }}@if ($p->in_date) / {{ $p->dateOf('in_date')?->format('d/m/Y') }}@endif</span>@endif
                                </div>
                            @endif
                            @if ($p->out_to)
                                <div class="from-to-line"><span @if ($outMore) class="from-to-more position-relative z-2" tabindex="0" data-bs-title="{{ $outMore }}"
                                      x-init="bootstrap.Tooltip.getOrCreateInstance($el, { placement: 'top', customClass: 'from-to-tooltip' })" @endif>→ {{ $p->out_to }}</span>
                                    @if ($p->out_date)<span class="text-body-secondary" x-show="detailed || open" x-cloak>{{ $p->dateOf('out_date')?->format('d/m/Y') }}</span>@endif</div>
                            @endif
                        </td>
                        <td class="d-none d-md-table-cell small">
                            @if ($p->assignments->isNotEmpty())
                                <x-assignee-pills :assignments="$p->assignments" :names="$names" class="mb-1" />
                            @endif
                            @if ($p->diekp_date)
                                <span class="badge text-bg-success">✔ {{ $p->dateOf('diekp_date')?->format('d/m/Y') }}</span>
                            @elseif ($p->diekp_eos)
                                <span class="badge {{ $tone ? 'text-bg-'.$tone : 'text-bg-light' }}" title="Διεκπεραίωση έως">έως {{ $p->dateOf('diekp_eos')?->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="d-none d-sm-table-cell small">
                            {{-- R2b: the file's name, shortened (whole in the detailed view and on hover); R2k: the first one and
                                 "+N" on one line until the row is opened --}}
                            <div class="attachment-list">
                            {{-- R2r (owner, 2026-11-25): the e-mail's own file last (setting emailFileFirst), shown by its date and time --}}
                            @foreach (\App\Support\EmailFile::ordered($p->attachments, $emailFileFirst) as $a)
                                @php $shown = \App\Support\EmailFile::label($a->name); @endphp
                                <div class="d-flex align-items-center gap-1 attachment-name @if (\App\Support\EmailFile::is($a->name)) attachment-email @endif">
                                    @if ($a->savedPath)
                                        {{-- R4q (owner, 2026-11-27): 👁 here too; ‹ › go through this protocol's files. R2u (owner, 2026-11-27):
                                             as in the form: the type, 👁, then the name (the link) --}}
                                        @php $stampCorner ??= \App\Services\Stamp\StampTemplate::current()->corner; @endphp
                                        <a href="{{ url('/download/'.$a->id) }}" target="_blank" class="text-decoration-none position-relative z-2 d-inline-flex flex-shrink-0"
                                           title="{{ $a->name }}" tabindex="-1" aria-hidden="true"><x-file-badge :name="$a->name" :mime="$a->mimeType" /></a>
                                        <x-preview-button class="position-relative z-2 flex-shrink-0" :name="$a->name" :download="url('/download/'.$a->id)" :preview="url('/preview/'.$a->id)"
                                                          :group="'list-'.$p->id" :stamp-in-file="(bool) $a->stamp_in_file" :stampable="\App\Services\Stamp\StampedCopy::applies($a)"
                                                          :stamp-place="$a" :stamp-corner="$stampCorner" />
                                        <a href="{{ url('/download/'.$a->id) }}" target="_blank" class="text-decoration-none position-relative z-2 d-inline-flex align-items-center attachment-link"
                                           title="{{ $a->name }}">
                                            <span x-show="! (detailed || open)" class="text-truncate">{{ \App\Support\FileBadge::shortName($shown) }}</span>
                                            <span x-show="detailed || open" x-cloak class="text-break">{{ $shown }}</span></a>
                                        {{-- R2u (owner, 2026-11-27): the size after the name, dimmed (the type alone before it leaves the name more room) --}}
                                        @if (($size = \App\Support\FileBadge::size($a->size)) !== '')<small class="attachment-size text-body-tertiary text-nowrap flex-shrink-0">{{ $size }}</small>@endif
                                    @endif
                                    @if ($a->ada && ! $a->savedPath)
                                        {{-- R2p (owner, 2026-11-25): only an ΑΔΑ: a pill «ΑΔΑ», then its code as the link (as a file's name), as R1m --}}
                                        <span class="file-badge d-inline-flex align-items-center text-nowrap"><span class="badge text-bg-info">ΑΔΑ</span></span>
                                        @if ($diavgeiaUrl)<a href="{{ $diavgeiaUrl . rawurlencode($a->ada) }}" target="_blank" class="text-decoration-none position-relative z-2 text-break attachment-ada"
                                                             title="Στη Διαύγεια">{{ $a->ada }}</a>
                                        @else<span class="text-break attachment-ada">{{ $a->ada }}</span>@endif
                                    @elseif ($a->ada)
                                        @if ($diavgeiaUrl)<a href="{{ $diavgeiaUrl . rawurlencode($a->ada) }}" target="_blank" class="badge text-bg-light position-relative z-2" title="{{ $a->ada }}">ΑΔΑ<span x-show="detailed || open" x-cloak> {{ $a->ada }}</span></a>
                                        @else<span class="badge text-bg-light" title="{{ $a->ada }}">ΑΔΑ<span x-show="detailed || open" x-cloak> {{ $a->ada }}</span></span>@endif
                                    @endif
                                </div>
                            @endforeach
                            @if ($p->attachments->count() > 1)
                                <button type="button" class="btn btn-link btn-sm p-0 small more-files position-relative z-2 text-nowrap" x-on:click="open = true"
                                        title="Όλα τα συνημμένα ({{ $p->attachments->count() }})">+{{ $p->attachments->count() - 1 }}</button>
                            @endif
                            </div>
                        </td>
                        {{-- R2h (owner, 2026-11-19): the row's actions under ⋮, to save space; above the row's link (z-2) --}}
                        <td class="text-end row-actions-cell text-nowrap">
                            {{-- R2i: this row in full, or short again --}}
                            <button type="button" class="btn btn-sm btn-link text-body-secondary row-toggle position-relative z-2" x-show="! detailed"
                                    x-on:click="open = ! open" :aria-expanded="open.toString()" aria-expanded="false"
                                    :title="open ? 'Σύντμηση' : 'Όλα τα στοιχεία'" title="Όλα τα στοιχεία"
                                    aria-label="Όλα τα στοιχεία του {{ $p->protocolnum }}/{{ $p->etos }}">
                                <i class="bi bi-chevron-down" aria-hidden="true"></i></button>
                            <div class="dropdown d-inline-block position-relative z-2">
                                <button type="button" class="btn btn-sm btn-link text-body-secondary row-actions" data-bs-toggle="dropdown"
                                        data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false"
                                        aria-label="Ενέργειες για το {{ $p->protocolnum }}/{{ $p->etos }}" title="Ενέργειες">
                                    <i class="bi bi-three-dots-vertical" aria-hidden="true"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    @if ($mayCreate ??= (bool) auth()->user()?->can('create', \App\Models\Protocol::class))   {{-- R1j: not for readers --}}
                                    <li><a class="dropdown-item" href="{{ url('/home/'.$p->id.'/1') }}" data-action="copy">
                                        <i class="bi bi-copy me-2" aria-hidden="true"></i>Αντιγραφή ως νέο</a></li>
                                    @endif
                                    <li><a class="dropdown-item" href="{{ url('/receipt/'.$p->id) }}" target="_blank" data-action="receipt">
                                        <i class="bi bi-receipt me-2" aria-hidden="true"></i>Απόδειξη</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @php $gap = $gapRows[$p->etos.':'.$p->protocolnum] ?? null; @endphp
                    @if ($gap)
                        @php $single = $gap['from'] === $gap['to']; $first = $sort === 'desc' ? $gap['to'] : $gap['from']; @endphp
                        {{-- R2d (owner, 2026-11-18): green, dimmed text, the number first, the button in the middle --}}
                        <tr wire:key="gap-{{ $gap['year'] }}-{{ $gap['from'] }}" class="gap-row">
                            <td colspan="7" data-full-row>
                                <div class="gap-row-grid">
                                    <span class="gap-label">
                                        @if ($single) <span class="fw-semibold">{{ $gap['from'] }}/{{ $gap['year'] }}</span> ΚΕΝΟ
                                        @else <span class="fw-semibold">{{ $gap['from'] }}–{{ $gap['to'] }}/{{ $gap['year'] }}</span> ΚΕΝΑ
                                            · {{ $gap['to'] - $gap['from'] + 1 }} αριθμοί
                                        @endif
                                    </span>
                                    @if ($canCreate && $gap['year'] == $formYear)
                                        <a href="{{ url('/home') }}?num={{ $first }}" class="btn btn-sm btn-outline-success rounded-pill gap-create">
                                            <x-icon name="plus" /> Καταχώριση με αριθμό {{ $first }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" data-full-row class="text-center text-body-secondary py-4">Δεν υπάρχουν πρωτόκολλα σε αυτή τη λίστα.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-body-secondary">Σύνολο: {{ $protocols->total() }}</small>
        <x-list-pager :paginator="$protocols" position="bottom" class="ms-auto justify-content-end" />
    </div>
</div>
