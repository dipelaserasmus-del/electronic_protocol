<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Models\Attachment;
use App\Models\Protocol;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/** R1b: the rebuilt protocol form (App\Livewire\ProtocolForm). Saving rules themselves: ProtocolWriterTest. */
class ProtocolFormTest extends FeatureTestCase
{
    public function test_number_shows_at_once_whether_it_is_free_or_used(): void
    {
        $this->setting('protocolValidate', 0);
        $used = Protocol::factory()->number(7)->create();
        Protocol::factory()->number(8)->placeholder()->create();

        Livewire::actingAs($this->admin())->test(ProtocolForm::class)
            ->assertSee('Κενό: ο επόμενος ελεύθερος')
            ->set('form.protocolnum', '9')->assertSee('✔ Ελεύθερος')
            ->set('form.protocolnum', '7')->assertSee('✖ Δεσμευμένος από το')->assertSee('/home/' . $used->id, false)
            ->set('form.protocolnum', '8')->assertSee('Κρατημένος ως «ΕΛΕΥΘΕΡΟ»')
            ->set('form.protocolnum', 'x1')->assertSee('Γράψτε θετικό ακέραιο αριθμό.')
            ->set('form.etos', (string) (now()->year - 1))->set('form.protocolnum', '7')->assertSee('✔ Ελεύθερος');
    }

