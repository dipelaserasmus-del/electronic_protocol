<?php

namespace Tests\Feature;

use App\Livewire\FolderAdmin;
use App\Models\FolderAssignment;
use App\Models\Keepvalue;
use App\Models\Protocol;
use App\Models\User;
use App\Services\Folders\FolderCatalog;
use Livewire\Livewire;

/** R6b: the folders page (App\Livewire\FolderAdmin), its rules and import (App\Services\Folders\FolderCatalog). */
class FolderAdminTest extends FeatureTestCase
{
    private function page(User $user, array $params = [])
    {
        return Livewire::actingAs($user)->test(FolderAdmin::class, $params);
    }

    public function test_everybody_sees_the_folders_only_administrators_change_them(): void
    {
        Keepvalue::create(['fakelos' => 'Φ.950', 'keep' => 5, 'describe' => 'Φάκελος δοκιμής']);
        $this->actingAs($this->reader())->get('/keep')->assertOk()->assertSee('Φ.950')->assertSee('5 έτη')->assertDontSee('Νέος φάκελος');
        $this->actingAs($this->admin())->get('/keep')->assertOk()->assertSee('Νέος φάκελος');

        $writer = $this->writer();
        foreach (['create', 'openImport'] as $action) {
            $this->page($writer)->call($action)->assertForbidden();
        }
        $f = Keepvalue::where('fakelos', 'Φ.950')->first();
        $this->page($writer)->call('delete', $f->id)->assertForbidden();
        $this->assertNotNull($f->fresh());
    }

    /** R6g (owner, 2026-11-25): Κωδικός and Πρωτ. sort the table; each code opens a search for its protocols */
    public function test_the_table_sorts_by_code_or_count_and_each_code_searches_its_protocols(): void
    {
        Keepvalue::query()->delete();
        foreach (['Φ.9002', 'Φ.9010', 'Φ.9001'] as $code) {
            Keepvalue::create(['fakelos' => $code, 'keep' => 5]);
        }
        foreach ([['Φ.9001', 1], ['Φ.9010', 3]] as [$code, $n]) {
            for ($i = 0; $i < $n; $i++) {
                Protocol::factory()->number(100 + $i + ($code === 'Φ.9010' ? 10 : 0))->create(['fakelos' => $code]);
            }
        }
        $order = fn ($page) => array_values(array_map(fn ($m) => $m, (preg_match_all('~class="folder-row[^"]*" data-code="([^"]+)"~', $page->html(), $m) ? $m[1] : [])));

        $page = Livewire::actingAs($this->writer())->test(FolderAdmin::class);
        $this->assertSame(['Φ.9001', 'Φ.9002', 'Φ.9010'], $order($page), 'by code (numbers as numbers), as before');
        $page->assertSeeHtml('data-sort="code" wire:click="sortBy(\'code\')" aria-sort="ascending">Κωδικός ▲</button>');
        $page->call('sortBy', 'code')->assertSet('dir', 'desc');
        $this->assertSame(['Φ.9010', 'Φ.9002', 'Φ.9001'], $order($page));
        $page->call('sortBy', 'count')->assertSet('sort', 'count')->assertSet('dir', 'desc');   // the most used first
        $this->assertSame(['Φ.9010', 'Φ.9001', 'Φ.9002'], $order($page));
        $page->assertSeeHtml('aria-sort="descending">Πρωτ. ▼</button>');
        $page->call('sortBy', 'count')->assertSet('dir', 'asc');
        $this->assertSame(['Φ.9002', 'Φ.9001', 'Φ.9010'], $order($page));
        $page->call('sortBy', 'nonsense')->assertSet('sort', 'count');
        // kept in the address
        $this->actingAs($this->writer())->get('/keep?sort=count&dir=desc')->assertOk()->assertSeeInOrder(['Φ.9010', 'Φ.9001', 'Φ.9002']);

        // every code is a link to the search for its folder (also an unused one: it shows that none is there)
        $html = $page->html();
        foreach (['Φ.9001', 'Φ.9002', 'Φ.9010'] as $code) {
            $this->assertStringContainsString('<a href="'.e(url('/search?q='.urlencode('φάκελος:'.$code))).'" class="text-decoration-none folder-search-link"', $html);
        }
        // the link shows the results at once: that folder's protocols only
        $results = Livewire::actingAs($this->writer())->withQueryParams(['q' => 'φάκελος:Φ.9010'])->test(\App\Livewire\SearchResults::class);
        $results->assertSee('110/')->assertDontSee('>100/');
    }

