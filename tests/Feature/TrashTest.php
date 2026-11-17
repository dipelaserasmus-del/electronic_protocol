<?php

namespace Tests\Feature;

use App\Livewire\TrashPage;
use App\Models\Attachment;
use App\Models\Config;
use App\Models\Protocol;
use App\Services\Attachments\Trash;
use App\Services\Protocols\ProtocolWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** R4b: the attachments' trash with retention. */
class TrashTest extends FeatureTestCase
{
    private function file(Protocol $p, string $name, string $content = '%PDF'): Attachment
    {
        Storage::put("arxeio/Φ.1/$name", $content);
        return Attachment::create(['protocol_id' => $p->id, 'name' => $name, 'savedPath' => "arxeio/Φ.1/$name", 'mimeType' => 'application/pdf']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('trashRetentionStart', now()->subYear()->toDateTimeString());   // installed long ago
    }

    private function trash(): Trash
    {
        return app(Trash::class);
    }

    public function test_trashing_moves_the_file_and_records_where_it_was(): void
    {
        $writer = $this->writer();
        $p = Protocol::factory()->number(1)->create(['user_id' => $writer->id, 'thema' => '']);   // open to writers
        $a = $this->file($p, 'α.pdf', 'Α');

        app(ProtocolWriter::class)->removeAttachment($writer, $a);

        $a = Attachment::withTrashed()->find($a->id);
        $this->assertTrue($a->trashed());
        $this->assertSame(['trash/Φ.1/α.pdf', 'arxeio/Φ.1/α.pdf', $writer->id], [$a->savedPath, $a->trashed_from, $a->deleted_by],
            'before R4b the row kept the arxeio/ path');
        Storage::assertMissing('arxeio/Φ.1/α.pdf');
        $this->assertSame('Α', Storage::get('trash/Φ.1/α.pdf'));
    }

    public function test_a_file_already_in_the_trash_with_the_same_name_is_kept(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $first = $this->file($p, 'ίδιο.pdf', 'ΠΡΩΤΟ');
        $this->trash()->trash($first);
        $second = $this->file($p, 'ίδιο.pdf', 'ΔΕΥΤΕΡΟ');
        $this->trash()->trash($second);

        $this->assertSame('ΠΡΩΤΟ', Storage::get(Attachment::withTrashed()->find($first->id)->savedPath), 'before R4b it was deleted');
        $this->assertSame('ΔΕΥΤΕΡΟ', Storage::get(Attachment::withTrashed()->find($second->id)->savedPath));
        $this->assertSame('trash/Φ.1/ίδιο (2).pdf', Attachment::withTrashed()->find($second->id)->savedPath);
    }

    public function test_restore_puts_it_back_also_for_rows_trashed_before_r4b(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $a = $this->file($p, 'β.pdf', 'Β');
        $this->trash()->trash($a);
        $this->assertTrue($this->trash()->restore(Attachment::withTrashed()->find($a->id)));
        $a = Attachment::find($a->id);
        $this->assertSame(['arxeio/Φ.1/β.pdf', null, null], [$a->savedPath, $a->trashed_from, $a->deleted_by]);
        $this->assertSame('Β', Storage::get('arxeio/Φ.1/β.pdf'));

        // the old shape: row still says arxeio/, the file is in trash/
        Storage::put('trash/Φ.1/γ.pdf', 'Γ');
        $old = Attachment::create(['protocol_id' => $p->id, 'name' => 'γ.pdf', 'savedPath' => 'arxeio/Φ.1/γ.pdf']);
        DB::table('attachments')->where('id', $old->id)->update(['deleted_at' => now()]);
        $this->assertTrue($this->trash()->restore(Attachment::withTrashed()->find($old->id)));
        $this->assertSame('Γ', Storage::get('arxeio/Φ.1/γ.pdf'));
        Storage::assertMissing('trash/Φ.1/γ.pdf');
    }

    public function test_deleted_protocol_keeps_its_files_in_the_trash_with_its_number_but_they_cant_be_restored(): void
    {
        $this->setting('protocolValidate', 0);
        $p = Protocol::factory()->number(12)->create(['thema' => 'Διαγραμμένο θέμα']);
        $a = $this->file($p, 'δ.pdf');

        app(ProtocolWriter::class)->delete($this->admin(), $p);

        $a = Attachment::withTrashed()->find($a->id);
        $this->assertSame("12/{$p->etos} · Διαγραμμένο θέμα", $a->protocol_label);
        Storage::assertExists($a->savedPath);
        $this->assertFalse($this->trash()->restore($a));
    }

    public function test_daily_clean_up_respects_the_retention_days(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $old = $this->file($p, 'παλιό.pdf');
        $new = $this->file($p, 'νέο.pdf');
        $this->trash()->trash($old);
        $this->trash()->trash($new);
        DB::table('attachments')->where('id', $old->id)->update(['deleted_at' => now()->subDays(31)]);
        DB::table('attachments')->where('id', $new->id)->update(['deleted_at' => now()->subDays(29)]);
        $oldPath = Attachment::withTrashed()->find($old->id)->savedPath;

        $this->setting('trashRetentionDays', 0);   // until emptied by hand
        $this->assertSame(0, $this->trash()->purgeExpired());

        $this->setting('trashRetentionDays', '');   // default 30
        $this->artisan('attachments:purge-trash')->expectsOutputToContain('Διαγράφηκαν οριστικά: 1')->assertSuccessful();
        $this->assertNull(Attachment::withTrashed()->find($old->id));
        Storage::assertMissing($oldPath);
        $this->assertNotNull(Attachment::withTrashed()->find($new->id));
    }

    /** Production never emptied its trash: what was there on the upgrade day gets its days from that day. */
    public function test_files_trashed_before_the_upgrade_count_from_the_upgrade_day(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $years = $this->file($p, 'πολύ-παλιό.pdf');
        $this->trash()->trash($years);
        DB::table('attachments')->where('id', $years->id)->update(['deleted_at' => now()->subYears(3)]);
        $this->setting('trashRetentionStart', now()->subDays(10)->toDateTimeString());   // upgraded 10 days ago

        $this->assertSame(0, $this->trash()->purgeExpired(), 'not on the first days after the upgrade');
        $this->assertSame(now()->addDays(20)->toDateString(), $this->trash()->purgeDate(Attachment::withTrashed()->find($years->id))->toDateString());
        $this->assertSame(1, $this->trash()->purgeExpired(now()->addDays(21)));
    }

    public function test_the_first_request_of_the_day_runs_the_clean_up_once(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $old = $this->file($p, 'παλιό.pdf');
        $this->trash()->trash($old);
        DB::table('attachments')->where('id', $old->id)->update(['deleted_at' => now()->subDays(40)]);
        $this->setting('trashLastPurge', now()->subDay()->toDateString());
        $user = $this->writer();

        $this->actingAs($user)->get('/about')->assertOk();

        $this->assertNull(Attachment::withTrashed()->find($old->id));
        $this->assertSame(now()->toDateString(), Config::getConfigValueOf('trashLastPurge'));

        $again = $this->file($p, 'ξανά.pdf');
        $this->trash()->trash($again);
        DB::table('attachments')->where('id', $again->id)->update(['deleted_at' => now()->subDays(40)]);
        $this->actingAs($user)->get('/about')->assertOk();
        $this->assertNotNull(Attachment::withTrashed()->find($again->id), 'only once a day');
    }

    public function test_trash_page_for_administrators(): void
    {
        $writer = $this->writer();
        $p = Protocol::factory()->number(5)->create(['thema' => 'Θέμα πέντε']);
        $a = $this->file($p, 'ε.pdf');
        $this->trash()->trash($a, $writer);
        $gone = $this->file($p, 'ζ.pdf');
        $this->trash()->trash($gone, null, '99/2025 · Παλιό σβησμένο');
        DB::table('attachments')->where('id', $gone->id)->update(['protocol_id' => 999999]);

        $this->actingAs($writer)->get('/trash')->assertForbidden();
        $this->actingAs($writer)->get("/trash/{$a->id}/download")->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/trash')->assertOk()
            ->assertSee('ε.pdf')->assertSee('5/' . $p->etos)->assertSee('Θέμα πέντε')->assertSee($writer->name)
            ->assertSee('99/2025 · Παλιό σβησμένο')->assertSee('το πρωτόκολλο διαγράφηκε')
            ->assertSee('μένει στον κάδο 30 ημέρες')->assertSee(now()->addDays(30)->format('d/m/Y'));
        $this->assertSame('%PDF', $this->actingAs($admin)->get("/trash/{$a->id}/download")->assertOk()->streamedContent());

        Livewire::actingAs($admin)->test(TrashPage::class)
            ->call('restore', $a->id)
            ->assertDispatched('toast', type: 'success', url: url('/home/' . $p->id), label: "Άνοιγμα πρωτοκόλλου 5/{$p->etos}")   // R1e
            ->call('restore', $gone->id)->assertDispatched('toast', type: 'error')
            ->call('purge', $gone->id);
        $this->assertFalse(Attachment::find($a->id)->trashed());
        $this->assertNull(Attachment::withTrashed()->find($gone->id));

        $this->trash()->trash(Attachment::find($a->id));
        Livewire::actingAs($admin)->test(TrashPage::class)->call('purgeAll')->assertSee('Ο κάδος είναι άδειος.');
        $this->assertSame(0, Attachment::onlyTrashed()->count());
    }

    public function test_retention_setting_is_validated(): void
    {
        $admin = $this->admin();
        $base = \App\Models\Config::getConfigValues()->all();
        $this->actingAs($admin)->from('/settings')->post('/settings', ['trashRetentionDays' => '-1'] + $base)->assertSessionHasErrors('trashRetentionDays');
        $this->actingAs($admin)->post('/settings', ['trashRetentionDays' => '7'] + $base)->assertSessionHasNoErrors();
        $this->assertSame(7, app(Trash::class)->retentionDays());
    }

    public function test_legacy_clean_up_pages_use_the_trash(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $expired = $this->file($p, 'ληγμένο.pdf');
        DB::table('attachments')->where('id', $expired->id)->update(['expires' => '2000-01-01']);
        $admin = $this->admin();

        $this->actingAs($admin)->from('/arxeio')->get('/delExpired')->assertRedirect('/arxeio');
        $row = Attachment::withTrashed()->find($expired->id);
        $this->assertSame(['trash/Φ.1/ληγμένο.pdf', 'arxeio/Φ.1/ληγμένο.pdf', $admin->id], [$row->savedPath, $row->trashed_from, $row->deleted_by]);

        Storage::put('trash/άσχετο.txt', 'not an attachment');
        $this->actingAs($admin)->from('/arxeio')->get('/delDeleted')->assertRedirect('/arxeio');
        $this->assertNull(Attachment::withTrashed()->find($expired->id));
        Storage::assertMissing('trash/Φ.1/ληγμένο.pdf');
        Storage::assertExists('trash/άσχετο.txt');
    }

    /** Review of R4b: purging an old-shape row never deletes a live attachment's file at the same arxeio/ path. */
    public function test_purging_an_old_row_never_deletes_a_live_file(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $live = $this->file($p, 'κοινό.pdf', 'ΖΩΝΤΑΝΟ');                    // live, at arxeio/Φ.1/κοινό.pdf
        $old = Attachment::create(['protocol_id' => $p->id, 'name' => 'κοινό.pdf', 'savedPath' => 'arxeio/Φ.1/κοινό.pdf']);
        DB::table('attachments')->where('id', $old->id)->update(['deleted_at' => now()]);   // trashed before R4b, its trash file gone

        $this->trash()->purge(Attachment::withTrashed()->find($old->id));

        $this->assertSame('ΖΩΝΤΑΝΟ', Storage::get($live->savedPath));
        $this->assertNull(Attachment::withTrashed()->find($old->id));
    }

    /** Review of R4b: a name at the length limit still gets a free name that fits. */
    public function test_same_long_name_twice_still_fits(): void
    {
        $p = Protocol::factory()->number(1)->create();
        $name = '1-20261116_' . str_repeat('α', 120) . '.pdf';   // 255 bytes, as FileNames::fit can make it
        $this->assertSame(255, strlen($name));
        foreach (['ΠΡΩΤΟ', 'ΔΕΥΤΕΡΟ'] as $content) {
            $this->trash()->trash($this->file($p, $name, $content));
        }
        $paths = Attachment::onlyTrashed()->orderBy('id')->pluck('savedPath')->all();
        $this->assertSame("trash/Φ.1/$name", $paths[0]);
        $this->assertStringEndsWith(' (2).pdf', $paths[1]);
        $this->assertLessThanOrEqual(255, strlen(basename($paths[1])));
        $this->assertSame('ΔΕΥΤΕΡΟ', Storage::get($paths[1]));
    }
}
