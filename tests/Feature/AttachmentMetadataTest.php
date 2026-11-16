<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Protocol;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** D03: size, SHA-256 and "deleted by" of attachments. */
class AttachmentMetadataTest extends FeatureTestCase
{
    public function test_uploaded_file_gets_its_size_and_hash(): void
    {
        $this->setting('safeNewProtocolNum', 0);
        $contents = "%PDF-1.4 D03\n" . str_repeat('x', 5000) . "\n%%EOF\n";

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 1, 'fakelos' => 'Φ.1',
            'att1' => UploadedFile::fake()->createWithContent('Πρόσκληση.pdf', $contents),
        ]))->assertRedirect();

        $a = Attachment::firstOrFail();
        $this->assertSame(strlen($contents), $a->size);
        $this->assertSame(hash('sha256', $contents), $a->sha256);
        $this->assertSame($contents, Storage::get($a->savedPath), 'hash is of the stored file');
    }

    public function test_attachment_without_file_has_no_size(): void
    {
        $a = Attachment::factory()->create(['ada' => 'ΑΔΑ-ΔΟΚΙΜΗ', 'savedPath' => null]);
        $this->assertNull($a->size);
        $this->assertNull($a->sha256);

        $b = Attachment::factory()->create(['savedPath' => 'arxeio/Φ.1/den-yparxei.pdf']);
        $this->assertNull($b->fresh()->size, 'missing file: left empty, no error');
    }

    public function test_replacing_the_stored_file_measures_again(): void
    {
        $a = Attachment::factory()->withFile('πρώτο')->create();
        Storage::put('arxeio/test/allo.pdf', 'δεύτερο περιεχόμενο');
        $a->update(['savedPath' => 'arxeio/test/allo.pdf']);

        $this->assertSame(strlen('δεύτερο περιεχόμενο'), $a->fresh()->size);
        $this->assertSame(hash('sha256', 'δεύτερο περιεχόμενο'), $a->fresh()->sha256);
    }

    /** Review finding: a new upload with the same name overwrites the file at the same path */
    public function test_file_overwritten_at_the_same_path_is_measured_again(): void
    {
        $a = Attachment::factory()->withFile('παλιά έκδοση')->create();
        Storage::put($a->savedPath, 'νέα έκδοση, μεγαλύτερη');
        $a->update(['keep' => '5']);   // what updateOrCreate does for the existing row

        $this->assertSame(strlen('νέα έκδοση, μεγαλύτερη'), $a->fresh()->size);
        $this->assertSame(hash('sha256', 'νέα έκδοση, μεγαλύτερη'), $a->fresh()->sha256);
    }

    /** Review finding: a trashed attachment is measured in trash/, even if a newer file now has its old path */
    public function test_trashed_attachment_is_measured_in_the_trash_first(): void
    {
        Storage::put('arxeio/Φ.1/5-20250105_x.pdf', 'νεότερο αρχείο με το ίδιο όνομα');
        Storage::put('trash/Φ.1/5-20250105_x.pdf', 'το διαγραμμένο');
        DB::table('attachments')->insert(['protocol_id' => 1, 'name' => 'x.pdf', 'savedPath' => 'arxeio/Φ.1/5-20250105_x.pdf',
            'deleted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('attachments:fill-metadata')->assertSuccessful();

        $this->assertSame(hash('sha256', 'το διαγραμμένο'), DB::table('attachments')->value('sha256'));
    }

    public function test_moving_to_the_trash_records_who_did_it(): void
    {
        $writer = $this->writer();
        $p = Protocol::factory()->number(1)->create(['user_id' => $writer->id, 'thema' => '']);   // open to writers
        $a = Attachment::factory()->withFile()->create(['protocol_id' => $p->id]);

        \Livewire\Livewire::actingAs($writer)->test(\App\Livewire\ProtocolForm::class, ['protocol' => $p])
            ->call('removeAttachment', $a->id);   // R1b form (was GET /attach/del)

        $trashed = Attachment::withTrashed()->findOrFail($a->id);
        $this->assertTrue($trashed->trashed());
        $this->assertSame($writer->id, $trashed->deleted_by);
        $this->assertSame($writer->id, $trashed->deletedBy->id);
    }

    public function test_expired_files_moved_to_the_trash_record_the_administrator(): void
    {
        $admin = $this->admin();
        $old = Attachment::factory()->withFile()->create(['expires' => 20200101]);
        $current = Attachment::factory()->withFile()->create(['expires' => 29990101]);

        $this->actingAs($admin)->from('/settings')->get('/delExpired')->assertRedirect();

        $this->assertSame($admin->id, Attachment::withTrashed()->find($old->id)->deleted_by);
        $this->assertNull(Attachment::find($current->id)->deleted_by);
        $this->assertNotNull(Attachment::find($current->id), 'not expired: not in the trash');
    }

    public function test_fill_metadata_command_measures_old_rows_and_counts_missing_files(): void
    {
        Storage::put('arxeio/Φ.1/1-20250105_old.pdf', 'παλιό αρχείο');
        Storage::put('trash/Φ.1/2-20250105_deleted.pdf', 'στον κάδο');
        $now = now();
        $row = fn (array $a) => $a + ['ada' => null, 'deleted_at' => null, 'protocol_id' => 1, 'name' => 'x.pdf', 'created_at' => $now, 'updated_at' => '2025-01-05 10:00:00'];
        // rows as in a production backup: written without the model, no size yet
        DB::table('attachments')->insert([
            $row(['savedPath' => 'arxeio/Φ.1/1-20250105_old.pdf']),
            $row(['savedPath' => 'arxeio/Φ.1/2-20250105_deleted.pdf', 'deleted_at' => $now]),   // moved to trash/
            $row(['savedPath' => 'arxeio/Φ.1/3-20250105_lost.pdf']),
            $row(['savedPath' => null, 'ada' => 'ΑΔΑ']),
        ]);

        $this->artisan('attachments:fill-metadata')
            ->expectsOutputToContain('Attachments measured: 2; file not found: 1.')
            ->assertSuccessful();

        $rows = DB::table('attachments')->orderBy('id')->get();
        $this->assertSame([strlen('παλιό αρχείο'), strlen('στον κάδο'), null, null], $rows->pluck('size')->map(fn ($v) => $v === null ? null : (int) $v)->all());
        $this->assertSame(hash('sha256', 'παλιό αρχείο'), $rows[0]->sha256);
        $this->assertSame('2025-01-05 10:00:00', $rows[0]->updated_at, 'not a change by a user: updated_at kept');

        $this->artisan('attachments:fill-metadata')
            ->expectsOutputToContain('Attachments measured: 0; file not found: 1.')->assertSuccessful();
    }
}
