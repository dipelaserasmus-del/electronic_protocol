{{--
    R5m: "waiting for the mail server".
    $initial: the page's first connection (EmailInbox::connect): seconds counter; when the web server gives up (504) or
    nothing comes back, it turns into a plain message with «Δοκιμή ξανά» (event "inbox-unreachable", inbox.js), never
    Livewire's error popup (owner, 2026-11-23). R5n: with $staleAt, the e-mails below are those of the last check.
    Otherwise: the notice while «Ανανέωση» or a page change waits (wire:loading).
--}}
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
        @if (! empty($staleAt))
            <div class="small mt-1" id="inbox-stale-note">Μέχρι τότε βλέπετε τα email της τελευταίας ανανέωσης ({{ $staleAt }}).</div>
        @endif
    </div>
    @if ($initial)
        <div x-show="failed" style="display: none" class="inbox-unreachable">
            <strong>Ο διακομιστής email δεν απάντησε εγκαίρως.</strong> Δοκιμάστε ξανά σε λίγο.
            @if (! empty($staleAt))
                <div class="small mt-1">Τα email που βλέπετε είναι της τελευταίας ανανέωσης ({{ $staleAt }}).</div>
            @endif
            <div class="mt-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" @click="retry(() => $wire.connect())">
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Δοκιμή ξανά
                </button>
            </div>
        </div>
    @endif
</div>
