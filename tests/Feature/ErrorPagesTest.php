<?php

namespace Tests\Feature;

use App\Protocol;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/** B04: descriptive error pages with a reference code; technical details only for administrators. */
class ErrorPagesTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);   // production behaviour
        Route::middleware('web')->get('/__boom', fn () => throw new \RuntimeException('internal detail xyz'));
        Route::middleware('web')->post('/__boom-json', fn () => throw new \RuntimeException('internal detail xyz'));
    }

    public function test_user_sees_friendly_message_and_reference_code_but_no_details(): void
    {
        $response = $this->actingAs($this->writer())->get('/__boom');

        $response->assertStatus(500)
            ->assertSee('Κάτι πήγε στραβά')
            ->assertSee('Κωδικός σφάλματος')
            ->assertDontSee('internal detail xyz')
            ->assertDontSee('RuntimeException');
        $this->assertMatchesRegularExpression('/E-[A-Z0-9]{6}/', $response->getContent());
    }

    public function test_admin_also_sees_technical_details(): void
    {
        $this->actingAs($this->admin())->get('/__boom')
            ->assertStatus(500)
            ->assertSee('Τεχνικές λεπτομέρειες')
            ->assertSee('RuntimeException')
            ->assertSee('internal detail xyz')
            ->assertSee('tests/Feature/ErrorPagesTest.php');
    }

    public function test_reference_code_is_written_to_the_log_with_the_error(): void
    {
        $logged = [];
        Log::listen(function ($event) use (&$logged) { $logged[] = $event; });

        $html = $this->actingAs($this->writer())->get('/__boom')->getContent();
        preg_match('/E-[A-Z0-9]{6}/', $html, $m);

        $entry = collect($logged)->first(fn ($e) => ($e->context['error_id'] ?? null) === $m[0]);
        $this->assertNotNull($entry, 'log entry with the shown reference code');
        $this->assertStringContainsString('internal detail xyz', $entry->message);
        $this->assertSame('/__boom', parse_url($entry->context['url'], PHP_URL_PATH));
    }

    public function test_ajax_requests_get_json_with_the_reference_code(): void
    {
        $this->actingAs($this->writer())->postJson('/__boom-json')
            ->assertStatus(500)
            ->assertJsonStructure(['message', 'error_id'])
            ->assertJsonMissingPath('details');
    }

    public function test_greek_pages_for_common_http_errors(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $hidden = Protocol::factory()->number(1)->create();

        $this->actingAs($this->writer())->get("/home/{$hidden->id}")->assertNotFound()->assertSee('Η σελίδα δεν βρέθηκε');
        $this->actingAs($this->writer())->get('/delprotocol/' . $hidden->id)->assertForbidden()->assertSee('Δεν επιτρέπεται');
    }

    public function test_expired_form_shows_page_expired_explanation(): void
    {
        $this->actingAs($this->writer());
        // a real CSRF check (the testing environment normally skips it)
        $this->app->instance('env', 'production');
        $this->post('/home', $this->protocolForm())->assertStatus(419)->assertSee('Η σελίδα έληξε');
    }

    public function test_debug_mode_keeps_laravel_debug_page(): void
    {
        config(['app.debug' => true]);
        // Laravel's debug page shows the exception even to a non-admin (development only)
        $this->actingAs($this->writer())->get('/__boom')->assertStatus(500)->assertSee('internal detail xyz');
    }
}
