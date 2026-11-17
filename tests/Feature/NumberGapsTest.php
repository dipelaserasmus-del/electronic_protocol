<?php

namespace Tests\Feature;

use App\Models\Protocol;
use App\Services\NumberGaps;
use Carbon\Carbon;

/** A02: missing-number reminder on the protocol list. */
class NumberGapsTest extends FeatureTestCase
{
    private function book(int $year, array $numbers, string $mmdd = '0601'): void
    {
        foreach ($numbers as $n) {
            Protocol::factory()->number($n, $year)->onDate($year . $mmdd)->create();
        }
    }

    private function gaps(string $today = '2026-11-12'): array
    {
        return app(NumberGaps::class)->find(Carbon::parse($today));
    }

    public function test_finds_a_deleted_number_between_two_existing_ones(): void
    {
        $this->book(2026, [998, 999, 1001, 1002], '1001');

        $this->assertSame([2026 => [1000]], $this->gaps());
    }

    public function test_no_gaps_means_empty_result(): void
    {
        $this->book(2026, [1, 2, 3], '1001');

        $this->assertSame([], $this->gaps());
    }

    public function test_only_protocols_inside_the_window_are_checked(): void
    {
        $this->setting('gapCheckMonths', 3);
        $this->book(2026, [1, 3], '0201');     // February: outside a 3-month window
        $this->book(2026, [10, 12], '1001');   // October: inside

        $this->assertSame([2026 => [11]], $this->gaps());
    }

    public function test_start_number_setting_ignores_lower_numbers(): void
    {
        $this->setting('firstProtocolNum', 50);   // R2c: the start number
        $this->book(2026, [1, 3, 50, 52], '1001');

        $this->assertSame([2026 => [51]], $this->gaps());
    }

    public function test_window_crossing_new_year_checks_each_year_separately(): void
    {
        $this->book(2025, [500, 502], '1215');
        $this->book(2026, [1, 3], '0110');

        $this->assertSame([2025 => [501], 2026 => [2]], $this->gaps('2026-02-01'));
    }

    /** Review finding: start number above the last entry must not produce descending "gaps" */
    public function test_start_number_above_last_entry_gives_no_gaps(): void
    {
        $this->setting('firstProtocolNum', 10);
        $this->book(2026, [1, 2, 3], '1001');

        $this->assertSame([], $this->gaps());
    }

    /** Review finding: links must open the form in the same year as the gap */
    public function test_gap_links_only_for_the_active_protocol_year(): void
    {
        $this->setting('protocolValidate', 0);
        $year = now()->year;
        $this->book($year, [1, 2, 4], now()->format('md'));
        $admin = $this->admin();

        $this->setting('yearInUse', $year - 1);   // form would save into last year's book
        $this->actingAs($admin)->get('/home/list')->assertSee('Κενά στην αρίθμηση')->assertDontSee('/home?num=3', false);

        $this->setting('yearInUse', $year);
        $this->actingAs($admin)->get('/home/list')->assertSee('/home?num=3', false);
    }

    public function test_compact_formatting(): void
    {
        $this->assertSame('37, 88, 100–105', NumberGaps::compact([37, 88, 100, 101, 102, 103, 104, 105]));
    }

    public function test_banner_is_shown_on_protocol_list(): void
    {
        $year = now()->year;
        $this->book($year, [1, 2, 4], now()->format('md'));

        $this->actingAs($this->writer())->get('/home/list')
            ->assertOk()->assertSee('Κενά στην αρίθμηση')->assertSee("έτος $year");
    }

    public function test_banner_is_hidden_without_gaps_and_for_readers(): void
    {
        $year = now()->year;
        $this->book($year, [1, 2, 3], now()->format('md'));
        $this->actingAs($this->writer())->get('/home/list')->assertDontSee('Κενά στην αρίθμηση');

        Protocol::factory()->number(5, $year)->onDate(now()->format('Ymd'))->create();
        $this->actingAs($this->reader())->get('/home/list')->assertDontSee('Κενά στην αρίθμηση');
    }

    public function test_gap_link_prefills_number_for_admin_who_can_choose_numbers(): void
    {
        $this->setting('protocolValidate', 0);
        $year = now()->year;
        $this->book($year, [1, 2, 4], now()->format('md'));
        $admin = $this->admin();

        $this->actingAs($admin)->get('/home/list')->assertSee('/home?num=3', false);
        $this->actingAs($admin)->get('/home?num=3')->assertSee('value="3"', false)
            ->assertSee('placeholder="5"', false);   // R1b: the next free number is shown as a hint
    }

