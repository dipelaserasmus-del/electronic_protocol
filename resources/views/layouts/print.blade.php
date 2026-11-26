{{--
    R6c/R7: layout of the printed lists (no top bar, no scripts). A4 landscape by default; table headers repeat on
    every page; rows are not split. Sections: title (the list's name), heading (optional, under the title), content.
    $printedAt is shown at the top right ("Εκτυπώθηκε"); 'foot' (optional) under the content. R2s: restyled.
--}}
@php
    $institution = \App\Models\Config::getConfigValueOf('ipiresiasName');
    $printedAt = now()->format('d/m/Y H:i');
    // R2s: text for a CSS string (the page's foot): entities are not decoded inside <style>, so escape as CSS does
    $cssText = fn (string $t) => str_replace(['\\', '"', "\n", "\r", '<', '>'], ['\\\\', '\\"', ' ', ' ', '\\3c ', '\\3e '], $t);
    $footText = implode(' · ', array_filter([trim((string) $institution), trim(html_entity_decode(strip_tags($__env->yieldContent('title')), ENT_QUOTES))]));
@endphp
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $institution ?: config('app.name') }}</title>
    <style>
        /* R2s (owner, 2026-11-26): a cleaner printout. On screen: a sheet on a grey desk, the buttons above it. On paper:
           A4, thin lines, the headings repeated on every page, rows never split, page numbers at the foot (browsers
           that support @page boxes: Chrome, Edge). */
        @page {
            size: A4 @yield('orientation', 'landscape');
            margin: 11mm 9mm 13mm;
            @bottom-left { content: "{!! $cssText($footText) !!}"; font: 7.5pt "Nunito", "Segoe UI", Arial, sans-serif; color: #555; }
            @bottom-right { content: "Σελίδα " counter(page) " από " counter(pages); font: 7.5pt "Nunito", "Segoe UI", Arial, sans-serif; color: #555; }
        }
        * { box-sizing: border-box; }
        html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { font-family: "Nunito", "Segoe UI", Arial, sans-serif; font-size: 9pt; line-height: 1.3; color: #111; background: #e9ecef; margin: 0; }
        .sheet { background: #fff; margin: 0 auto 32px; padding: 14mm 10mm 12mm; max-width: 297mm; box-shadow: 0 2px 12px rgba(0, 0, 0, .15); border-radius: 4px; }
        .sheet.portrait { max-width: 210mm; }
        .print-head { display: grid; grid-template-columns: 1fr auto 1fr; align-items: end; gap: 16px; padding-bottom: 6px; border-bottom: 2px solid #1f4e79; }
        .print-head .institution { font-weight: 700; font-size: 10pt; color: #1f4e79; }
        .print-head h1 { font-size: 15pt; margin: 0; text-align: center; letter-spacing: .01em; }
        .print-head .meta { font-size: 8pt; color: #555; text-align: right; }
        .print-sub { text-align: center; font-size: 9pt; color: #333; margin: 6px 0 10px; }
        .print-sub strong { color: #a61b1b; }
        table.print { width: 100%; border-collapse: collapse; }
        #protocol-book { table-layout: fixed; }   /* its own column widths (colgroup) */
        table.print th, table.print td { border: .6pt solid #9aa5b1; padding: 3px 5px; vertical-align: top; text-align: left; }
        table.print thead th { background: #e8eef5; color: #12324f; font-size: 8pt; font-weight: 700; line-height: 1.25; }
        table.print thead tr.groups th { background: #1f4e79; color: #fff; text-align: center; letter-spacing: .08em; font-size: 7.5pt; border-color: #1f4e79; }
        table.print thead tr.groups th.span-head { background: #e8eef5; color: #12324f; text-align: left; letter-spacing: 0; font-size: 8pt; border-color: #9aa5b1; }
        table.print thead tr.cols th { background: #f4f6f9; color: #5b6773; font-weight: 600; font-size: 7pt; padding: 1px 5px; }
        table.print td { font-size: 8.5pt; }
        table.print tbody tr:nth-child(even) td { background: #f7f9fb; }
        table.print thead { display: table-header-group; }
        table.print tfoot { display: table-footer-group; }
        table.print tr { break-inside: avoid; page-break-inside: avoid; }
        .nowrap { white-space: nowrap; }
        .num { font-weight: 800; font-size: 10.5pt; color: #12324f; }
        .small { font-size: 7.5pt; }
        .muted { color: #555; }
        .break { overflow-wrap: anywhere; }
        .sub { color: #444; font-size: 8pt; margin-top: 1px; }
        .sub .mk { color: #8795a3; margin-right: 2px; }
        .print-foot { margin-top: 8px; display: flex; justify-content: space-between; font-size: 8pt; color: #555; }
        .toolbar { position: sticky; top: 0; z-index: 1; display: flex; gap: 8px; align-items: center; justify-content: center; padding: 10px 16px; margin-bottom: 16px;
            background: rgba(233, 236, 239, .92); backdrop-filter: blur(4px); border-bottom: 1px solid #d5dbe1; }
        .toolbar .hint { font-size: 8.5pt; color: #555; margin-left: 8px; }
        .toolbar button, .toolbar a { font: inherit; font-size: 10pt; padding: 5px 16px; border-radius: 999px; border: 1px solid #1f4e79; background: #1f4e79; color: #fff; text-decoration: none; cursor: pointer; }
        .toolbar a.secondary, .toolbar button.secondary { background: #fff; color: #1f4e79; }
        .empty { padding: 24px; text-align: center; color: #444; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()" id="print-now">Εκτύπωση</button>
        @yield('toolbar')
        <span class="hint">A4 {{ $__env->yieldContent('orientation', 'landscape') === 'portrait' ? 'όρθια' : 'οριζόντια' }}</span>
    </div>
    <main class="sheet {{ $__env->yieldContent('orientation', 'landscape') }}">
        <header class="print-head">
            <span class="institution">{{ $institution }}</span>
            <h1>@yield('title')</h1>
            <span class="meta">Εκτυπώθηκε: {{ $printedAt }}</span>
        </header>
        @hasSection('heading')<div class="print-sub">@yield('heading')</div>@endif
        @yield('content')
        @hasSection('foot')<footer class="print-foot">@yield('foot')</footer>@endif
    </main>
</body>
</html>
