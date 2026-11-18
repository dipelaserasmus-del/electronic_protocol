{{-- R7: the receipt of one protocol, on the print layout (portrait) --}}
@extends('layouts.print')

@section('orientation', 'portrait')
@section('title', 'Απόδειξη καταχώρισης στο Ηλ. Πρωτόκολλο')

@section('content')
    <table class="print" id="receipt" style="max-width: 40rem; margin: 16px auto; font-size: 12pt">
        <tr><td style="width: 30%; text-align: right">Αρ. πρωτ.:</td><th style="text-align:center; font-size: 14pt">{{ $protocol->protocolnum }}/{{ $protocol->etos }}</th></tr>
        <tr><td style="text-align: right">Ημ. παραλαβής:</td><th style="text-align:center">{{ $protocol->dateOf('protocoldate')?->format('d/m/Y') }}</th></tr>
        <tr><td style="text-align: right">Θέμα:</td><td class="break">{{ $protocol->thema }}</td></tr>
        @if ($protocol->in_num || $protocol->in_date)
            <tr><td style="text-align: right">Αρ./Ημ. εισερχ.:</td><td>{{ $protocol->in_num }}@if ($protocol->in_num && $protocol->in_date) / @endif{{ $protocol->dateOf('in_date')?->format('d/m/Y') }}</td></tr>
        @endif
    </table>
@endsection
