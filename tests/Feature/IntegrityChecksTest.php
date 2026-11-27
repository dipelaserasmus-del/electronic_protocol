<?php

namespace Tests\Feature;

use App\Livewire\IntegrityChecks;
use App\Livewire\ProtocolList;
use App\Models\Protocol;
use App\Services\Integrity\Checks;
use App\Services\Integrity\Finding;
use App\Services\Integrity\Rule;
use App\Services\Integrity\Rules\NumberGapRule;
use App\Services\NumberGaps;
use App\Support\Navigation;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/** A01: the integrity-check engine, the «Έλεγχοι» page and the list's line. */
class IntegrityChecksTest extends FeatureTestCase
{
    private function book(int $year, array $numbers, string $mmdd): void
    {
        foreach ($numbers as $n) {
            Protocol::factory()->number($n, $year)->onDate($year.$mmdd)->create();
        }
    }

    private function between(string $from, string $to, string $today = '2026-11-12'): array
    {
        return app(NumberGaps::class)->between(Carbon::parse($from), Carbon::parse($to), Carbon::parse($today));
    }

    public function test_gaps_between_the_protocols_of_a_range(): void
    {
        $this->book(2026, [1, 3], '0201');
        $this->book(2026, [10, 12, 13, 17], '1101');

        // 4–9 are missing next to 10, the range's first protocol, so November reports them too (and February)
        $this->assertSame([2026 => [4, 5, 6, 7, 8, 9, 11, 14, 15, 16]], $this->between('2026-11-01', '2026-11-12'));
        $this->assertSame([2026 => [2, 4, 5, 6, 7, 8, 9]], $this->between('2026-02-01', '2026-02-28'));
        $this->assertSame([], $this->between('2026-11-02', '2026-11-12'), 'no protocol dated in the range: nothing to check');
    }

    public function test_a_gap_at_the_edge_of_the_range_counts(): void
    {
        // 5 is dated 31/10, 7 on 1/11: the missing 6 sits between the ranges, so both of them report it
        $this->book(2026, [4, 5], '1031');
        $this->book(2026, [7, 8], '1101');

        $this->assertSame([2026 => [6]], $this->between('2026-11-01', '2026-11-12'));
        $this->assertSame([2026 => [6]], $this->between('2026-10-01', '2026-10-31'));
        $this->assertSame([], $this->between('2026-09-01', '2026-09-30'), 'no protocols in the range');
    }

    public function test_ranges_over_new_year_and_the_start_number(): void
    {
        $this->book(2025, [100, 102], '1220');
        $this->book(2026, [1, 3, 50, 52], '0105');
        $this->setting('firstProtocolNum', 50);   // the current year only, as A02

        $this->assertSame([2025 => [101], 2026 => [51]], $this->between('2025-12-01', '2026-01-31', '2026-01-31'));
    }

