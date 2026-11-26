{{--
    R7: the protocol book, on the print layout. Same 13 numbered columns as the legacy printout (and the Excel file,
    printedXls): ✧ and ✦ mark the second and third value of a cell. Dates as dates (D04), handlers by name, also
    inactive users (before: their id). R2s (owner, 2026-11-26): restyled for paper (layouts/print), the second and third
    values on their own lines with a light mark, column widths, a total at the end.
--}}
@extends('layouts.print')

@section('title', 'Πρωτόκολλο '.$range->describe())
@section('heading')
    {{ $protocols->count() }} {{ $protocols->count() === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα' }}@if ($total > $protocols->count()) <strong>από {{ $total }}: τα υπόλοιπα δεν χωρούν στο όριο των Ρυθμίσεων</strong>@endif
@endsection
@section('toolbar')<a class="secondary" href="{{ url('/printed').'?'.http_build_query($range->params() + ['target' => 'xls']) }}">Excel</a>@endsection

@section('content')
    @include('partials.ignoredFiltersNotice')
    @php
        $d = fn ($p, $c) => $p->dateOf($c)?->format('d/m/Y');
        // R2s: a second / third value of a cell on its own line, its mark (✧ ✦, as in the header and the Excel file) light
        $sub = fn (string $mark, ?string $text) => $text === null || $text === '' ? '' : '<div class="sub"><span class="mk">'.$mark.'</span> '.e($text).'</div>';
    @endphp
    <table class="print" id="protocol-book">
        <colgroup>
            <col style="width:7%"><col style="width:14%"><col style="width:21%"><col style="width:9%">
            <col style="width:11%"><col style="width:13%"><col style="width:10%"><col style="width:15%">
        </colgroup>
        <thead>
            <tr class="groups">
                <th rowspan="2" class="span-head">Αύξ. αριθ.<br>Ημ. παραλ.</th>
                <th colspan="3">ΕΙΣΕΡΧΟΜΕΝΑ</th>
                <th colspan="2">ΕΞΕΡΧΟΜΕΝΑ</th>
                <th>ΔΙΕΚΠΕΡΑΙΩΣΗ</th>
                <th rowspan="2" class="span-head">Φάκελος<br>✧ Σχετ. αριθμοί<br>✦ Παρατηρήσεις</th>
            </tr>
            <tr>
                <th>Αριθ./Ημ. εισερχ.<br>✧ Τόπος έκδοσης<br>✦ Αρχή έκδοσης</th>
                <th>Θέμα<br>✧ Περίληψη εισερχομένου</th>
                <th>Παραλήπτης</th>
                <th>Ημ/νία εξερχ.<br>✧ Απευθύνεται</th>
                <th>Περίληψη εξερχομένου</th>
                <th>Διεκπεραίωση<br>✧ Ημ/νία διεκπ.</th>
            </tr>
            <tr class="cols" title="Οι στήλες του βιβλίου πρωτοκόλλου">
                <th>1, 2</th><th>3, 4, 5</th><th>6</th><th>7</th><th>10, 8</th><th>9</th><th>11</th><th>13, 12</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($protocols as $p)
                <tr class="book-row">
                    <td class="nowrap"><span class="num">{{ $p->protocolnum }}</span>@if ($range->year === null)<span class="small muted">/{{ $p->etos }}</span>@endif<div class="small muted">{{ $d($p, 'protocoldate') }}</div></td>
                    <td class="break">
                        {{ $p->in_num }}@if ($p->in_num && $p->in_date)/@endif{{ $d($p, 'in_date') }}
                        {!! $sub('✧', $p->in_topos_ekdosis) !!}{!! $sub('✦', $p->in_arxi_ekdosis) !!}
                    </td>
                    <td class="break">{{ $p->thema }}{!! $sub('✧', $p->in_perilipsi) !!}</td>
                    <td class="break">{{ $p->in_paraliptis }}</td>
                    <td class="break">{{ $d($p, 'out_date') }}{!! $sub('✧', $p->out_to) !!}</td>
                    <td class="break">{{ $p->out_perilipsi }}</td>
                    <td class="break">{{ $handlers[$p->id] ?? '' }}{!! $sub('✧', $d($p, 'diekp_date')) !!}</td>
                    <td class="break">{{ $p->fakelos }}{!! $sub('✧', $p->sxetiko) !!}{!! $sub('✦', $p->paratiriseis) !!}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Δεν υπάρχουν πρωτόκολλα που να ταιριάζουν.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection

@section('foot')
    <span>✧ δεύτερη · ✦ τρίτη τιμή του κελιού · οι αριθμοί των στηλών όπως στο βιβλίο πρωτοκόλλου</span>
    <span id="book-total">Σύνολο: {{ $protocols->count() }} {{ $protocols->count() === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα' }}</span>
@endsection