    public function test_old_write_routes_are_gone(): void
    {
        $f = Keepvalue::create(['fakelos' => 'Φ.951']);
        $this->actingAs($this->admin())->get('/keep/del/'.$f->id)->assertNotFound();
        $this->actingAs($this->admin())->post('/keep', ['fakelos' => 'Φ.952'])->assertStatus(405);
        $this->assertNotNull($f->fresh());
    }

    public function test_adds_a_folder_with_years_or_a_text_and_automatic_assignments(): void
    {
        $d = $this->writer();
        $e = $this->reader();
        $this->page($this->admin())->call('create')
            ->set('form.fakelos', 'Φ.960')->set('form.keep', 'Μόνιμα')->set('form.describe', 'Αιτήσεις')
            ->set('form.d', [(string) $d->id])->set('form.e', [(string) $e->id, (string) $d->id])
            ->call('save')->assertHasNoErrors()->assertDispatched('toast');

        $f = Keepvalue::where('fakelos', 'Φ.960')->firstOrFail();
        $this->assertNull($f->keep);
        $this->assertSame('Μόνιμα', $f->keep_alt);
        $this->assertSame('Μόνιμα', $f->keepLabel());
        // a user for διεκπεραίωση is not also listed for ενημέρωση
        $this->assertEqualsCanonicalizing([[$d->id, 'd'], [$e->id, 'e']],
            FolderAssignment::where('keepvalue_id', $f->id)->get()->map(fn ($a) => [$a->user_id, $a->type])->all());

        $this->page($this->admin())->call('edit', $f->id)->assertSet('form.keep', 'Μόνιμα')->assertSet('form.d', [(string) $d->id])
            ->set('form.keep', '10 έτη')->set('form.d', [])->call('save')->assertHasNoErrors();
        $this->assertSame(10, (int) $f->fresh()->keep);
        $this->assertNull($f->fresh()->keep_alt);
        $this->assertSame(['e'], FolderAssignment::where('keepvalue_id', $f->id)->pluck('type')->all());
    }

    public function test_code_rules(): void
    {
        Keepvalue::create(['fakelos' => 'Φ.970']);
        $page = $this->page($this->admin())->call('create');
        foreach (['', 'Α.1', 'Φ.', 'Φ.1/2', 'Φ.970', 'φ 970'] as $bad) {
            $page->set('form.fakelos', $bad)->call('save')->assertHasErrors('form.fakelos');
        }
        $page->set('form.fakelos', 'Φ.970')->set('form.keep', '0')->call('save')->assertHasErrors('form.keep');
        $inactive = User::factory()->writer()->inactive()->create();
        $page->set('form.fakelos', 'Φ.971')->set('form.keep', '')->set('form.d', [(string) $inactive->id])->call('save')->assertHasErrors('form.d');
        $page->set('form.fakelos', 'φ971')->set('form.d', [])->call('save')->assertHasNoErrors();
        $this->assertSame('Φ.971', Keepvalue::where('fakelos', 'Φ.971')->value('fakelos'));   // written as the folders are
    }

    public function test_a_code_in_use_is_neither_renamed_nor_deleted(): void
    {
        $used = Keepvalue::create(['fakelos' => 'Φ.980']);
        Protocol::factory()->create(['fakelos' => 'Φ.980']);
        $admin = $this->admin();
        $this->page($admin)->call('edit', $used->id)->set('form.fakelos', 'Φ.981')->call('save')->assertHasErrors('form.fakelos');
        $this->page($admin)->call('edit', $used->id)->set('form.describe', 'Νέα περιγραφή')->call('save')->assertHasNoErrors();
        $this->assertSame('Νέα περιγραφή', $used->fresh()->describe);
        $this->page($admin)->call('delete', $used->id)->assertDispatched('toast', type: 'error');
        $this->assertNotNull($used->fresh());

        $free = Keepvalue::create(['fakelos' => 'Φ.982']);
        FolderAssignment::create(['keepvalue_id' => $free->id, 'user_id' => $admin->id, 'type' => 'd']);
        $this->page($admin)->call('edit', $free->id)->set('form.fakelos', 'Φ.983')->call('save')->assertHasNoErrors();
        $this->page($admin)->call('delete', $free->id)->assertDispatched('toast', type: 'success');
        $this->assertNull($free->fresh());
        $this->assertSame(0, FolderAssignment::where('keepvalue_id', $free->id)->count());
    }

