{{--
    R7: the protocol book for Excel (App\Exports\ProtocolExport: the first 4 rows are the header, A–H). Same columns
    as the printout (printed.blade.php); dates as dates, handlers by name.
--}}
@php $d = fn ($p, $c) => $p->dateOf($c)?->format('d/m/Y'); @endphp
<table>
    <thead>
        <tr>
            <th colspan="2">{{ \App\Models\Config::getConfigValueOf('ipiresiasName') }}</th>
            <th colspan="6">ΠΡΩΤΟΚΟΛΛΟ {{ mb_strtoupper($range->describe()) }}</th>
        </tr>
        <tr>
            <th rowspan="2">Αύξ.Αριθ.<br>Ημ.Παραλ.</th>
            <th colspan="3">ΕΙΣΕΡΧΟΜΕΝΑ</th>
            <th colspan="2">ΕΞΕΡΧΟΜΕΝΑ</th>
            <th>ΔΙΕΚΠΕΡΑΙΩΣΗ</th>
            <th rowspan="2">Φάκελος<br>✧Σχετ.αριθμοί<br>✦Παρατηρήσεις</th>
        </tr>
        <tr>
            <th>Αριθ/Ημ.Εισερχ.<br>✧Τόπος έκδοσης<br>✦Αρχή Έκδοσης</th>
            <th>Θέμα<br>✧Περίληψη Εισερχομένου</th>
            <th>Παραλήπτης</th>
            <th>Ημνια Εξερχ.<br>✧Απευθύνεται</th>
            <th>Περίληψη Εξερχόμενου</th>
            <th>Διεκπεραίωση<br>✧Ημνια Διεκπ.</th>
        </tr>
        <tr>
            <th>1, 2</th><th>3, 4, 5</th><th>6</th><th>7</th><th>10, 8</th><th>9</th><th>11</th><th>13, 12</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($protocols as $p)
            <tr>
                <td><strong>{{ $p->protocolnum }}</strong>@if ($range->year === null)/{{ $p->etos }}@endif<br>{{ $d($p, 'protocoldate') }}</td>
                <td>{{ $p->in_num }}@if ($p->in_num && $p->in_date)/@endif{{ $d($p, 'in_date') }}@if ($p->in_topos_ekdosis)<br>✧{{ $p->in_topos_ekdosis }}@endif @if ($p->in_arxi_ekdosis)<br>✦{{ $p->in_arxi_ekdosis }}@endif</td>
                <td>{{ $p->thema }}@if ($p->in_perilipsi)<br>✧{{ $p->in_perilipsi }}@endif</td>
                <td>{{ $p->in_paraliptis }}</td>
                <td>{{ $d($p, 'out_date') }}@if ($p->out_to)<br>✧{{ $p->out_to }}@endif</td>
                <td>{{ $p->out_perilipsi }}</td>
                <td>{{ $handlers[$p->id] ?? '' }}@if ($p->diekp_date)<br>✧{{ $d($p, 'diekp_date') }}@endif</td>
                <td>{{ $p->fakelos }}@if ($p->sxetiko)<br>✧{{ $p->sxetiko }}@endif @if ($p->paratiriseis)<br>✦{{ $p->paratiriseis }}@endif</td>
            </tr>
        @endforeach
    </tbody>
</table>
