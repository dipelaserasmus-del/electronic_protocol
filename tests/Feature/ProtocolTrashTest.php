<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Livewire\TrashPage;
use App\Models\Attachment;
use App\Models\Protocol;
use App\Models\TrashedProtocol;
use App\Services\Protocols\ProtocolTrash;
use App\Services\Protocols\ProtocolWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** R4h: deleted protocols go to the trash; restore with the original number if free, else a new one. */
class ProtocolTrashTest extends FeatureTestCase
{
    private function protocol(int $num, array $attributes = [], ?int $year = null): Protocol
    {
        $p = Protocol::factory()->number($num, $year)->create($attributes + ['thema' => "Θέμα $num", 'fakelos' => 'Φ.1', 'paratiriseis' => 'Αρχική σημείωση']);
        $a = Attachment::factory()->make(['protocol_id' => $p->id, 'name' => "αρχείο-$num.pdf"]);
        $a->savedPath = "arxeio/Φ.1/$num-αρχείο.pdf";
        Storage::put($a->savedPath, '%PDF');
        $a->save();
        return $p->fresh();
    }

    private function toTrash(Protocol $p): TrashedProtocol
    {
        return app(ProtocolWriter::class)->delete($this->admin(), $p);
    }

    private function trash(): ProtocolTrash
    {
        return app(ProtocolTrash::class);
    }

    public function test_deleting_moves_the_protocol_to_the_trash_and_frees_its_number(): void
    {
        $handler = $this->writer();
        $p = $this->protocol(15);
        $p->assignments()->create(['user_id' => $handler->id, 'type' => 'd']);
        $file = $p->attachments()->first();
        $earlier = Attachment::factory()->create(['protocol_id' => $p->id, 'name' => 'παλιό.pdf']);
        $earlier->delete();   // trashed on its own before: does not come back with the protocol

        $t = $this->toTrash($p);

        $this->assertNull(Protocol::find($p->id));
        $this->assertSame(0, DB::table('protocol_assignments')->where('protocol_id', $p->id)->count());
        $this->assertSame([$p->id, 15, (int) $p->etos, 'Θέμα 15', 'Φ.1'], [$t->protocol_id, $t->protocolnum, $t->etos, $t->thema, $t->fakelos]);
        $this->assertSame([$file->id], $t->data['attachments']);
        $this->assertSame('Αρχική σημείωση', $t->data['protocol']['paratiriseis']);
        $this->assertCount(1, $t->data['assignments']);
        $this->assertTrue(Attachment::withTrashed()->find($file->id)->trashed());

        // the number is free again (Q-2: delete to register the number again)
        $again = Protocol::factory()->number(15)->create();
        $this->assertSame(15, (int) $again->protocolnum);

        // not found by links, list or search
        $admin = $this->admin();
        $this->actingAs($admin)->get('/home/' . $p->id)->assertNotFound();
        $this->actingAs($admin)->get('/protocol/16/' . $p->etos)->assertNotFound();
    }

    public function test_restore_with_the_original_number_when_it_is_free(): void
    {
        $handler = $this->writer();
        $p = $this->protocol(20);
        $p->assignments()->create(['user_id' => $handler->id, 'type' => 'd']);
        $file = $p->attachments()->first();
        $t = $this->toTrash($p);
        $this->travelTo(now()->setTime(14, 30));

        $r = $this->trash()->restore($admin = $this->admin(), $t);

        $this->assertFalse($r['newNumber']);
        $back = Protocol::find($p->id);
        $this->assertSame([20, (int) $p->etos, $p->protocoldate], [(int) $back->protocolnum, (int) $back->etos, $back->protocoldate]);
        $this->assertSame($p->thema, $back->thema);
        $this->assertStringStartsWith("Αρχική σημείωση\nΕπαναφορά από τον κάδο " . now()->format('d/m/Y') . " 14:30 ({$admin->name}) — αρχικός αρ. πρωτ. 20/{$p->etos}", $back->paratiriseis);
        $this->assertSame([$handler->id], $back->assignments()->pluck('user_id')->all());
        $file = Attachment::find($file->id);
        $this->assertNotNull($file, 'its file came back');
        $this->assertSame("arxeio/Φ.1/20-αρχείο.pdf", $file->savedPath);
        Storage::assertExists($file->savedPath);
        $this->assertSame(0, TrashedProtocol::count());
    }

