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
}
