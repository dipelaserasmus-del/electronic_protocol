{{--
    R7: the protocol book, on the print layout. Same 13 numbered columns as the legacy printout (and the Excel file,
    printedXls): ✧ and ✦ mark the second and third value of a cell. Dates as dates (D04), handlers by name, also
    inactive users (before: their id).
--}}
@extends('layouts.print')

@section('title', 'Πρωτόκολλο '.$range->describe())
@section('heading')
    {{ $protocols->count() }} {{ $protocols->count() === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα' }}@if ($total > $protocols->count()) <strong>από {{ $total }}: τα υπόλοιπα δεν χωρούν στο όριο των Ρυθμίσεων</strong>@endif
@endsection
@section('toolbar')<a class="secondary" href="{{ url('/printed').'?'.http_build_query($range->params() + ['target' => 'xls']) }}">Excel</a>@endsection

@section('content')
    @include('partials.ignoredFiltersNotice')
    @php $d = fn ($p, $c) => $p->dateOf($c)?->format('d/m/Y'); @endphp
    <table class="print" id="protocol-book">
        <thead>
            <tr>
                <th rowspan="2">Αύξ. αριθ.<br>Ημ. παραλ.</th>
                <th colspan="3" style="text-align:center">ΕΙΣΕΡΧΟΜΕΝΑ</th>
                <th colspan="2" style="text-align:center">ΕΞΕΡΧΟΜΕΝΑ</th>
                <th style="text-align:center">ΔΙΕΚΠΕΡΑΙΩΣΗ</th>
                <th rowspan="2">Φάκελος<br>✧ Σχετ. αριθμοί<br>✦ Παρατηρήσεις</th>
            </tr>
            <tr>
                <th>Αριθ./Ημ. εισερχ.<br>✧ Τόπος έκδοσης<br>✦ Αρχή έκδοσης</th>
                <th>Θέμα<br>✧ Περίληψη εισερχομένου</th>
                <th>Παραλήπτης</th>
                <th>Ημ/νία εξερχ.<br>✧ Απευθύνεται</th>
                <th>Περίληψη εξερχομένου</th>
                <th>Διεκπεραίωση<br>✧ Ημ/νία διεκπ.</th>
            </tr>
            <tr class="small">
                <th>1, 2</th><th>3, 4, 5</th><th>6</th><th>7</th><th>10, 8</th><th>9</th><th>11</th><th>13, 12</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($protocols as $p)
                <tr class="book-row">
                    <td class="nowrap"><span class="num">{{ $p->protocolnum }}</span>@if ($range->year === null)<span class="small">/{{ $p->etos }}</span>@endif<br><span class="small">{{ $d($p, 'protocoldate') }}</span></td>
                    <td class="break">
                        {{ $p->in_num }}@if ($p->in_num && $p->in_date)/@endif{{ $d($p, 'in_date') }}
                        @if ($p->in_topos_ekdosis)<br>✧ {{ $p->in_topos_ekdosis }}@endif
                        @if ($p->in_arxi_ekdosis)<br>✦ {{ $p->in_arxi_ekdosis }}@endif
                    </td>
                    <td class="break">{{ $p->thema }}@if ($p->in_perilipsi)<br>✧ {{ $p->in_perilipsi }}@endif</td>
                    <td class="break">{{ $p->in_paraliptis }}</td>
                    <td class="break">{{ $d($p, 'out_date') }}@if ($p->out_to)@if ($p->out_date)<br>@endif✧ {{ $p->out_to }}@endif</td>
                    <td class="break">{{ $p->out_perilipsi }}</td>
                    <td class="break">{{ $handlers[$p->id] ?? '' }}@if ($p->diekp_date)<br>✧ {{ $d($p, 'diekp_date') }}@endif</td>
                    <td class="break">
                        {{ $p->fakelos }}
                        @if ($p->sxetiko)<br>✧ {{ $p->sxetiko }}@endif
                        @if ($p->paratiriseis)<br>✦ {{ $p->paratiriseis }}@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Δεν υπάρχουν πρωτόκολλα που να ταιριάζουν.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