    public function test_restore_with_a_new_number_when_the_original_was_given_again(): void
    {
        $this->setting('yearInUse', (int) now()->format('Y'));
        $p = $this->protocol(30, [], (int) now()->format('Y'));
        $t = $this->toTrash($p);
        Protocol::factory()->number(30)->create(['thema' => 'Άλλο με τον ίδιο αριθμό']);
        Protocol::factory()->number(31)->create();

        $r = $this->trash()->restore($this->admin(), $t);

        $this->assertTrue($r['newNumber']);
        $this->assertSame("30/{$p->etos}", $r['original']);
        $back = $r['protocol'];
        $this->assertSame([32, (int) now()->format('Y'), now()->format('Y-m-d')], [(int) $back->protocolnum, (int) $back->etos, $back->dateOf('protocoldate')->format('Y-m-d')]);
        $this->assertStringContainsString("αρχικός αρ. πρωτ. 30/{$p->etos}, ημ/νία " . $p->dateOf('protocoldate')->format('d/m/Y'), $back->paratiriseis);
        $this->assertSame($p->id, $back->id, 'its own id back: files and links follow');
    }

    public function test_who_may_restore_and_who_sees_what(): void
    {
        $writer = $this->writer();
        $other = $this->writer();
        $assigner = $this->assigner();
        $mine = $this->toTrash($this->protocol(40, ['user_id' => $writer->id]));
        $theirs = $this->toTrash($this->protocol(41, ['user_id' => $other->id]));
        $assigners = $this->toTrash($this->protocol(42, ['user_id' => $assigner->id]));

        $this->assertTrue(ProtocolTrash::mayRestore($writer, $mine));
        $this->assertFalse(ProtocolTrash::mayRestore($writer, $theirs));
        $this->assertTrue(ProtocolTrash::mayRestore($assigner, $assigners));
        $this->assertFalse(ProtocolTrash::mayRestore($assigner, $mine), 'an assigner: only their own too');
        $this->assertFalse(ProtocolTrash::mayRestore($this->reader(), $mine));
        $this->assertTrue(ProtocolTrash::mayRestore($this->admin(), $theirs));
        $this->assertSame([40], ProtocolTrash::visibleTo($writer)->pluck('protocolnum')->all());
        $this->assertSame([], ProtocolTrash::visibleTo($this->reader())->pluck('protocolnum')->all());
        $this->assertSame([40, 41, 42], ProtocolTrash::visibleTo($this->admin())->orderBy('protocolnum')->pluck('protocolnum')->all());

        try {
            $this->trash()->restore($writer, $theirs);
            $this->fail('restored by another writer');
        } catch (AuthorizationException) {
            $this->assertNotNull(TrashedProtocol::find($theirs->id));
        }

        // the page: a writer sees only their own, restores it; cannot purge
        Livewire::actingAs($writer)->test(TrashPage::class)
            ->assertSet('tab', 'protocols')->assertSee('40/')->assertDontSee('41/')->assertDontSee('id="trash-tab-files"', false)
            ->assertDontSeeHtml('data-action="purge-protocol"')
            ->call('purgeProtocol', $mine->id)->assertForbidden();
        Livewire::actingAs($writer)->test(TrashPage::class)
            ->call('restoreProtocol', $theirs->id)->assertDispatched('toast', type: 'error');
        Livewire::actingAs($writer)->test(TrashPage::class)
            ->call('restoreProtocol', $mine->id)->assertDispatched('toast', type: 'success');
        $this->assertNotNull(Protocol::where('protocolnum', 40)->first());
        $this->actingAs($this->reader())->get('/trash')->assertForbidden();
    }

    public function test_administrators_search_purge_and_see_it_from_a_link(): void
    {
        $admin = $this->admin();
        $t = $this->toTrash($p = $this->protocol(50, ['thema' => 'Εκδρομή Ε τάξης']));
        $this->toTrash($this->protocol(51, ['thema' => 'Άλλο θέμα']));
        $file = Attachment::onlyTrashed()->where('protocol_id', $p->id)->first();

        Livewire::actingAs($admin)->test(TrashPage::class, ['tab' => 'protocols'])
            ->assertSee('Εκδρομή Ε τάξης')->assertSee('Άλλο θέμα')
            ->set('search', 'εκδρομη')->assertSee('Εκδρομή Ε τάξης')->assertDontSee('Άλλο θέμα')
            ->set('search', '51')->assertSee('Άλλο θέμα')->assertDontSee('Εκδρομή Ε τάξης');

        // R3h: administrators are told it is in the trash; others get the same page as for any missing number
        $this->actingAs($admin)->get("/protocol/50/{$p->etos}")->assertNotFound()->assertSee('Στον κάδο')->assertSee('Άνοιγμα στον κάδο');
        $this->actingAs($this->writer())->get("/protocol/50/{$p->etos}")->assertNotFound()->assertDontSee('Στον κάδο');

        Livewire::actingAs($admin)->test(TrashPage::class, ['tab' => 'protocols'])->call('purgeProtocol', $t->id)->assertDispatched('toast', type: 'success');
        $this->assertNull(TrashedProtocol::find($t->id));
        $this->assertNull(Attachment::withTrashed()->find($file->id), 'its files go with it');
        Storage::assertMissing($file->savedPath);
    }