    public function test_users_who_can_not_choose_see_the_number_given_on_save(): void
    {
        Protocol::factory()->number(41)->create();
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)
            ->assertSee('Δίνεται κατά την αποθήκευση (επόμενος: 42)')->assertDontSee('id="number-status"', false);
    }

    public function test_save_creates_the_protocol_and_opens_it(): void
    {
        $writer = $this->writer();
        Livewire::actingAs($writer)->test(ProtocolForm::class)
            ->set('form.thema', 'Πρόσκληση -- σε ημερίδα')->set('form.fakelos', 'Φ.1')
            ->call('save')->assertHasNoErrors()->assertRedirect(url('/home/' . Protocol::firstOrFail()->id));

        $p = Protocol::firstOrFail();
        $this->assertSame(['Πρόσκληση -- σε ημερίδα', 'Φ.1', $writer->id], [$p->thema, $p->fakelos, $p->user_id]);
        $this->assertStringContainsString("Καταχωρίστηκε με αριθμό {$p->protocolnum}/{$p->etos}", session('notification')['message']);
    }

    public function test_errors_show_next_to_the_fields_and_keep_what_was_typed(): void
    {
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)
            ->set('form.thema', 'Εισερχόμενο')->set('form.in_num', '12/3')
            ->call('save')
            ->assertHasErrors(['in_date', 'in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis'])
            ->assertSee('Συμπληρώστε τον τόπο έκδοσης.')
            ->assertSet('form.thema', 'Εισερχόμενο')->assertSet('form.in_num', '12/3');
        $this->assertSame(0, Protocol::count());
    }

    public function test_same_subject_is_a_notice_not_a_question(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $writer = $this->writer();
        $mine = Protocol::factory()->number(1)->create(['thema' => 'Ίδιο θέμα', 'user_id' => $writer->id]);
        $hidden = Protocol::factory()->number(2)->create(['thema' => 'Ίδιο θέμα']);

        Livewire::actingAs($writer)->test(ProtocolForm::class)
            ->set('form.thema', 'Ίδιο θέμα')
            ->assertSee('Υπάρχει ήδη πρωτόκολλο με το ίδιο θέμα')
            ->assertSee('/home/' . $mine->id, false)
            ->assertDontSee('/home/' . $hidden->id . '"', false)   // only protocols the user may see
            ->call('save')->assertHasNoErrors();                   // never blocks
        $this->assertSame(3, Protocol::count());
    }

    public function test_reader_sees_a_read_only_form(): void
    {
        $p = Protocol::factory()->number(1)->create(['thema' => 'Για ανάγνωση']);

        Livewire::actingAs($this->reader())->test(ProtocolForm::class, ['protocol' => $p])
            ->assertSee('Για ανάγνωση')->assertDontSee('>Αποθήκευση<', false)
            ->assertSeeHtml('<fieldset class="card shadow-sm mb-3" disabled>')
            ->set('form.thema', 'Αλλοιωμένο')->call('save')->assertDispatched('toast', type: 'error');
        $this->assertSame('Για ανάγνωση', $p->fresh()->thema);
    }

    public function test_reader_assigned_for_handling_marks_it_done(): void
    {
        $reader = $this->reader();
        $p = Protocol::factory()->number(1)->assignedTo($reader)->create();

        Livewire::actingAs($reader)->test(ProtocolForm::class, ['protocol' => $p])
            ->assertSee('Διεκπεραιώθηκε')->call('markDone')->assertRedirect();

        $p->refresh();
        $this->assertSame((int) now()->format('Ymd'), $p->diekp_date);
        $this->assertStringContainsString("διεκπεραιώθηκε από {$reader->name}", $p->paratiriseis);
    }

    public function test_copy_keeps_the_content_but_not_the_number(): void
    {
        $handler = $this->writer();
        $p = Protocol::factory()->number(3)->incoming()->assignedTo($handler)->create(['thema' => 'Προς αντιγραφή']);

        Livewire::actingAs($this->assigner())->test(ProtocolForm::class, ['protocol' => $p, 'copy' => true])
            ->assertSee('Αντιγραφή ως νέο πρωτόκολλο')
            ->assertSet('form.thema', 'Προς αντιγραφή')->assertSet('form.in_num', $p->in_num)->assertSet('handle', [(string) $handler->id])
            ->call('save')->assertHasNoErrors();

        $this->assertSame(2, Protocol::where('thema', 'Προς αντιγραφή')->count());
        $this->assertSame(4, Protocol::max('protocolnum'));
    }

    public function test_files_are_uploaded_with_the_save(): void
    {
        $writer = $this->writer();
        Livewire::actingAs($writer)->test(ProtocolForm::class)
            ->set('form.thema', 'Με συνημμένο')->set('form.fakelos', 'Φ.1')
            ->set('uploads', [UploadedFile::fake()->createWithContent('Απόφαση.pdf', '%PDF-1.4 απόφαση')])
            ->set('newAda', 'ΨΩ1Δ46ΜΤΛΗ-ΑΒΓ')
            ->call('save')->assertHasNoErrors();

        $p = Protocol::firstOrFail();
        $file = $p->attachments()->whereNotNull('savedPath')->firstOrFail();
        $this->assertSame('Απόφαση.pdf', $file->name);
        $this->assertSame('%PDF-1.4 απόφαση', Storage::get($file->savedPath));
        $this->assertSame(['ΨΩ1Δ46ΜΤΛΗ-ΑΒΓ'], $p->attachments()->whereNull('savedPath')->pluck('ada')->all());
    }

    public function test_attachment_goes_to_the_trash_from_the_form(): void
    {
        $writer = $this->writer();
        $p = Protocol::factory()->number(1)->create(['user_id' => $writer->id, 'thema' => '']);   // open to writers
        $a = Attachment::factory()->withFile()->create(['protocol_id' => $p->id]);

        Livewire::actingAs($writer)->test(ProtocolForm::class, ['protocol' => $p])
            ->assertSee($a->name)->call('removeAttachment', $a->id)->assertDontSee('/download/' . $a->id, false);

        $this->assertTrue(Attachment::withTrashed()->find($a->id)->trashed());
    }

    public function test_administrator_deletes_a_protocol(): void
    {
        $this->setting('protocolValidate', 0);
        $p = Protocol::factory()->number(1)->create();
        $a = Attachment::factory()->withFile()->create(['protocol_id' => $p->id]);
        $path = $a->savedPath;

        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])
            ->call('deleteProtocol')->assertRedirect(url('/home/list'));

        $this->assertNull(Protocol::find($p->id));
        $this->assertSame(0, Attachment::withTrashed()->count());
        Storage::assertMissing($path);
    }

    public function test_page_offers_values_while_typing(): void
    {
        $writer = $this->writer();
        Protocol::factory()->number(1)->create(['in_topos_ekdosis' => 'Αθήνα', 'keywords' => 'σχολείο, εκδρομή', 'user_id' => $writer->id]);

        $this->actingAs($writer)->get('/home')->assertOk()
            ->assertSee('<datalist id="suggest-in_topos_ekdosis">', false)->assertSee('<option value="Αθήνα">', false)
            ->assertSee('<option value="εκδρομή">', false)
            ->assertSee('list="suggest-in_topos_ekdosis"', false);
    }

    public function test_tab_from_the_number_goes_to_the_date(): void
    {
        $this->setting('protocolValidate', 0);
        $html = $this->actingAs($this->admin())->get('/home')->assertOk()->getContent();

        // between the number and the date field nothing else can take the focus: the date's helper buttons are out
        // of the tab order (the year comes before the number and is out too)
        $between = substr($html, strpos($html, 'id="f-protocolnum"'), strpos($html, 'id="f-protocoldate"') - strpos($html, 'id="f-protocolnum"'));
        preg_match_all('/<(input|button|select|textarea|a)\b[^>]*>/', $between, $m);
        foreach (array_slice($m[0], 1) as $tag) {
            $this->assertMatchesRegularExpression('/tabindex="-1"|type="checkbox"/', $tag, "focusable before the date: $tag");
        }
    }
}