    public function test_search_by_code_or_word(): void
    {
        Keepvalue::create(['fakelos' => 'Φ.993.2', 'describe' => 'Μετακινήσεις ζωολογικού κήπου']);
        Keepvalue::create(['fakelos' => 'Φ.990', 'describe' => 'Προμήθειες']);
        $admin = $this->admin();
        $this->page($admin)->set('search', 'φ993')->assertSee('Φ.993.2')->assertDontSee('Φ.990');
        $this->page($admin)->set('search', 'ζωολογικου')->assertSee('Φ.993.2')->assertDontSee('Φ.990');
    }

    public function test_keep_id_address_opens_the_folder_for_administrators(): void
    {
        $f = Keepvalue::create(['fakelos' => 'Φ.991']);
        $this->page($this->admin(), ['keepvalue' => $f->id])->assertSet('editing', $f->id)->assertSet('formOpen', true);
        $this->page($this->reader(), ['keepvalue' => $f->id])->assertSet('formOpen', false);
    }

    public function test_import_from_a_pasted_spreadsheet(): void
    {
        Keepvalue::create(['fakelos' => 'Φ.700', 'describe' => 'Παλιά περιγραφή', 'keep' => 5, 'remarks' => 'μένει']);
        Keepvalue::create(['fakelos' => 'Φ.701', 'describe' => 'Ίδια']);
        $text = "Κωδικός\tΠεριγραφή\tΔιατήρηση\tΠαρατηρήσεις\n"
            ."Φ.700\tΝέα περιγραφή\t\t\n"
            ."Φ.701\tΊδια\n"
            ."φ 702\tΝέος φάκελος\tΜόνιμα\tσημείωση\n"
            ."\n"
            ."Φ.703;Με ερωτηματικό;10\n"
            ."Χ.1\tΛάθος κωδικός\n"
            ."Φ.702\tΔιπλός\n";
        $rows = app(FolderCatalog::class)->parseImport($text);
        $this->assertSame(['update', 'same', 'new', 'new', 'error', 'error'], array_column($rows, 'status'));
        $this->assertSame('Φ.702', $rows[2]['fakelos']);
        $this->assertStringContainsString('γραμμή 4', $rows[5]['error']);

        $this->page($this->admin())->call('openImport')->set('importText', $text)->call('checkImport')
            ->assertSee('2 νέοι, 1 αλλαγές, 1 ίδιοι')->call('runImport')->assertDispatched('toast')->assertSet('importOpen', false);

        $old = Keepvalue::where('fakelos', 'Φ.700')->first();
        $this->assertSame('Νέα περιγραφή', $old->describe);
        $this->assertSame(5, (int) $old->keep);          // empty cell: kept
        $this->assertSame('μένει', $old->remarks);
        $this->assertSame('Μόνιμα', Keepvalue::where('fakelos', 'Φ.702')->value('keep_alt'));
        $this->assertSame(10, (int) Keepvalue::where('fakelos', 'Φ.703')->value('keep'));
        $this->assertFalse(Keepvalue::where('fakelos', 'Χ.1')->exists());
    }

    public function test_deleting_a_user_removes_their_folder_rules(): void
    {
        $f = Keepvalue::create(['fakelos' => 'Φ.992']);
        $u = $this->writer();
        FolderAssignment::create(['keepvalue_id' => $f->id, 'user_id' => $u->id, 'type' => 'e']);
        app(\App\Services\Users\UserAccounts::class)->delete($u, $this->admin());
        $this->assertSame(0, FolderAssignment::where('user_id', $u->id)->count());
    }

