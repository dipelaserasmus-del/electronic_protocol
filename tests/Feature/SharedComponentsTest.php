<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/** N04: shared Blade components of the rebuilt pages. Their behaviour in the browser: e2e/tests/components.spec.js. */
class SharedComponentsTest extends FeatureTestCase
{
    public function test_component_page_needs_login_and_shows_every_component(): void
    {
        $this->get('/dev/ui')->assertRedirect('/login');

        $this->actingAs($this->writer())->get('/dev/ui')->assertOk()
            ->assertSee('data-date-input', false)
            ->assertSee('data-pill-actions', false)
            ->assertSee('file-badge', false)
            ->assertSee('id="app-confirm"', false)
            ->assertSee('id="app-toasts"', false);
    }

    public function test_component_page_does_not_exist_in_production(): void
    {
        $result = Process::env(['APP_ENV' => 'production', 'APP_DEBUG' => 'false'])
            ->path(base_path())->run(['php', 'artisan', 'route:list', '--path=dev', '--json']);

        $this->assertSame([], json_decode($result->output(), true) ?? [], $result->errorOutput());
    }

    public function test_date_input_markup(): void
    {
        $html = $this->render('<x-date-input name="in_date" label="Ημ/νία" value="09/03/2026" wire:model="date" class="col-6" />');

        $this->assertStringContainsString('name="in_date"', $html);
        $this->assertStringContainsString('value="09/03/2026"', $html);
        $this->assertStringContainsString('<label for="date-in-date"', $html);
        $this->assertMatchesRegularExpression('/<input type="text"[^>]*wire:model="date"/s', $html, 'wire: attributes go on the input');
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*wire:model/s', $html, '… not on the wrapper');
        $this->assertStringContainsString('class="date-input col-6"', $html);
        $this->assertSame(4, substr_count($html, 'data-date-action='), 'prev, next, today, calendar');
    }

    public function test_read_only_date_input_has_its_buttons_disabled(): void
    {
        $html = $this->render('<x-date-input name="d" :readonly="true" />');

        $this->assertStringContainsString('readonly', $html);
        $this->assertSame(4, substr_count($html, 'disabled'));
    }

    public function test_date_input_shows_the_validation_error_for_its_field(): void
    {
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['in_date' => 'Η ημερομηνία είναι υποχρεωτική.'])));

        $html = $this->render('<x-date-input name="in_date" />');

        $this->assertStringContainsString('is-invalid', $html);
        $this->assertStringContainsString('Η ημερομηνία είναι υποχρεωτική.', $html);
    }

    public function test_pills_are_links_or_buttons(): void
    {
        $html = $this->render('<x-pill-actions><x-pill href="/receipt/1">Απόδειξη</x-pill>'
            .'<x-pill type="submit" variant="primary">Αποθήκευση</x-pill>'
            .'<x-pill variant="danger" data-confirm="Να διαγραφεί;">Διαγραφή</x-pill></x-pill-actions>');

        $this->assertStringContainsString('<a href="/receipt/1" class="pill btn rounded-pill text-nowrap btn-outline-secondary">Απόδειξη</a>', $html);
        $this->assertStringContainsString('<button type="submit" class="pill btn rounded-pill text-nowrap btn-primary">Αποθήκευση</button>', $html);
        $this->assertStringContainsString('data-confirm="Να διαγραφεί;"', $html);
        $this->assertStringContainsString('aria-label="Περισσότερες ενέργειες"', $html, 'the ⋯ menu for narrow screens');
    }

    public function test_file_badge_and_notice(): void
    {
        $badge = $this->render('<x-file-badge name="Απόφαση.pdf" :size="1258291" />');
        $this->assertStringContainsString('text-bg-danger">PDF</span>', $badge);
        $this->assertStringContainsString('1,2 MB', $badge);

        $notice = $this->render('<x-notice type="warning">Ο αριθμός {{ $n }} είναι δεσμευμένος.</x-notice>', ['n' => '<b>124</b>']);
        $this->assertStringContainsString('alert-warning', $notice);
        $this->assertStringContainsString('&lt;b&gt;124&lt;/b&gt;', $notice, 'content is escaped');
        $this->assertStringContainsString('role="alert"', $this->render('<x-notice type="error">x</x-notice>'));
    }

    public function test_toast_from_the_session_is_passed_to_the_page_as_text(): void
    {
        $this->actingAs($this->writer())->withSession(['toast' => ['Αποθηκεύτηκε <script>x</script>', 'success']])
            ->get('/about')
            ->assertSee('data-toast-message="Αποθηκεύτηκε &lt;script&gt;x&lt;/script&gt;"', false)
            ->assertSee('data-toast-type="success"', false);
    }

    private function render(string $blade, array $data = []): string
    {
        return Blade::render($blade, $data, deleteCachedView: true);
    }
}
