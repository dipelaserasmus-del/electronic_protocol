{{-- R6c: the expired attachments, to print before moving them to the trash (App\Services\Attachments\Expired) --}}
@extends('layouts.print')

@section('title', 'Εκκαθάριση αρχείου')
@section('heading'){{ $items->count() }} {{ $items->count() === 1 ? 'συνημμένο' : 'συνημμένα' }} με χρόνο διατήρησης που έληξε@endsection
@section('toolbar')<a class="secondary" href="{{ url('/arxeio') }}">Πίσω</a>@endsection

@section('content')
    <table class="print" id="expired-print">
        <thead>
            <tr>
                <th>Φ. / Αριθμός</th>
                <th>Ημ. παραλαβής</th>
                <th>Έληξε</th>
                <th>Αρχείο</th>
                <th>Θέμα</th>
                <th>Αρ./Ημ. εισερχ. ✧ Ημ. εξερχ.</th>
                <th>Περίληψη εισερχ. ✧ εξερχ.</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $a)
                @php $p = $a->protocol; @endphp
                <tr>
                    <td class="nowrap">@if ($p?->fakelos)<span class="small">{{ $p->fakelos }} /</span> @endif<span class="num">{{ $p?->protocolnum }}</span>@if ($p)<span class="small">/{{ $p->etos }}</span>@endif</td>
                    <td class="nowrap">{{ $p?->dateOf('protocoldate')?->format('d/m/Y') }}</td>
                    <td class="nowrap">{{ $a->expiresDate()?->format('d/m/Y') }}</td>
                    <td class="break">{{ $a->name }}@if ($a->name && $a->ada)<br>@endif @if ($a->ada)<span class="small">ΑΔΑ: {{ $a->ada }}</span>@endif</td>
                    <td class="break">{{ $p?->thema }}</td>
                    <td class="small">
                        {{ $p?->in_num }}@if ($p?->in_num && $p?->in_date) / @endif{{ $p?->dateOf('in_date')?->format('d/m/Y') }}
                        @if ($p?->out_date)<br>✧ {{ $p->dateOf('out_date')?->format('d/m/Y') }}@endif
                    </td>
                    <td class="small break">
                        {{ $p?->in_perilipsi }}
                        @if ($p?->out_perilipsi)<br>✧ {{ $p->out_perilipsi }}@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">Κανένα συνημμένο δεν έχει περάσει τον χρόνο διατήρησης.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
