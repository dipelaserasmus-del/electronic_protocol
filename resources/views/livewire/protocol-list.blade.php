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

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto" id="list-title">{{ $title }}</h1>
        <x-pill-actions class="flex-grow-0">
            <x-pill :href="url('/home')" variant="primary">Νέο</x-pill>
            <x-pill :href="url('/find')">Αναζήτηση</x-pill>
            <x-pill wire:click="$refresh">Ανανέωση</x-pill>
        </x-pill-actions>
    </div>

    {{-- A02: missing numbers --}}
    @if (! empty($numberGaps))
        <div class="alert alert-warning py-2" id="numberGaps" role="alert">
            <strong>Κενά στην αρίθμηση:</strong>
            @foreach ($numberGaps as $gapYear => $gapNumbers)
                <span class="me-3">
                    έτος {{ $gapYear }} →
                    @foreach (array_slice($gapNumbers, 0, 30) as $gapNum)
                        @if ($canCreate && $gapYear == $formYear)
                            <a href="{{ url('/home') }}?num={{ $gapNum }}" title="Νέο πρωτόκολλο με αριθμό {{ $gapNum }}">{{ $gapNum }}</a>@if (! $loop->last), @endif
                        @else
                            {{ $gapNum }}@if (! $loop->last), @endif
                        @endif
                    @endforeach
                    @if (count($gapNumbers) > 30)
                        … (σύνολο {{ count($gapNumbers) }}: {{ \App\Services\NumberGaps::compact($gapNumbers) }})
                    @endif
                </span>
            @endforeach
            <small class="text-body-secondary">(έλεγχος τελευταίων {{ $gapMonths }} μηνών)</small>
        </div>
    @endif

    {{-- filters --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2" role="group" aria-label="Φίλτρα">
        @foreach (\App\Livewire\ProtocolList::FILTERS as $key => $label)
            <button type="button" wire:click="setFilter('{{ $key }}')" data-filter="{{ $key === '' ? 'all' : $key }}"
                    class="btn btn-sm rounded-pill {{ $filter === $key ? 'btn-primary' : 'btn-outline-primary' }}"
                    @if ($filter === $key) aria-pressed="true" @endif>{{ $label }}</button>
        @endforeach
        @if ($canSeeOthers)
            <label for="list-who" class="visually-hidden">Χρήστης</label>
            <select id="list-who" class="form-select form-select-sm w-auto" wire:model.live="who">
                <option value="me">Εγώ</option>
                <option value="all">Όλοι οι χρήστες</option>
                @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
            </select>
        @endif
        <div class="form-check form-switch ms-auto">
            <input class="form-check-input" type="checkbox" role="switch" id="list-detailed" x-model="detailed">
            <label class="form-check-label small" for="list-detailed">Αναλυτικά</label>
        </div>
        <span class="small text-body-secondary" wire:loading.delay>φόρτωση…</span>
    </div>

    <div class="card shadow-sm">
        <table class="table table-hover table-sm align-middle mb-0 table-sticky">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="text-nowrap">{!! $sortHeader('protocolnum', 'Αριθμός') !!}<br>{!! $sortHeader('protocoldate', 'Ημ/νία') !!}</th>
                    <th scope="col" class="d-none d-md-table-cell">{!! $sortHeader('fakelos', 'Φάκελος') !!}</th>
                    <th scope="col">{!! $sortHeader('thema', 'Θέμα') !!}</th>
                    <th scope="col" class="d-none d-lg-table-cell">{!! $sortHeader('in_arxi_ekdosis', 'Από') !!} / {!! $sortHeader('out_to', 'Προς') !!}</th>
                    <th scope="col" class="d-none d-md-table-cell">{!! $sortHeader('diekperaiosi', 'Ανάθεση') !!}<br>{!! $sortHeader('diekp_eos', 'Έως') !!}</th>
                    <th scope="col" class="d-none d-sm-table-cell">Συνημμένα</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Ενέργειες</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($protocols as $p)
                    @php
                        $tone = \App\Livewire\ProtocolList::deadlineTone($p);
                        $handlers = $p->assignments->where('type', 'd');
                        $informed = $p->assignments->where('type', 'e');
                    @endphp
                    <tr wire:key="p-{{ $p->id }}" class="protocol-row" data-id="{{ $p->id }}">
                        <td class="text-nowrap">
                            <a href="{{ url('/home/'.$p->id) }}" class="fw-bold text-decoration-none stretched-link-row">{{ $p->protocolnum }}/{{ $p->etos }}</a>
                            <div class="small text-body-secondary">{{ $p->dateOf('protocoldate')?->format('d/m/Y') }}</div>
                        </td>
                        <td class="d-none d-md-table-cell small">
                            @if ($p->fakelos)<span title="{{ $folders[$p->fakelos] ?? '' }}">{{ $p->fakelos }}</span>@endif
                        </td>
                        <td>
                            <div class="text-break">{{ $p->thema }}</div>
                            <div x-show="detailed" x-cloak class="small text-body-secondary">
                                @if ($p->in_perilipsi)<div><span class="fw-semibold">Περίληψη εισ.:</span> {{ $p->in_perilipsi }}</div>@endif
                                @if ($p->out_perilipsi)<div><span class="fw-semibold">Περίληψη εξ.:</span> {{ $p->out_perilipsi }}</div>@endif
                                @if ($p->keywords)<div><span class="fw-semibold">Λέξεις:</span> {{ $p->keywords }}</div>@endif
                                @if ($p->sxetiko)
                                    <div><span class="fw-semibold">Σχετικοί:</span>
                                        @foreach (array_map('trim', explode(',', $p->sxetiko)) as $ref)
                                            @if (preg_match('~^(\d+)/(\d{4})$~', $ref, $m))<a href="{{ url('/goto/'.$m[2].'/'.$m[1]) }}?find=1">{{ $ref }}</a>@else{{ $ref }}@endif@if (! $loop->last), @endif
                                        @endforeach
                                    </div>
                                @endif
                                @if ($p->paratiriseis)<div><span class="fw-semibold">Παρατηρήσεις:</span> {{ $p->paratiriseis }}</div>@endif
                            </div>
                        </td>
                        <td class="d-none d-lg-table-cell small">
                            @if ($p->in_arxi_ekdosis || $p->in_num)
                                <div>← {{ $p->in_arxi_ekdosis }}
                                    @if ($p->in_num)<span class="text-body-secondary">αρ. {{ $p->in_num }}@if ($p->in_date) / {{ $p->dateOf('in_date')?->format('d/m/Y') }}@endif</span>@endif
                                </div>
                            @endif
                            @if ($p->out_to)
                                <div>→ {{ $p->out_to }} @if ($p->out_date)<span class="text-body-secondary">{{ $p->dateOf('out_date')?->format('d/m/Y') }}</span>@endif</div>
                            @endif
                        </td>
                        <td class="d-none d-md-table-cell small">
                            @if ($handlers->isNotEmpty())
                                <span class="fw-semibold">{{ $handlers->map(fn ($a) => $names[$a->user_id] ?? '—')->implode(', ') }}</span>
                            @endif
                            @if ($informed->isNotEmpty())
                                <div class="text-body-secondary">ενημ.: {{ $informed->map(fn ($a) => $names[$a->user_id] ?? '—')->implode(', ') }}</div>
                            @endif
                            @if ($p->diekp_date)
                                <span class="badge text-bg-success">✔ {{ $p->dateOf('diekp_date')?->format('d/m/Y') }}</span>
                            @elseif ($p->diekp_eos)
                                <span class="badge {{ $tone ? 'text-bg-'.$tone : 'text-bg-light' }}" title="Διεκπεραίωση έως">έως {{ $p->dateOf('diekp_eos')?->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="d-none d-sm-table-cell small">
                            @foreach ($p->attachments as $a)
                                <div class="text-nowrap">
                                    @if ($a->savedPath)
                                        <a href="{{ url('/download/'.$a->id) }}" target="_blank" class="text-decoration-none position-relative z-2"
                                           title="{{ $a->name }}"><x-file-badge :name="$a->name" :size="$a->size" :mime="$a->mimeType" /></a>
                                    @endif
                                    @if ($a->ada)
                                        @if ($diavgeiaUrl)<a href="{{ $diavgeiaUrl . rawurlencode($a->ada) }}" target="_blank" class="badge text-bg-light position-relative z-2">ΑΔΑ</a>
                                        @else<span class="badge text-bg-light" title="{{ $a->ada }}">ΑΔΑ</span>@endif
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ url('/home/'.$p->id.'/1') }}" class="btn btn-sm btn-outline-secondary rounded-pill position-relative z-2" title="Αντιγραφή ως νέο">Αντιγραφή</a>
                            <a href="{{ url('/receipt/'.$p->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill position-relative z-2 d-none d-xl-inline-block" title="Απόδειξη">Απόδειξη</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">Δεν υπάρχουν πρωτόκολλα σε αυτή τη λίστα.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <small class="text-body-secondary">Σύνολο: {{ $protocols->total() }}</small>
        {{ $protocols->links() }}
    </div>
</div>
