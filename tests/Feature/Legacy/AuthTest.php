<?php

namespace Tests\Feature\Legacy;

use App\User;
use Tests\Feature\FeatureTestCase;

class AuthTest extends FeatureTestCase
{
    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('password', false);
    }

    public function test_user_logs_in_with_username(): void
    {
        $user = $this->writer();

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = $this->writer();

        $this->post('/login', ['username' => $user->username, 'password' => 'wrong']);
        $this->assertGuest();
    }

    public function test_guest_is_redirected_from_protocol_list(): void
    {
        $this->get('/home/list')->assertRedirect('/login');
    }

    public function test_first_registered_user_becomes_administrator_and_closes_registration(): void
    {
        $this->post('/register', [
            'name' => 'Πρώτος Διαχειριστής', 'username' => 'first', 'email' => 'first@school.test',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ])->assertRedirect();

        $user = User::whereUsername('first')->firstOrFail();
        $this->assertSame('Διαχειριστής', $user->role->role);
        $this->assertFileExists(storage_path('conf/.denyregister'));
    }

    public function test_registration_form_is_hidden_once_registration_is_closed(): void
    {
        file_put_contents(storage_path('conf/.denyregister'), 'x');
        $this->refreshApplication();   // routes are decided when the app boots

        $this->get('/register')->assertRedirect('login');
    }

    private function registerPayload(string $username = 'intruder'): array
    {
        return [
            'name' => 'Εισβολέας', 'username' => $username, 'email' => "$username@example.test",
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ];
    }

    /** S00 (was: known_vulnerability_anonymous_post_register_creates_admin_when_closed) */
    public function test_anonymous_post_register_is_refused_when_registration_is_closed(): void
    {
        $this->admin();
        file_put_contents(storage_path('conf/.denyregister'), 'x');
        $this->refreshApplication();

        $this->post('/register', $this->registerPayload())->assertForbidden();

        $this->assertNull(User::whereUsername('intruder')->first());
    }

    /** S00: deleting the marker file is not enough while an administrator exists */
    public function test_post_register_is_refused_while_an_admin_exists_even_without_marker_file(): void
    {
        $this->admin();

        $this->post('/register', $this->registerPayload())->assertForbidden();

        $this->assertNull(User::whereUsername('intruder')->first());
    }

    public function test_register_form_redirects_to_login_while_an_admin_exists(): void
    {
        $this->admin();

        $this->get('/register')->assertRedirect('login');
    }

    public function test_inactive_admins_do_not_block_first_registration(): void
    {
        User::factory()->admin()->inactive()->create();

        $this->post('/register', $this->registerPayload('newadmin'))->assertRedirect();

        $this->assertSame('Διαχειριστής', User::whereUsername('newadmin')->first()->role->role);
    }

    public function test_registration_is_rate_limited(): void
    {
        $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', $this->registerPayload("u$i"));
        }
        $this->post('/register', $this->registerPayload('u6'))->assertStatus(429);
    }

    /** S00: a user row created without an explicit role is no longer an administrator */
    public function test_default_role_is_reader(): void
    {
        $id = \DB::table('users')->insertGetId([
            'name' => 'Χωρίς ρόλο', 'username' => 'norole', 'email' => 'n@example.test', 'password' => 'x',
        ]);

        $this->assertSame('Αναγνώστης', User::find($id)->role->role);
    }
}
