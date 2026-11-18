<?php

namespace Tests\Feature;

use App\Livewire\UserAdmin;
use App\Models\Protocol;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/** R6a: the users page (App\Livewire\UserAdmin) and its rules (App\Services\Users\UserAccounts). */
class UserAdminTest extends FeatureTestCase
{
    private function roleId(string $role): int
    {
        return (int) \App\Models\Role::whereRole($role)->value('id');
    }

    private function page(User $admin, array $params = [])
    {
        return Livewire::actingAs($admin)->test(UserAdmin::class, $params);
    }

    public function test_only_administrators_open_the_page(): void
    {
        $this->actingAs($this->admin())->get('/users')->assertOk()->assertSee('Χρήστες')->assertSee('Ρόλοι');
        foreach ([$this->assigner(), $this->writer(), $this->reader()] as $u) {
            $this->actingAs($u)->get('/users')->assertRedirect('/home');
        }
    }

    public function test_old_write_routes_are_gone(): void
    {
        $admin = $this->admin();
        $other = $this->writer();
        $this->actingAs($admin)->get('/users/del/'.$other->id)->assertNotFound();
        $this->assertNotNull($other->fresh());
        $this->actingAs($admin)->post('/users', ['name' => 'x'])->assertStatus(405);
    }

    public function test_adds_a_user(): void
    {
        $this->page($this->admin())
            ->call('create')
            ->set('form.name', 'Νέα Χρήστρια')->set('form.username', 'nea')->set('form.email', 'nea@example.org')
            ->set('form.role_id', (string) $this->roleId(Roles::WRITER))
            ->set('form.password', 'secret123')->set('form.password_confirmation', 'secret123')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast')
            ->assertSet('formOpen', false);

        $u = User::where('username', 'nea')->firstOrFail();
        $this->assertSame(1, (int) $u->active);
        $this->assertTrue(Hash::check('secret123', $u->password));
        $this->assertSame($this->roleId(Roles::WRITER), (int) $u->role_id);
    }

    public function test_new_user_needs_a_long_enough_confirmed_password_and_a_free_username(): void
    {
        $this->writer()->forceFill(['username' => 'taken'])->save();
        $this->page($this->admin())
            ->call('create')
            ->set('form.name', 'Χ')->set('form.username', 'taken')->set('form.email', 'not-an-email')
            ->set('form.password', 'short')->set('form.password_confirmation', 'other')
            ->call('save')
            ->assertHasErrors(['form.username', 'form.email', 'form.role_id', 'form.password']);

        $this->page($this->admin())
            ->call('create')
            ->set('form.name', 'Χ')->set('form.username', 'two words')->set('form.email', 'x@example.org')
            ->set('form.role_id', (string) $this->roleId(Roles::WRITER))
            ->set('form.password', 'longenough')->set('form.password_confirmation', 'longenough')
            ->call('save')
            ->assertHasErrors(['form.username']);
    }

    public function test_editing_keeps_the_password_when_left_empty_and_ends_sessions_when_changed(): void
    {
        $admin = $this->admin();
        $u = $this->writer();
        $old = $u->password;
        DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);

