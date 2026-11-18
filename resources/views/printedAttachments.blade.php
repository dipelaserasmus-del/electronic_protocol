{{--
    R7: protocols with attachments, on the print layout. Every attachment with its own Διατήρηση and expiry (before:
    the first attachment's, for all); protocols of several years in year order (before: by number only).
--}}
@extends('layouts.print')

@section('title', 'Πρωτόκολλα με συνημμένα '.$range->describe())
@section('heading')
    {{ $protocols->count() }} {{ $protocols->count() === 1 ? 'πρωτόκολλο' : 'πρωτόκολλα' }} με συνημμένα@if ($total > $protocols->count()) <strong>από {{ $total }}: τα υπόλοιπα δεν χωρούν στο όριο των Ρυθμίσεων</strong>@endif
@endsection

@section('content')
    @include('partials.ignoredFiltersNotice')
    @php $d = fn ($p, $c) => $p->dateOf($c)?->format('d/m/Y'); @endphp
    <table class="print" id="attachments-print">
        <thead>
            <tr>
                <th rowspan="2">Αύξ. αριθ.<br>Ημ. παραλ.</th>
                <th colspan="2" style="text-align:center">ΕΙΣΕΡΧΟΜΕΝΑ</th>
                <th colspan="2" style="text-align:center">ΕΞΕΡΧΟΜΕΝΑ</th>
                <th rowspan="2">Φάκελος</th>
                <th colspan="3" style="text-align:center">ΣΥΝΗΜΜΕΝΑ</th>
            </tr>
            <tr>
                <th>Αριθ./Ημ. εισερχ.<br>✧ Τόπος έκδοσης<br>✦ Αρχή έκδοσης</th>
                <th>Θέμα<br>✧ Περίληψη εισερχομένου</th>
                <th>Ημ/νία εξερχ.<br>✧ Απευθύνεται</th>
                <th>Περίληψη εξερχομένου</th>
                <th>Όνομα αρχείου ή ΑΔΑ</th>
                <th>Διατήρηση</th>
                <th>Έως</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($protocols as $p)
                @php $files = $p->attachments; $n = max(1, $files->count()); @endphp
                @foreach ($files as $i => $a)
                    <tr class="attachment-print-row">
                        @if ($loop->first)
                            <td rowspan="{{ $n }}" class="nowrap"><span class="num">{{ $p->protocolnum }}</span>@if ($range->year === null)<span class="small">/{{ $p->etos }}</span>@endif<br><span class="small">{{ $d($p, 'protocoldate') }}</span></td>
                            <td rowspan="{{ $n }}" class="break">
                                {{ $p->in_num }}@if ($p->in_num && $p->in_date)/@endif{{ $d($p, 'in_date') }}
                                @if ($p->in_topos_ekdosis)<br>✧ {{ $p->in_topos_ekdosis }}@endif
                                @if ($p->in_arxi_ekdosis)<br>✦ {{ $p->in_arxi_ekdosis }}@endif
                            </td>
                            <td rowspan="{{ $n }}" class="break">{{ $p->thema }}@if ($p->in_perilipsi)<br>✧ {{ $p->in_perilipsi }}@endif</td>
                            <td rowspan="{{ $n }}" class="break">{{ $d($p, 'out_date') }}@if ($p->out_to)@if ($p->out_date)<br>@endif✧ {{ $p->out_to }}@endif</td>
                            <td rowspan="{{ $n }}" class="break">{{ $p->out_perilipsi }}</td>
                            <td rowspan="{{ $n }}" class="nowrap">{{ $p->fakelos }}</td>
                        @endif
                        <td class="break">{{ $a->name }}@if ($a->name && $a->ada)<br>@endif @if ($a->ada)<span class="small">ΑΔΑ: {{ $a->ada }}</span>@endif</td>
                        <td class="nowrap">@if (is_numeric($a->keep)){{ $a->keep }} {{ (int) $a->keep === 1 ? 'έτος' : 'έτη' }}@else{{ $a->keep }}@endif</td>
                        <td class="nowrap">{{ $a->expiresDate()?->format('d/m/Y') }}</td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="9" class="empty">Δεν υπάρχουν πρωτόκολλα με συνημμένα που να ταιριάζουν.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
