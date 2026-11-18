<?php

namespace Tests\Feature;

use App\Livewire\SearchPage;
use App\Models\Attachment;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Services\Search\ProtocolSearch;
use App\Services\Search\SearchCriteria;
use App\Support\Highlight;
use Livewire\Livewire;

/** R3a: the advanced search screen (App\Livewire\SearchPage) and the search itself (App\Services\Search\ProtocolSearch). */
class SearchTest extends FeatureTestCase
{
    private function find(array $q): string
    {
        return '/find?' . http_build_query($q + ['s' => 1, 'year' => '']);
    }

    /** @return list<string> subjects found, in order */
    private function subjects(SearchCriteria $c, $user = null): array
    {
        return app(ProtocolSearch::class)->query($c, $user ?? $this->admin())->pluck('thema')->all();
    }

    private function criteria(array $props): SearchCriteria
    {
        $c = new SearchCriteria();
        foreach ($props as $k => $v) {
            $c->{$k} = $v;
        }
        return $c;
    }

    public function test_the_year_is_filled_in_with_the_current_one_and_can_be_cleared(): void
    {
        $this->setting('yearInUse', 2026);
        Protocol::factory()->number(1, 2025)->create(['thema' => 'Περσινό']);
        Protocol::factory()->number(1, 2026)->create(['thema' => 'Φετινό']);
        $reader = $this->reader();

        $this->actingAs($reader)->get('/find')->assertOk()->assertSee('value="2026"', false)->assertDontSee('id="search-results"', false);
        // Enter with only the year: that year's protocols
        $this->actingAs($reader)->get('/find?s=1&year=2026')->assertSee('Φετινό')->assertDontSee('Περσινό');
        // year cleared: every year, newest first
        $this->actingAs($reader)->get('/find?s=1&year=&t[thema]=ινο')->assertSeeTextInOrder(['Φετινό', 'Περσινό']);
    }