    public function test_the_daily_clean_up_and_folder_merges_include_the_trash(): void
    {
        $old = $this->toTrash($this->protocol(60));
        $new = $this->toTrash($this->protocol(61));
        TrashedProtocol::whereKey($old->id)->update(['deleted_at' => now()->subDays(31)]);
        $this->assertSame(1, $this->trash()->purgeExpired());
        $this->assertNull(TrashedProtocol::find($old->id));
        $this->assertNotNull(TrashedProtocol::find($new->id));

        \App\Models\Keepvalue::firstOrCreate(['fakelos' => 'Φ.1'], ['describe' => 'Ένα']);
        \App\Models\Keepvalue::firstOrCreate(['fakelos' => 'Φ.2'], ['describe' => 'Δύο']);
        app(\App\Services\Folders\FolderMerge::class)->apply('Φ1', 'Φ.2');
        $new->refresh();
        $this->assertSame(['Φ.2', 'Φ.2'], [$new->fakelos, $new->data['protocol']['fakelos']]);
        $file = $new->attachments()->first();
        $this->assertSame('arxeio/Φ.2/61-αρχείο.pdf', $file->trashed_from, 'restored under the new folder');
        $this->trash()->restore($this->admin(), $new);
        $this->assertSame('Φ.2', Protocol::where('protocolnum', 61)->first()->fakelos);
        Storage::assertExists('arxeio/Φ.2/61-αρχείο.pdf');
    }

    public function test_the_form_says_it_goes_to_the_trash(): void
    {
        $this->setting('protocolValidate', 0);
        $p = $this->protocol(70);
        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])
            ->assertSeeHtml('Να μεταφερθεί στον κάδο το πρωτόκολλο 70/')
            ->call('deleteProtocol')->assertRedirect(url('/home/list'));
        $this->assertSame(1, TrashedProtocol::count());
        // R4l (owner, 2026-11-25): only «… μεταφέρθηκε στον κάδο.», with «κάδο» a link to the trash's protocols
        $this->assertSame(['κάδο', '/trash?tab=protocols'], session('notification')['link']);
        $html = $this->actingAs($this->admin())->withSession(['notification' => session('notification')])->get('/home/list')->getContent();
        $this->assertStringContainsString('Το πρωτόκολλο 70/'.now()->year.' μεταφέρθηκε στον <a href="'.e(url('/trash?tab=protocols')).'" class="alert-link">κάδο</a>.', $html);
        $this->assertStringNotContainsString('πάλι ελεύθερος', $html);
        $this->assertStringNotContainsString(':link', $html);

        // a link that is not a path of this site is never drawn (the marker stays as text)
        $html = $this->actingAs($this->admin())->withSession(['notification' => ['message' => 'Δες :link', 'link' => ['εδώ', 'https://example.org/'], 'alert-type' => 'success']])
            ->get('/home/list')->getContent();
        $this->assertStringNotContainsString('example.org', $html);
    }

    /** review of R4h: the files tab on every request; a file that does not come back is said and kept */
    public function test_the_files_tab_is_checked_on_every_request_and_failed_files_are_said(): void
    {
        $writer = $this->writer();
        $t = $this->toTrash($this->protocol(80, ['user_id' => $writer->id]));
        $page = Livewire::actingAs($writer)->test(TrashPage::class)->set('tab', 'files');
        $page->assertSet('tab', 'protocols')->assertDontSee('αρχείο-80.pdf')->assertDontSeeHtml('class="trash-row"');

        // its file's place in the trash is gone: the protocol comes back, the file is said to be missing and kept
        $file = Attachment::onlyTrashed()->where('protocol_id', $t->protocol_id)->first();
        $file->forceFill(['deleted_at' => now()->subDays(29)])->saveQuietly();
        Storage::deleteDirectory('arxeio/Φ.1');
        Storage::put('arxeio/Φ.1', 'a file where its folder must go: it cannot be moved back');
        Livewire::actingAs($writer)->test(TrashPage::class)->call('restoreProtocol', $t->id)
            ->assertDispatched('toast', fn ($name, $p) => $p['type'] === 'warning' && str_contains($p['message'], '1 αρχείο δεν επανήλθε'));
        $this->assertTrue($file->fresh()->trashed());
        $this->assertTrue($file->fresh()->deleted_at->isToday(), 'kept for another full period');
    }
}
