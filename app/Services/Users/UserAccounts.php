<?php

namespace App\Services\Users;

use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\Role;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * R6a: adding, changing and deleting user accounts (page App\Livewire\UserAdmin), with the rules on the server.
 *
 * - At least one **active administrator** always remains (the old page checked this only when editing, and let the
 *   last administrator be deleted, which reopened self-registration).
 * - Nobody deactivates, demotes or deletes their own account here (another administrator does it): this keeps an
 *   administrator from locking themselves out by mistake.
 * - A user who registered protocols or has assignments is **deactivated, not deleted**, so the lists keep their name.
 * - Deactivating a user or changing their password ends their open sessions; an inactive user cannot log in
 *   (App\Http\Middleware\EnsureActiveUser, LoginController::credentials).
 */
final class UserAccounts
{
    /** Same as the first administrator's registration (RegisterController). The old users page asked for 6. */
    public const PASSWORD_MIN = 8;

    /**
     * @param  array{name?: string, username?: string, email?: string, role_id?: int|string, active?: bool, password?: string, password_confirmation?: string}  $data
     * @throws ValidationException (keys: name, username, email, role_id, active, password)
     */
    public function save(?User $user, array $data, User $actor): User
    {
        $creating = $user === null || ! $user->exists;
        $data = [
            'name' => trim((string) ($data['name'] ?? '')),
            'username' => trim((string) ($data['username'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'role_id' => $data['role_id'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
            'password' => (string) ($data['password'] ?? ''),
            'password_confirmation' => (string) ($data['password_confirmation'] ?? ''),
        ];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'not_regex:/\s/', Rule::unique('users', 'username')->ignore($creating ? null : $user->id)],
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'password' => $creating || $data['password'] !== ''
                ? ['required', 'string', 'min:'.self::PASSWORD_MIN, 'max:255', 'confirmed']
                : ['nullable'],
        ];
        Validator::make($data, $rules, [
            'username.not_regex' => 'Το username δεν μπορεί να έχει κενά.',
            'username.unique' => 'Υπάρχει ήδη χρήστης με αυτό το username.',
            'password.confirmed' => 'Οι δύο κωδικοί δεν είναι ίδιοι.',
        ], [
            'name' => 'Ονοματεπώνυμο', 'username' => 'Username', 'email' => 'E-mail', 'role_id' => 'Ρόλος', 'password' => 'Κωδικός',
        ])->validate();

        $roleId = (int) $data['role_id'];
        $adminId = Roles::adminId();
        if (! $creating) {
            $wasActiveAdmin = (int) $user->role_id === $adminId && $user->active;
            $staysActiveAdmin = $roleId === $adminId && $data['active'];
            if ((int) $user->id === (int) $actor->id && ! $data['active'] && $user->active) {
                throw ValidationException::withMessages(['active' => 'Δεν μπορείτε να απενεργοποιήσετε τον δικό σας λογαριασμό: το κάνει άλλος Διαχειριστής.']);
            }
            if ((int) $user->id === (int) $actor->id && $wasActiveAdmin && ! $staysActiveAdmin) {
                throw ValidationException::withMessages(['role_id' => 'Δεν μπορείτε να αφαιρέσετε από τον εαυτό σας τον ρόλο Διαχειριστή: το κάνει άλλος Διαχειριστής.']);
            }
            if ($wasActiveAdmin && ! $staysActiveAdmin && self::activeAdmins() <= 1) {
                throw ValidationException::withMessages([$data['active'] ? 'role_id' : 'active' => 'Πρέπει να μείνει τουλάχιστον ένας ενεργός Διαχειριστής.']);
            }
        }

        return DB::transaction(function () use ($user, $creating, $data, $roleId) {
            $user = $creating ? new User() : $user;
            $wasActive = (bool) $user->active;
            $user->name = $data['name'];
            $user->username = $data['username'];
            $user->email = $data['email'];
            $user->role_id = $roleId;
            $user->active = $data['active'] ? 1 : null;   // the column's meaning since 2016: 1 or NULL
            $passwordChanged = $data['password'] !== '';
            if ($passwordChanged) {
                $user->password = Hash::make($data['password']);
            }
            if (! $creating && ($passwordChanged || ($wasActive && ! $data['active']))) {
                $user->remember_token = Str::random(60);   // "remember me" on other computers stops working
            }
            $user->save();
            if (! $creating && ($passwordChanged || ($wasActive && ! $data['active']))) {
                self::endSessions($user);
            }

            return $user;
        });
    }

    /** @throws ValidationException (key: delete) */
    public function delete(User $user, User $actor): void
    {
        if ($reason = $this->cannotDelete($user, $actor)) {
            throw ValidationException::withMessages(['delete' => $reason]);
        }
        DB::transaction(function () use ($user) {
            self::endSessions($user);
            \App\Models\FolderAssignment::where('user_id', $user->id)->delete();   // R6b: no foreign key there
            $user->delete();
        });
    }

    /**
     * Why this account cannot be deleted (null: it can).
     *
     * @param  array{protocols: int, assignments: int}|null  $uses  counts already loaded (the users page loads them for
     *         the whole page at once), else counted here
     */
    public function cannotDelete(User $user, User $actor, ?array $uses = null): ?string
    {
        if ((int) $user->id === (int) $actor->id) {
            return 'Δεν μπορείτε να διαγράψετε τον δικό σας λογαριασμό.';
        }
        if ((int) $user->role_id === Roles::adminId() && $user->active && self::activeAdmins() <= 1) {
            return 'Είναι ο μόνος ενεργός Διαχειριστής.';
        }
        $uses ??= $this->uses($user);
        if ($uses['protocols'] || $uses['assignments']) {
            $parts = [];
            if ($uses['protocols']) {
                $parts[] = $uses['protocols'].($uses['protocols'] === 1 ? ' πρωτόκολλο' : ' πρωτόκολλα');
            }
            if ($uses['assignments']) {
                $parts[] = $uses['assignments'].($uses['assignments'] === 1 ? ' ανάθεση' : ' αναθέσεις');
            }

            return 'Έχει '.implode(' και ', $parts).': απενεργοποιήστε τον αντί να τον διαγράψετε, ώστε να μείνει το όνομά του.';
        }

        return null;
    }

    /** @return array{protocols: int, assignments: int} */
    public function uses(User $user): array
    {
        return [
            'protocols' => Protocol::where('user_id', $user->id)->count(),
            'assignments' => ProtocolAssignment::where('user_id', $user->id)->count(),
        ];
    }

    public static function activeAdmins(): int
    {
        return User::where('role_id', Roles::adminId())->whereNotNull('active')->count();
    }


    /**
     * Logs the user out everywhere: their rows in `sessions` (SESSION_DRIVER=database). With another session driver,
     * EnsureActiveUser still logs out a deactivated user on the next request.
     */
    public static function endSessions(User $user): void
    {
        DB::table(config('session.table') ?: 'sessions')->where('user_id', $user->id)->delete();
    }

    /** All roles, in the order of the Roles descriptions (most rights first) */
    public static function roles(): \Illuminate\Support\Collection
    {
        $order = array_keys(Roles::DESCRIPTIONS);

        return Role::all()->sortBy(fn (Role $r) => ($i = array_search($r->role, $order, true)) === false ? 99 : $i)->values();
    }
}
