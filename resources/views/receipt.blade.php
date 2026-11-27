{{--
    R7: the receipt of one protocol, on the print layout (portrait). R2v (owner, 2026-11-27): restyled as the protocol
    book (R2s): the number and the date large in a box, the details in a clean table (only those filled in), and a place
    for a signature and the stamp on paper.
--}}
@extends('layouts.print')

@section('orientation', 'portrait')
@section('title', 'Απόδειξη καταχώρισης στο Ηλ. Πρωτόκολλο')

@push('print-styles')
    <style>
        .receipt { max-width: 165mm; margin: 10mm auto 0; font-size: 11pt; line-height: 1.45; }
        .receipt-lead { margin: 0 0 6mm; color: #333; }
        .receipt-number { display: grid; grid-template-columns: 1fr 1fr; border: 1.2pt solid #1f4e79; border-radius: 6px; overflow: hidden; margin-bottom: 7mm; }
        .receipt-number > div { padding: 4mm 5mm; text-align: center; }
        .receipt-number > div + div { border-left: .8pt solid #9aa5b1; }
        .receipt-number .label { display: block; font-size: 8.5pt; color: #5b6773; text-transform: uppercase; letter-spacing: .06em; margin-bottom: 1mm; }
        .receipt-number .value { display: block; font-size: 20pt; font-weight: 800; color: #12324f; letter-spacing: .02em; }
        .receipt-number .number { background: #e8eef5; }
        table.receipt-details { width: 100%; border-collapse: collapse; }
        table.receipt-details th, table.receipt-details td { padding: 2.2mm 3mm; vertical-align: top; border-bottom: .6pt solid #d5dbe1; text-align: left; }
        table.receipt-details th { width: 34%; font-weight: 600; color: #5b6773; font-size: 9.5pt; white-space: nowrap; }
        table.receipt-details td { color: #111; overflow-wrap: anywhere; }
        table.receipt-details tr:first-child th, table.receipt-details tr:first-child td { border-top: .6pt solid #d5dbe1; }
        .receipt-subject { font-weight: 700; }
        .receipt-sign { display: grid; grid-template-columns: 1fr 1fr; gap: 12mm; margin-top: 18mm; break-inside: avoid; }
        .receipt-sign > div { text-align: center; font-size: 9.5pt; color: #333; }
        .receipt-sign .line { border-top: .8pt solid #555; margin-top: 18mm; padding-top: 1.5mm; }
        .receipt-sign .stamp { border: .8pt dashed #9aa5b1; border-radius: 6px; height: 30mm; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 2mm; color: #8795a3; font-size: 8.5pt; }
        @media print { .receipt { margin-top: 6mm; } }
    </style>
@endpush

@section('content')
    @php
        $inDate = $protocol->dateOf('in_date')?->format('d/m/Y');
        $incoming = trim(($protocol->in_num ?? '').($protocol->in_num && $inDate ? ' / ' : '').($inDate ?? ''));
        $rows = array_filter([
            'Αρχή έκδοσης' => trim((string) $protocol->in_arxi_ekdosis),
            'Αρ./Ημ. εισερχομένου' => $incoming,
            'Τόπος έκδοσης' => trim((string) $protocol->in_topos_ekdosis),
            'Φάκελος' => trim((string) $protocol->fakelos),
        ], fn ($v) => $v !== '');
    @endphp
    <section class="receipt" id="receipt">
        <p class="receipt-lead">Το έγγραφο καταχωρίστηκε στο Ηλεκτρονικό Πρωτόκολλο με τα παρακάτω στοιχεία.</p>
        <div class="receipt-number">
            <div class="number"><span class="label">Αριθμός πρωτοκόλλου</span><span class="value" id="receipt-number">{{ $protocol->protocolnum }}/{{ $protocol->etos }}</span></div>
            <div><span class="label">Ημερομηνία παραλαβής</span><span class="value" id="receipt-date">{{ $protocol->dateOf('protocoldate')?->format('d/m/Y') }}</span></div>
        </div>
        <table class="receipt-details">
            <tr><th scope="row">Θέμα</th><td class="receipt-subject">{{ $protocol->thema }}</td></tr>
            @foreach ($rows as $label => $value)
                <tr><th scope="row">{{ $label }}</th><td>{{ $value }}</td></tr>
            @endforeach
        </table>
        <div class="receipt-sign">
            <div><div class="line">Ο/Η παραλαβών/ούσα</div></div>
            <div><div class="stamp">Σφραγίδα</div></div>
        </div>
    </section>
@endsection
