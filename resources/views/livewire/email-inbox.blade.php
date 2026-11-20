{{-- R5c: "Εισερχόμενα". Rules: App\Livewire\EmailInbox → App\Services\Mail\EmailRegistration → ProtocolWriter. --}}
@php
    $first = ($page - 1) * $perPage + 1;
    $last = min($total, $page * $perPage);
    $date = fn (?string $iso, string $format = 'd/m/Y H:i') => $iso ? (new \DateTimeImmutable($iso))->setTimezone($zone)->format($format) : '';
    $err = fn (int $uid, string $f) => $errors->first("forms.$uid.$f") ?: null;
@endphp
<div class="inbox-page" x-data="inboxPage">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0 me-auto"><i class="bi bi-inbox me-1" aria-hidden="true"></i>Εισερχόμενα email</h1>
        @if ($total > 0)
            <span class="small text-body-secondary" id="inbox-count">
                @if ($total > $perPage) {{ $first }}–{{ $last }} από {{ $total }} @else {{ $total }} {{ $total === 1 ? 'email' : 'email' }} @endif
                · {{ $newestFirst ? 'νεότερα πρώτα' : 'παλαιότερα πρώτα' }}
            </span>
        @endif
        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" wire:click="refresh" wire:loading.attr="disabled" title="Νέα email από τον διακομιστή">
            <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Ανανέωση
        </button>
    </div>

    {{-- R5g (owner, 2026-11-20): the list's pager above the e-mails too (the only one was below the last e-mail) --}}
    @if (! $failure && $total > $perPage)
        <x-list-pager :paginator="$paginator" position="top" class="mb-3 justify-content-end" />
    @endif

    @if ($failure)
        <x-notice type="error" id="inbox-failure">Η σύνδεση με τον λογαριασμό email απέτυχε. Ελέγξτε τις ρυθμίσεις. Κωδικός σφάλματος: {{ $failure }}</x-notice>
    @elseif ($total === 0)
        <x-notice type="info" id="inbox-empty">Δεν υπάρχουν εισερχόμενα email ({{ $account }}).</x-notice>
    @else
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
                            <div class="d-flex gap-1 align-items-baseline">
                                <span class="fw-semibold text-truncate flex-grow-1">{{ $m['from_name'] ?: $m['from_email'] }}</span>
                                <small class="text-body-secondary text-nowrap">{{ $date($m['date'], 'd/m H:i') }}</small>
                            </div>
                            <div class="small text-truncate">
                                @if ($m['attachments'])<i class="bi bi-paperclip" aria-label="με συνημμένα"></i>@endif
                                {{ $m['subject'] !== '' ? $m['subject'] : '(χωρίς θέμα)' }}
                            </div>
                            @if (($done[$uid]['type'] ?? null) === 'registered')
                                <span class="badge text-bg-success">{{ $done[$uid]['label'] }}</span>
                            @elseif (($done[$uid]['type'] ?? null) === 'read')
                                <span class="badge text-bg-secondary">αναγνωσμένο</span>
                            @endif
                        </a>
                    @endforeach
                </nav>
            </aside>

            <div class="col-lg-9">
                @foreach ($messages as $uid => $m)
                    @php $f = $forms[$uid] ?? []; $state = $done[$uid] ?? null; $n = $first + $loop->index; $x = $extra[$uid] ?? null; @endphp
                    <article class="card shadow-sm mb-3 inbox-message @if ($state) is-done @endif" id="msg-{{ $uid }}" wire:key="msg-{{ $uid }}" tabindex="-1"
                             data-uid="{{ $uid }}" x-intersect.margin.-40%.0.-55%.0="current = {{ $uid }}">
                        <header class="card-header d-flex flex-wrap gap-2 align-items-baseline">
                            <span class="badge text-bg-primary">{{ $n }} από {{ $total }}</span>
                            <h2 class="h6 mb-0 flex-grow-1 text-break inbox-subject">{{ $m['subject'] !== '' ? $m['subject'] : '(χωρίς θέμα)' }}</h2>
                            <small class="text-body-secondary text-nowrap">{{ $date($m['date'], 'd/m/Y H:i:s') }}</small>
                        </header>

                        @if ($state)
                            <div class="card-body">
                                @if ($state['type'] === 'registered')
                                    <x-notice type="{{ $state['warnings'] ? 'warning' : 'success' }}" class="mb-0 inbox-done">
                                        Καταχωρίστηκε με αριθμό <strong>{{ $state['label'] }}</strong>
                                        <a href="{{ url('/home/'.$state['protocol_id']) }}" target="_blank" class="ms-1">Άνοιγμα πρωτοκόλλου ↗</a>
                                        @if ($state['messages'] || $state['warnings'])
                                            <ul class="mb-0 ps-3">
                                                @foreach ($state['messages'] as $line)<li>{{ $line }}</li>@endforeach
                                                @foreach ($state['warnings'] as $line)<li><strong>{{ $line }}</strong></li>@endforeach
                                            </ul>
                                        @endif
                                    </x-notice>
                                @else
                                    <x-notice type="info" class="mb-0 inbox-done">Μεταφέρθηκε στα αναγνωσμένα, χωρίς πρωτόκολλο.</x-notice>
                                @endif
                            </div>
                        @else
                            <form class="card-body inbox-form" wire:submit="register({{ $uid }}, {{ $receiptOnly ? 'true' : 'false' }})" novalidate>
                                @if ($err($uid, 'form'))<x-notice type="error" class="inbox-form-error">{{ $err($uid, 'form') }}</x-notice>@endif
                                <div class="row g-2">
                                    {{-- folder: required for e-mails with attachments (R1f picker) --}}
                                    <div class="col-md-4 position-relative" x-data="folderPicker({ model: 'forms.{{ $uid }}.fakelos' })" x-on:click.outside="close()">
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
                                    <div class="col-12 col-sm-4 col-md-2">
                                        <label for="f-{{ $uid }}-in_num" class="form-label small fw-semibold mb-1">Αριθ. εισερχ.</label>
                                        <input id="f-{{ $uid }}-in_num" class="form-control @if ($err($uid, 'in_num')) is-invalid @endif" autocomplete="off" wire:model="forms.{{ $uid }}.in_num" value="{{ $f['in_num'] ?? '' }}">
                                        @if ($err($uid, 'in_num'))<div class="invalid-feedback">{{ $err($uid, 'in_num') }}</div>@endif
                                    </div>
                                    <div class="col-12 col-sm-8 col-md-4">
                                        <label for="f-{{ $uid }}-in_date" class="form-label small fw-semibold mb-1">Ημ/νία εισερχ.</label>
                                        {{-- R5e (owner, 2026-11-19): the app's date field: "9" → 09/this month, ◀ ▶, Σήμερα, calendar --}}
                                        <x-date-input name="in_date" :id="'f-'.$uid.'-in_date'" :value="$f['in_date'] ?? ''" :help="false"
                                                      wire:model="forms.{{ $uid }}.in_date" />
                                        @if ($err($uid, 'in_date'))<div class="text-danger small">{{ $err($uid, 'in_date') }}</div>@endif
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

                                {{-- R5d: similar protocols found on «Καταχώριση»: a second, explicit click registers it --}}
                                @if ($err($uid, 'similar'))
                                    <x-notice type="warning" class="mt-2 mb-0 inbox-confirm">
                                        Υπάρχει ήδη πρωτόκολλο που μοιάζει με αυτό το email (δείτε παραπάνω). Να καταχωριστεί ούτως ή άλλως;
                                        <button type="button" class="btn btn-sm btn-warning rounded-pill ms-2" data-action="register-anyway"
                                                wire:click="register({{ $uid }}, {{ ($confirming[$uid] ?? false) ? 'true' : 'false' }}, true)">Ναι, καταχώριση</button>
                                    </x-notice>
                                @endif
                                <x-pill-actions class="mt-2 inbox-actions">
                                    @unless ($receiptOnly)
                                        <x-pill type="submit" variant="primary" wire:loading.attr="disabled" wire:target="register" data-action="register">
                                            <i class="bi bi-journal-plus" aria-hidden="true"></i> Καταχώριση
                                        </x-pill>
                                    @endunless
                                    <x-pill :variant="$receiptOnly ? 'primary' : 'secondary'" wire:click="register({{ $uid }}, true)" wire:loading.attr="disabled" wire:target="register" data-action="register-receipt">
                                        <i class="bi bi-envelope-check" aria-hidden="true"></i> Καταχώριση και απόδειξη
                                    </x-pill>
                                    <x-pill wire:click="markRead({{ $uid }})" data-action="mark-read" title="Το email φεύγει από τα εισερχόμενα χωρίς πρωτόκολλο">
                                        <i class="bi bi-envelope-open" aria-hidden="true"></i> Αναγνωσμένο
                                    </x-pill>
                                    @if ($m['has_html'])
                                        <x-pill :href="url('/viewEmailHtml/'.$uid)" target="_blank"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> HTML σε νέα καρτέλα</x-pill>
                                    @endif
                                </x-pill-actions>
                            </form>
                        @endif

                        {{-- the e-mail itself --}}
                        <div class="card-body border-top inbox-mail small">
                            <dl class="row mb-2 inbox-headers">
                                <dt class="col-sm-2">Από</dt><dd class="col-sm-10 text-break mb-1">{{ \App\Support\Mail\MailContent::sender($m) }}</dd>
                                @if (! empty($m['to']))<dt class="col-sm-2">Προς</dt>@include('livewire.partials.inbox-addresses', ['addresses' => $m['to'], 'kind' => 'to'])@endif
                                @if (! empty($m['cc']))<dt class="col-sm-2">Κοιν.</dt>@include('livewire.partials.inbox-addresses', ['addresses' => $m['cc'], 'kind' => 'cc'])@endif
                                @if (! empty($m['reply_to']))<dt class="col-sm-2">Απάντηση σε</dt><dd class="col-sm-10 text-break mb-1">{{ $m['reply_to'] }}</dd>@endif
                            </dl>

                            @if (! empty($m['winmail']))
                                {{-- R5b: Outlook's winmail.dat, opened --}}
                                <x-notice type="info" class="inbox-winmail">
                                    @if ($m['winmail']['report'])
                                        <strong>Αναφορά ανάγνωσης / παράδοσης</strong> (Outlook): τα στοιχεία της φαίνονται στο κείμενο.
                                    @else
                                        Το email ήρθε ως <code>winmail.dat</code> (Outlook): εμφανίζονται το κείμενο και τα αρχεία που περιέχει.
                                    @endif
                                </x-notice>
                            @endif
                            @if ($m['attachments'])
                                <div class="inbox-attachments mb-2" role="group" aria-label="Συνημμένα">
                                    <div class="fw-semibold mb-1">Συνημμένα @unless ($state)<span class="fw-normal text-body-secondary">(✓ = αποθηκεύεται με το πρωτόκολλο)</span>@endunless</div>
                                    <ul class="list-unstyled mb-0 d-flex flex-wrap gap-2">
                                        @foreach ($m['attachments'] as $a)
                                            <li class="inbox-attachment border rounded px-2 py-1 d-flex align-items-center gap-2" wire:key="a-{{ $uid }}-{{ $a['key'] }}">
                                                @unless ($state)
                                                    <input type="checkbox" class="form-check-input mt-0" id="f-{{ $uid }}-file-{{ $a['key'] }}" wire:model="forms.{{ $uid }}.files.{{ $a['key'] }}"
                                                           @checked($f['files'][$a['key']] ?? false) aria-label="Αποθήκευση του {{ $a['name'] }}">
                                                @endunless
                                                <x-file-badge :name="$a['name']" :size="$a['size']" :mime="$a['mime']" />
                                                <a href="{{ url('/viewEmailAttachment/'.$uid.'/'.$a['key']) }}" target="_blank" class="text-break">{{ $a['name'] }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
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
                                    <div x-show="open" x-cloak wire:ignore>
                                        <iframe class="inbox-html w-100 border rounded" title="Το email ως HTML" loading="lazy"
                                                sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                                src="{{ url('/viewEmailHtml/'.$uid) }}" x-on:load="fit($el)"></iframe>
                                    </div>
                                </section>
                            @endif
                        </div>
                    </article>
                @endforeach

                @if ($lastPage > 1)
                    <x-list-pager :paginator="$paginator" position="bottom" class="justify-content-end inbox-pager" />
                @endif
            </div>
        </div>
    @endif
</div>
