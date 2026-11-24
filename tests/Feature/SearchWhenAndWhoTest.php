<?php

namespace Tests\Feature;

use App\Livewire\SearchPage;
use App\Livewire\SearchResults;
use App\Models\Protocol;
use App\Models\User;
use Livewire\Livewire;

/** R3i: relative dates (πότε:σήμερα, χθες, εβδομάδα, 7μέρες, μήνας, φέτος, πέρσι) and who registered (χρήστης:). */
class SearchWhenAndWhoTest extends FeatureTestCase
{
    private User $maria;
    private User $nikos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(10, 0));   // a Wednesday
        $this->setting('yearInUse', 2026);
        $this->maria = User::factory()->writer()->create(['name' => 'Μαρία Παπαδοπούλου']);
        $this->nikos = User::factory()->writer()->create(['name' => 'Νίκος Γεωργίου']);
        Protocol::factory()->number(10, 2026)->onDate('20261118')->create(['thema' => 'Σημερινό', 'user_id' => $this->maria->id]);
        Protocol::factory()->number(9, 2026)->onDate('20261117')->create(['thema' => 'Χθεσινό', 'user_id' => $this->nikos->id]);
        Protocol::factory()->number(8, 2026)->onDate('20261116')->create(['thema' => 'Δευτεριάτικο', 'user_id' => $this->maria->id]);
        Protocol::factory()->number(7, 2026)->onDate('20261112')->create(['thema' => 'Προ εβδομάδας', 'user_id' => $this->nikos->id]);
        Protocol::factory()->number(6, 2026)->onDate('20261102')->create(['thema' => 'Αρχές μήνα', 'user_id' => $this->maria->id]);
        Protocol::factory()->number(5, 2025)->onDate('20251215')->create(['thema' => 'Περσινό', 'user_id' => $this->nikos->id]);
    }

    private function results(string $q): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->admin())->withQueryParams(['q' => $q])->test(SearchResults::class);
    }

    public function test_relative_dates_in_the_search_box(): void
    {
        $all = ['Σημερινό', 'Χθεσινό', 'Δευτεριάτικο', 'Προ εβδομάδας', 'Αρχές μήνα', 'Περσινό'];
        $expect = [
            'πότε:σήμερα' => ['Σημερινό'],
            'πότε:χθες' => ['Χθεσινό'],
            'πότε:εβδομάδα' => ['Σημερινό', 'Χθεσινό', 'Δευτεριάτικο'],
            'πότε:7μέρες' => ['Σημερινό', 'Χθεσινό', 'Δευτεριάτικο', 'Προ εβδομάδας'],
            'ημ:μήνας' => ['Σημερινό', 'Χθεσινό', 'Δευτεριάτικο', 'Προ εβδομάδας', 'Αρχές μήνα'],
            'πότε:πέρσι' => ['Περσινό'],
        ];
        foreach ($expect as $q => $found) {
            $page = $this->results($q);
            foreach ($all as $subject) {
                in_array($subject, $found, true) ? $page->assertSee($subject) : $page->assertDontSee($subject);
            }
        }
    }

    public function test_who_registered(): void
    {
        $this->results('χρήστης:Μαρία')->assertSee('Σημερινό')->assertSee('Αρχές μήνα')->assertDontSee('Χθεσινό');
        $this->results('καταχώρισε:γεωργιου')->assertSee('Χθεσινό')->assertSee('Περσινό')->assertDontSee('Σημερινό');
        $this->results('χρήστης:Κανείς')->assertDontSee('Σημερινό')->assertDontSee('Χθεσινό');   // unknown name: nothing
        // the owner's example
        $this->results('πότε:7μέρες χρήστης:Μαρ')->assertSee('Σημερινό')->assertSee('Δευτεριάτικο')
            ->assertDontSee('Χθεσινό')->assertDontSee('Αρχές μήνα');
    }

    /** a limited writer finds by registrar only among what they may see */
    public function test_the_registrar_filter_keeps_the_access_limit(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        Livewire::actingAs($this->nikos)->withQueryParams(['q' => 'χρήστης:Μαρία'])->test(SearchResults::class)
            ->assertDontSee('Σημερινό')->assertDontSee('Αρχές μήνα');
    }

    public function test_quick_date_buttons_fill_the_dates_and_clear_the_year(): void
    {
        Livewire::actingAs($this->admin())->test(SearchPage::class)
            ->assertSet('year', '2026')
            ->call('quickDate', 'χθες')
            ->assertSet('from', '17/11/2026')->assertSet('to', '17/11/2026')->assertSet('year', '')->assertSet('searched', true)
            ->assertSee('Χθεσινό')->assertDontSee('Σημερινό')
            ->call('quickDate', 'πέρσι')
            ->assertSet('from', '01/01/2025')->assertSet('to', '31/12/2025')
            ->assertSee('Περσινό')->assertDontSee('Χθεσινό')
            ->call('quickDate', 'αύριο')->assertSet('from', '01/01/2025');   // not a button: nothing changes
        $this->actingAs($this->admin())->get('/find')->assertSeeInOrder(['data-quick-date="σήμερα"', 'data-quick-date="πέρσι"'], false);
    }

    public function test_the_registrar_menu_and_typed_dates_on_the_advanced_page(): void
    {
        $page = fn () => Livewire::actingAs($this->admin())->test(SearchPage::class);
        $page()->set('by', (string) $this->nikos->id)->call('search')
            ->assertSee('Χθεσινό')->assertDontSee('Σημερινό')->assertDontSee('Περσινό');   // the year (2026) still applies
        // the menu wins over a typed χρήστης:
        $page()->set('by', (string) $this->nikos->id)->set('words', 'χρήστης:Μαρία')->call('search')
            ->assertSee('Χθεσινό')->assertDontSee('Σημερινό');
        // a typed protocol date decides the year (the prefilled 2026 would hide last year's)
        $page()->set('words', 'πότε:πέρσι')->call('search')->assertSee('Περσινό')->assertDontSee('Χθεσινό')
            ->assertSee('ημ:πέρσι');
        // … unless a year is typed too
        $page()->set('words', 'πότε:πέρσι έτος:2026')->call('search')->assertDontSee('Περσινό');
    }

    /** the results page's «Σύνθετη αναζήτηση» keeps a relative date relative and the registrar */
    public function test_the_advanced_link_keeps_relative_dates(): void
    {
        $params = SearchPage::paramsFor((new \App\Services\Search\QueryParser())->parse('πότε:εβδομάδα ημεισ:1/11/2026 χρήστης:Μαρία'));
        $this->assertSame('ημ:εβδομάδα χρήστης:Μαρία', $params['w']);
        $this->assertSame(['01/11/2026', '01/11/2026'], [$params['in_from'], $params['in_to']]);
        $this->assertArrayNotHasKey('from', $params);

        $c = new \App\Services\Search\SearchCriteria();
        $c->registrarId = $this->maria->id;
        $this->assertSame((string) $this->maria->id, SearchPage::paramsFor($c)['by']);
        $this->assertSame('χρήστης:"Μαρία Παπαδοπούλου"', \App\Services\Search\QueryParser::format($c));
    }
}
