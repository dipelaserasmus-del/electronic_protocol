<?php

namespace Tests\Feature;

use App\Protocol;

/** B07: inputs that used to crash with HTTP 500. */
class RobustnessTest extends FeatureTestCase
{
    private function find(array $q): string
    {
        $q += ['searchField1' => 'thema', 'searchField2' => 'in_arxi_ekdosis', 'searchField3' => 'ada',
               'searchData1' => '', 'searchData2' => '', 'searchData3' => ''];
        return '/getFindData?' . http_build_query($q);
    }

    public function test_non_numeric_number_filter_is_ignored_with_a_note(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'Ορατό θέμα']);

        // with another valid criterion the search still runs
        $this->actingAs($this->reader())->get($this->find(['aponum' => 'abc', 'eosnum' => '5x', 'searchData1' => 'Ορατό']))
            ->assertOk()
            ->assertSee('Αγνοήθηκαν μη έγκυρα κριτήρια')
            ->assertSee('Ορατό');

        // with only invalid criteria: the note, no crash
        $this->actingAs($this->reader())->get($this->find(['aponum' => 'abc']))
            ->assertOk()->assertSee('Αγνοήθηκαν μη έγκυρα κριτήρια');
    }

    public function test_invalid_date_filter_is_ignored(): void
    {
        $this->actingAs($this->reader())->get($this->find(['apoProtocolDate' => '31/02/2026', 'eosProtocolDate' => 'yesterday']))
            ->assertOk()->assertSee('Αγνοήθηκαν μη έγκυρα κριτήρια');
    }

    public function test_valid_filters_still_work_without_note(): void
    {
        Protocol::factory()->number(3)->create(['thema' => 'Τρία']);
        Protocol::factory()->number(9)->create(['thema' => 'Εννέα']);

        $this->actingAs($this->reader())->get($this->find(['aponum' => '2', 'eosnum' => '4', 'etosForMany' => now()->year]))
            ->assertOk()->assertSee('Τρία')->assertDontSee('Εννέα')->assertDontSee('Αγνοήθηκαν');
    }

    public function test_search_by_unknown_assignee_returns_no_results_instead_of_500(): void
    {
        Protocol::factory()->number(1)->assignedTo($this->writer())->create(['thema' => 'Ανατεθειμένο']);

        $this->actingAs($this->reader())
            ->get($this->find(['searchField1' => 'diekperaiosi', 'searchData1' => 'Κανένας Τέτοιος']))
            ->assertOk()->assertDontSee('Ανατεθειμένο');
    }

    public function test_print_with_invalid_filters_does_not_crash(): void
    {
        $this->actingAs($this->writer())->post('/printed', ['aponum' => 'abc', 'apoProtocolDate' => 'x', 'etosForMany' => now()->year])
            ->assertSuccessful();
    }

    public function test_update_check_makes_no_network_call(): void
    {
        $this->setting('updatesAutoCheck', 1);
        $start = microtime(true);

        $this->actingAs($this->admin())->withHeader('referer', url('/login'))->get('/chkForUpdates')->assertNoContent();

        $this->assertLessThan(1.0, microtime(true) - $start);
    }
}
