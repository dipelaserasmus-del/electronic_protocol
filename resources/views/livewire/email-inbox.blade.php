{{-- R5c: "Εισερχόμενα". Rules: App\Livewire\EmailInbox → App\Services\Mail\EmailRegistration → ProtocolWriter. --}}
@php
    $first = ($page - 1) * $perPage + 1;
    $last = min($total, $page * $perPage);
    $date = fn (?string $iso, string $format = 'd/m/Y H:i') => $iso ? (new \DateTimeImmutable($iso))->setTimezone($zone)->format($format) : '';
    $err = fn (int $uid, string $f) => $errors->first("forms.$uid.$f") ?: null;
@endphp
<div class="inbox-page" x-data="inboxPage" data-inbox-state="{{ $connecting ? 'connecting' : ($failure ? 'failed' : 'ready') }}"
     @if ($connecting) wire:init="connect" @endif>
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 me-auto"><i class="bi bi-inbox me-1" aria-hidden="true"></i>Εισερχόμενα email</h1>
        @if ($total > 0)
            <span class="small text-body-secondary" id="inbox-count">
                @if ($total > $perPage) {{ $first }}–{{ $last }} από {{ $total }} @else {{ $total }} {{ $total === 1 ? 'email' : 'email' }} @endif
                · {{ $newestFirst ? 'νεότερα πρώτα' : 'παλαιότερα πρώτα' }}
            </span>
        @endif
        {{-- R5m (owner, 2026-11-23): when the e-mails last came from the mail server --}}
        @if ($checkedAt)
            <span class="small text-body-secondary" id="inbox-checked-at" title="Τελευταία φορά που ήρθαν τα email από τον διακομιστή">Τελευταία ανανέωση: {{ $checkedAt }}</span>
        @endif
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" wire:click="refresh" wire:loading.attr="disabled" title="Νέα email από τον διακομιστή">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Ανανέωση
        </button>
    </div>

    {{-- R5n: the first connection to the mail server, after the page is shown (EmailInbox::connect) --}}
    @if ($connecting)
        @include('livewire.partials.inbox-connecting', ['initial' => true, 'staleAt' => $stale ? $checkedAt : null])
    @endif

    {{-- R5m: «Ανανέωση» / a page change that the web server gave up on (504) or that got no answer --}}
    @unless ($connecting)
    <div x-data="{ failed: false }" @inbox-unreachable.window="failed = true" x-show="failed" style="display: none"
         class="alert alert-warning mb-3" role="alert" id="inbox-unreachable">
        <strong>Ο διακομιστής email δεν απάντησε εγκαίρως.</strong> Δοκιμάστε ξανά σε λίγο.
        <div class="mt-2">
            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" @click="failed = false; $wire.refresh()">
                <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Δοκιμή ξανά
            </button>
        </div>
    </div>

    @endunless

    {{-- R5m: while «Ανανέωση» / a page change waits for the mail server --}}
    <div wire:loading.block wire:target="refresh,gotoPage,previousPage,nextPage">
        @include('livewire.partials.inbox-connecting', ['initial' => false])
    </div>

    {{-- R5g (owner, 2026-11-20): the list's pager above the e-mails too (the only one was below the last e-mail) --}}
    @if (! $failure && $total > $perPage)
        <x-list-pager :paginator="$paginator" position="top" class="mb-3 justify-content-end" />
    @endif

    @if ($failure)
        {{-- R5m: what happened, in words a user can act on; the server's delay is not the application's fault --}}
        <div class="alert {{ $failureKind === 'slow' ? 'alert-warning' : 'alert-danger' }} mb-3" role="alert" id="inbox-failure" data-kind="{{ $failureKind }}">
            @if ($failureKind === 'slow')
                <strong>Ο διακομιστής email{{ $host !== '' ? " ($host)" : '' }} δεν απάντησε μέσα σε {{ max($failureSeconds, 1) }} δευτερόλεπτα.</strong>
                Δοκιμάστε ξανά σε λίγο.
            @elseif ($failureKind === 'login')
                <strong>Ο διακομιστής email δεν δέχτηκε τη σύνδεση του λογαριασμού.</strong>
                Το όνομα χρήστη ή ο κωδικός δεν έγινε δεκτός: ενημερώστε τον διαχειριστή (Ρυθμίσεις → E-mail).
            @else
                <strong>Η σύνδεση με τον λογαριασμό email απέτυχε.</strong> Ελέγξτε τις ρυθμίσεις ή δοκιμάστε ξανά σε λίγο.
            @endif
            @if ($stale && $uids !== [])
                <div class="small mt-1" id="inbox-stale-failed">Τα email που βλέπετε είναι της τελευταίας ανανέωσης{{ $checkedAt ? " ($checkedAt)" : '' }}: μπορεί να μην είναι τα τρέχοντα.</div>
            @endif
            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" id="inbox-retry" wire:click="refresh" wire:loading.attr="disabled">
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Δοκιμή ξανά
                </button>
                <span class="small text-body-secondary">Κωδικός σφάλματος: {{ $failure }}</span>
            </div>
        </div>
    @endif

    {{-- R5n: after a failure, the e-mails already shown (last check) stay below the message --}}
    @if (! $failure && $total === 0 && ! $connecting)
        <x-notice type="info" id="inbox-empty">Δεν υπάρχουν εισερχόμενα email ({{ $account }}).</x-notice>
    @elseif ($uids !== [])
        <div class="row g-3">
            {{-- the page's messages: a click scrolls to the message --}}
            <aside class="col-lg-3 d-none d-lg-block">
                <nav class="inbox-sidebar list-group shadow-sm" aria-label="Email της σελίδας" id="inbox-sidebar">
                    {{-- R5g: the list on the left is one page of the inbox: which, and the way to the others --}}
                    @if ($lastPage > 1)
                        <div class="list-group-item d-flex align-items-center gap-1 py-1 bg-body-tertiary inbox-sidebar-pager">
                            <button type="button" class="btn btn-sm btn-link px-1" wire:click="gotoPage({{ $page - 1 }})" @disabled($page <= 1)
                                    x-on:click="window.scrollTo({ top: 0 })" aria-label="Προηγούμενη σελίδα" data-pager="side-previous">‹</button>
                            <span class="small flex-grow-1 text-center">Σελίδα {{ $page }} από {{ $lastPage }}
                                <span class="text-body-secondary">· {{ $first }}–{{ $last }} από {{ $total }}</span></span>
                            <button type="button" class="btn btn-sm btn-link px-1" wire:click="gotoPage({{ $page + 1 }})" @disabled($page >= $lastPage)
                                    x-on:click="window.scrollTo({ top: 0 })" aria-label="Επόμενη σελίδα" data-pager="side-next">›</button>
                        </div>
                    @endif
                    @foreach ($messages as $uid => $m)
                        <a href="#msg-{{ $uid }}" class="list-group-item list-group-item-action py-2 inbox-sidebar-item @if (isset($done[$uid])) is-done @endif"
                           wire:key="side-{{ $uid }}" x-on:click.prevent="go({{ $uid }})" :class="{ active: current === {{ $uid }} }">
                            {{-- R5o (owner, 2026-11-23): place in the inbox, as in the e-mail's header; the result (✓ number) under the time --}}
                            <div class="d-flex gap-2">
                                <div class="flex-grow-1" style="min-width: 0">
                                    <div class="d-flex gap-1 align-items-baseline">
                                        <span class="badge rounded-pill border text-reset fw-normal inbox-sidebar-n">{{ $first + $loop->index }} από {{ $total }}</span>
                                        <span class="fw-semibold text-truncate flex-grow-1">{{ $m['from_name'] ?: $m['from_email'] }}</span>
                                    </div>
                                    <div class="small text-truncate">
                                        @if ($m['attachments'])<i class="bi bi-paperclip" aria-label="με συνημμένα"></i>@endif
                                        {{ $m['subject'] !== '' ? $m['subject'] : '(χωρίς θέμα)' }}
                                    </div>
                                </div>
                                <div class="text-end flex-shrink-0 inbox-sidebar-when">
                                    <small class="d-block text-body-secondary text-nowrap">{{ $date($m['date'], 'd/m H:i') }}</small>
                                    @if (($done[$uid]['type'] ?? null) === 'registered')
                                        <span class="badge text-bg-success inbox-sidebar-done"><i class="bi bi-check-lg" aria-hidden="true"></i> {{ $done[$uid]['label'] }}</span>
                                    @elseif (($done[$uid]['type'] ?? null) === 'read')
                                        <span class="badge text-bg-secondary inbox-sidebar-done">αναγνωσμένο</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="col-lg-9">
                {{-- R5q: the look in the key: another look replaces the whole list (Livewire would mix old and new e-mails) --}}
                <div wire:key="inbox-look-{{ $envelope }}">
                @foreach ($messages as $uid => $m)
                    @php $f = $forms[$uid] ?? []; $state = $done[$uid] ?? null; $n = $first + $loop->index; $x = $extra[$uid] ?? null; @endphp
                    {{-- R5p (owner, 2026-11-23): each e-mail in an envelope: the sender and the date as the address strip,
                         «N από M» kept; a gap between envelopes. R5q (owner, 2026-11-25): the user's own look; «none» = R5o's
                         thin line with ↓ N από M ↓ between two e-mails, no envelope --}}
                    @if ($envelope === 'none')
                        @unless ($loop->first)
                            <div class="inbox-sep" role="separator" wire:key="sep-{{ $uid }}">
                                <span class="inbox-sep-label"><i class="bi bi-arrow-down" aria-hidden="true"></i> {{ $n }} από {{ $total }} <i class="bi bi-arrow-down" aria-hidden="true"></i></span>
                            </div>
                        @endunless
                        <div class="inbox-noenvelope" wire:key="env-{{ $uid }}">
                    @else
                    <div class="inbox-envelope inbox-envelope-{{ $envelope }}" wire:key="env-{{ $uid }}">
                    <div class="inbox-envelope-address" aria-hidden="true">
                        <span class="inbox-envelope-n">{{ $n }} από {{ $total }}</span>
                        <span class="inbox-envelope-from text-truncate">{{ $m['from_name'] ?: ($m['from_email'] ?: '—') }}@if ($m['from_name'] && $m['from_email']) <small>&lt;{{ $m['from_email'] }}&gt;</small>@endif</span>
                        <span class="inbox-envelope-date text-nowrap">{{ $date($m['date'], 'd/m/Y H:i') }}</span>
                    </div>
                    @endif
                    <article class="card shadow-sm inbox-message @if ($state) is-done @endif" id="msg-{{ $uid }}" wire:key="msg-{{ $uid }}" tabindex="-1"
                             data-uid="{{ $uid }}" x-intersect.margin.-40%.0.-55%.0="current = {{ $uid }}" x-data="{ shown: false }" :class="{ 'mail-shown': shown }">
                        {{-- R5o (owner, 2026-11-23): lavender like the e-mail below, so the e-mails stand apart (done: green) --}}
                        <header class="card-header d-flex flex-wrap gap-2 align-items-baseline inbox-message-head">
                            <span class="badge text-bg-primary">{{ $n }} από {{ $total }}</span>
                            <h2 class="h6 mb-0 flex-grow-1 text-break inbox-subject">{{ $m['subject'] !== '' ? $m['subject'] : '(χωρίς θέμα)' }}</h2>
                            <small class="text-body-secondary text-nowrap">{{ $date($m['date'], 'd/m/Y H:i:s') }}</small>
                        </header>

                        @if ($state)
                            <div class="card-body">
                                @if ($state['type'] === 'registered')
                                    <x-notice type="{{ $state['warnings'] ? 'warning' : 'success' }}" class="mb-0 inbox-done">
                                        Καταχωρίστηκε με αριθμό <strong>{{ $state['label'] }}</strong>
                                        @if (\App\Models\TrashedProtocol::where('protocol_id', $state['protocol_id'])->exists())
                                            {{-- R4h: deleted since: it is in the trash --}}
                                            <span class="badge text-bg-secondary ms-1 inbox-in-trash">το πρωτόκολλο είναι στον κάδο</span>
                                        @else
                                            <a href="{{ url('/home/'.$state['protocol_id']) }}" target="_blank" class="ms-1">Άνοιγμα πρωτοκόλλου ↗</a>
                                        @endif
                                        @if ($state['messages'] || $state['warnings'])
                                            <ul class="mb-0 ps-3">
                                                @foreach ($state['messages'] as $line)<li>{{ $line }}</li>@endforeach
                                                @foreach ($state['warnings'] as $line)<li><strong>{{ $line }}</strong></li>@endforeach
                                            </ul>
                                        @endif
                                    </x-notice>
                                @else
                                    <x-notice type="info" class="mb-0 inbox-done">Μεταφέρθηκε στα αναγνωσμένα, χωρίς πρωτόκολλο.
                                        @foreach ($state['messages'] ?? [] as $line)<div>{{ $line }}</div>@endforeach
                                    </x-notice>
                                @endif
                                {{-- R5l (owner, 2026-11-20): done: the e-mail folds; open it again, or go on to the next one --}}
                                @php
                                    $keys = array_keys($messages);
                                    $next = collect(array_slice($keys, array_search($uid, $keys, true) + 1))->first(fn ($k) => ! isset($done[$k]));
                                @endphp
                                <div class="d-flex flex-wrap gap-2 mt-2 inbox-done-actions">
                                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" x-on:click="shown = ! shown" :aria-expanded="shown.toString()"
                                            aria-expanded="false" aria-controls="mail-{{ $uid }}" data-action="show-mail">
                                        <span x-show="! shown">Εμφάνιση email ▾</span><span x-show="shown" x-cloak>Απόκρυψη ▴</span></button>
                                    @if ($next)
                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" x-on:click="go({{ $next }})" data-action="next-mail">
                                            Επόμενο email ↓</button>
                                    @endif
                                </div>
                            </div>
                        @else
                            <form class="card-body inbox-form" wire:submit="register({{ $uid }}, {{ $receiptOnly ? 'true' : 'false' }})" novalidate>
                                @if ($err($uid, 'form'))<x-notice type="error" class="inbox-form-error">{{ $err($uid, 'form') }}</x-notice>@endif
                                <div class="row g-2">
                                    {{-- folder (R1f picker): R5s (owner, 2026-11-25) required for every e-mail, so no «— Χωρίς φάκελο» here --}}
                                    <div class="col-md-4 position-relative" x-data="folderPicker({ model: 'forms.{{ $uid }}.fakelos', choices: [] })" x-on:click.outside="close()">
                                        <label for="f-{{ $uid }}-fakelos" class="form-label small fw-semibold mb-1">Φάκελος</label>
                                        <input id="f-{{ $uid }}-fakelos" class="form-control folder-input @if ($err($uid, 'fakelos')) is-invalid @endif" autocomplete="off" data-combobox
                                               role="combobox" aria-autocomplete="list" aria-controls="folder-list-{{ $uid }}" :aria-expanded="open"
                                               :value="open ? query : label()" :placeholder="open ? (label() || 'Γράψτε κωδικό ή λέξη της περιγραφής') : '—'"
                                               x-on:focus="show()" x-on:click="show()" x-on:input="input($event)" x-on:keydown="key($event)"
                                               x-on:blur="setTimeout(() => { if (! $root.contains(document.activeElement)) close() }, 150)">
                                        <ul class="list-group shadow folder-list" id="folder-list-{{ $uid }}" role="listbox" x-show="open" x-cloak>
                                            <template x-for="(f, n) in items" :key="f[0]">
                                                <li class="list-group-item list-group-item-action py-1" role="option" :aria-selected="n === active"
                                                    :class="{ active: n === active }" :data-folder-active="n === active ? '' : null" :title="f[1]"
                                                    x-on:mousedown.prevent="choose(f)" x-on:mousemove="active = n">
                                                    <span class="fw-semibold" x-html="f[0] === '' ? '—' : mark(f[0])"></span>
                                                    <span class="folder-describe" x-html="mark(f[1])"></span>
                                                </li>
                                            </template>
                                            <li class="list-group-item text-body-secondary py-1" x-show="! items.length">Κανένας φάκελος δεν ταιριάζει.</li>
                                        </ul>
                                        @if ($err($uid, 'fakelos'))<div class="invalid-feedback">{{ $err($uid, 'fakelos') }}</div>@endif
                                    </div>
                                    <div class="col-md-8">
                                        <label for="f-{{ $uid }}-thema" class="form-label small fw-semibold mb-1">Θέμα</label>
                                        {{-- R5d: an e-mail without a subject: its attachment's name is offered (a click puts it in the field) --}}
                                        <textarea id="f-{{ $uid }}-thema" rows="1" class="form-control @if ($err($uid, 'thema')) is-invalid @endif" wire:model="forms.{{ $uid }}.thema"
                                                  @if ($x['subjectGuess']) placeholder="{{ $x['subjectGuess'] }}" data-fill-placeholder
                                                  @elseif (($m['subject'] ?? '') === '') placeholder="Το email δεν έχει θέμα: γράψτε το θέμα" @endif>{{ $f['thema'] ?? '' }}</textarea>
                                        @if ($err($uid, 'thema'))<div class="invalid-feedback">{{ $err($uid, 'thema') }}</div>@endif
                                        @if ($x['subjectGuess'])
                                            <div class="form-text subject-guess">Το email δεν έχει θέμα: αν μείνει κενό, θέμα θα γίνει το όνομα του συνημμένου.</div>
                                        @endif
                                    </div>
                                    {{-- R5d: the same document registered before? (were YES/NO dialogs) --}}
                                    @if ($x['similar'][0] || $x['similar'][1])
                                        <div class="col-12 inbox-similar">
                                            <x-notice type="warning" class="mb-0">
                                                @if ($x['similar'][0])
                                                    Υπάρχει ήδη πρωτόκολλο με το ίδιο θέμα:
                                                    @foreach ($x['similar'][0] as $p)<a href="{{ url('/home/'.$p->id) }}" target="_blank">{{ $p->protocolnum }}/{{ $p->etos }}</a>@if (! $loop->last), @endif@endforeach.
                                                @endif
                                                @if ($x['similar'][1])
                                                    Υπάρχει ήδη πρωτόκολλο με τον ίδιο αριθμό και ημερομηνία εισερχομένου:
                                                    @foreach ($x['similar'][1] as $p)<a href="{{ url('/home/'.$p->id) }}" target="_blank">{{ $p->protocolnum }}/{{ $p->etos }}</a>@if (! $loop->last), @endif@endforeach.
                                                @endif
                                            </x-notice>
                                        </div>
                                    @endif
                                    {{-- R5o (owner, 2026-11-23): the protocol form's sections and colours: Εισερχόμενο (green) --}}
                                    <div class="col-12">
                                        <fieldset class="card border-success-subtle inbox-section" data-section="incoming">
                                            <legend class="card-header h6 mb-0 w-100 bg-success-subtle py-1 small fw-semibold">Εισερχόμενο</legend>
                                            <div class="card-body p-2">
                                                <div class="row g-2">
                                                <div class="col-12 col-sm-4 col-md-2">
                                                    <label for="f-{{ $uid }}-in_num" class="form-label small fw-semibold mb-1">Αριθ. εισερχ.</label>
                                                    <input id="f-{{ $uid }}-in_num" class="form-control @if ($err($uid, 'in_num')) is-invalid @endif" autocomplete="off" wire:model="forms.{{ $uid }}.in_num" value="{{ $f['in_num'] ?? '' }}">
                                                    @if ($err($uid, 'in_num'))<div class="invalid-feedback">{{ $err($uid, 'in_num') }}</div>@endif
                                                </div>
                                                <div class="col-12 col-sm-8 col-md-4">
                                                    <label for="f-{{ $uid }}-in_date" class="form-label small fw-semibold mb-1">Ημ/νία εισερχ.</label>
                                                    {{-- R5e (owner, 2026-11-19): the app's date field: "9" → 09/this month, ◀ ▶, Σήμερα, calendar --}}
                                                    <x-date-input name="in_date" :id="'f-'.$uid.'-in_date'" :error="$err($uid, 'in_date')" :value="$f['in_date'] ?? ''" :help="false"
                                                                  wire:model="forms.{{ $uid }}.in_date" />
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="f-{{ $uid }}-in_arxi_ekdosis" class="form-label small fw-semibold mb-1">Αρχή έκδοσης</label>
                                                    <input id="f-{{ $uid }}-in_arxi_ekdosis" class="form-control @if ($err($uid, 'in_arxi_ekdosis')) is-invalid @endif" autocomplete="off" list="suggest-in_arxi_ekdosis"
                                                           wire:model="forms.{{ $uid }}.in_arxi_ekdosis" value="{{ $f['in_arxi_ekdosis'] ?? '' }}">
                                                    @if ($err($uid, 'in_arxi_ekdosis'))<div class="invalid-feedback">{{ $err($uid, 'in_arxi_ekdosis') }}</div>@endif
                                                </div>
                                                <div class="col-md-5">
                                                    <label for="f-{{ $uid }}-in_topos_ekdosis" class="form-label small fw-semibold mb-1">Τόπος έκδοσης</label>
                                                    <input id="f-{{ $uid }}-in_topos_ekdosis" class="form-control" autocomplete="off" list="suggest-in_topos_ekdosis"
                                                           wire:model="forms.{{ $uid }}.in_topos_ekdosis" value="{{ $f['in_topos_ekdosis'] ?? '' }}">
                                                </div>
                                                <div class="col-md-7">
                                                    <label for="f-{{ $uid }}-in_paraliptis" class="form-label small fw-semibold mb-1">Παραλήπτης</label>
                                                    <input id="f-{{ $uid }}-in_paraliptis" class="form-control @if ($err($uid, 'in_paraliptis')) is-invalid @endif" autocomplete="off" list="suggest-in_paraliptis"
                                                           wire:model="forms.{{ $uid }}.in_paraliptis" value="{{ $f['in_paraliptis'] ?? '' }}">
                                                    @if ($err($uid, 'in_paraliptis'))<div class="invalid-feedback">{{ $err($uid, 'in_paraliptis') }}</div>@endif
                                                </div>
                                                <div class="col-12">
                                                    <label for="f-{{ $uid }}-in_perilipsi" class="form-label small fw-semibold mb-1">Περίληψη</label>
                                                    <textarea id="f-{{ $uid }}-in_perilipsi" rows="2" class="form-control @if ($err($uid, 'in_perilipsi')) is-invalid @endif" wire:model="forms.{{ $uid }}.in_perilipsi">{{ $f['in_perilipsi'] ?? '' }}</textarea>
                                                    @if ($err($uid, 'in_perilipsi'))<div class="invalid-feedback">{{ $err($uid, 'in_perilipsi') }}</div>@endif
                                                </div>
                                                </div>
                                            </div>
                                        </fieldset>
                                    </div>
                                    {{-- R5o: Εξερχόμενο (blue): some e-mails are outgoing documents; folded unless filled in or wrong --}}
                                    @if ($canOut)
                                        @php $outOpen = trim(($f['out_to'] ?? '').($f['out_date'] ?? '').($f['out_perilipsi'] ?? '').($f['diekp_date'] ?? '')) !== ''
                                            || $err($uid, 'out_to') || $err($uid, 'out_date') || $err($uid, 'out_perilipsi') || $err($uid, 'diekp_date'); @endphp
                                        <div class="col-12" x-data="{ open: @js($outOpen) }" wire:key="out-{{ $uid }}-{{ $outOpen ? 'open' : 'folded' }}">
                                            <fieldset class="card border-info-subtle inbox-section" data-section="outgoing">
                                                <legend class="card-header h6 mb-0 w-100 bg-info-subtle py-1 small fw-semibold">
                                                    <button type="button" class="btn btn-link btn-sm p-0 text-reset fw-semibold text-decoration-none w-100 text-start"
                                                            x-on:click="open = ! open" :aria-expanded="open" aria-controls="out-{{ $uid }}" data-section-toggle="outgoing">
                                                        <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i> Εξερχόμενο
                                                    </button>
                                                </legend>
                                                <div class="card-body p-2" id="out-{{ $uid }}" x-show="open" @unless ($outOpen) style="display: none" @endunless>
                                                    <div class="row g-2">
                                                        <div class="col-md-6">
                                                            <label for="f-{{ $uid }}-out_to" class="form-label small fw-semibold mb-1">Απευθύνεται σε</label>
                                                            <input id="f-{{ $uid }}-out_to" class="form-control @if ($err($uid, 'out_to')) is-invalid @endif" autocomplete="off" list="suggest-out_to"
                                                                   wire:model="forms.{{ $uid }}.out_to" value="{{ $f['out_to'] ?? '' }}">
                                                            @if ($err($uid, 'out_to'))<div class="invalid-feedback">{{ $err($uid, 'out_to') }}</div>@endif
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label for="f-{{ $uid }}-out_date" class="form-label small fw-semibold mb-1">Ημ/νία εξερχομένου</label>
                                                            <x-date-input name="out_date" :id="'f-'.$uid.'-out_date'" :error="$err($uid, 'out_date')" :value="$f['out_date'] ?? ''" :help="false"
                                                                          wire:model="forms.{{ $uid }}.out_date" />
                                                        </div>
                                                        <div class="col-12">
                                                            <label for="f-{{ $uid }}-out_perilipsi" class="form-label small fw-semibold mb-1">Περίληψη</label>
                                                            <textarea id="f-{{ $uid }}-out_perilipsi" rows="2" class="form-control @if ($err($uid, 'out_perilipsi')) is-invalid @endif"
                                                                      wire:model="forms.{{ $uid }}.out_perilipsi">{{ $f['out_perilipsi'] ?? '' }}</textarea>
                                                            @if ($err($uid, 'out_perilipsi'))<div class="invalid-feedback">{{ $err($uid, 'out_perilipsi') }}</div>@endif
                                                        </div>
                                                        {{-- review of R5o: with «Διεκπεραίωση» (Ανάθεση), an outgoing document needs it (protocol checks) --}}
                                                        @if ($canDone)
                                                            <div class="col-md-6">
                                                                <label for="f-{{ $uid }}-diekp_date" class="form-label small fw-semibold mb-1">Διεκπεραιώθηκε την</label>
                                                                <x-date-input name="diekp_date" :id="'f-'.$uid.'-diekp_date'" :error="$err($uid, 'diekp_date')" :value="$f['diekp_date'] ?? ''" :help="false"
                                                                              wire:model="forms.{{ $uid }}.diekp_date" />
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </fieldset>
                                        </div>
                                    @endif
                                    {{-- R5d: keywords as tags (R1f), with suggestions from the e-mail's subject --}}
                                    <div class="col-md-8" x-data="tagBox('keywords', true, [{{ $uid }}])">
                                        <label for="f-{{ $uid }}-keywords" class="form-label small fw-semibold mb-1">Λέξεις κλειδιά</label>
                                        <div class="position-relative">
                                            <div class="form-control d-flex flex-wrap align-items-center gap-1 tag-box @if ($err($uid, 'keywords')) is-invalid @endif"
                                                 x-on:click.self="$el.querySelector('.tag-input')?.focus()">
                                                @foreach ($x['keywords'] as $i => $k)
                                                    <span class="badge rounded-pill border d-inline-flex align-items-center gap-1 tag-pill keyword-pill"
                                                          wire:key="kw-{{ $uid }}-{{ $i }}-{{ $k }}" data-tag="{{ $k }}">
                                                        <span class="d-inline-flex align-items-center gap-1" x-show="editing !== {{ $i }}">
                                                            <button type="button" class="tag-text" x-on:click="edit({{ $i }})" aria-label="Αλλαγή {{ $k }}">{{ $k }}</button>
                                                            <button type="button" class="btn-close" style="font-size: .55em" wire:click="removeTag('keywords', {{ $i }}, {{ $uid }})"
                                                                    aria-label="Αφαίρεση {{ $k }}"></button>
                                                        </span>
                                                        <input class="tag-edit" data-edit="{{ $i }}" x-show="editing === {{ $i }}" x-cloak autocomplete="off" aria-label="Αλλαγή {{ $k }}"
                                                               x-on:keydown="key($event, {{ $i }})" x-on:input="typed($event, {{ $i }})" x-on:blur="left($event, {{ $i }})">
                                                    </span>
                                                @endforeach
                                                <input id="f-{{ $uid }}-keywords" class="border-0 flex-grow-1 p-0 bg-transparent tag-input" autocomplete="off"
                                                       role="combobox" aria-autocomplete="list" aria-controls="keywords-suggest-{{ $uid }}" :aria-expanded="items.length > 0"
                                                       placeholder="{{ $x['keywords'] ? '' : 'π.χ. εκδρομή' }}"
                                                       x-on:keydown="key($event)" x-on:input="typed($event)" x-on:blur="left($event)">
                                            </div>
                                            <ul class="list-group shadow tag-suggest" id="keywords-suggest-{{ $uid }}" role="listbox" x-show="items.length" x-cloak>
                                                <template x-for="(it, n) in items" :key="it[0]">
                                                    <li class="list-group-item list-group-item-action py-1 d-flex justify-content-between gap-2" role="option"
                                                        :class="{ active: n === active }" :aria-selected="n === active"
                                                        x-on:mousedown.prevent="pick(it[0])" x-on:mousemove="active = n">
                                                        <span x-html="mark(it[0])"></span>
                                                        <small class="opacity-75" x-text="it[1] + (it[1] === 1 ? ' φορά' : ' φορές')"></small>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                        @if ($err($uid, 'keywords'))<div class="invalid-feedback d-block">{{ $err($uid, 'keywords') }}</div>@endif
                                        @if ($x['suggested'])
                                            <div class="d-flex flex-wrap align-items-center gap-1 mt-1 keyword-suggestions">
                                                <small class="text-body-secondary me-1">Προτάσεις από το θέμα και τα συνημμένα:</small>
                                                @foreach ($x['suggested'] as [$word, $inUse])
                                                    <button type="button" class="btn btn-sm rounded-pill py-0 {{ $inUse ? 'btn-outline-primary' : 'btn-outline-secondary' }}"
                                                            wire:click="addTag('keywords', @js($word), {{ $uid }})" data-suggest="{{ $word }}"
                                                            title="{{ $inUse ? 'Λέξη κλειδί που ήδη χρησιμοποιείται' : 'Νέα λέξη κλειδί' }}">+ {{ $word }}</button>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="col-md-4">
                                        <label for="f-{{ $uid }}-keep" class="form-label small fw-semibold mb-1">Διατήρηση</label>
                                        <select id="f-{{ $uid }}-keep" class="form-select" wire:model="forms.{{ $uid }}.keep" @disabled(! $mayChangeKeep)
                                                @unless ($mayChangeKeep) title="Οι ρυθμίσεις δεν επιτρέπουν αλλαγή: ακολουθεί τον φάκελο" @endunless>
                                            <option value="">—</option>
                                            @foreach ($keepYears as $y)<option value="{{ $y }}" @selected((string) $y === ($f['keep'] ?? ''))>{{ $y }} {{ $y == 1 ? 'έτος' : 'έτη' }}</option>@endforeach
                                            @foreach ($keepWords as $w)<option value="{{ $w }}" @selected((string) $w === ($f['keep'] ?? ''))>{{ $w }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>

                                {{-- less used: assignment (administrators and Αναθέτων) and the receipt's address --}}
                                <details class="mt-2 inbox-more" @if ($err($uid, 'handle') || $err($uid, 'diekp_eos') || $err($uid, 'reply_to_email')) open @endif>
                                    <summary class="small fw-semibold">Ανάθεση και απόδειξη παραλαβής</summary>
                                    <div class="row g-2 mt-1">
                                        @if ($canAssign)
                                            <div class="col-md-4">
                                                <label for="f-{{ $uid }}-handle" class="form-label small fw-semibold mb-1">Διεκπεραίωση</label>
                                                <select id="f-{{ $uid }}-handle" multiple size="3" class="form-select @if ($err($uid, 'handle')) is-invalid @endif" wire:model="forms.{{ $uid }}.handle">
                                                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                                                </select>
                                                @if ($err($uid, 'handle'))<div class="invalid-feedback">{{ $err($uid, 'handle') }}</div>@endif
                                            </div>
                                            <div class="col-md-4">
                                                <label for="f-{{ $uid }}-inform" class="form-label small fw-semibold mb-1">Ενημέρωση</label>
                                                <select id="f-{{ $uid }}-inform" multiple size="3" class="form-select" wire:model="forms.{{ $uid }}.inform">
                                                    @foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="f-{{ $uid }}-diekp_eos" class="form-label small fw-semibold mb-1">Διεκπεραίωση έως</label>
                                                <input id="f-{{ $uid }}-diekp_eos" class="form-control @if ($err($uid, 'diekp_eos')) is-invalid @endif" autocomplete="off" placeholder="ηη/μμ/εεεε"
                                                       wire:model="forms.{{ $uid }}.diekp_eos" value="{{ $f['diekp_eos'] ?? '' }}">
                                                @if ($err($uid, 'diekp_eos'))<div class="invalid-feedback">{{ $err($uid, 'diekp_eos') }}</div>@endif
                                            </div>
                                        @endif
                                        <div class="col-md-8">
                                            <label for="f-{{ $uid }}-reply_to_email" class="form-label small fw-semibold mb-1">Απόδειξη παραλαβής σε</label>
                                            <input id="f-{{ $uid }}-reply_to_email" type="email" class="form-control @if ($err($uid, 'reply_to_email')) is-invalid @endif" autocomplete="off"
                                                   wire:model="forms.{{ $uid }}.reply_to_email" value="{{ $f['reply_to_email'] ?? '' }}">
                                            @if ($err($uid, 'reply_to_email'))<div class="invalid-feedback">{{ $err($uid, 'reply_to_email') }}</div>@endif
                                        </div>
                                    </div>
                                </details>

                                {{-- R5j (owner, 2026-11-20): the files right above the buttons: opened and printed almost every time --}}
                                <div class="mt-3">@include('livewire.partials.inbox-attachments')</div>

                                {{-- R5k (owner, 2026-11-20): this e-mail is being registered (any of its buttons): say so until it is done.
                                     R5r (owner, 2026-11-25): only after half a second, so a quick refusal (e.g. no Φάκελος) does not
                                     look like a save under way --}}
                                @php $mine = "register({$uid}, false), register({$uid}, true), register({$uid}, false, true), register({$uid}, true, true)"; @endphp
                                <div class="alert alert-info py-2 mt-2 mb-0 align-items-center gap-2 inbox-saving" role="status" style="display: none" wire:loading.flex.delay.longer wire:target="{{ $mine }}">{{-- hidden until loading: Livewire's own CSS knows only some spellings --}}
                                    <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                                    Καταχωρίζεται… αποθηκεύονται και τα συνημμένα. Μην κλείσετε τη σελίδα.
                                </div>

                                {{-- R5d: similar protocols found on «Καταχώριση»: a second, explicit click registers it --}}
                                @if ($err($uid, 'similar'))
                                    <x-notice type="warning" class="mt-2 mb-0 inbox-confirm">
                                        Υπάρχει ήδη πρωτόκολλο που μοιάζει με αυτό το email (δείτε παραπάνω). Να καταχωριστεί ούτως ή άλλως;
                                        @php $anyway = 'register('.$uid.', '.(($confirming[$uid] ?? false) ? 'true' : 'false').', true)'; @endphp
                                        <button type="button" class="btn btn-sm btn-warning rounded-pill ms-2" data-action="register-anyway"
                                                wire:click="{{ $anyway }}" wire:loading.attr="disabled" wire:target="register,markRead">
                                            <span class="spinner-border spinner-border-sm me-1" wire:loading wire:target="{{ $anyway }}" aria-hidden="true"></span>Ναι, καταχώριση</button>
                                    </x-notice>
                                @endif
                                {{-- R5r (owner, 2026-11-25): refused: why, right above the buttons (the field may be far above) --}}
                                @php $why = collect(\App\Livewire\EmailInbox::FIELDS)->push('form')->reject(fn ($k) => $k === 'similar')->map(fn ($k) => $err($uid, $k))->filter()->first(); @endphp
                                @if ($why)
                                    <x-notice type="error" class="mt-2 mb-0 inbox-register-error">
                                        <strong>Δεν καταχωρίστηκε.</strong> {{ $why }}
                                        <button type="button" class="btn btn-sm btn-link p-0 ms-1 align-baseline" data-goto-invalid="{{ $uid }}">Μετάβαση στο πεδίο</button>
                                    </x-notice>
                                @endif
                                <x-pill-actions class="mt-2 inbox-actions">
                                    @unless ($receiptOnly)
                                        <x-pill type="submit" variant="primary" wire:loading.attr="disabled" wire:target="register,markRead" data-action="register">
                                            <span class="spinner-border spinner-border-sm" wire:loading.delay.longer wire:target="register({{ $uid }}, false)" aria-hidden="true"></span>
                                            <i class="bi bi-journal-plus" aria-hidden="true" wire:loading.remove.delay.longer wire:target="register({{ $uid }}, false)"></i> Καταχώριση
                                        </x-pill>
                                    @endunless
                                    <x-pill :variant="$receiptOnly ? 'primary' : 'secondary'" wire:click="register({{ $uid }}, true)" wire:loading.attr="disabled" wire:target="register,markRead" data-action="register-receipt">
                                        <span class="spinner-border spinner-border-sm" wire:loading.delay.longer wire:target="register({{ $uid }}, true)" aria-hidden="true"></span>
                                        <i class="bi bi-envelope-check" aria-hidden="true" wire:loading.remove.delay.longer wire:target="register({{ $uid }}, true)"></i> Καταχώριση και απόδειξη
                                    </x-pill>
                                    <x-pill wire:click="markRead({{ $uid }})" wire:loading.attr="disabled" wire:target="register,markRead" data-action="mark-read" title="Το email φεύγει από τα εισερχόμενα χωρίς πρωτόκολλο">
                                        <i class="bi bi-envelope-open" aria-hidden="true"></i> Αναγνωσμένο
                                    </x-pill>
                                </x-pill-actions>
                            </form>
                        @endif

                        {{-- the e-mail itself; R5j: coloured apart from the form above (to glance at) --}}
                        <div class="card-body border-top inbox-mail small" id="mail-{{ $uid }}">
                            <dl class="row mb-2 inbox-headers">
                                <dt class="col-sm-2">Από</dt><dd class="col-sm-10 text-break mb-1">{{ \App\Support\Mail\MailContent::sender($m) }}</dd>
                                @if (! empty($m['to']))<dt class="col-sm-2">Προς</dt>@include('livewire.partials.inbox-addresses', ['addresses' => $m['to'], 'kind' => 'to'])@endif
                                @if (! empty($m['cc']))<dt class="col-sm-2">Κοιν.</dt>@include('livewire.partials.inbox-addresses', ['addresses' => $m['cc'], 'kind' => 'cc'])@endif
                                {{-- R5j: as an e-mail program shows it --}}
                                <dt class="col-sm-2">Θέμα</dt><dd class="col-sm-10 text-break mb-1 fw-semibold inbox-mail-subject">{{ $m['subject'] !== '' ? $m['subject'] : '(χωρίς θέμα)' }}</dd>
                                @if (! empty($m['reply_to']))<dt class="col-sm-2">Απάντηση σε</dt><dd class="col-sm-10 text-break mb-1">{{ $m['reply_to'] }}</dd>@endif
                            </dl>

                            {{-- the files: above the buttons while the e-mail waits (R5j); here once it is registered or read --}}
                            @if ($state)
                                @include('livewire.partials.inbox-attachments')
                            @endif

                            @if (trim((string) ($m['text'] ?? '')) !== '')
                                <section class="inbox-part" x-data="inboxPart('text')">
                                    <button type="button" class="btn btn-sm btn-link px-0 text-reset fw-semibold inbox-part-toggle" x-on:click="toggle()" :aria-expanded="open" data-part-toggle="text">
                                        <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i> Κείμενο
                                    </button>
                                    <div class="inbox-text" x-show="open" x-cloak>{{ $m['text'] }}</div>
                                </section>
                            @endif
                            @if ($m['has_html'] ?? false)
                                <section class="inbox-part" x-data="inboxPart('html')">
                                    <button type="button" class="btn btn-sm btn-link px-0 text-reset fw-semibold inbox-part-toggle" x-on:click="toggle()" :aria-expanded="open" data-part-toggle="html">
                                        <i class="bi" :class="open ? 'bi-chevron-up' : 'bi-chevron-down'" aria-hidden="true"></i> Εμφάνιση HTML
                                    </button>
                                    {{-- R5o (owner, 2026-11-23): was the pill «HTML σε νέα καρτέλα» among the actions --}}
                                    <a href="{{ url('/viewEmailHtml/'.$uid) }}" target="_blank" rel="noopener" class="btn btn-sm btn-link px-1 text-reset inbox-html-tab"
                                       title="Άνοιγμα του HTML σε νέα καρτέλα" aria-label="Άνοιγμα του HTML σε νέα καρτέλα"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                                    <div x-show="open" x-cloak wire:ignore>
                                        <iframe class="inbox-html w-100 border rounded" title="Το email ως HTML" loading="lazy"
                                                sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                                src="{{ url('/viewEmailHtml/'.$uid) }}" x-on:load="fit($el)"></iframe>
                                    </div>
                                </section>
                            @endif
                        </div>
                    </article>
                    </div>
                @endforeach
                </div>

                @if ($lastPage > 1)
                    <x-list-pager :paginator="$paginator" position="bottom" class="justify-content-end inbox-pager" />
                @endif
            </div>
        </div>
    @endif
</div>
