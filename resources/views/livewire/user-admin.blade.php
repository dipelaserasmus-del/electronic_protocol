{{-- R6a: users and roles (administrators). Rules: App\Services\Users\UserAccounts; roles: App\Support\Roles. --}}
<div class="user-admin">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
        <h1 class="h4 mb-0 me-auto">Χρήστες</h1>
        <x-pill-actions class="flex-grow-0">
            <x-pill variant="primary" wire:click="create" id="user-new"><i class="bi bi-person-plus me-1" aria-hidden="true"></i>Νέος χρήστης</x-pill>
        </x-pill-actions>
    </div>

    @if ($formOpen)
        <div class="card shadow-sm mb-3" id="user-form">
            <div class="card-header fw-semibold">
                @if ($editingUser) Επεξεργασία: {{ $editingUser->name }} @else Νέος χρήστης @endif
            </div>
            <form class="card-body" wire:submit="save">
                <div class="row g-2">
                    <x-form.text class="col-md-4" name="name" label="Ονοματεπώνυμο" :value="$form['name']" :error="$errors->first('form.name')" max="255" />
                    <x-form.text class="col-md-4" name="username" label="Username (για τη σύνδεση)" :value="$form['username']" :error="$errors->first('form.username')" max="255" />
                    <x-form.text class="col-md-4" name="email" type="email" label="E-mail" :value="$form['email']" :error="$errors->first('form.email')" max="255" />
                </div>
                <div class="row g-2">
                    <div class="col-md-4 mb-2">
                        <label for="f-role_id" class="form-label small fw-semibold mb-1">Ρόλος</label>
                        <select id="f-role_id" class="form-select @error('form.role_id') is-invalid @enderror" wire:model.live="form.role_id">
                            <option value="">— επιλέξτε —</option>
                            @foreach ($roles as $r)
                                <option value="{{ $r->id }}" @selected((string) $form['role_id'] === (string) $r->id)>{{ $r->role }}</option>
                            @endforeach
                        </select>
                        @error('form.role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @php $chosen = $roles->firstWhere('id', (int) $form['role_id']); @endphp
                        @if ($chosen)<div class="form-text" id="role-help">{{ \App\Support\Roles::describe($chosen->role) }}</div>@endif
                    </div>
                    <x-form.text class="col-md-4" name="password" type="password" :label="$editingUser ? 'Νέος κωδικός (κενό: μένει ο ίδιος)' : 'Κωδικός'"
                                 :error="$errors->first('form.password')" :help="'Τουλάχιστον '.$passwordMin.' χαρακτήρες.'" />
                    <x-form.text class="col-md-4" name="password_confirmation" type="password" label="Ξανά ο κωδικός" />
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input @error('form.active') is-invalid @enderror" type="checkbox" id="f-active" wire:model.live="form.active">
                    <label class="form-check-label" for="f-active">Ενεργός: συνδέεται και εμφανίζεται στις αναθέσεις</label>
                    @error('form.active')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @if ($editingUser && ! $form['active'] && $editingUser->active)
                    <x-notice type="warning">Με την αποθήκευση ο χρήστης αποσυνδέεται και δεν μπορεί να συνδεθεί ξανά.</x-notice>
                @endif
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-pill" id="user-save">Αποθήκευση</button>
                    <button type="button" class="btn btn-outline-secondary rounded-pill" wire:click="cancel">Ακύρωση</button>
                </div>
            </form>
        </div>
    @endif

    <div class="d-flex flex-wrap align-items-center gap-2 mb-2" role="search">
        <label for="user-search" class="visually-hidden">Αναζήτηση χρήστη</label>
        <input id="user-search" type="search" class="form-control form-control-sm w-auto" placeholder="Όνομα, username ή e-mail"
               wire:model.live.debounce.300ms="search">
        <label for="user-role" class="visually-hidden">Ρόλος</label>
        <select id="user-role" class="form-select form-select-sm w-auto" wire:model.live="roleFilter">
            <option value="">Όλοι οι ρόλοι</option>
            @foreach ($roles as $r)<option value="{{ $r->id }}">{{ $r->role }}</option>@endforeach
        </select>
        <label for="user-state" class="visually-hidden">Κατάσταση</label>
        <select id="user-state" class="form-select form-select-sm w-auto" wire:model.live="stateFilter">
            <option value="">Ενεργοί και ανενεργοί</option>
            <option value="active">Μόνο ενεργοί</option>
            <option value="inactive">Μόνο ανενεργοί</option>
        </select>
        <span class="small text-body-secondary ms-auto">{{ $users->total() }} {{ $users->total() === 1 ? 'χρήστης' : 'χρήστες' }}</span>
    </div>

    <div class="card shadow-sm mb-3">
        <table class="table table-sm align-middle mb-0 table-sticky" id="users-table">
            <thead class="table-light">
                <tr>
                    <th scope="col">Ονοματεπώνυμο</th>
                    <th scope="col">Username</th>
                    <th scope="col" class="d-none d-lg-table-cell">E-mail</th>
                    <th scope="col">Ρόλος</th>
                    <th scope="col" class="d-none d-md-table-cell text-end" title="Πρωτόκολλα που καταχώρισε">Πρωτ.</th>
                    <th scope="col" class="d-none d-md-table-cell text-end" title="Ανοιχτές αναθέσεις διεκπεραίωσης">Εκκρεμή</th>
                    <th scope="col" class="d-none d-xl-table-cell">Τελευταία κίνηση</th>
                    <th scope="col" class="text-end"><span class="visually-hidden">Ενέργειες</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    @php $blocked = $accounts->cannotDelete($u, auth()->user()); @endphp
                    <tr wire:key="u-{{ $u->id }}" class="user-row @if (! $u->active) text-body-secondary @endif @if ($editing === $u->id) table-active @endif" data-username="{{ $u->username }}">
                        <td>
                            {{ $u->name }}
                            @if ($u->id === $me)<span class="badge text-bg-light border ms-1">εσείς</span>@endif
                            @if (! $u->active)<span class="badge text-bg-secondary ms-1">ανενεργός</span>@endif
                        </td>
                        <td class="text-break">{{ $u->username }}</td>
                        <td class="d-none d-lg-table-cell text-break small">{{ $u->email }}</td>
                        <td class="small">{{ $u->role?->role }}</td>
                        <td class="d-none d-md-table-cell text-end small">{{ $u->protocols_count ?: '' }}</td>
                        <td class="d-none d-md-table-cell text-end small">
                            @if ($u->open_assignments_count)<a href="{{ url('/home/list/d/'.$u->id) }}">{{ $u->open_assignments_count }}</a>@endif
                        </td>
                        <td class="d-none d-xl-table-cell small">
                            {{ isset($lastSeen[$u->id]) ? \Carbon\Carbon::createFromTimestamp($lastSeen[$u->id])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '' }}
                        </td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" wire:click="edit({{ $u->id }})"
                                    title="Επεξεργασία {{ $u->name }}">Επεξεργασία</button>
                            @if ($blocked)
                                <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" disabled title="{{ $blocked }}">Διαγραφή</button>
                            @else
                                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" wire:click="delete({{ $u->id }})"
                                        data-confirm="Να διαγραφεί ο χρήστης «{{ $u->name }}»;">Διαγραφή</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">Κανένας χρήστης δεν ταιριάζει.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mb-3">{{ $users->links() }}</div>

    <h2 class="h5">Ρόλοι</h2>
    <div class="row g-2" id="roles">
        @foreach ($roles as $r)
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body py-2">
                        <div class="d-flex align-items-baseline gap-2">
                            <h3 class="h6 mb-1 me-auto">{{ $r->role }}</h3>
                            <button type="button" class="btn btn-link btn-sm p-0" wire:click="$set('roleFilter', '{{ $r->id }}')">{{ $roleCounts[$r->id] ?? 0 }} {{ ($roleCounts[$r->id] ?? 0) == 1 ? 'ενεργός' : 'ενεργοί' }}</button>
                        </div>
                        <p class="small mb-0 text-body-secondary">{{ \App\Support\Roles::describe($r->role) }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
