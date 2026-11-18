<?php

namespace Tests\Feature;

use App\Models\Protocol;

/** R3c: the search box in the top bar, its results page (/search) and the suggestions while typing (/search/suggest). */
class GlobalSearchTest extends FeatureTestCase
{
    public function test_the_box_is_on_the_rebuilt_and_the_old_pages(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/home/list')->assertOk()->assertSee('id="app-search-q"', false)->assertSee('action="' . url('/search') . '"', false);
        $this->actingAs($admin)->get('/keep')->assertOk()->assertSee('action="' . url('/search') . '"', false);   // legacy layout
        auth()->logout();
        $this->get('/login')->assertDontSee('id="app-search-q"', false);
    }

    public function test_results_page_reads_the_search_words_in_every_year_newest_first(): void
    {
        Protocol::factory()->number(10, 2025)->create(['thema' => 'Αίτηση μετάθεσης περσινή', 'fakelos' => 'Φ.1']);
        Protocol::factory()->number(11, 2026)->create(['thema' => 'Αίτηση μετάθεσης φετινή', 'fakelos' => 'Φ.1']);
        Protocol::factory()->number(12, 2026)->create(['thema' => 'Αίτηση άδειας', 'fakelos' => 'Φ.2']);

        $this->actingAs($this->reader())->get('/search?q=' . urlencode('αιτηση μεταθεσης φάκελος:φ1'))
            ->assertOk()->assertSee('Βρέθηκαν 2 πρωτόκολλα')
            ->assertSeeTextInOrder(['Αίτηση μετάθεσης φετινή', 'Αίτηση μετάθεσης περσινή'])->assertDontSee('Αίτηση άδειας')
            ->assertSee('value="αιτηση μεταθεσης φάκελος:φ1"', false);   // the box keeps the text on this page
        $this->actingAs($this->reader())->get('/search?q=' . urlencode('11/2026'))->assertSeeText('Αίτηση μετάθεσης φετινή')->assertDontSee('περσινή');
    }

    public function test_results_page_says_what_was_not_used_and_asks_for_text(): void
    {
        $this->actingAs($this->reader())->get('/search?q=' . urlencode('έτος:πέρσι'))
            ->assertOk()->assertSee('id="search-ignored"', false)->assertSee('«έτος:πέρσι»')->assertSee('id="search-empty"', false);
        $this->actingAs($this->reader())->get('/search')->assertOk()->assertSee('Γράψτε στο πεδίο αναζήτησης');
    }

    public function test_the_advanced_screen_opens_with_the_same_search(): void
    {
        Protocol::factory()->number(5, 2024)->create(['thema' => 'Παλιά αίτηση', 'fakelos' => 'Φ.1', 'diekperaiosi' => '']);
        Protocol::factory()->number(9, 2024)->create(['thema' => 'Άλλη αίτηση', 'fakelos' => 'Φ.2']);
        $q = 'αίτηση φάκελος:Φ.1 αρ:5-9 ανάθεση:κανένας ημ:1/1/2024..31/12/2024 από:"Δήμος Α"';

        $page = $this->actingAs($this->reader())->get('/search?q=' . urlencode($q));
        preg_match('~<a href="([^"]+)"[^>]*id="to-advanced"~', $page->getContent(), $m);
        $this->assertNotEmpty($m, 'link to the advanced screen');
        $url = html_entity_decode($m[1]);
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        $this->assertSame([
            's' => '1', 'year' => '', 'num' => '5-9', 'from' => '01/01/2024', 'to' => '31/12/2024', 'folder' => 'Φ.1',
            't' => ['in_arxi_ekdosis' => 'Δήμος Α'], 'assignee' => 'none', 'w' => 'αίτηση',
        ], $params);

        // without the sender, the advanced screen finds the same protocol
        unset($params['t']);
        $this->actingAs($this->reader())->get('/find?' . http_build_query($params))
            ->assertSee('Βρέθηκαν 1 πρωτόκολλο')->assertSeeText('Παλιά αίτηση')->assertDontSee('Άλλη αίτηση');
    }

    public function test_suggestions_are_few_marked_escaped_and_only_what_the_user_may_see(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $writer = $this->writer();
        foreach (range(1, 8) as $n) {
            Protocol::factory()->number($n)->create(['thema' => "Εκδρομή <b>τάξης</b> $n", 'user_id' => $writer->id]);
        }
        Protocol::factory()->number(20)->create(['thema' => 'Εκδρομή ξένη']);

        $json = $this->actingAs($writer)->getJson('/search/suggest?q=' . urlencode('εκδρομη'))->assertOk()->json();
        $this->assertCount(6, $json['items']);
        $this->assertTrue($json['more']);
        $this->assertSame('8/' . date('Y'), $json['items'][0]['number'], 'newest first');
        $this->assertSame('<mark><strong>Εκδρομή</strong></mark> &lt;b&gt;τάξης&lt;/b&gt; 8', $json['items'][0]['html']);
        $this->assertStringNotContainsString('ξένη', json_encode($json, JSON_UNESCAPED_UNICODE));

        $this->actingAs($writer)->getJson('/search/suggest?q=ε')->assertExactJson(['items' => [], 'more' => false]);
        $this->actingAs($writer)->getJson('/search/suggest?q=' . urlencode('έτος:πέρσι'))->assertExactJson(['items' => [], 'more' => false]);
        auth()->logout();
        $this->getJson('/search/suggest?q=εκδρομη')->assertUnauthorized();
    }

    /** review of R3c: a quoted word that looks like a search word stays a word on the advanced screen */
    public function test_the_advanced_link_keeps_quoted_words(): void
    {
        $params = \App\Livewire\SearchPage::paramsFor((new \App\Services\Search\QueryParser())->parse('"από:δήμος" αίτηση'));
        $this->assertSame('"από:δήμος" αίτηση', $params['w']);
        $this->assertArrayNotHasKey('t', $params);
    }
}