    public function test_text_fields_match_part_of_the_text_without_case_or_accents(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'ΕΚΔΡΟΜΉ ΣΤΟ ΜΟΥΣΕΊΟ', 'in_num' => 'Φ.20/12345/Δ1']);
        Protocol::factory()->number(2)->create(['thema' => 'Άλλο θέμα', 'in_num' => '999']);

        $this->assertSame(['ΕΚΔΡΟΜΉ ΣΤΟ ΜΟΥΣΕΊΟ'], $this->subjects($this->criteria(['text' => ['thema' => 'εκδρομη στο']])));
        $this->assertSame(['ΕΚΔΡΟΜΉ ΣΤΟ ΜΟΥΣΕΊΟ'], $this->subjects($this->criteria(['text' => ['in_num' => '2345']])), 'incoming number: any part');
        $this->assertSame(['ΕΚΔΡΟΜΉ ΣΤΟ ΜΟΥΣΕΊΟ'], $this->subjects($this->criteria(['text' => ['thema' => 'μουσειο']])));
        // the results mark the match in the stored spelling
        $this->actingAs($this->reader())->get($this->find(['t' => ['thema' => 'μουσειο']]))
            ->assertSee('ΕΚΔΡΟΜΉ ΣΤΟ <mark><strong>ΜΟΥΣΕΊΟ</strong></mark>', false)->assertDontSee('Άλλο θέμα');
    }

    public function test_percent_and_underscore_are_plain_characters(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'Έκπτωση 50% στα εισιτήρια']);
        Protocol::factory()->number(2)->create(['thema' => 'Έκπτωση 500 ευρώ']);
        Protocol::factory()->number(3)->create(['thema' => 'αρχείο_1']);

        $this->assertSame(['Έκπτωση 50% στα εισιτήρια'], $this->subjects($this->criteria(['text' => ['thema' => '50%']])));
        $this->assertSame(['αρχείο_1'], $this->subjects($this->criteria(['text' => ['thema' => '_']])));
    }

    public function test_folder_chosen_is_exact_typed_without_dots_or_by_its_description(): void
    {
        Keepvalue::updateOrCreate(['fakelos' => 'Φ.1'], ['describe' => 'Εγκύκλιοι Υπουργείου']);
        Keepvalue::updateOrCreate(['fakelos' => 'Φ.10'], ['describe' => 'Εκδρομές και μετακινήσεις']);
        Keepvalue::updateOrCreate(['fakelos' => 'Φ.22.1'], ['describe' => 'Προσωπικό']);
        Protocol::factory()->number(1)->create(['thema' => 'Στο Φ.1', 'fakelos' => 'Φ.1']);
        Protocol::factory()->number(2)->create(['thema' => 'Στο Φ.10', 'fakelos' => 'Φ.10']);
        Protocol::factory()->number(3)->create(['thema' => 'Στο Φ.22.1', 'fakelos' => 'Φ.22.1']);
        Protocol::factory()->number(4)->create(['thema' => 'Χωρίς', 'fakelos' => null]);
        Protocol::factory()->number(5)->create(['thema' => 'Κενός', 'fakelos' => '']);

        $this->assertSame(['Στο Φ.1'], $this->subjects($this->criteria(['folder' => 'Φ.1'])), 'Φ.1 is not Φ.10');
        $this->assertSame(['Στο Φ.1'], $this->subjects($this->criteria(['folder' => 'φ1'])), 'dots and case optional');
        $this->assertSame(['Στο Φ.22.1'], $this->subjects($this->criteria(['folder' => 'Φ 22 1'])));
        // without dots a text can name two folders (Φ.1.1 and Φ.11): both; with its dots only that one
        Keepvalue::updateOrCreate(['fakelos' => 'Φ.1.1'], ['describe' => 'Νόμοι']);
        Keepvalue::updateOrCreate(['fakelos' => 'Φ.11'], ['describe' => 'Άλλο']);
        Protocol::factory()->number(7)->create(['thema' => 'Στο Φ.1.1', 'fakelos' => 'Φ.1.1']);
        Protocol::factory()->number(8)->create(['thema' => 'Στο Φ.11', 'fakelos' => 'Φ.11']);
        $this->assertSame(['Στο Φ.11', 'Στο Φ.1.1'], $this->subjects($this->criteria(['folder' => 'φ11'])));
        $this->assertSame(['Στο Φ.1.1'], $this->subjects($this->criteria(['folder' => 'φ.1.1'])));
        $this->assertSame('Φ.1.1', ProtocolSearch::folderCode('φ.1.1'));
        $this->assertSame('φ11', ProtocolSearch::folderCode('φ11'), 'two folders: the text stays');
        $this->assertSame(['Στο Φ.10'], $this->subjects($this->criteria(['folder' => 'εκδρομες'])), 'a word of the description');
        $this->assertSame(['Κενός', 'Χωρίς'], $this->subjects($this->criteria(['folder' => SearchCriteria::NO_FOLDER])), '«Χωρίς φάκελο»');
        // a protocol's folder that is not in the folders list is still found by part of its text
        Protocol::factory()->number(6)->create(['thema' => 'Παλιός', 'fakelos' => 'ΠΑΛΙΟ-7']);
        $this->assertSame(['Παλιός'], $this->subjects($this->criteria(['folder' => 'παλιο'])));
    }

    public function test_pending_check_finds_no_folder_or_the_word_in_the_subject_within_the_dates(): void
    {
        Keepvalue::updateOrCreate(['fakelos' => 'Φ.1'], ['describe' => 'Εγκύκλιοι']);
        Protocol::factory()->number(1, 2026)->onDate('20260110')->create(['thema' => 'Χωρίς φάκελο', 'fakelos' => null]);
        Protocol::factory()->number(2, 2026)->onDate('20260115')->create(['thema' => 'Αίτηση - εκκρεμότητα', 'fakelos' => 'Φ.1']);
        Protocol::factory()->number(3, 2026)->onDate('20260120')->create(['thema' => 'ΑΔΕΙΑ ΕΚΚΡΕΜΟΤΗΤΕΣ', 'fakelos' => 'Φ.1']);
        Protocol::factory()->number(4, 2026)->onDate('20260125')->create(['thema' => 'Τακτοποιημένο', 'fakelos' => 'Φ.1']);
        Protocol::factory()->number(5, 2026)->onDate('20260301')->create(['thema' => 'Εκτός διαστήματος', 'fakelos' => null]);
        $admin = $this->admin();

        $this->actingAs($admin)->get($this->find(['pending' => 1, 'year' => 2026, 'from' => '01/01/2026', 'to' => '31/01/2026']))
            ->assertOk()->assertSee('3 εκκρεμότητες')
            ->assertSeeInOrder(['id="search-results"', 'ΑΔΕΙΑ ΕΚΚΡΕΜΟΤΗΤΕΣ', 'pending-reason">ΕΚΚΡΕΜΟΤΗΤΑ στο θέμα',
                'Αίτηση - εκκρεμότητα', 'Χωρίς φάκελο', 'pending-reason">χωρίς φάκελο'], false)
            ->assertDontSee('Τακτοποιημένο')->assertDontSee('Εκτός διαστήματος');

        // nothing pending: said clearly, with the range
        $this->actingAs($admin)->get($this->find(['pending' => 1, 'year' => 2026, 'from' => '21/01/2026', 'to' => '31/01/2026']))
            ->assertSee('id="no-pending"', false)->assertSee('Καμία εκκρεμότητα από 21/01/2026 έως 31/01/2026')
            ->assertSee('Όλα τα πρωτόκολλα έχουν φάκελο');
        // with another filter an empty result is just "nothing found", never "nothing pending"
        $this->actingAs($admin)->get($this->find(['pending' => 1, 'year' => 2026, 'from' => '21/01/2026', 't' => ['thema' => 'ζζζ']]))
            ->assertDontSee('id="no-pending"', false)->assertSee('Δεν βρέθηκε κανένα πρωτόκολλο');
    }

    public function test_pending_check_button_keeps_only_the_year_and_the_dates(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'Εκκρεμότητα', 'fakelos' => 'Φ.1']);

        Livewire::actingAs($this->admin())->test(SearchPage::class)
            ->set('text', ['thema' => 'κάτι άλλο'])->set('folder', 'Φ.9')->set('num', '77')
            ->call('pendingCheck')
            ->assertSet('pending', true)->assertSet('text', [])->assertSet('folder', '')->assertSet('num', '')
            ->assertSee('1 εκκρεμότητα');
    }

    public function test_related_numbers_are_matched_whole(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'Σχετικό με 12', 'sxetiko' => '12/2026, 40']);
        Protocol::factory()->number(2)->create(['thema' => 'Σχετικό με 112', 'sxetiko' => '112/2026']);
        Protocol::factory()->number(3)->create(['thema' => 'Σχετικό με 5/2012', 'sxetiko' => '5/2012']);
        Protocol::factory()->number(4)->create(['thema' => 'Σκέτο 12', 'sxetiko' => '12']);

        $this->assertSame(['Σκέτο 12', 'Σχετικό με 12'], $this->subjects($this->criteria(['text' => ['sxetiko' => '12']])));
        $this->assertSame(['Σχετικό με 12'], $this->subjects($this->criteria(['text' => ['sxetiko' => '12/2026']])));
    }

    public function test_empty_fields_and_attachments(): void
    {
        $withAda = Protocol::factory()->number(1)->create(['thema' => 'Με ΑΔΑ', 'keywords' => 'λέξη']);
        Attachment::factory()->create(['protocol_id' => $withAda->id, 'name' => 'Απόφαση.pdf', 'ada' => '6ΩΡΖ46ΜΤΛΗ-ΑΒΓ']);
        $noAda = Protocol::factory()->number(2)->create(['thema' => 'Χωρίς ΑΔΑ', 'keywords' => null]);
        Attachment::factory()->create(['protocol_id' => $noAda->id, 'name' => 'Έγγραφο.pdf', 'ada' => null]);
        Protocol::factory()->number(3)->create(['thema' => 'Χωρίς συνημμένο', 'keywords' => '']);

        $this->assertSame(['Με ΑΔΑ'], $this->subjects($this->criteria(['text' => ['ada' => 'μτλη']])));
        $this->assertSame(['Χωρίς ΑΔΑ'], $this->subjects($this->criteria(['text' => ['name' => 'εγγραφο']])));
        $this->assertSame(['Χωρίς ΑΔΑ'], $this->subjects($this->criteria(['empty' => ['ada']])), 'an attachment without ΑΔΑ');
        $this->assertSame(['Χωρίς συνημμένο', 'Χωρίς ΑΔΑ'], $this->subjects($this->criteria(['empty' => ['keywords']])), 'NULL and empty text');
    }

    public function test_limited_writers_only_find_their_own_or_assigned_protocols(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $writer = $this->writer();
        Protocol::factory()->number(1)->create(['thema' => 'Δικό μου', 'user_id' => $writer->id]);
        Protocol::factory()->number(2)->assignedTo($writer)->create(['thema' => 'Ανατεθειμένο σε μένα']);
        Protocol::factory()->number(3)->create(['thema' => 'Ξένο']);

        $this->assertSame(['Ανατεθειμένο σε μένα', 'Δικό μου'], $this->subjects($this->criteria(['year' => (int) date('Y')]), $writer));
        $this->actingAs($writer)->get($this->find(['year' => date('Y')]))->assertSee('Δικό μου')->assertDontSee('Ξένο');
    }

    public function test_no_criteria_asks_for_one_and_the_page_needs_login(): void
    {
        $this->actingAs($this->reader())->get('/find?s=1&year=')->assertOk()->assertSee('Συμπληρώστε τουλάχιστον ένα πεδίο');
        auth()->logout();
        $this->get('/find')->assertRedirect('/login');
    }

    public function test_dates_accept_the_short_forms_of_every_date_field(): void
    {
        Livewire::actingAs($this->admin())->test(SearchPage::class)
            ->set('from', '9/1/2026')->set('to', '31/1/26')->call('search')
            ->assertSet('from', '09/01/2026')->assertSet('to', '31/01/2026')->assertHasNoErrors();
        Livewire::actingAs($this->admin())->test(SearchPage::class)
            ->set('from', '31/01/2026')->set('to', '01/01/2026')->call('search')->assertHasErrors('to');
    }

    public function test_number_and_range(): void
    {
        $this->assertSame([125, null], SearchCriteria::parseNumber(' 125 '));
        $this->assertSame([120, 130], SearchCriteria::parseNumber('120 - 130'));
        $this->assertSame([120, 130], SearchCriteria::parseNumber('130–120'));
        $this->assertNull(SearchCriteria::parseNumber('12a'));
    }

    public function test_highlight_ignores_case_and_accents_and_escapes(): void
    {
        $this->assertSame('ΑΔΕΙΑ <mark><strong>ΕΚΚΡΕΜΌΤΗΤΑ</strong></mark>', Highlight::html('ΑΔΕΙΑ ΕΚΚΡΕΜΌΤΗΤΑ', 'εκκρεμοτητα'));
        $this->assertSame('<mark><strong>&lt;b&gt;</strong></mark> &amp; οδός', Highlight::html('<b> & οδός', '<b>'));
        $this->assertSame('<mark><strong>Οδός</strong></mark> και <mark><strong>πλατ</strong></mark>εία', Highlight::html('Οδός και πλατεία', ['οδοσ', 'πλατ']));
        $this->assertSame('χωρίς', Highlight::html('χωρίς', ''));
    }

    /** R3b: free words must all be found, each in any field, the folder, an attachment, or as the number */
    public function test_free_words_are_found_anywhere(): void
    {
        $p = Protocol::factory()->number(125, 2025)->create(['thema' => 'Αίτηση μετάθεσης', 'in_arxi_ekdosis' => 'Δήμος Πατρέων', 'fakelos' => 'Φ.1']);
        Attachment::factory()->create(['protocol_id' => $p->id, 'name' => 'Βεβαίωση.pdf', 'ada' => null]);
        Protocol::factory()->number(126, 2025)->create(['thema' => 'Αίτηση άδειας', 'in_arxi_ekdosis' => 'Υπουργείο']);
        Protocol::factory()->number(125, 2026)->create(['thema' => 'Άλλο έτος']);

        $find = fn (string $text) => $this->subjects((new \App\Services\Search\QueryParser())->parse($text));
        $this->assertSame(['Αίτηση μετάθεσης'], $find('αιτηση πατρεων'), 'subject + sender');
        $this->assertSame(['Αίτηση μετάθεσης'], $find('βεβαιωση'), 'attachment name');
        $this->assertSame(['Αίτηση μετάθεσης'], $find('125/2025'), 'number/year');
        $this->assertSame(['Άλλο έτος', 'Αίτηση μετάθεσης'], $find('125'), 'number in every year');
        $this->assertSame(['Αίτηση άδειας', 'Αίτηση μετάθεσης'], $find('αίτηση έτος:2025'));
        $this->assertSame([], $find('"μετάθεσης πατρέων"'), 'a phrase must be in one field');

        // the advanced screen: «Οπουδήποτε» and its search written as text
        $this->actingAs($this->reader())->get($this->find(['w' => 'αιτηση πατρεων', 'year' => 2025]))
            ->assertSeeText('Αίτηση μετάθεσης')->assertDontSee('Αίτηση άδειας')
            ->assertSee('Ως κείμενο: <code class="user-select-all">αιτηση πατρεων έτος:2025</code>', false)
            ->assertSee('<mark><strong>Πατρέων</strong></mark>', false);
    }

    /** R3b: «Οπουδήποτε» on the advanced screen also takes operators; a field filled in on the form wins */
    public function test_the_anywhere_field_takes_operators(): void
    {
        $this->setting('yearInUse', 2026);
        Protocol::factory()->number(1, 2025)->create(['thema' => 'Περσινό χωρίς φάκελο', 'fakelos' => null]);
        Protocol::factory()->number(2, 2026)->create(['thema' => 'Φετινό χωρίς φάκελο', 'fakelos' => null]);
        Protocol::factory()->number(3, 2026)->create(['thema' => 'Φετινό με φάκελο', 'fakelos' => 'Φ.1']);

        Livewire::actingAs($this->reader())->test(SearchPage::class)
            ->set('words', 'φάκελος:κανένας')->call('search')
            ->assertSee('Φετινό χωρίς φάκελο')->assertDontSee('Περσινό χωρίς')->assertDontSee('Φετινό με φάκελο')
            ->set('words', 'φάκελος:κανένας έτος:όλα')->call('search')
            ->assertSee('Περσινό χωρίς φάκελο')->assertSee('Φετινό χωρίς φάκελο')
            ->set('words', 'έτος:abc')->call('search')
            ->assertHasErrors('words')->assertSee('Δεν χρησιμοποιήθηκαν: «έτος:abc»');
    }
}
