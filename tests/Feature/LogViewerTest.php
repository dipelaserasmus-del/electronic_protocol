<?php

namespace Tests\Feature;

use App\Livewire\LogViewer;
use App\Services\Logs\LogReader;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/** R6e: the log viewer: only the application's own files, a day at a time, filters, search, everything escaped. */
class LogViewerTest extends FeatureTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/logs-' . uniqid();
        mkdir($this->dir);
        // an old single file with two days, a daily file, and files that are not the application's log
        file_put_contents("$this->dir/laravel.log", implode("\n", [
            '[2026-11-20 09:00:00] production.INFO: Πρώτη μέρα',
            '[2026-11-20 10:15:30] production.ERROR: Κάτι χάλασε {"exception":"[object] (RuntimeException(code: 0): boom at /app/X.php:12)"}',
            '[stacktrace]',
            '#0 /var/www/html/app/Services/X.php(12): App\Services\X->run()',
            '#1 {main}',
            '[2026-11-21 08:00:00] production.WARNING: Δεύτερη μέρα <script>alert(1)</script>',
        ]) . "\n");
        file_put_contents("$this->dir/laravel-2026-11-21.log", "[2026-11-21 12:00:00] production.INFO: Από το ημερήσιο αρχείο\n");
        file_put_contents("$this->dir/other.log", "[2026-11-22 12:00:00] production.INFO: ξένο αρχείο\n");
        file_put_contents("$this->dir/laravel-2026-11-23.log.gz", 'x');
        $this->app->instance(LogReader::class, new LogReader($this->dir));
        Cache::flush();
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("$this->dir/*"));
        rmdir($this->dir);
        parent::tearDown();
    }

    public function test_only_the_applications_files_split_by_day(): void
    {
        $r = new LogReader($this->dir);
        $this->assertSame(['laravel-2026-11-21.log', 'laravel.log'], array_keys($r->files()));
        $this->assertSame(['2026-11-21', '2026-11-20'], $r->days(), 'the single file split by date; other.log never read');

        $day = $r->day('2026-11-21');
        $this->assertSame(['12:00:00', '08:00:00'], array_column($day, 'time'), 'both files, newest first');
        $this->assertSame(['info', 'warning'], array_column($day, 'level'));

        $error = $r->day('2026-11-20')[0];
        $this->assertSame(['10:15:30', 'error', 'production'], [$error['time'], $error['level'], $error['channel']]);
        $this->assertStringStartsWith('Κάτι χάλασε', $error['message']);
        $this->assertSame("[stacktrace]\n#0 /var/www/html/app/Services/X.php(12): App\\Services\\X->run()\n#1 {main}", $error['details']);
        $this->assertSame([], $r->day('../../.env'), 'a day is only ever a date');
    }

    public function test_a_growing_file_and_a_large_day(): void
    {
        $r = new LogReader($this->dir);
        $this->assertSame(['2026-11-21', '2026-11-20'], $r->days());
        file_put_contents("$this->dir/laravel.log", "[2026-11-22 07:00:00] production.INFO: νέα μέρα\n", FILE_APPEND);
        $this->assertSame(['2026-11-22', '2026-11-21', '2026-11-20'], $r->days(), 'the part added is read');
        $this->assertSame('νέα μέρα', $r->day('2026-11-22')[0]['message']);

        // a day larger than the limit: its newest part
        $lines = '';
        for ($i = 0; $i < 300; $i++) {
            $lines .= sprintf("[2026-11-23 10:%02d:%02d] production.INFO: γραμμή %d\n", intdiv($i, 60), $i % 60, $i);
        }
        file_put_contents("$this->dir/laravel-2026-11-23.log", $lines);
        $r->maxBytes = 2000;
        $day = $r->day('2026-11-23');
        $this->assertTrue($r->truncated);
        $this->assertSame('γραμμή 299', $day[0]['message']);
        $this->assertLessThan(300, count($day));
    }

    public function test_administrators_only(): void
    {
        $this->actingAs($this->admin())->get('/logs')->assertOk()->assertSee('Αρχεία καταγραφής');
        $this->actingAs($this->assigner())->get('/logs')->assertRedirect();
        Livewire::actingAs($this->writer())->test(LogViewer::class)->assertForbidden();
        auth()->logout();
        $this->get('/logs')->assertRedirect('/login');
    }

    public function test_days_levels_search_and_details(): void
    {
        $page = Livewire::actingAs($this->admin())->test(LogViewer::class)
            ->assertSet('day', '2026-11-21')
            ->assertSee('Από το ημερήσιο αρχείο')->assertSee('Δεύτερη μέρα')
            ->assertSeeHtml('Δεύτερη μέρα &lt;script&gt;alert(1)&lt;/script&gt;')->assertDontSeeHtml('<script>alert(1)')
            ->call('step', -1)->assertSet('day', '2026-11-20')
            ->assertSee('Πρώτη μέρα')->assertSee('Κάτι χάλασε')->assertSee('2 από 2 καταγραφές');

        $page->set('levels', ['error'])->assertDontSee('Πρώτη μέρα')->assertSee('Κάτι χάλασε')->assertSee('1 από 2 καταγραφές')
            ->set('levels', [])->set('q', 'πρωτη')->assertSeeHtml('<mark><strong>Πρώτη</strong></mark> μέρα')->assertDontSee('Κάτι χάλασε');

        // details: the stack trace marked up (and escaped)
        $id = 'laravel.log:' . strlen("[2026-11-20 09:00:00] production.INFO: Πρώτη μέρα\n");
        $page->set('q', '')->call('toggle', $id)
            ->assertSeeHtml('<span class="lt-n">#0</span> <span class="lt-file">/var/www/html/app/Services/X.php</span>(<span class="lt-line">12</span>)')
            ->assertSeeHtml('App\Services\X-&gt;run()')
            ->call('toggle', $id)->assertDontSeeHtml('lt-file');

        $page->call('step', 1)->assertSet('day', '2026-11-21');
        $page->set('day', '2026-11-25')->assertSee('Καμία καταγραφή');
    }

    /** R6j (owner, 2026-11-25): «Σήμερα» goes to today (an empty day says so); it is off while today is shown */
    public function test_today_button(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-25 10:00'));
        $page = Livewire::actingAs($this->admin())->test(LogViewer::class)->assertSet('day', '2026-11-21')
            ->assertSeeHtml('wire:click="today"')->assertDontSeeHtml('id="log-today" disabled')
            ->assertSeeHtml('max="2026-11-25"');   // today can be chosen in the calendar too
        $page->call('today')->assertSet('day', '2026-11-25')->assertSet('page', 1)->assertSee('Καμία καταγραφή');
        $this->assertMatchesRegularExpression('~wire:click="today"\s+disabled~', $page->html());
        $page->call('step', -1)->assertSet('day', '2026-11-21');   // back to the newest day with entries
    }

    /** review of R6e: an empty file, entries of the same second in order, the whole of a long message */
    public function test_empty_files_same_second_and_long_messages(): void
    {
        file_put_contents("$this->dir/laravel-2026-11-24.log", '');
        $r = new LogReader($this->dir);
        $this->assertSame([], $r->day('2026-11-24'));
        Livewire::actingAs($this->admin())->test(LogViewer::class)->assertSet('day', '2026-11-24')->assertSee('Καμία καταγραφή');

        $lines = '';
        foreach (range(0, 12) as $i) {
            $lines .= "[2026-11-25 10:00:00] production.INFO: μήνυμα $i\n";
        }
        file_put_contents("$this->dir/laravel-2026-11-25.log", $lines);
        $this->assertSame(array_map(fn ($i) => "μήνυμα $i", range(12, 0)), array_column($r->day('2026-11-25'), 'message'));

        $long = str_repeat('α', 700) . ' ΤΕΛΟΣ';
        file_put_contents("$this->dir/laravel-2026-11-26.log", "[2026-11-26 10:00:00] production.ERROR: $long\n");
        $page = Livewire::actingAs($this->admin())->test(LogViewer::class)->set('day', '2026-11-26')->assertDontSee('ΤΕΛΟΣ');
        $id = (new LogReader($this->dir))->day('2026-11-26')[0]['id'];
        $page->call('toggle', $id)->assertSee('ΤΕΛΟΣ');
    }
}
