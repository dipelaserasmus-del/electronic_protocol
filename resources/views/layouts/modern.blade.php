{{--
    N03: layout of the rebuilt pages. Bootstrap 5 + Livewire 4 (Alpine included), built by Vite.
    Legacy pages keep layouts/app until their module is rebuilt.
    Sections: title, search (global search box in the top bar, R3c), content.
--}}
@php
    $user = auth()->user();
    $brandName = config('app.name', 'Ηλ. Πρωτόκολλο');
    $institution = \App\Models\Config::getConfigValueOf('ipiresiasName');
    // the colour comes from Ρυθμίσεις: used only if it is a CSS colour (hex or a name), never pasted unchecked
    $topbarColor = (string) \App\Models\Config::getConfigValueOf('titleColor');
    $topbarColor = preg_match('/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,20})$/', $topbarColor) ? $topbarColor : null;
    $notification = session('notification');
@endphp
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@hasSection('title')@yield('title') · @endif{{ $brandName }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
    @vite(['resources/modern/app.scss', 'resources/modern/app.js'])
    @livewireStyles
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary app-topbar sticky-top shadow-sm"
     @if ($topbarColor) style="background-color: {{ $topbarColor }} !important" @endif
     aria-label="Κύριο μενού">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ $user ? \App\Support\Navigation::landing($user) : url('/') }}">
            {{ $brandName }} @if ($institution)<small class="ms-2 d-none d-md-inline">{{ $institution }}</small>@endif
        </a>
        @auth
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#app-nav"
                    aria-controls="app-nav" aria-expanded="false" aria-label="Μενού">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="app-nav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @foreach (\App\Support\Navigation::for($user) as $i => $item)
                        @if (isset($item['children']))
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                   aria-expanded="false" id="nav-{{ $i }}">{{ $item['label'] }}</a>
                                <ul class="dropdown-menu" aria-labelledby="nav-{{ $i }}">
                                    @foreach ($item['children'] as $child)
                                        @if (isset($child['divider']))
                                            <li><hr class="dropdown-divider"></li>
                                        @elseif (isset($child['header']))
                                            <li><h6 class="dropdown-header">{{ $child['header'] }}</h6></li>
                                        @elseif (isset($child['links']))
                                            <li class="d-flex align-items-center pe-2">
                                                <a class="dropdown-item flex-grow-1" href="{{ $child['url'] }}">{{ $child['label'] }}</a>
                                                @foreach ($child['links'] as $short => $url)
                                                    <a class="badge rounded-pill text-bg-light text-decoration-none ms-1" href="{{ $url }}">{{ $short }}</a>
                                                @endforeach
                                            </li>
                                        @else
                                            <li><a class="dropdown-item" href="{{ $child['url'] }}">{{ $child['label'] }}</a></li>
                                        @endif
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link @if (url()->current() === $item['url']) active @endif"
                                   @if (url()->current() === $item['url']) aria-current="page" @endif
                                   href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                            </li>
                        @endif
                    @endforeach
                </ul>

                @hasSection('search')
                    <div class="app-search me-lg-3 my-2 my-lg-0">@yield('search')</div>
                @endif

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                           aria-expanded="false" id="nav-user">
                            {{ $user->name }} <span class="badge text-bg-light ms-1">{{ $user->role?->role }}</span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="nav-user">
                            <li><a class="dropdown-item" href="{{ url('/about') }}">Περί…</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ url('/logout') }}">
                                    @csrf
                                    <button type="submit" class="dropdown-item">Έξοδος</button>
                                </form>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        @endauth
    </div>
</nav>

<main class="container-fluid py-3">
    @if (is_array($notification) && isset($notification['message']))
        @php $type = ['error' => 'danger', 'warning' => 'warning', 'success' => 'success'][$notification['alert-type'] ?? ''] ?? 'info'; @endphp
        <div class="alert alert-{{ $type }} alert-dismissible fade show" role="alert">
            {{-- legacy notifications carry simple HTML (<br>, <strong>, <li>): everything is escaped, then only
                 those bare tags (no attributes) are turned back into HTML --}}
            {!! preg_replace('~&lt;(/?)(br|strong|b|i|em|ul|li)\s*/?&gt;~i', '<$1$2>', e($notification['message'])) !!}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Κλείσιμο"></button>
        </div>
    @endif

    @yield('content')
</main>

@livewireScripts
</body>
</html>
