{{-- Standalone error layout: must work even when the database or the session is unavailable. --}}
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — Ηλ. Πρωτόκολλο</title>
    <style>
        body { font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif; background: #f5f6f8; color: #212529; margin: 0; }
        .box { max-width: 720px; margin: 8vh auto; background: #fff; border-radius: 8px; padding: 32px 36px;
               box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        h1 { font-size: 1.6rem; margin: 0 0 12px; }
        .code { font-family: ui-monospace, Consolas, monospace; background: #f1f3f5; padding: 2px 8px; border-radius: 4px; font-size: 1.05rem; }
        .muted { color: #6c757d; }
        a.btn { display: inline-block; margin-top: 18px; padding: 8px 18px; background: #337ab7; color: #fff;
                border-radius: 20px; text-decoration: none; }
        details { margin-top: 24px; border-top: 1px solid #dee2e6; padding-top: 12px; }
        summary { cursor: pointer; font-weight: 600; }
        pre { white-space: pre-wrap; word-break: break-word; background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: .85rem; }
    </style>
</head>
<body>
<div class="box">
    @yield('content')
    <a class="btn" href="{{ url('/home/list') }}">Επιστροφή στο Πρωτόκολλο</a>
</div>
</body>
</html>