        $this->page($admin)->call('edit', $u->id)->set('form.name', 'Άλλο Όνομα')->call('save')->assertHasNoErrors();
        $this->assertSame('Άλλο Όνομα', $u->fresh()->name);
        $this->assertSame($old, $u->fresh()->password);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $u->id)->count());

        $this->page($admin)->call('edit', $u->id)
            ->set('form.password', 'newpass123')->set('form.password_confirmation', 'newpass123')->call('save')->assertHasNoErrors();
        $this->assertTrue(Hash::check('newpass123', $u->fresh()->password));
        $this->assertSame(0, DB::table('sessions')->where('user_id', $u->id)->count());
    }

    public function test_users_id_address_opens_that_user(): void
    {
        $u = $this->writer();
        $this->page($this->admin(), ['user' => $u->id])
            ->assertSet('editing', $u->id)->assertSet('formOpen', true)->assertSet('form.username', $u->username);
        $this->actingAs($this->admin())->get('/users/'.$u->id)->assertOk()->assertSee('Επεξεργασία: '.$u->name);
    }

    public function test_the_last_active_administrator_cannot_be_demoted_deactivated_or_deleted(): void
    {
        $admin = $this->admin();
        $other = $this->admin();   // two administrators: "other" may lose the role…
        $this->page($admin)->call('edit', $other->id)->set('form.role_id', (string) $this->roleId(Roles::WRITER))->call('save')->assertHasNoErrors();

        // …now $admin is the only one; a second administrator edits it: refused (would leave none)
        $second = $this->admin();
        $second->forceFill(['active' => null])->save();   // inactive administrators don't count
        $accounts = app(\App\Services\Users\UserAccounts::class);
        try {
            $accounts->save($admin, ['name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email,
                'role_id' => $this->roleId(Roles::ADMIN), 'active' => false], $other);
            $this->fail('the last active administrator was deactivated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('τουλάχιστον ένας ενεργός Διαχειριστής', $e->errors()['active'][0]);
        }
        $this->assertSame('Είναι ο μόνος ενεργός Διαχειριστής.', $accounts->cannotDelete($admin, $other));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        try {
            $accounts->delete($admin, $other);
        } finally {
            $this->assertNotNull($admin->fresh());
        }
    }

    public function test_nobody_deactivates_demotes_or_deletes_themselves(): void
    {
        $admin = $this->admin();
        $this->admin();   // another administrator exists, still refused
        $this->page($admin)->call('edit', $admin->id)->set('form.active', false)->call('save')->assertHasErrors(['form.active']);
        $this->page($admin)->call('edit', $admin->id)->set('form.role_id', (string) $this->roleId(Roles::READER))->call('save')->assertHasErrors(['form.role_id']);
        $this->page($admin)->call('delete', $admin->id)->assertDispatched('toast', type: 'error');
        $this->assertSame(1, (int) $admin->fresh()->active);
        $this->assertSame($this->roleId(Roles::ADMIN), (int) $admin->fresh()->role_id);
    }

    public function test_a_user_with_protocols_is_deactivated_not_deleted(): void
    {
        $admin = $this->admin();
        $u = $this->writer();
        Protocol::factory()->create(['user_id' => $u->id]);
        $this->page($admin)->call('delete', $u->id)->assertDispatched('toast', type: 'error');
        $this->assertNotNull($u->fresh());
        $this->assertStringContainsString('απενεργοποιήστε', app(\App\Services\Users\UserAccounts::class)->cannotDelete($u, $admin));

        $free = $this->writer();
        DB::table('sessions')->insert(['id' => 'sess-2', 'user_id' => $free->id, 'payload' => '', 'last_activity' => time()]);
        $this->page($admin)->call('delete', $free->id)->assertDispatched('toast', type: 'success');
        $this->assertNull($free->fresh());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $free->id)->count());
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        $u = User::factory()->writer()->inactive()->create(['username' => 'gone']);
        $this->post('/login', ['username' => 'gone', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();

        $u->forceFill(['active' => 1])->save();
        $this->post('/login', ['username' => 'gone', 'password' => 'password']);
        $this->assertAuthenticatedAs($u);
    }

    public function test_a_user_deactivated_while_logged_in_is_logged_out_on_the_next_request(): void
    {
        $u = User::factory()->writer()->create(['username' => 'leaving']);
        $this->post('/login', ['username' => 'leaving', 'password' => 'password']);
        $this->assertAuthenticatedAs($u);
        $this->app['auth']->forgetGuards();   // the next request reads the user again, as a real one does
        $u->forceFill(['active' => null])->save();
        $this->get('/home/list')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_search_and_filters(): void
    {
        $admin = $this->admin();
        User::factory()->writer()->create(['name' => 'Αλέξης Δοκιμαστικός', 'username' => 'alexis']);
        User::factory()->reader()->inactive()->create(['name' => 'Βασιλική Ανενεργή', 'username' => 'vasiliki']);

        $this->page($admin)->set('search', 'alex')->assertSee('Αλέξης Δοκιμαστικός')->assertDontSee('Βασιλική Ανενεργή');
        $this->page($admin)->set('stateFilter', 'inactive')->assertSee('Βασιλική Ανενεργή')->assertDontSee('Αλέξης Δοκιμαστικός');
        $this->page($admin)->set('roleFilter', (string) $this->roleId(Roles::READER))->assertSee('Βασιλική Ανενεργή')->assertDontSee('Αλέξης Δοκιμαστικός');
        $this->page($admin)->set('search', '%')->assertDontSee('Αλέξης Δοκιμαστικός');   // LIKE wildcards are escaped
    }

    public function test_actions_refuse_non_administrators(): void
    {
        $writer = $this->writer();
        $victim = $this->reader();
        Livewire::actingAs($writer)->test(UserAdmin::class)->assertForbidden();
        $this->assertNotNull($victim->fresh());
    }
}