    public function test_the_gap_rule_names_the_protocols_around_each_run(): void
    {
        Protocol::factory()->number(87, 2026)->onDate('20261103')->create();
        Protocol::factory()->number(89, 2026)->onDate('20261105')->create();
        Protocol::factory()->number(95, 2026)->onDate('20261106')->create();

        $findings = app(NumberGapRule::class)->findings(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'));

        $this->assertSame(['88/2026', '90–94/2026'], array_map(fn (Finding $f) => $f->ref, $findings));
        $this->assertSame('Λείπει 1 αριθμός, ανάμεσα στο 87 (03/11/2026) και στο 89 (05/11/2026).', $findings[0]->message);
        $this->assertSame('Λείπουν 5 αριθμοί, ανάμεσα στο 89 (05/11/2026) και στο 95 (06/11/2026).', $findings[1]->message);
        $this->assertNull($findings[0]->protocolId);
        $this->assertSame(['year' => 2026, 'from' => 90, 'to' => 94], $findings[1]->meta);
    }

    public function test_the_page_lists_the_findings_of_the_last_30_days(): void
    {
        $today = CarbonImmutable::today();
        Protocol::factory()->number(1, $today->year)->onDate($today->format('Ymd'))->create();
        Protocol::factory()->number(3, $today->year)->onDate($today->format('Ymd'))->create();
        $this->setting('yearInUse', $today->year);
        $this->setting('protocolValidate', 0);   // the administrator may type a number (A02)

        $html = $this->actingAs($this->admin())->get('/checks')->assertOk()->getContent();
        $this->assertStringContainsString('Έλεγχοι πρωτοκόλλου', $html);
        $this->assertStringContainsString('value="'.$today->subDays(29)->format('d/m/Y').'"', $html, 'from: 30 days ago, today included');
        $this->assertMatchesRegularExpression('~id="check-gaps"[^>]*data-count="1"~', $html);
        $this->assertStringContainsString('2/'.$today->year, $html);
        $this->assertStringContainsString('?num=2', $html, 'the administrator may register the missing number');
        $this->assertStringContainsString('1 εύρημα', $html);
    }

    public function test_a_range_without_problems_says_so(): void
    {
        $this->book(2026, [1, 2, 3], '0301');

        Livewire::actingAs($this->writer())->test(IntegrityChecks::class)
            ->set('from', '01/03/2026')->set('to', '31/3/2026')->call('check')
            ->assertSee('Κανένα πρόβλημα από 01/03/2026 έως 31/03/2026')
            ->assertDontSee('Καταχώριση με το');
    }

    public function test_a_wrong_range_is_refused(): void
    {
        Livewire::actingAs($this->admin())->test(IntegrityChecks::class)
            ->set('from', '31/03/2026')->set('to', '01/03/2026')->call('check')->assertHasErrors('to')
            ->set('from', 'χθες')->set('to', '01/03/2026')->call('check')->assertHasErrors('from')
            ->set('from', '01/01/2020')->set('to', '01/03/2026')->call('check')->assertHasErrors('to');
    }

    public function test_quick_ranges(): void
    {
        $today = CarbonImmutable::today();
        Livewire::actingAs($this->admin())->test(IntegrityChecks::class)
            ->call('range', 'year')->assertSet('from', $today->startOfYear()->format('d/m/Y'))->assertSet('to', $today->format('d/m/Y'))
            ->call('range', 'days')->assertSet('from', $today->subDays(29)->format('d/m/Y'));
    }

    public function test_who_may_open_the_checks(): void
    {
        foreach ([$this->admin(), $this->assigner(), $this->writer()] as $user) {
            $this->actingAs($user)->get('/checks')->assertOk();
            $labels = array_column(array_merge(...array_values(Navigation::manage($user))), 'label');
            $this->assertContains('Έλεγχοι πρωτοκόλλου', $labels);
        }
        $reader = $this->reader();
        $this->actingAs($reader)->get('/checks')->assertForbidden();
        $this->assertNotContains('Έλεγχοι πρωτοκόλλου', array_column(array_merge(...array_values(Navigation::manage($reader))), 'label'));
        auth()->logout();
        $this->get('/checks')->assertRedirect();
    }

    public function test_findings_about_protocols_a_limited_user_may_not_see_are_left_out(): void
    {
        $writer = $this->writer();
        $own = Protocol::factory()->create(['user_id' => $writer->id]);
        $other = Protocol::factory()->create(['user_id' => $this->writer()->id]);
        FakeRule::$found = [Finding::about($own, 'ένα'), Finding::about($other, 'άλλο'), new Finding('κενό', '5/2026')];
        $checks = new Checks([FakeRule::class]);
        [$from, $to] = Checks::defaultRange();

        $this->assertCount(3, $checks->run($writer, $from, $to)['fake']['findings'], 'without «Περιορισμός λίστας»: all');
        $this->setting('limitProtocolAccessList', 1);
        $seen = $checks->run($writer, $from, $to)['fake']['findings'];
        $this->assertSame(['ένα', 'κενό'], array_map(fn (Finding $f) => $f->message, $seen));
        $this->assertCount(3, $checks->run($this->admin(), $from, $to)['fake']['findings']);
        $this->assertSame(2, $checks->listCount($writer));
    }

    public function test_the_list_shows_a_line_when_checks_find_something(): void
    {
        $admin = $this->admin();
        Livewire::actingAs($admin)->test(ProtocolList::class)->assertDontSeeHtml('id="checks-notice"');

        FakeRule::$found = [new Finding('κάτι', '1/2026'), new Finding('κάτι ακόμη', '2/2026')];
        $this->app->bind(Checks::class, fn () => new Checks([FakeRule::class]));
        Livewire::actingAs($admin)->test(ProtocolList::class)
            ->assertSeeHtml('id="checks-notice"')->assertSee('2 ευρήματα τις τελευταίες 30 ημέρες')
            ->assertSeeHtml('href="'.url('/checks').'"');
        Livewire::actingAs($this->reader())->test(ProtocolList::class)->assertDontSeeHtml('id="checks-notice"');
    }

    public function test_the_gaps_are_not_counted_twice_in_the_list(): void
    {
        $today = CarbonImmutable::today();
        $this->book($today->year, [1, 3], $today->format('md'));

        Livewire::actingAs($this->admin())->test(ProtocolList::class)
            ->assertSeeHtml('id="numberGaps"')->assertDontSeeHtml('id="checks-notice"');
    }
}

/** A rule whose findings a test sets */
class FakeRule implements Rule
{
    /** @var list<Finding> */
    public static array $found = [];

    public function key(): string { return 'fake'; }

    public function title(): string { return 'Δοκιμή'; }

    public function explanation(): string { return 'Δοκιμαστικός κανόνας.'; }

    public function inList(): bool { return true; }

    public function findings(CarbonImmutable $from, CarbonImmutable $to): array { return self::$found; }
}
