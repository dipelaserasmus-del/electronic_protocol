<?php

namespace Tests\Feature;

use App\Models\Protocol;

/** B07: inputs that used to crash with HTTP 500. */
class RobustnessTest extends FeatureTestCase
{
    /** R3a: the search screen (fields in the address; year empty: all years) */
    private function find(array $q): string
    {
        return '/find?' . http_build_query($q + ['s' => 1, 'year' => '']);
    }

    public function test_non_numeric_number_is_marked_on_the_field_without_crashing(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'Ορατό θέμα']);

        // R3a: the wrong field is marked and nothing is searched until it is corrected (B07: it was a 500)
        $this->actingAs($this->reader())->get($this->find(['num' => 'abc', 't' => ['thema' => 'Ορατό']]))
            ->assertOk()->assertSee('Ένας αριθμός (π.χ. 125)')->assertDontSee('id="search-results"', false);
        $this->actingAs($this->reader())->get($this->find(['year' => '20x6']))
            ->assertOk()->assertSee('Τέσσερα ψηφία');
    }

    public function test_invalid_date_is_marked_on_the_field(): void
    {
        $this->actingAs($this->reader())->get($this->find(['from' => '31/02/2026', 'to' => 'yesterday']))
            ->assertOk()->assertSee('Μη έγκυρη ημερομηνία')->assertDontSee('id="search-results"', false);
    }

    public function test_valid_filters_still_work_without_note(): void
    {
        Protocol::factory()->number(3)->create(['thema' => 'Τρία']);
        Protocol::factory()->number(9)->create(['thema' => 'Εννέα']);

        $this->actingAs($this->reader())->get($this->find(['num' => '2-4', 'year' => now()->year]))
            ->assertOk()->assertSee('Τρία')->assertDontSee('Εννέα')->assertDontSee('is-invalid');
    }

    public function test_search_by_unknown_assignee_returns_no_results_instead_of_500(): void
    {
        Protocol::factory()->number(1)->assignedTo($this->writer())->create(['thema' => 'Ανατεθειμένο']);

        $this->actingAs($this->reader())->get($this->find(['assignee' => '999999']))
            ->assertOk()->assertDontSee('Ανατεθειμένο')->assertSee('Δεν βρέθηκε κανένα πρωτόκολλο');
    }

    public function test_print_with_invalid_filters_does_not_crash(): void
    {
        $this->actingAs($this->writer())->post('/printed', ['aponum' => 'abc', 'apoProtocolDate' => 'x', 'etosForMany' => now()->year])
            ->assertSuccessful()
            ->assertSee('id="ignoredFilters"', false)->assertSee('Από αριθμό «abc»')->assertSee('Από ημ/νία «x»');
    }

    public function test_attachment_print_reports_ignored_filters(): void
    {
        $this->actingAs($this->writer())->post('/printedAttachments', ['eosnum' => '12a', 'etosForMany' => now()->year])
            ->assertSuccessful()
            ->assertSee('id="ignoredFilters"', false)->assertSee('Έως αριθμό «12a»');
    }

    public function test_print_with_valid_filters_has_no_notice(): void
    {
        $this->actingAs($this->writer())->post('/printed', ['aponum' => '1', 'etosForMany' => now()->year])
            ->assertSuccessful()->assertDontSee('id="ignoredFilters"', false);
    }

    public function test_ignored_filter_value_is_escaped_on_printout(): void
    {
        $this->actingAs($this->writer())->post('/printed', ['aponum' => '<b>x</b>', 'etosForMany' => now()->year])
            ->assertSuccessful()->assertDontSee('<b>x</b>', false)->assertSee('&lt;b&gt;x&lt;/b&gt;', false);
    }

    public function test_login_lands_on_the_protocol_list(): void
    {
        $user = $this->writer();
        $this->followingRedirects()->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertOk()->assertSee('Θέμα');
    }

    public function test_update_check_makes_no_network_call(): void
    {
        $this->setting('updatesAutoCheck', 1);
        $start = microtime(true);

        // still forwards to the role's landing page (the login redirects through this route)
        $this->actingAs($this->admin())->withHeader('referer', url('/login'))->get('/chkForUpdates')->assertRedirect();

        $this->assertLessThan(1.0, microtime(true) - $start);
    }
}
