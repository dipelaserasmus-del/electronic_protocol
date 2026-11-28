<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Password;

/** Z01b: the sign-in pages on the new look (layouts.modern); the old layout (Bootstrap 3, jQuery, Vue 1) is gone. */
class SignInPagesTest extends FeatureTestCase
{
    private const OLD = ['css/bootstrap.min.css', 'js/app.js', 'toastr', 'panel-heading', 'col-md-offset'];

    private function assertNewLook(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertOk()->assertSee('app-topbar', false);   // tests run without Vite: no /build/ link
        foreach (self::OLD as $old) {
            $response->assertDontSee($old, false);
        }
    }

    public function test_the_login_page(): void
    {
        $page = $this->get('/login');
        $this->assertNewLook($page);
        $page->assertSee('<h1 class="h4 text-center mb-4">Είσοδος</h1>', false)
            ->assertSee('name="username"', false)->assertSee('name="password"', false)->assertSee('name="remember"', false)
            ->assertSee('Ξεχάσατε τον κωδικό;')->assertDontSee('id="nav-user"', false);
    }

    public function test_a_wrong_password_is_shown_under_the_field_and_the_name_is_kept(): void
    {
        $user = User::factory()->writer()->create();
        $this->followingRedirects()->from('/login')->post('/login', ['username' => $user->username, 'password' => 'λάθος'])
            ->assertSee('is-invalid', false)->assertSee('value="'.$user->username.'"', false)->assertSee('invalid-feedback', false);
    }

    public function test_the_register_link_only_while_registration_is_open(): void
    {
        // the test database has no administrator: registration is open (S00), the page is on the new look
        $this->get('/login')->assertSee('id="login-register"', false);
        $this->assertNewLook($this->get('/register'));
        $this->get('/register')->assertSee('name="password_confirmation"', false);

        $this->admin();
        $this->get('/login')->assertDontSee('id="login-register"', false);
        $this->get('/register')->assertRedirect('login');
    }

    public function test_the_forgot_and_reset_password_pages(): void
    {
        $this->assertNewLook($this->get('/password/resetForm'));
        $this->get('/password/resetForm')->assertSee('name="email"', false)->assertSee('Αποστολή συνδέσμου');

        $user = User::factory()->writer()->create();
        $token = Password::broker()->createToken($user);
        $page = $this->get('/password/reset?token='.$token.'&username='.urlencode($user->username));
        $this->assertNewLook($page);
        $page->assertSee('name="token" value="'.$token.'"', false)->assertSee('value="'.$user->username.'"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_the_old_layout_and_its_files_are_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/layouts/app.blade.php'));
        foreach (['js/app.js', 'js/jquery.min.js', 'css/bootstrap.min.css', 'js/toastr.min.js', 'fonts/glyphicons-halflings-regular.woff'] as $file) {
            $this->assertFileDoesNotExist(public_path($file));
        }
    }
}
