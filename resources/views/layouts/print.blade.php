{{--
    R6c/R7: layout of the printed lists (no top bar, no scripts). A4 landscape by default; table headers repeat on
    every page; rows are not split. Sections: title (the list's name), heading (optional, under the title), content.
    $printedAt is shown at the bottom ("Εκτυπώθηκε").
--}}
@php
    $institution = \App\Models\Config::getConfigValueOf('ipiresiasName');
    $printedAt = now()->format('d/m/Y H:i');
@endphp
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $institution ?: config('app.name') }}</title>
    <style>
        @page { size: A4 @yield('orientation', 'landscape'); margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: "Nunito", "Segoe UI", Arial, sans-serif; font-size: 10pt; color: #000; background: #fff; margin: 0; padding: 16px; }
        .print-head { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; margin-bottom: 8px; }
        .print-head h1 { font-size: 13pt; margin: 0; text-align: center; flex: 1; }
        .print-head .institution { font-weight: 600; }
        .print-head .meta { font-size: 9pt; color: #333; text-align: right; }
        .print-sub { text-align: center; font-size: 9.5pt; margin: -2px 0 8px; }
        table.print { width: 100%; border-collapse: collapse; }
        table.print th, table.print td { border: 1px solid #000; padding: 2px 4px; vertical-align: top; text-align: left; }
        table.print th { background: #eee; font-size: 9pt; }
        table.print td { font-size: 9pt; }
        table.print thead { display: table-header-group; }
        table.print tfoot { display: table-footer-group; }
        table.print tr { page-break-inside: avoid; }
        .nowrap { white-space: nowrap; }
        .num { font-weight: 700; }
        .small { font-size: 8pt; }
        .muted { color: #444; }
        .break { overflow-wrap: anywhere; }
        .toolbar { margin-bottom: 12px; display: flex; gap: 8px; align-items: center; font-family: inherit; }
        .toolbar button, .toolbar a { font: inherit; padding: 4px 14px; border-radius: 999px; border: 1px solid #1f4e79; background: #1f4e79; color: #fff; text-decoration: none; cursor: pointer; }
        .toolbar a.secondary, .toolbar button.secondary { background: #fff; color: #1f4e79; }
        .empty { padding: 24px; text-align: center; color: #444; }
        @media print { body { padding: 0; } .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()" id="print-now">Εκτύπωση</button>
        @yield('toolbar')
    </div>
    <div class="print-head">
        <span class="institution">{{ $institution }}</span>
        <h1>@yield('title')</h1>
        <span class="meta">Εκτυπώθηκε: {{ $printedAt }}</span>
    </div>
    @hasSection('heading')<div class="print-sub">@yield('heading')</div>@endif
    @yield('content')
</body>
</html>
