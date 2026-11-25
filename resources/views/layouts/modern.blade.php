{{--
    N03: layout of the rebuilt pages. Bootstrap 5 + Livewire 4 (Alpine included), built by Vite.
    Legacy pages keep layouts/app until their module is rebuilt.
    Sections: title, content. The top bar has the search box (R3c, search/box.blade.php).
--}}
@php
    $user = auth()->user();
    $brandName = config('app.name', 'Ηλ. Πρωτόκολλο');
    $institution = \App\Models\Config::getConfigValueOf('ipiresiasName');
    // the colour comes from Ρυθμίσεις: used only if it is a CSS colour (hex or a name), never pasted unchecked
    $topbarColor = (string) \App\Models\Config::getConfigValueOf('titleColor');
    $topbarColor = preg_match('/^(#[0-9a-fA-F]{3,8}|[a-zA-Z]{3,20})$/', $topbarColor) ? $topbarColor : null;
    $notification = session('notification');
    // R3d: the user's page width (full window, or narrow and centred), saved with the account
    $narrow = $user && \App\Support\UserPreferences::get($user, 'width') === 'narrow';
    // R3j (owner, 2026-11-25): the narrow page's own width (− / +), in rem; none = the default
    $narrowWidth = $user ? \App\Support\UserPreferences::get($user, 'narrowWidth') : null;
    [$widthMin, $widthMax, $widthStep] = \App\Support\UserPreferences::RANGES['narrowWidth'];
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
<body class="bg-body-tertiary @if ($narrow) layout-narrow @endif @auth has-notepad @endauth"@if ($narrowWidth) style="--narrow-width: {{ (int) $narrowWidth }}rem"@endif>
{{-- R3e (owner, 2026-11-20): the menu stays open from 768px up (it folded under 992px); see app.scss --}}
<nav class="navbar navbar-expand-md navbar-dark bg-primary app-topbar sticky-top shadow-sm"
     @if ($topbarColor) style="background-color: {{ $topbarColor }} !important" @endif
     aria-label="Κύριο μενού">
    <div class="container-fluid">
        {{-- R3d: the institution under the application's name (two short lines instead of one long one) --}}
        <a class="navbar-brand app-brand" data-home-link href="{{ $user ? \App\Support\Navigation::landing($user) : url('/') }}">
            <span class="d-block">{{ $brandName }}</span>
            @if ($institution)<small class="d-block brand-institution" title="{{ $institution }}">{{ $institution }}</small>@endif
        </a>
        @auth
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#app-nav"
                    aria-controls="app-nav" aria-expanded="false" aria-label="Μενού">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="app-nav">
                <ul class="navbar-nav me-auto mb-2 mb-md-0">
                    @foreach (\App\Support\Navigation::for($user) as $i => $item)
                        @if (isset($item['children']))
                            <li class="nav-item dropdown @isset($item['url']) nav-split @endisset">
                                @isset($item['url'])
                                    {{-- R1j: the label leads to the page, the ▾ opens the menu --}}
                                    <a class="nav-link pe-1 @if (url()->current() === $item['url']) active @endif" href="{{ $item['url'] }}"
                                       @if (url()->current() === $item['url']) aria-current="page" @endif id="nav-{{ $i }}-link" @if ($item['home'] ?? false) data-home-link @endif><i class="bi bi-{{ $item['icon'] }} me-1" aria-hidden="true"></i>{{ $item['label'] }}</a>
                                    <a class="nav-link dropdown-toggle dropdown-toggle-split ps-1" href="#" role="button" data-bs-toggle="dropdown"
                                       aria-expanded="false" id="nav-{{ $i }}" aria-label="Περισσότερες: {{ $item['label'] }}" title="Περισσότερες: {{ $item['label'] }}"></a>
                                @else
                                <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                                   aria-expanded="false" id="nav-{{ $i }}"><i class="bi bi-{{ $item['icon'] }} me-1" aria-hidden="true"></i>{{ $item['label'] }}</a>
                                @endisset
                                <ul class="dropdown-menu" aria-labelledby="nav-{{ $i }}">
                                    @foreach ($item['children'] as $child)
                                        @if (isset($child['divider']))
                                            <li><hr class="dropdown-divider"></li>
                                        @elseif (isset($child['header']))
                                            <li><h6 class="dropdown-header">{{ $child['header'] }}</h6></li>
                                        @elseif (isset($child['links']))
                                            <li class="d-flex align-items-center pe-2">
                                                <a class="dropdown-item flex-grow-1" href="{{ $child['url'] }}"><i class="bi bi-person me-2" aria-hidden="true"></i>{{ $child['label'] }}</a>
                                                @foreach ($child['links'] as $short => $url)
                                                    <a class="badge rounded-pill text-bg-light text-decoration-none ms-1" href="{{ $url }}">{{ $short }}</a>
                                                @endforeach
                                            </li>
                                        @else
                                            <li><a class="dropdown-item" href="{{ $child['url'] }}">@if (isset($child['icon']))<i class="bi bi-{{ $child['icon'] }} me-2" aria-hidden="true"></i>@endif{{ $child['label'] }}</a></li>
                                        @endif
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            <li class="nav-item">
                                <a class="nav-link @if (url()->current() === $item['url']) active @endif"
                                   @if (url()->current() === $item['url']) aria-current="page" @endif
                                   href="{{ $item['url'] }}"><i class="bi bi-{{ $item['icon'] }} me-1" aria-hidden="true"></i>{{ $item['label'] }}@isset($item['count'])
                                    {{-- R5f: e-mails waiting in the inbox (resources/modern/inbox-count.js keeps it up to date) --}}
                                    @php $waiting = \App\Services\Mail\InboxCount::cached(); @endphp
                                    <span class="badge rounded-pill text-bg-danger ms-1 inbox-count" data-inbox-count="{{ $item['count'] }}"
                                          @if ($waiting) title="{{ $waiting }} e-mail στα Εισερχόμενα" @else hidden @endif>{{ $waiting > 99 ? '99+' : ($waiting ?: '') }}</span>@endisset</a>
                            </li>
                        @endif
                    @endforeach
                </ul>

                {{-- R3c: the search box, on every page --}}
                <div class="me-md-2 me-xl-3 my-2 my-md-0">@include('search.box')</div>

                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                           aria-expanded="false" id="nav-user" title="{{ $user->name }} ({{ $user->role?->role }})" aria-label="{{ $user->name }} {{ $user->role?->role }}">
                            <i class="bi bi-person-circle me-1" aria-hidden="true"></i><span class="nav-user-name">{{ $user->name }} <span class="badge text-bg-light ms-1">{{ $user->role?->role }}</span></span>
                        </a>
                        {{-- R3g (owner, 2026-11-20): Διαχείριση is here, in groups, beside the user's own choices --}}
                        <div class="dropdown-menu dropdown-menu-end user-menu" aria-labelledby="nav-user">
                            <div class="user-menu-columns">
                                {{-- owner, 2026-11-20: no heading above the groups (the last one is «Διαχείριση»); Προτιμήσεις set apart --}}
                                <div class="user-menu-manage" role="group" aria-label="Διαχείριση">
                                    @foreach (\App\Support\Navigation::manage($user) as $group => $links)
                                        <h6 class="dropdown-header">{{ $group }}</h6>
                                        @foreach ($links as $link)
                                            <a class="dropdown-item" href="{{ $link['url'] }}"><i class="bi bi-{{ $link['icon'] }} me-2" aria-hidden="true"></i>{{ $link['label'] }}</a>
                                        @endforeach
                                    @endforeach
                                </div>
                                <div class="user-menu-own" role="group" aria-labelledby="user-menu-own-title">
                                    <h6 class="dropdown-header text-uppercase fw-bold" id="user-menu-own-title"><i class="bi bi-person me-1" aria-hidden="true"></i>Προτιμήσεις</h6>
                                    {{-- R3d: page width, saved with the account (resources/modern/preferences.js) --}}
                                    <h6 class="dropdown-header">Πλάτος σελίδας</h6>
                                    <button type="button" class="dropdown-item d-flex align-items-center" data-preference="width" data-value="full" data-url="{{ url('/preferences') }}"
                                            aria-pressed="{{ $narrow ? 'false' : 'true' }}"><i class="bi bi-arrows-angle-expand me-2" aria-hidden="true"></i>Όλη η οθόνη
                                        <i class="bi bi-check2 ms-auto ps-3 preference-check" aria-hidden="true"></i></button>
                                    <button type="button" class="dropdown-item d-flex align-items-center" data-preference="width" data-value="narrow" data-url="{{ url('/preferences') }}"
                                            aria-pressed="{{ $narrow ? 'true' : 'false' }}"><i class="bi bi-arrows-angle-contract me-2" aria-hidden="true"></i>Στενό, στο κέντρο
                                        <i class="bi bi-check2 ms-auto ps-3 preference-check" aria-hidden="true"></i></button>
                                    {{-- R3j (owner, 2026-11-25): the narrow page narrower / wider, or back to the default; saved with the account --}}
                                    <div class="width-steps d-flex align-items-center gap-1 px-3 pb-1" role="group" aria-label="Πλάτος της στενής σελίδας"
                                         data-width-steps data-url="{{ url('/preferences') }}" data-min="{{ $widthMin }}" data-max="{{ $widthMax }}" data-step="{{ $widthStep }}">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-width-step="-1" title="Στενότερη" aria-label="Στενότερη σελίδα"><i class="bi bi-dash-lg" aria-hidden="true"></i></button>
                                        <span class="width-steps-value small text-body-secondary text-center flex-grow-1" aria-live="polite"></span>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-width-step="1" title="Φαρδύτερη" aria-label="Φαρδύτερη σελίδα"><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-width-step="0" title="Επαναφορά στο αρχικό πλάτος" aria-label="Επαναφορά στο αρχικό πλάτος"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                                    </div>
                                    {{-- R3e (owner, 2026-11-20): the user's home page (login, the brand link, Λίστα) --}}
                                    <h6 class="dropdown-header">Αρχική σελίδα</h6>
                                    @php $home = \App\Support\Navigation::homeChoice($user); @endphp
                                    @foreach (\App\Support\UserPreferences::HOMES as $key => [$label, $icon, $path])
                                        <button type="button" class="dropdown-item d-flex align-items-center" data-preference="home" data-value="{{ $key }}" data-url="{{ url('/preferences') }}"
                                                data-home="{{ url($path) }}" aria-pressed="{{ $home === $key ? 'true' : 'false' }}"><i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>{{ $label }}
                                            <i class="bi bi-check2 ms-auto ps-3 preference-check" aria-hidden="true"></i></button>
                                    @endforeach
                                    {{-- R5q (owner, 2026-11-25): the inbox's look, each user's own (users who have the inbox) --}}
                                    @if (collect(\App\Support\Navigation::for($user))->contains('label', 'Εισερχόμενα'))
                                        <h6 class="dropdown-header">Εμφάνιση των e-mail</h6>
                                        @php $envelope = \App\Support\UserPreferences::envelope($user); @endphp
                                        @foreach (\App\Support\UserPreferences::ENVELOPES as $key => [$label, $icon])
                                            <button type="button" class="dropdown-item d-flex align-items-center" data-preference="envelope" data-value="{{ $key }}" data-url="{{ url('/preferences') }}"
                                                    aria-pressed="{{ $envelope === $key ? 'true' : 'false' }}"><i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>{{ $label }}
                                                <i class="bi bi-check2 ms-auto ps-3 preference-check" aria-hidden="true"></i></button>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <hr class="dropdown-divider">
                            <div class="d-flex flex-wrap user-menu-end">
                                <a class="dropdown-item w-auto flex-grow-1" href="{{ url('/about') }}"><i class="bi bi-info-circle me-2" aria-hidden="true"></i>Περί…</a>
                                <form method="POST" action="{{ url('/logout') }}" class="flex-grow-1">
                                    @csrf
                                    <button type="submit" class="dropdown-item"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Έξοδος</button>
                                </form>
                            </div>
                        </div>
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

{{-- N04 toasts: window.appToast(message, type, link), session('toast') = [message, type], Livewire dispatch('toast').
     R1e: top centre under the top bar (bottom right covered the save button); a click closes them --}}
@include('partials.preview-modal')   {{-- R4c --}}
@auth @include('partials.notepad') @endauth   {{-- N05 --}}
<div id="app-toasts" class="toast-container position-fixed start-50 translate-middle-x p-3">
    @if (is_array(session('toast')))
        <div hidden data-toast-message="{{ session('toast')[0] ?? '' }}" data-toast-type="{{ session('toast')[1] ?? 'success' }}"></div>
    @endif
</div>

{{-- N04 confirm dialog for any element/form with data-confirm="question" --}}
<div class="modal fade" id="app-confirm" tabindex="-1" aria-labelledby="app-confirm-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="app-confirm-title">Επιβεβαίωση</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
            </div>
            <div class="modal-body" data-confirm-message></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Άκυρο</button>
                <button type="button" class="btn btn-primary rounded-pill" data-confirm-yes>Ναι</button>
            </div>
        </div>
    </div>
</div>

@livewireScripts
</body>
</html>