    /** Review of R6b: a user deactivated after being put in a folder's rules does not block saving the folder */
    public function test_an_inactive_user_in_the_rules_can_be_kept_or_removed(): void
    {
        $admin = $this->admin();
        $gone = $this->writer();
        $f = Keepvalue::create(['fakelos' => 'Φ.994']);
        FolderAssignment::create(['keepvalue_id' => $f->id, 'user_id' => $gone->id, 'type' => 'd']);
        $gone->forceFill(['active' => null])->save();

        $this->page($admin)->call('edit', $f->id)->assertSee('ανενεργός: δεν παίρνει αναθέσεις')
            ->set('form.describe', 'Μόνο η περιγραφή')->call('save')->assertHasNoErrors();
        $this->assertSame('Μόνο η περιγραφή', $f->fresh()->describe);
        $this->assertSame(1, FolderAssignment::where('keepvalue_id', $f->id)->count());

        $this->page($admin)->call('edit', $f->id)->set('form.d', [])->call('save')->assertHasNoErrors();
        $this->assertSame(0, FolderAssignment::where('keepvalue_id', $f->id)->count());

        // an inactive user who was not in the rules is still refused
        $this->page($admin)->call('edit', $f->id)->set('form.e', [(string) $gone->id])->call('save')->assertHasErrors('form.e');
    }

    /** Review of R6b: a spreadsheet cell with a line break comes in quotes and stays one cell of its row */
    public function test_import_keeps_multi_line_cells_in_their_row(): void
    {
        $text = "Φ.710\t\"Πρώτη γραμμή\nΔεύτερη γραμμή\"\t5\t\"με \"\"εισαγωγικά\"\"\"\nΦ.711\tΑπλή\n";
        $rows = app(FolderCatalog::class)->parseImport($text);
        $this->assertSame(['Φ.710', 'Φ.711'], array_column($rows, 'fakelos'));
        $this->assertSame("Πρώτη γραμμή\nΔεύτερη γραμμή", $rows[0]['describe']);
        $this->assertSame('5', $rows[0]['keep']);
        $this->assertSame('με "εισαγωγικά"', $rows[0]['remarks']);
        $this->assertSame([1, 3], array_column($rows, 'line'));

        app(FolderCatalog::class)->import($text);
        $this->assertSame("Πρώτη γραμμή\nΔεύτερη γραμμή", Keepvalue::where('fakelos', 'Φ.710')->value('describe'));
        $this->assertSame(5, (int) Keepvalue::where('fakelos', 'Φ.710')->value('keep'));
    }

    public function test_import_keeps_greek_letters_at_the_end_of_a_cell(): void
    {
        // D07: trim() with "\u{00A0}" removed the last byte of Π (CE A0): "ΑΣΕΠ" became "ΑΣΕ" + half a letter → 500
        $text = "Φ.712\tΕξετάσεις, ΑΣΕΠ\t\tΠΥΣΠ\nΦ.713\t\u{00A0}Με κενά\u{00A0} \n";
        $rows = app(FolderCatalog::class)->parseImport($text);
        $this->assertSame(['Εξετάσεις, ΑΣΕΠ', 'Με κενά'], array_column($rows, 'describe'));
        $this->assertSame('ΠΥΣΠ', $rows[0]['remarks']);

        app(FolderCatalog::class)->import($text);
        $this->assertSame('Εξετάσεις, ΑΣΕΠ', Keepvalue::where('fakelos', 'Φ.712')->value('describe'));
    }

    public function test_import_updates_a_folder_written_without_the_dot_and_keeps_its_code(): void
    {
        // D07: the pasted code was normalised ("Φ2.1" → "Φ.2.1") but the list's was not → a duplicate folder
        Keepvalue::create(['fakelos' => 'Φ714.1', 'describe' => 'Παλιά']);
        $p = Protocol::factory()->number(714)->create(['fakelos' => 'Φ714.1']);

        $rows = app(FolderCatalog::class)->parseImport("Φ.714.1\tΝέα περιγραφή\n");
        $this->assertSame('update', $rows[0]['status']);
        app(FolderCatalog::class)->import("Φ.714.1\tΝέα περιγραφή\n");

        $this->assertSame(['Φ714.1'], Keepvalue::where('describe', 'Νέα περιγραφή')->pluck('fakelos')->all(), 'code kept as written');
        $this->assertSame(0, Keepvalue::where('fakelos', 'Φ.714.1')->count());
        $this->assertSame('Φ714.1', $p->fresh()->fakelos);
    }
}
