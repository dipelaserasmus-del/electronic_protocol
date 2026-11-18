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

    /** R1e/R1f: related numbers are tags; existing protocol = green ✔ with a link ↗ (new tab), others orange ⚠. */
    public function test_related_numbers_are_checked_tags_linking_to_existing_protocols(): void
    {
        $year = (int) now()->year;
        $mine = Protocol::factory()->number(12)->create(['thema' => 'Σχετικό δώδεκα']);
        $old = Protocol::factory()->number(40)->create(['etos' => $year - 1]);
        $writer = $this->writer();

        $form = Livewire::actingAs($this->admin())->test(ProtocolForm::class)
            ->call('addTag', 'sxetiko', '12')                                  // a bare number gets the year
            ->call('addTag', 'sxetiko', '40/' . ($year - 1) . '; 77, φ.3')      // pasted list
            ->call('addTag', 'sxetiko', '12/' . $year)                         // no repeats
            ->assertSet('form.sxetiko', "12/$year, 40/" . ($year - 1) . ', 77/' . $year . ', φ.3');
        $html = $form->html();
        $form->assertSeeHtml('href="' . url('/home/' . $mine->id) . '" target="_blank"')
            ->assertSeeHtml('title="Σχετικό δώδεκα"')
            ->assertSeeHtml('href="' . url('/home/' . $old->id) . '" target="_blank"')
            ->assertSeeHtml('aria-label="Αφαίρεση 77/' . $year . '"')
            ->assertSeeHtml('aria-label="Αφαίρεση φ.3"')
            ->assertSeeHtml('title="Δεν υπάρχει πρωτόκολλο 77/' . $year . '"')
            ->assertSeeHtml('title="Δεν είναι αριθμός πρωτοκόλλου (π.χ. 12/2026)"');
        $this->assertSame(2, substr_count($html, 'tag-ok'));
        $this->assertSame(2, substr_count($html, 'tag-missing'));
        $this->assertSame(2, substr_count($html, 'icon-check'));
        $this->assertSame(2, substr_count($html, 'icon-warning'));

        // edited in place: the tag keeps its position; a bare number gets the year; editing to nothing removes it
        $form->call('editTag', 'sxetiko', 2, '13')->assertSet('form.sxetiko', "12/$year, 40/" . ($year - 1) . ", 13/$year, φ.3")
            ->call('editTag', 'sxetiko', 3, '')->assertSet('form.sxetiko', "12/$year, 40/" . ($year - 1) . ", 13/$year")
            ->call('editTag', 'sxetiko', 2, '12')->assertSet('form.sxetiko', "12/$year, 40/" . ($year - 1))   // already there
            ->call('addTag', 'sxetiko', '77, φ.3');

        $form->call('removeTag', 'sxetiko', 1)->assertSet('form.sxetiko', "12/$year, 77/$year, φ.3")
            ->set('form.thema', 'Με σχετικά')->set('form.fakelos', 'Φ.1')
            ->call('save')->assertHasNoErrors();
        $this->assertSame("12/$year, 77/$year, φ.3", Protocol::latest('id')->first()->sxetiko);

        // a writer limited to own/assigned protocols: green (it exists) but no link and no subject
        $this->setting('limitProtocolAccessList', 1);
        $limited = Livewire::actingAs($writer)->test(ProtocolForm::class)->call('addTag', 'sxetiko', '12');
        $limited->assertDontSeeHtml('href="' . url('/home/' . $mine->id) . '"')->assertDontSee('Σχετικό δώδεκα')
            ->assertSeeHtml('title="Υπάρχει πρωτόκολλο με αυτόν τον αριθμό"');
        $this->assertStringContainsString('tag-ok', $limited->html());
    }

    /** R1f: keywords as tags: no repeats (case and accents ignored), edited in place, saved as "a, b". */
    public function test_keywords_are_tags(): void
    {
        $form = Livewire::actingAs($this->admin())->test(ProtocolForm::class)
            ->call('addTag', 'keywords', 'Διαύγεια')
            ->call('addTag', 'keywords', 'εκδρομή;  σχολικά   έξοδα , διαυγεια')
            ->assertSet('form.keywords', 'Διαύγεια, εκδρομή, σχολικά έξοδα')
            ->assertSeeHtml('data-tag="σχολικά έξοδα"')
            ->call('editTag', 'keywords', 1, 'Εκδρομές')
            ->assertSet('form.keywords', 'Διαύγεια, Εκδρομές, σχολικά έξοδα')
            ->call('removeTag', 'keywords', 0)
            ->assertSet('form.keywords', 'Εκδρομές, σχολικά έξοδα')
            ->set('form.thema', 'Με λέξεις')->set('form.fakelos', 'Φ.1')
            ->call('save')->assertHasNoErrors();
        $this->assertSame('Εκδρομές, σχολικά έξοδα', Protocol::latest('id')->first()->keywords);

        // only these two fields
        Livewire::actingAs($this->admin())->test(ProtocolForm::class)->call('addTag', 'thema', 'x')->assertStatus(400);
    }

    public function test_fold_ignores_case_and_accents(): void
    {
        foreach (json_decode(file_get_contents(base_path('tests/fixtures/fold-cases.json')), true) as [$in, $out]) {
            $this->assertSame($out, \App\Support\TagList::fold($in), $in);
        }
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
        // R4b: its files wait in the trash, labelled with the protocol (before: deleted at once)
        $trashed = Attachment::onlyTrashed()->findOrFail($a->id);
        $this->assertSame("{$p->protocolnum}/{$p->etos} · {$p->thema}", $trashed->protocol_label);
        Storage::assertMissing($path);
        Storage::assertExists($trashed->savedPath);
    }

    public function test_page_offers_values_while_typing(): void
    {
        $writer = $this->writer();
        Protocol::factory()->number(1)->create(['in_topos_ekdosis' => 'Αθήνα', 'keywords' => 'σχολείο, εκδρομή', 'user_id' => $writer->id]);

        $this->actingAs($writer)->get('/home')->assertOk()
            ->assertSee('<datalist id="suggest-in_topos_ekdosis">', false)->assertSee('<option value="Αθήνα">', false)
            ->assertDontSee('<datalist id="suggest-keywords">', false)
            ->assertSee('list="suggest-in_topos_ekdosis"', false);
    }

    /** R1f: every keyword used, most used first; spellings differing in case/accents are one; limited users: theirs only. */
    public function test_keyword_and_folder_suggestions(): void
    {
        $writer = $this->writer();
        Protocol::factory()->number(1)->create(['keywords' => 'Διαύγεια, εκδρομή', 'user_id' => $writer->id]);
        Protocol::factory()->number(2)->create(['keywords' => 'διαύγεια; σχολικά έξοδα', 'user_id' => $writer->id]);
        Protocol::factory()->number(3)->create(['keywords' => 'Διαύγεια', 'user_id' => $writer->id]);
        Protocol::factory()->number(4)->create(['keywords' => 'απόρρητο θέμα']);   // someone else's

        $all = \App\Support\Suggestions::keywords($writer);
        $this->assertSame(['Διαύγεια', 3], $all[0]);
        $this->assertContains(['απόρρητο θέμα', 1], $all);
        $this->assertCount(4, $all);

        $this->setting('limitProtocolAccessList', 1);
        $mine = \App\Support\Suggestions::keywords($writer);
        $this->assertNotContains(['απόρρητο θέμα', 1], $mine);

        \App\Models\Keepvalue::create(['fakelos' => 'Φ.950', 'describe' => 'Εκδρομές </script> και μετακινήσεις']);
        $page = $this->actingAs($writer)->get('/home')->assertOk();
        $page->assertSee('<script type="application/json" id="suggest-keywords">[["Διαύγεια",3]', false)
            ->assertSee('id="suggest-folders"', false)
            ->assertSee('["Φ.950","Εκδρομές \\u003C\\/script\\u003E και μετακινήσεις"]', false)   // can't end the script early
            ->assertSee('x-data="folderPicker"', false);
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

    /** The browser can't switch the form to a protocol the user may not see. */
    public function test_the_form_can_not_be_pointed_at_another_protocol(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $writer = $this->writer();
        $mine = Protocol::factory()->number(1)->create(['user_id' => $writer->id]);
        Protocol::factory()->number(2)->create(['thema' => 'Απόρρητο']);

        $this->expectException(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($writer)->test(ProtocolForm::class, ['protocol' => $mine])->set('protocolId', $mine->id + 1);
    }

    /** Review of R1b: a reader calling the form's save directly can't register a protocol. */
    public function test_reader_can_not_register_through_the_form(): void
    {
        Livewire::actingAs($this->reader())->test(ProtocolForm::class)
            ->set('form.thema', 'Από αναγνώστη')->call('save')->assertDispatched('toast', type: 'error');
        $this->assertSame(0, Protocol::count());
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(\App\Services\Protocols\ProtocolWriter::class)->create($this->reader(), $this->protocolForm());
    }

    /** Review of R1b: the receipt can be sent (again) for a protocol already saved. */
    public function test_receipt_is_sent_for_an_existing_protocol(): void
    {
        $p = Protocol::factory()->number(4)->create();
        Livewire::actingAs($this->writer())->test(ProtocolForm::class, ['protocol' => $p])
            ->set('receiptEmail', 'λάθος')->call('sendReceipt')->assertHasErrors('receipt_email')
            ->set('receiptEmail', 'sender@example.test')->call('sendReceipt')->assertHasNoErrors()->assertDispatched('toast', type: 'success');

        $this->assertCount(1, $this->sentMails());
        $this->assertStringContainsString('απόδειξη παραλαβής σε sender@example.test', $p->fresh()->paratiriseis);
        Livewire::actingAs($this->reader())->test(ProtocolForm::class, ['protocol' => $p])->assertDontSee('id="f-receipt"', false);
    }
}
