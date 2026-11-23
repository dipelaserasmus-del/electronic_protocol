{{--
    R5m: "waiting for the mail server". $initial = the deferred inbox's placeholder (whole page head + a seconds counter);
    otherwise the notice shown while «Ανανέωση» or a page change waits for the server (wire:loading).
    After a few seconds it explains that the organisation's mail server is often slow (CSS: .inbox-connecting-slow).
--}}
@if ($initial)
<div class="inbox-page" data-inbox-state="connecting">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 me-auto"><i class="bi bi-inbox me-1" aria-hidden="true"></i>Εισερχόμενα email</h1>
    </div>
@endif
    <div class="alert alert-info d-flex gap-2 align-items-start mb-3 inbox-connecting" role="status" id="{{ $initial ? 'inbox-connecting' : 'inbox-reconnecting' }}"
         @if ($initial) x-data="{ s: 0 }" x-init="setInterval(() => s++, 1000)" @endif>
        <span class="spinner-border spinner-border-sm mt-1 flex-shrink-0" aria-hidden="true"></span>
        <div>
            <strong>Σύνδεση με τον διακομιστή email{{ $host !== '' ? " ($host)" : '' }}…</strong>
            @if ($initial)
                <span class="text-body-secondary ms-1" x-show="s > 0" x-text="s + ' δευτ.'"></span>
            @endif
            <div class="small mt-1 inbox-connecting-slow">
                Ο διακομιστής email αργεί να απαντήσει. Συμβαίνει συχνά, κυρίως τις πρωινές ώρες που είναι φορτωμένος: δεν
                είναι πρόβλημα της εφαρμογής. Η σελίδα περιμένει την απάντησή του έως {{ $timeout }} δευτερόλεπτα.
            </div>
        </div>
    </div>
@if ($initial)
</div>
@endif
