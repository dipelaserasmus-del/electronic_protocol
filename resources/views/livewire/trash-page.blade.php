{{-- R4b: the attachments' trash. Rules: App\Services\Attachments\Trash. R4h: and the deleted protocols (App\Services\Protocols\ProtocolTrash). --}}
<div class="trash-page">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Κάδος</h1>
        @if ($tab === 'files' && $total > 0)
            <x-pill-actions class="flex-grow-0">
                <x-pill variant="danger" wire:click="purgeAll" data-confirm="Να διαγραφούν οριστικά όλα τα {{ $total }} αρχεία του κάδου;">Άδειασμα κάδου</x-pill>
            </x-pill-actions>
        @endif
    </div>
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link @if ($tab === 'protocols') active @endif" role="tab" id="trash-tab-protocols"
                    aria-selected="{{ $tab === 'protocols' ? 'true' : 'false' }}" wire:click="show('protocols')">Πρωτόκολλα
                <span class="badge rounded-pill text-bg-secondary">{{ $protocolTotal }}</span></button>
        </li>
        @if ($isAdmin)
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link @if ($tab === 'files') active @endif" role="tab" id="trash-tab-files"
                        aria-selected="{{ $tab === 'files' ? 'true' : 'false' }}" wire:click="show('files')">Συνημμένα
                    <span class="badge rounded-pill text-bg-secondary">{{ $fileTotal }}</span></button>
            </li>
        @endif
    </ul>

    @if ($tab === 'protocols')
        <x-notice type="info" id="trash-protocols-info">
            Τα διαγραμμένα πρωτόκολλα, με τα συνημμένα τους. Ο αριθμός τους είναι πάλι ελεύθερος· με την <em>Επαναφορά</em>
            το πρωτόκολλο παίρνει πίσω τον αριθμό του αν δεν έχει δοθεί, αλλιώς νέο αριθμό (και σημείωση στις Παρατηρήσεις).
            @unless ($isAdmin) Εμφανίζονται μόνο όσα καταχωρίσατε εσείς. @endunless
            @if ($days > 0) Μένουν {{ $days }} ημέρες και μετά διαγράφονται οριστικά. @endif
        </x-notice>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
            <input type="search" class="form-control w-auto" placeholder="Αριθμός ή θέμα" aria-label="Αναζήτηση στον κάδο"
                   wire:model.live.debounce.400ms="search" id="trash-search">
            <x-list-pager :paginator="$protocols" position="top" class="ms-auto justify-content-end" />
        </div>
        <div class="card shadow-sm">
            <table class="table table-sm align-middle mb-0 table-sticky" id="trash-protocols">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Αριθμός</th>
                        <th scope="col">Θέμα</th>
                        <th scope="col" class="d-none d-lg-table-cell">Φάκελος</th>
                        <th scope="col" class="d-none d-md-table-cell">Διαγραφή</th>
                        <th scope="col" class="d-none d-md-table-cell">Αρχεία</th>
                        <th scope="col" class="text-end"><span class="visually-hidden">Ενέργειες</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($protocols as $t)
                        @php $until = $protocolTrash->purgeDate($t); $files = count($t->data['attachments'] ?? []); @endphp
                        <tr wire:key="tp-{{ $t->id }}" class="trash-protocol-row">
                            <td class="text-nowrap fw-semibold">{{ $t->label() }}
                                <div class="small text-body-secondary fw-normal">{{ $t->protocoldate?->format('d/m/Y') }}</div></td>
                            <td class="small text-break">{{ $t->thema }}
                                <div class="text-body-secondary">Καταχώριση: {{ $t->user_id ? ($names[$t->user_id] ?? '—') : '—' }}</div></td>
                            <td class="small d-none d-lg-table-cell">{{ $t->fakelos }}</td>
                            <td class="small d-none d-md-table-cell">{{ $t->deleted_at?->format('d/m/Y H:i') }}
                                <div class="text-body-secondary">{{ $t->deleted_by ? ($names[$t->deleted_by] ?? '—') : '—' }}</div>
                                @if ($until)<div class="text-body-secondary">οριστικά: {{ $until->format('d/m/Y') }}</div>@endif</td>
                            <td class="small d-none d-md-table-cell">{{ $files }}</td>
                            <td class="text-end text-nowrap">
                                @if (\App\Services\Protocols\ProtocolTrash::mayRestore(auth()->user(), $t))
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" wire:click="restoreProtocol({{ $t->id }})"
                                            data-action="restore-protocol"
                                            data-confirm="Επαναφορά του πρωτοκόλλου {{ $t->label() }}; Αν ο αριθμός του έχει δοθεί, παίρνει νέο αριθμό.">Επαναφορά</button>
                                @endif
                                @if ($isAdmin)
                                    <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" wire:click="purgeProtocol({{ $t->id }})"
                                            data-action="purge-protocol"
                                            data-confirm="Να διαγραφεί οριστικά το πρωτόκολλο {{ $t->label() }} με τα {{ $files }} αρχεία του;">Οριστική διαγραφή</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-body-secondary py-4">Δεν υπάρχουν διαγραμμένα πρωτόκολλα.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <x-list-pager :paginator="$protocols" position="bottom" class="mt-3 justify-content-end" />
    @else
    <x-notice type="info" id="trash-retention">
        {{ $total }} {{ $total === 1 ? 'αρχείο' : 'αρχεία' }}, {{ \App\Support\FileBadge::size($totalSize) }}.
        @if ($days > 0)
            Κάθε αρχείο μένει στον κάδο {{ $days }} ημέρες και μετά διαγράφεται οριστικά.
        @else
            Τα αρχεία μένουν στον κάδο μέχρι να τον αδειάσετε.
        @endif
        Η διάρκεια αλλάζει στις <a href="{{ url('/settings') }}">Ρυθμίσεις</a>.
    </x-notice>

    {{-- R2g: the pager above the list too --}}
    <x-list-pager :paginator="$items" position="top" class="mb-2 justify-content-end" />
    <div class="card shadow-sm">
        <table class="table table-sm align-middle mb-0 table-sticky">
            <thead class="table-light">
                <tr>
                    <th scope="col">Αρχείο</th>
                    <th scope="col">Πρωτόκολλο</th>
                    <th scope="col" class="d-none d-md-table-cell">Διαγραφή</th>
                    <th scope="col" class="d-none d-md-table-cell">Οριστική διαγραφή</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Ενέργειες</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $a)
                    @php $until = $trash->purgeDate($a); @endphp
                    <tr wire:key="t-{{ $a->id }}" class="trash-row">
                        <td>
                            <x-file-badge :name="$a->name" :size="$a->size" :mime="$a->mimeType" />
                            @if ($trash->fileInTrash($a))
                                <a href="{{ url('/trash/'.$a->id.'/download') }}" target="_blank" class="text-break">{{ $a->name ?: $a->ada }}</a>
                            @else
                                <span class="text-break">{{ $a->name ?: $a->ada }}</span> <small class="text-danger">(το αρχείο λείπει)</small>
                            @endif
                        </td>
                        <td class="small">
                            @if ($a->protocol)
                                <a href="{{ url('/home/'.$a->protocol->id) }}">{{ $a->protocol->protocolnum }}/{{ $a->protocol->etos }}</a>
                                <div class="text-body-secondary text-break">{{ $a->protocol->thema }}</div>
                            @else
                                <span class="text-body-secondary">{{ $a->protocol_label ?: 'πρωτόκολλο #'.$a->protocol_id }}</span>
                                <div><span class="badge text-bg-secondary">{{ \App\Models\TrashedProtocol::where('protocol_id', $a->protocol_id)->exists() ? 'το πρωτόκολλο είναι στον κάδο' : 'το πρωτόκολλο διαγράφηκε' }}</span></div>
                            @endif
                        </td>
                        <td class="small d-none d-md-table-cell">
                            {{ $a->deleted_at?->format('d/m/Y H:i') }}
                            <div class="text-body-secondary">{{ $a->deleted_by ? ($names[$a->deleted_by] ?? '—') : '—' }}</div>
                        </td>
                        <td class="small d-none d-md-table-cell">{{ $until ? $until->format('d/m/Y') : 'χειροκίνητα' }}</td>
                        <td class="text-end text-nowrap">
                            @if ($a->protocol)
                                <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" wire:click="restore({{ $a->id }})">Επαναφορά</button>
                            @endif
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" wire:click="purge({{ $a->id }})"
                                    data-confirm="Να διαγραφεί οριστικά το «{{ $a->name ?: $a->ada }}»;">Οριστική διαγραφή</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-body-secondary py-4">Ο κάδος είναι άδειος.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-list-pager :paginator="$items" position="bottom" class="mt-3 justify-content-end" />
    @endif
</div>