    public function test_filling_the_gap_saves_the_chosen_number(): void
    {
        $this->setting('protocolValidate', 0);
        $year = now()->year;
        $this->book($year, [1, 2, 4], now()->format('md'));

        $this->actingAs($this->admin())->post('/home', $this->protocolForm([
            'protocolnum' => 3, 'protocolnum_proposed' => 5, 'thema' => 'Συμπλήρωση κενού',
        ]));

        $this->assertDatabaseHas('protocols', ['protocolnum' => 3, 'etos' => $year, 'thema' => 'Συμπλήρωση κενού']);
        $this->assertSame([], app(NumberGaps::class)->find());
    }

    public function test_settings_are_saved_and_validated(): void
    {
        $admin = $this->admin();
        $base = ['ipiresiasName' => 'Σχολείο', 'firstProtocolNum' => 1, 'showRowsInPage' => 25,
                 'protocolArrowStep' => 10, 'minutesRefreshInterval' => 5];

        $this->actingAs($admin)->post('/settings', ['firstProtocolNum' => 1000] + $base + ['gapCheckFromNumber' => 7, 'gapCheckMonths' => 4])
            ->assertRedirect('/settings');
        $this->assertSame('1000', \App\Models\Config::getConfigValueOf('firstProtocolNum'));
        $this->assertFalse(\App\Models\Config::where('key', 'gapCheckFromNumber')->exists(), 'R2c: the old setting is not stored');
        // 0 is the installer's value (= 1): the page must still save
        $this->actingAs($admin)->post('/settings', ['firstProtocolNum' => 0] + $base)->assertSessionHasNoErrors();
        $this->assertSame('4', \App\Models\Config::getConfigValueOf('gapCheckMonths'));

        $this->actingAs($admin)->post('/settings', $base + ['gapCheckMonths' => 99])->assertSessionHasErrors('gapCheckMonths');
    }

    /** R2c (owner): missing numbers are rows of the list, a run of them one row, with a button for its first number. */
    public function test_missing_numbers_are_rows_in_the_list(): void
    {
        $this->setting('protocolValidate', 0);
        $year = now()->year;
        $this->book($year, [1, 2, 4, 8, 9], now()->format('md'));   // missing: 3, 5–7
        $admin = $this->admin();

        $html = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\ProtocolList::class)->html();
        $this->assertSame(2, substr_count($html, 'class="gap-row"'));
        $this->assertStringContainsString("Κενοί αριθμοί 5–7/$year", $html);
        $this->assertStringContainsString('· 3 αριθμοί', $html);
        $this->assertStringContainsString("Κενός αριθμός 3/$year", $html);
        // newest first: 9, 8, [7–5], 4, [3], 2, 1 — the button offers the highest number of the run
        $order = array_map(fn ($t) => strpos($html, $t), ["8/$year", "Κενοί αριθμοί 5–7", "4/$year", "Κενός αριθμός 3", "2/$year"]);
        $sorted = $order; sort($sorted);
        $this->assertSame($sorted, $order, 'rows in number order');
        $this->assertStringContainsString('/home?num=7', $html);
        $this->assertStringContainsString('/home?num=3', $html);
        $this->assertStringContainsString('Φαίνονται στη λίστα με διακεκομμένο πλαίσιο', $html);

        // ascending: the run follows the protocol below it, the button offers its lowest number
        $asc = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\ProtocolList::class)->call('sortBy', 'protocolnum')->html();
        $this->assertStringContainsString('/home?num=5', $asc);

        // another filter or sort: no rows, the banner says where to see them
        $other = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\ProtocolList::class)->call('setFilter', 'd')->html();
        $this->assertStringNotContainsString('class="gap-row"', $other);
        $this->assertStringContainsString('Επιλέξτε «Όλα»', $other);

        // a writer sees the rows but can't choose the number: no button
        $writer = \Livewire\Livewire::actingAs($this->writer())->test(\App\Livewire\ProtocolList::class)->html();
        $this->assertSame(2, substr_count($writer, 'class="gap-row"'));
        $this->assertStringNotContainsString('/home?num=', $writer);
    }

    /** R2c: the gap check starts at "Αρ.Πρωτοκόλλου για ξεκίνημα"; the old separate setting moves there. */
    public function test_old_gap_start_setting_moves_to_the_start_number(): void
    {
        $this->setting('gapCheckFromNumber', 500);
        $migration = require base_path('database/migrations/2026_11_17_000001_gap_check_from_first_number.php');
        $migration->up();
        $migration->up();   // safe to run again
        $this->assertSame('500', \App\Models\Config::where('key', 'firstProtocolNum')->value('value'));
        $this->assertFalse(\App\Models\Config::where('key', 'gapCheckFromNumber')->exists());
    }
}
