{{--
    R3: search results (advanced search R3a, results page R3c). A click on a row opens the protocol.
    $protocols paginator · $terms field => text(s) to mark in that field · $words (optional) marked in every field ·
    $names user id => name · $folders code => description · $showPending badges saying why a protocol is pending
--}}
@php
    $words ??= [];
    $mark = fn (string $field, $value) => \App\Support\Highlight::html($value, array_merge((array) ($terms[$field] ?? []), $words));
    // fields matched but not shown in the columns: shown under the subject, marked
    $extra = ['in_num' => 'Αρ. εισερχ.', 'in_topos_ekdosis' => 'Τόπος', 'in_paraliptis' => 'Παραλήπτης', 'in_perilipsi' => 'Περίληψη εισ.',
              'out_perilipsi' => 'Περίληψη εξ.', 'sxetiko' => 'Σχετικοί', 'keywords' => 'Λέξεις', 'paratiriseis' => 'Παρατηρήσεις'];
    $extra = $words ? $extra : array_intersect_key($extra, array_filter($terms));
@endphp
@if ($protocols->total() > 0 || empty($hideEmpty))
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
    <span id="search-count" class="fw-semibold">
        @if ($protocols->total() === 0) Δεν βρέθηκε κανένα πρωτόκολλο.
        @else Βρέθηκαν {{ $protocols->total() }} {{ $protocols->total() === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα' }}.
        @endif
    </span>
    <x-list-pager :paginator="$protocols" position="top" class="justify-content-end" />
</div>
@endif

@if ($protocols->total() > 0)
    <div class="card shadow-sm">
        <table class="table table-hover table-sm align-middle mb-0 table-sticky" id="search-results">
            <thead class="table-light">
                <tr>
                    <th scope="col" class="text-nowrap">Αριθμός<br>Ημ/νία</th>
                    <th scope="col" class="d-none d-md-table-cell">Φάκελος</th>
                    <th scope="col">Θέμα</th>
                    <th scope="col" class="d-none d-lg-table-cell">Από / Προς</th>
                    <th scope="col" class="d-none d-md-table-cell">Ανάθεση</th>
                    <th scope="col" class="d-none d-sm-table-cell">Συνημμένα</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($protocols as $p)
                    @php $handlers = $p->assignments->where('type', 'd'); @endphp
                    <tr wire:key="r-{{ $p->id }}" class="protocol-row" data-id="{{ $p->id }}">
                        <td class="text-nowrap">
                            <a href="{{ url('/home/'.$p->id) }}" class="fw-bold text-decoration-none stretched-link-row">{{ $p->protocolnum }}/{{ $p->etos }}</a>
                            <div class="small text-body-secondary">{{ $p->dateOf('protocoldate')?->format('d/m/Y') }}</div>
                        </td>
                        <td class="d-none d-md-table-cell small">
                            @if ($p->fakelos)<span title="{{ $folders[$p->fakelos] ?? '' }}">{!! $mark('fakelos', $p->fakelos) !!}</span>@endif
                        </td>
                        <td>
                            <div class="text-break">{!! $mark('thema', $p->thema) !!}</div>
                            @if (! empty($showPending))
                                @foreach (\App\Services\Search\ProtocolSearch::pendingReasons($p) as $why)
                                    <span class="badge text-bg-warning pending-reason">{{ $why === 'folder' ? 'χωρίς φάκελο' : 'ΕΚΚΡΕΜΟΤΗΤΑ στο θέμα' }}</span>
                                @endforeach
                            @endif
                            @foreach ($extra as $f => $label)
                                @php $html = $mark($f, $p->$f); @endphp
                                @if ($p->$f && (! $words || str_contains($html, '<mark>')))
                                    <div class="small text-body-secondary text-break matched-field" data-field="{{ $f }}"><span class="fw-semibold">{{ $label }}:</span> {!! $html !!}</div>
                                @endif
                            @endforeach
                        </td>
                        <td class="d-none d-lg-table-cell small">
                            @if ($p->in_arxi_ekdosis)<div>← {!! $mark('in_arxi_ekdosis', $p->in_arxi_ekdosis) !!}</div>@endif
                            @if ($p->out_to)<div>→ {!! $mark('out_to', $p->out_to) !!}</div>@endif
                        </td>
                        <td class="d-none d-md-table-cell small">
                            @if ($handlers->isNotEmpty())
                                <span class="fw-semibold">{{ $handlers->map(fn ($a) => $names[$a->user_id] ?? '—')->implode(', ') }}</span>
                            @endif
                            @if ($p->diekp_date)
                                <span class="badge text-bg-success">✔ {{ $p->dateOf('diekp_date')?->format('d/m/Y') }}</span>
                            @endif
                        </td>
                        <td class="d-none d-sm-table-cell small">
                            @foreach ($p->attachments as $a)
                                <div class="text-break attachment-name">{!! $mark('name', $a->name) !!}@if ($a->ada) <span class="badge text-bg-light">ΑΔΑ {!! $mark('ada', $a->ada) !!}</span>@endif</div>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3 d-flex justify-content-end"><x-list-pager :paginator="$protocols" position="bottom" class="justify-content-end" /></div>
@endif
