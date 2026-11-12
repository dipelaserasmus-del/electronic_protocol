<?php

namespace Tests\Feature;

use App\Protocol;
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
        $this->setting('gapCheckFromNumber', 50);
        $this->book(2026, [1, 3, 50, 52], '1001');

        $this->assertSame([2026 => [51]], $this->gaps());
    }

    public function test_window_crossing_new_year_checks_each_year_separately(): void
    {
        $this->book(2025, [500, 502], '1215');
        $this->book(2026, [1, 3], '0110');

        $this->assertSame([2025 => [501], 2026 => [2]], $this->gaps('2026-02-01'));
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
            ->assertSee('name="protocolnum_proposed" value="5"', false);
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

        $this->actingAs($admin)->post('/settings', $base + ['gapCheckFromNumber' => 1000, 'gapCheckMonths' => 4])
            ->assertRedirect('/settings');
        $this->assertSame('1000', \App\Config::getConfigValueOf('gapCheckFromNumber'));
        $this->assertSame('4', \App\Config::getConfigValueOf('gapCheckMonths'));

        $this->actingAs($admin)->post('/settings', $base + ['gapCheckMonths' => 99])->assertSessionHasErrors('gapCheckMonths');
    }
}
