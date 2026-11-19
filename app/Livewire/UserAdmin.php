<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Users\UserAccounts;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * R6a: "Χρήστες" (administrators). The users with their role, state, last activity and how many protocols and open
 * assignments they have; a search box and role/state filters; one form card for a new user or the chosen one; delete
 * only when nothing refers to the user (else deactivate). Rules: App\Services\Users\UserAccounts.
 */
class UserAdmin extends Component
{
    use WithPagination;
    use \App\Livewire\Concerns\PagesWithinRange;

    protected string $paginationTheme = 'bootstrap';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'role', except: '')]
    public string $roleFilter = '';

    /** '' all, 'active', 'inactive' */
    #[Url(as: 'state', except: '')]
    public string $stateFilter = '';

    /** The user being edited (null: the form adds a new one) */
    public ?int $editing = null;

    /** Whether the form card is open */
    public bool $formOpen = false;

    public array $form = [];

    public function mount(?int $user = null): void
    {
        self::authorizeAdmin();
        $this->resetForm();
        if ($user && ($u = User::find($user))) {
            $this->edit($u->id);   // /users/{id} opens that user, as the old page did
        }
    }

    private static function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role?->role === Roles::ADMIN, 403);
    }

    private function resetForm(): void
    {
        $this->form = ['name' => '', 'username' => '', 'email' => '', 'role_id' => '', 'active' => true, 'password' => '', 'password_confirmation' => ''];
        $this->editing = null;
        $this->resetErrorBag();
    }

    public function create(): void
    {
        self::authorizeAdmin();
        $this->resetForm();
        $this->formOpen = true;
    }

    public function edit(int $id): void
    {
        self::authorizeAdmin();
        $u = User::findOrFail($id);
        $this->resetErrorBag();
        $this->editing = $u->id;
        $this->form = [
            'name' => (string) $u->name, 'username' => (string) $u->username, 'email' => (string) $u->email,
            'role_id' => (string) $u->role_id, 'active' => (bool) $u->active, 'password' => '', 'password_confirmation' => '',
        ];
        $this->formOpen = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
        $this->formOpen = false;
    }

    public function save(UserAccounts $accounts): void
    {
        self::authorizeAdmin();
        $user = $this->editing ? User::findOrFail($this->editing) : null;
        try {
            $saved = $accounts->save($user, $this->form, auth()->user());
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('form.'.$key, $messages[0]);
            }
            return;
        }
        $this->dispatch('toast', message: $user ? "Ο χρήστης «{$saved->name}» ενημερώθηκε." : "Ο χρήστης «{$saved->name}» προστέθηκε.", type: 'success');
        $this->resetForm();
        $this->formOpen = false;
    }

    public function delete(int $id, UserAccounts $accounts): void
    {
        self::authorizeAdmin();
        $u = User::findOrFail($id);
        try {
            $accounts->delete($u, auth()->user());
        } catch (ValidationException $e) {
            $this->dispatch('toast', message: "Ο «{$u->name}» δεν διαγράφηκε: ".$e->errors()['delete'][0], type: 'error');
            return;
        }
        if ($this->editing === $u->id) {
            $this->cancel();
        }
        $this->dispatch('toast', message: "Ο χρήστης «{$u->name}» διαγράφηκε.", type: 'success');
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['search', 'roleFilter', 'stateFilter'], true)) {
            $this->resetPage();
        }
    }

    public function render(UserAccounts $accounts)
    {
        $roles = UserAccounts::roles();
        $query = User::query()->with('role')
            ->withCount(['protocols', 'assignments', 'openAssignments'])   // one query for the page (review of R6a)
            ->orderByRaw('active IS NULL')->orderBy('name');
        if (($q = trim($this->search)) !== '') {
            $like = '%'.addcslashes($q, '\\%_').'%';
            $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('username', 'like', $like)->orWhere('email', 'like', $like));
        }
        if ($this->roleFilter !== '' && ctype_digit($this->roleFilter)) {
            $query->where('role_id', (int) $this->roleFilter);
        }
        if ($this->stateFilter === 'active') {
            $query->whereNotNull('active');
        } elseif ($this->stateFilter === 'inactive') {
            $query->whereNull('active');
        }
        $users = $this->pageOf($query, 50);

        $lastSeen = DB::table(config('session.table', 'sessions'))
            ->whereIn('user_id', $users->pluck('id'))->groupBy('user_id')
            ->selectRaw('user_id, MAX(last_activity) AS t')->pluck('t', 'user_id');

        return view('livewire.user-admin', [
            'users' => $users,
            'roles' => $roles,
            'roleCounts' => User::query()->whereNotNull('active')->groupBy('role_id')->selectRaw('role_id, COUNT(*) AS n')->pluck('n', 'role_id'),
            'lastSeen' => $lastSeen,
            'accounts' => $accounts,
            'me' => auth()->id(),
            'editingUser' => $this->editing ? User::find($this->editing) : null,
            'passwordMin' => UserAccounts::PASSWORD_MIN,
        ]);
    }
}
