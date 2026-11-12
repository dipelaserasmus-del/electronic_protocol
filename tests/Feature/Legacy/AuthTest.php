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

        $this->get('/register')->assertOk()->assertDontSee('password_confirmation', false);
    }

    /**
     * KNOWN VULNERABILITY (task S00): POST /register stays open after registration is closed,
     * and new users get role_id 1 = Διαχειριστής. This test documents the current behaviour;
     * S00 replaces it with a test asserting the request is refused.
     */
    public function test_known_vulnerability_anonymous_post_register_creates_admin_when_closed(): void
    {
        $this->admin();
        file_put_contents(storage_path('conf/.denyregister'), 'x');
        $this->refreshApplication();

        $this->post('/register', [
            'name' => 'Εισβολέας', 'username' => 'intruder', 'email' => 'intruder@example.test',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
        ]);

        $intruder = User::whereUsername('intruder')->first();
        $this->assertNotNull($intruder, 'registration was refused (fixed?)');
        $this->assertSame('Διαχειριστής', $intruder->role->role);
    }

}
