{{--
    R5m: "waiting for the mail server". $initial = the deferred inbox's placeholder (page head + seconds counter); otherwise
    the notice shown while «Ανανέωση» or a page change waits for the server (wire:loading).
    Owner (2026-11-23): plain «Παρακαλώ περιμένετε…»; when the web server gives up (504) the banner itself says so
    (event "inbox-unreachable", resources/modern/inbox.js) instead of Livewire's unreadable error popup.
--}}
@if ($initial)
<div class="inbox-page" data-inbox-state="connecting">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 me-auto"><i class="bi bi-inbox me-1" aria-hidden="true"></i>Εισερχόμενα email</h1>
    </div>
@endif
    <div class="alert d-flex gap-2 align-items-start mb-3 inbox-connecting {{ $initial ? '' : 'alert-info' }}" role="status"
         id="{{ $initial ? 'inbox-connecting' : 'inbox-reconnecting' }}"
         @if ($initial) x-data="inboxWait(true)" @inbox-unreachable.window="unreachable()" :class="failed ? 'alert-warning' : 'alert-info'" @endif>
        <span class="spinner-border spinner-border-sm mt-1 flex-shrink-0" aria-hidden="true" @if ($initial) x-show="!failed" @endif></span>
        <div @if ($initial) x-show="!failed" @endif>
            <strong>Σύνδεση με τον διακομιστή email{{ $host !== '' ? " ($host)" : '' }}…</strong>
            <span class="ms-1">Παρακαλώ περιμένετε…</span>
            @if ($initial)
                <span class="text-body-secondary ms-1" x-show="seconds > 0" x-text="seconds + ' δευτ.'"></span>
            @endif
        </div>
        @if ($initial)
            <div x-show="failed" style="display: none" class="inbox-unreachable">
                <strong>Ο διακομιστής email δεν απάντησε εγκαίρως.</strong> Δοκιμάστε ξανά σε λίγο.
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" @click="window.location.reload()">
                        <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Δοκιμή ξανά
                    </button>
                </div>
            </div>
        @endif
    </div>
@if ($initial)
</div>
@endif
