<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Models\Attachment;
use App\Models\Protocol;
use App\Services\Stamp\Pdf\PdfStamper;
use App\Services\Stamp\StampedCopy;
use App\Services\Stamp\StampTemplate;
use App\Support\UserPreferences;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PdfBuilder;

/**
 * R4f: files are stored without the stamp; viewing, downloading and printing give a stamped copy (made once, made
 * again when the number, the date, the template or the file change), unless the user chose the originals or asks for
 * one; files saved before R4f carry their stamp inside them and are never stamped twice.
 */
class StampOnViewTest extends FeatureTestCase
{
    private string $pdf;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('putStamp', 1);
        $this->pdf = PdfBuilder::make();
    }

    private function attachment(array $attributes = [], ?Protocol $protocol = null, ?string $contents = null): Attachment
    {
        $protocol ??= Protocol::factory()->number(1)->create();
        $a = Attachment::factory()->make(['protocol_id' => $protocol->id, 'name' => 'απόφαση.pdf']);
        $a->forceFill($attributes);
        $a->savedPath = 'arxeio/test/' . uniqid() . '_' . $a->name;
        Storage::put($a->savedPath, $contents ?? $this->pdf);
        $a->save();
        return $a;
    }

    private function copies(): array
    {
        return Storage::files(StampedCopy::DIR);
    }

    public function test_new_files_are_stored_as_they_came(): void
    {
        Livewire::actingAs($this->admin())->test(ProtocolForm::class)
            ->set('form.thema', 'Με PDF')->set('form.fakelos', 'Φ.1')
            ->set('picked', [UploadedFile::fake()->createWithContent('έγγραφο.pdf', $this->pdf)])
            ->call('save')->assertHasNoErrors();
        $a = Attachment::latest('id')->first();
        $this->assertSame($this->pdf, Storage::get($a->savedPath), 'no stamp in the stored file');
        $this->assertFalse($a->stamp_in_file);
        $this->assertStringNotContainsString('f-nostamp', Livewire::actingAs($this->admin())->test(ProtocolForm::class)->html(), '«Χωρίς σφραγίδα» is gone');
    }

    public function test_the_download_is_stamped_once_and_made_again_when_something_changes(): void
    {
        $a = $this->attachment();
        $admin = $this->admin();

        $first = $this->actingAs($admin)->get('/download/' . $a->id)->assertOk();
        $stamped = $first->streamedContent();
        $this->assertStringStartsWith($this->pdf, $stamped, 'the original inside, the stamp after it');
        $this->assertStringContainsString(PdfStamper::MARK, $stamped);
        $this->assertCount(1, $this->copies());
        $this->assertSame($this->pdf, Storage::get($a->savedPath), 'the stored file is not changed');

        $this->actingAs($admin)->get('/download/' . $a->id)->assertOk();
        $this->assertCount(1, $this->copies(), 'made once, then reused');
        $before = $this->copies();

        // another number: another stamp, the old copy goes
        $a->protocol->update(['protocolnum' => 2]);
        $this->assertStringContainsString(PdfStamper::MARK, $this->actingAs($admin)->get('/download/' . $a->id)->streamedContent());
        $this->assertCount(1, $this->copies());
        $this->assertNotSame($before, $this->copies());

        // another template too
        $before = $this->copies();
        $t = StampTemplate::current();
        $t->color = '#000000';
        $t->save();
        $this->actingAs($admin)->get('/download/' . $a->id)->assertOk();
        $this->assertNotSame($before, $this->copies());

        // the original on request
        $this->assertSame($this->pdf, $this->actingAs($admin)->get('/download/' . $a->id . '?original=1')->streamedContent());

        // to the trash: its copy goes
        $a->delete();
        $this->assertSame([], $this->copies());
    }

    public function test_a_user_may_choose_the_originals(): void
    {
        $a = $this->attachment();
        $writer = $this->writer();
        $this->setting('limitProtocolAccessList', 0);
        $this->actingAs($writer)->post('/preferences', ['key' => 'stamped', 'value' => 'no'])->assertOk()->assertJson(['value' => 'no']);
        $this->assertFalse(StampedCopy::wanted($writer->fresh()));
        $this->assertSame($this->pdf, $this->actingAs($writer->fresh())->get('/download/' . $a->id)->streamedContent());
        $this->actingAs($writer)->post('/preferences', ['key' => 'stamped', 'value' => 'maybe'])->assertStatus(422);
        UserPreferences::set($writer, 'stamped', 'yes');
        $this->assertStringContainsString(PdfStamper::MARK, $this->actingAs($writer->fresh())->get('/download/' . $a->id)->streamedContent());
    }

    public function test_what_is_never_stamped(): void
    {
        $admin = $this->admin();
        $p = Protocol::factory()->number(5)->create();
        $old = $this->attachment(['stamp_in_file' => true, 'name' => 'παλιό.pdf'], $p);
        $text = $this->attachment(['name' => 'σημείωμα.txt'], $p, 'κείμενο');
        foreach ([$old, $text] as $a) {
            $this->assertFalse(StampedCopy::applies($a));
        }
        $this->assertSame($this->pdf, $this->actingAs($admin)->get('/download/' . $old->id)->streamedContent(), 'stamped before R4f: as stored');

        $this->assertSame([], $this->copies());
    }

    /** R4i (owner, 2026-11-25): every user may have the stamp; the setting is only the choice of those who have not chosen */
    public function test_the_setting_is_only_the_default_choice(): void
    {
        $p = Protocol::factory()->number(7)->create();
        $a = $this->attachment(['name' => 'νέο.pdf'], $p);
        [$undecided, $wants, $refuses] = [$this->writer(), $this->reader(), $this->admin()];
        UserPreferences::set($wants, 'stamped', 'yes');
        UserPreferences::set($refuses, 'stamped', 'no');
        $stamped = fn ($u) => str_contains($this->actingAs($u->fresh())->get('/download/' . $a->id)->streamedContent(), PdfStamper::MARK);

        $this->setting('putStamp', 0);
        $this->assertTrue(StampedCopy::applies($a), 'stampable whatever the setting');
        $this->assertSame([false, true, false], [$stamped($undecided), $stamped($wants), $stamped($refuses)]);
        $this->setting('putStamp', 1);
        $this->assertSame([true, true, false], [$stamped($undecided), $stamped($wants), $stamped($refuses)]);
        $this->assertSame($this->pdf, $this->actingAs($wants->fresh())->get('/download/' . $a->id . '?original=1')->streamedContent());
    }

    public function test_the_form_offers_the_switch_and_marks_files_stamped_before(): void
    {
        $p = Protocol::factory()->number(6)->create();
        $this->attachment(['name' => 'νέο.pdf'], $p);
        $this->attachment(['name' => 'παλιό.pdf', 'stamp_in_file' => true], $p);

        $html = Livewire::actingAs($this->reader())->test(ProtocolForm::class, ['protocol' => $p])->html();
        $this->assertStringContainsString('data-stamp-toggle', $html);
        $this->assertMatchesRegularExpression('/data-stamp-toggle[^>]*checked/', $html);
        $this->assertSame(1, substr_count($html, 'η σφραγίδα είναι μέσα στο αρχείο'));
        $this->assertSame(1, substr_count($html, 'data-preview-stamp="in-file"'));
        // the preview window has it too
        $this->assertStringContainsString('id="preview-stamp-input"', $this->actingAs($this->reader())->get('/home/' . $p->id)->getContent());

        // R4i: the setting off: the switch is still there, off for a user who has not chosen; the note stays
        $this->setting('putStamp', 0);
        $html = Livewire::actingAs($this->reader())->test(ProtocolForm::class, ['protocol' => $p])->html();
        $this->assertStringContainsString('data-stamp-toggle', $html);
        $this->assertDoesNotMatchRegularExpression('/data-stamp-toggle[^>]*checked/', $html);
        $this->assertSame(1, substr_count($html, 'η σφραγίδα είναι μέσα στο αρχείο'));
        $this->assertStringContainsString('id="preview-stamp-input"', $this->actingAs($this->reader())->get('/home/' . $p->id)->getContent());
    }

    /** files saved before R4f: the migration marks the PDFs, docx and odt */
    public function test_the_migration_marks_the_files_saved_before(): void
    {
        $p = Protocol::factory()->number(7)->create();
        $pdf = $this->attachment(['name' => 'α.PDF'], $p);
        $docx = $this->attachment(['name' => 'β.docx'], $p);
        $jpg = $this->attachment(['name' => 'γ.jpg'], $p);
        \Illuminate\Support\Facades\Schema::table('attachments', fn ($t) => $t->dropColumn('stamp_in_file'));
        (require database_path('migrations/2026_11_24_000002_attachments_stamp_in_file.php'))->up();
        $this->assertSame([true, true, false], [$pdf->fresh()->stamp_in_file, $docx->fresh()->stamp_in_file, $jpg->fresh()->stamp_in_file],
            json_encode(\Illuminate\Support\Facades\DB::table('attachments')->get(['name', 'savedPath', 'stamp_in_file'])));
        (require database_path('migrations/2026_11_24_000002_attachments_stamp_in_file.php'))->up();   // twice: no harm
        $this->assertTrue($pdf->fresh()->stamp_in_file);
    }

    /** review of R4f: the office's name is part of the stamp; a file uploaded again over an old one is stamped */
    public function test_office_name_and_a_file_uploaded_again(): void
    {
        $a = $this->attachment();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/download/' . $a->id)->assertOk();
        $before = $this->copies();
        $this->setting('ipiresiasName', 'Άλλη υπηρεσία');
        $this->actingAs($admin)->get('/download/' . $a->id)->assertOk();
        $this->assertNotSame($before, $this->copies(), 'made again with the new name');

        $p = Protocol::factory()->number(9)->create(['fakelos' => 'Φ.1']);
        $old = $this->attachment(['name' => 'παλιό.pdf', 'stamp_in_file' => true, 'mimeType' => 'application/pdf'], $p);
        $old->forceFill(['savedPath' => 'arxeio/Φ.1/9-' . $p->dateOf('protocoldate')->format('Ymd') . '_παλιό.pdf'])->save();
        Storage::put($old->savedPath, $this->pdf);
        app(\App\Services\Protocols\ProtocolWriter::class)->update($admin, $p->fresh(), $this->protocolForm(['thema' => $p->thema, 'fakelos' => $p->fakelos]),
            [['file' => UploadedFile::fake()->createWithContent('παλιό.pdf', $this->pdf)]]);
        $again = Attachment::where('protocol_id', $p->id)->where('name', 'παλιό.pdf')->get();
        $this->assertSame([[$old->id, false]], $again->map(fn ($x) => [$x->id, $x->stamp_in_file])->all(), 'the same row, now unstamped');
    }
}
