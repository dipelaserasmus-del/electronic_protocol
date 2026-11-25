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

        $form = Livewire::actingAs($this->admin())->test(ProtocolForm::class)
            ->assertDontSee('Κενό: ο επόμενος ελεύθερος')->assertDontSee('Ελεύθερος');   // R1g: untouched = nothing to say
        $this->assertMatchesRegularExpression('/protocol-number\s+is-valid/', $form->html());   // … but the green border
        $form
            ->set('form.protocolnum', '9')->assertSee('✔ Ελεύθερος')
            ->set('form.protocolnum', '7')->assertSee('✖ Δεσμευμένος από το')->assertSee('/home/' . $used->id, false)
            ->set('form.protocolnum', '8')->assertSee('Κρατημένος ως «ΕΛΕΥΘΕΡΟ»')
            ->set('form.protocolnum', 'x1')->assertSee('Γράψτε θετικό ακέραιο αριθμό.')
            ->set('form.etos', (string) (now()->year - 1))->set('form.protocolnum', '7')->assertSee('✔ Ελεύθερος')
            ->set('form.protocolnum', '')->assertSee('Κενό: ο επόμενος ελεύθερος');
    }

    /** R1g: an existing protocol's own number says nothing until it is changed; a number from a missing-number row does. */
    public function test_free_status_only_after_the_number_is_changed(): void
    {
        $this->setting('protocolValidate', 0);
        $p = Protocol::factory()->number(20)->create();
        $form = Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])->assertDontSee('Ελεύθερος');
        $this->assertMatchesRegularExpression('/protocol-number\s+is-valid/', $form->html());
        $form->set('form.protocolnum', '21')->assertSee('✔ Ελεύθερος');
        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['prefillNum' => 19])->assertSee('✔ Ελεύθερος');
    }

    /** R1g (owner, 2026-11-18): the number in the middle; "Διεκπεραιώθηκε την" high; assignment and receipt lower. */
    public function test_layout_number_in_the_middle_rarely_used_sections_lower(): void
    {
        $p = Protocol::factory()->number(30)->create();
        $html = $this->actingAs($this->admin())->get('/home/' . $p->id)->assertOk()->getContent();
        $pos = fn (string $needle) => strpos($html, $needle);

        $this->assertTrue($pos('number-head-year') < $pos('number-head-number') && $pos('number-head-number') < $pos('number-head-date'));
        $this->assertTrue($pos('id="done-card"') < $pos('id="f-out_to"'), 'done date above the outgoing section');
        $this->assertTrue($pos('id="f-diekp_date"') < $pos('id="attachments"'));
        // R1i: the default arrangement keeps this; the row below the columns is the "bottom" place
        $this->assertTrue($pos('id="attachments"') < $pos('data-zone="bottom"'));
        $this->assertTrue($pos('data-zone="bottom"') < $pos('id="f-handle"'), 'assignment below the attachments');
        $this->assertTrue($pos('data-zone="bottom"') < $pos('id="receipt-title"'), 'receipt by e-mail below too');
    }

    /** R1i (owner, 2026-11-18): each user moves and collapses the sections, one or two columns; saved per user */
    public function test_each_user_arranges_the_sections(): void
    {
        [$me, $other] = [$this->writer(), $this->writer()];
        $order = fn ($user) => \App\Support\UserPreferences::formLayout($user->fresh());

        $form = Livewire::actingAs($me)->test(ProtocolForm::class, ['protocol' => new Protocol()]);
        $form->call('moveSection', 'attachments', 0, 'left');
        $this->assertSame(['attachments', 'general', 'incoming'], $order($me)['zones']['left']);
        $this->assertSame(['done', 'outgoing'], $order($me)['zones']['right']);

        $form->call('moveSection', 'assign', 1, 'right')->call('moveSection', 'receipt', 99, 'right');
        $this->assertSame(['done', 'assign', 'outgoing', 'receipt'], $order($me)['zones']['right']);
        $this->assertSame([], $order($me)['zones']['bottom'], 'a place may be left empty');

        $form->call('toggleSection', 'incoming')->call('setColumns', 1)
            ->assertSee('one-column', false)
            ->assertSeeHtml('data-section-toggle="incoming"');
        $this->assertSame(['incoming'], $order($me)['collapsed']);
        $this->assertSame(1, $order($me)['columns']);
        // a collapsed section's fields are hidden, but still there (their values are kept)
        $html = $form->html();
        $this->assertMatchesRegularExpression('~id="f-in_num"~', $html);
        $this->assertMatchesRegularExpression('~data-section-toggle="incoming"[^>]*>\s*<i class="bi bi-chevron-down"~', $html);

        // unknown sections or places change nothing
        $form->call('moveSection', 'nonsense', 0, 'left')->call('moveSection', 'general', 0, 'roof');
        $this->assertSame(['attachments', 'general', 'incoming'], $order($me)['zones']['left']);

        // another user still has the default; the reset brings it back
        $this->assertSame(\App\Support\UserPreferences::FORM_DEFAULT, $order($other));
        $form->call('resetLayout');
        $this->assertSame(\App\Support\UserPreferences::FORM_DEFAULT, $order($me));
    }

    /** R1i: a collapsed section opens by itself when one of its fields has an error */
    /** R1l (owner, 2026-11-25): a refused save keeps its errors at their fields and tells the browser to show the first */
    public function test_a_refused_save_tells_the_page_to_show_the_error(): void
    {
        // editing a protocol with files: its folder cannot be emptied (the files' place depends on it)
        $p = Protocol::factory()->number(41)->create(['fakelos' => 'Φ.1', 'thema' => 'Με αρχείο']);
        Attachment::factory()->withFile()->create(['protocol_id' => $p->id, 'name' => 'έγγραφο.pdf']);
        $page = Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])
            ->set('form.fakelos', '')->call('save');
        $page->assertHasErrors('fakelos')->assertDispatched('form-refused')->assertNoRedirect();
        $this->assertMatchesRegularExpression('~id="f-fakelos" class="form-control folder-input\s+is-invalid~', $page->html());
        $this->assertSame('Φ.1', $p->fresh()->fakelos);

        // saved: no such event
        $page->set('form.fakelos', 'Φ.1')->set('form.thema', 'Με αρχείο, αλλαγμένο')->call('save')->assertHasNoErrors()->assertNotDispatched('form-refused');
        $this->assertSame('Με αρχείο, αλλαγμένο', $p->fresh()->thema);
    }

    public function test_a_collapsed_section_with_an_error_opens(): void
    {
        $this->setting('protocolValidate', 1);
        $me = $this->writer();
        \App\Support\UserPreferences::setFormLayout($me, ['collapsed' => ['incoming']] + \App\Support\UserPreferences::FORM_DEFAULT);

        $form = Livewire::actingAs($me)->test(ProtocolForm::class, ['protocol' => new Protocol()]);
        $this->assertMatchesRegularExpression('~data-section-toggle="incoming"[^>]*>\s*<i class="bi bi-chevron-down"~', $form->html());
        $form->set('form.thema', 'Με εισερχόμενο')->set('form.in_num', '12')->call('save');
        $this->assertNotEmpty($form->errors()->get('in_date'), 'incoming without date is refused while checks are on');
        $this->assertMatchesRegularExpression('~data-section-toggle="incoming"[^>]*>\s*<i class="bi bi-chevron-up"~', $form->html());
    }

    /** review of R1i: a file refused when dropped on a collapsed Συνημμένα opens it, so the reason is seen */
    public function test_a_refused_drop_opens_a_collapsed_attachments_section(): void
    {
        $me = $this->writer();
        \App\Support\UserPreferences::setFormLayout($me, ['collapsed' => ['attachments']] + \App\Support\UserPreferences::FORM_DEFAULT);

        $form = Livewire::actingAs($me)->test(ProtocolForm::class, ['protocol' => new Protocol()]);
        $this->assertMatchesRegularExpression('~data-section-toggle="attachments"[^>]*>\s*<i class="bi bi-chevron-down"~', $form->html());
        $form->set('picked', [UploadedFile::fake()->createWithContent('κακό.vbs', 'x')])->assertHasErrors('picked');
        $this->assertMatchesRegularExpression('~data-section-toggle="attachments"[^>]*>\s*<i class="bi bi-chevron-up"~', $form->html());
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

    /** R1j (owner, 2026-11-20): one floating Αποθήκευση; no Νέο / Λίστα (the top bar has them); copy and receipt last, with icons. */
    public function test_the_actions_one_floating_save_and_copy_receipt_at_the_end(): void
    {
        $p = Protocol::factory()->number(7)->create();
        $html = $this->actingAs($this->admin())->get('/home/'.$p->id)->assertOk()->getContent();
        $form = substr($html, (int) strpos($html, 'id="protocol-form"'));
        $form = substr($form, 0, strpos($form, '</form>'));

        $this->assertSame(1, substr_count($form, 'type="submit"'), 'one save button');
        $this->assertMatchesRegularExpression('~<div class="form-save-float">\s*<button type="submit"[^>]*id="form-save"~', $form);
        $this->assertStringNotContainsString('href="'.url('/home/list').'"', $form, 'no Λίστα in the form');
        $this->assertStringNotContainsString('href="'.url('/home').'"', $form, 'no Νέο in the form');

        $actions = substr($form, strpos($form, 'data-pill-actions'));
        $actions = substr($actions, 0, strpos($actions, 'pill-more'));
        $order = array_map(fn ($needle) => strpos($actions, $needle), ['Προηγούμενο', 'Επόμενο', 'data-action="copy"', 'data-action="link"', 'data-action="receipt"']);
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'copy, link (R3h) and receipt come last');
        $this->assertMatchesRegularExpression('~pill-far-right[^>]*data-action="copy"[^>]*>\s*<i class="bi bi-copy~', $actions);
        $this->assertMatchesRegularExpression('~data-action="receipt"[^>]*>\s*<i class="bi bi-receipt~', $actions);

        $new = $this->actingAs($this->writer())->get('/home')->assertOk()->getContent();
        $this->assertStringContainsString('id="form-save"', $new, 'a new protocol is saved by the same button');
        $this->assertStringNotContainsString('data-action="copy"', $new);

        // readers only view: no copy as new; the link (R3h) takes the far right, the receipt follows it
        $read = $this->actingAs($this->reader())->get('/home/'.$p->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('data-action="copy"', $read);
        $this->assertMatchesRegularExpression('~class="[^"]*pill-far-right[^"]*"[^>]*data-action="link"~', $read);
        $this->assertDoesNotMatchRegularExpression('~class="[^"]*pill-far-right[^"]*"[^>]*data-action="receipt"~', $read);
        $this->assertStringNotContainsString('id="form-save"', $read);
        $this->assertFalse($this->reader()->can('create', Protocol::class));
        $this->assertTrue($this->writer()->can('create', Protocol::class));
    }

    /** R1k (owner, 2026-11-20): "Αποθήκευση" in the leave dialog saves, then goes where the user was going (only here). */
    public function test_save_then_go_on_only_within_the_application(): void
    {
        $p = Protocol::factory()->number(7)->create(['thema' => 'Πριν']);
        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])
            ->set('form.thema', 'Μετά')->call('save', '/home/list?page=2')->assertRedirect('/home/list?page=2');
        $this->assertSame('Μετά', $p->fresh()->thema);

        foreach (['https://elsewhere.example/x', '//elsewhere.example/x', '/\\elsewhere.example', 'javascript:alert(1)', '/a b', ''] as $bad) {
            $this->assertNull(ProtocolForm::sameSite($bad), $bad);
            Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])
                ->set('form.thema', 'Ξανά')->call('save', $bad)->assertRedirect(url('/home/'.$p->id));
        }
        $this->assertSame('/home/3', ProtocolForm::sameSite('/home/3'));

        // the page knows the labels and the users for the list of changes, and has the dialog
        $html = $this->actingAs($this->admin())->get('/home/'.$p->id)->assertOk()->getContent();
        $this->assertStringContainsString('x-data="unsavedChanges({ labels:', $html);
        $this->assertStringContainsString('id="unsaved-dialog"', $html);
        $this->assertStringContainsString('class="unsaved-dot', $html);
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
    /**
     * R6d (owner, 2026-11-19): «Ελεύθερη επιλογή χρόνου διατήρησης» off → the retention is the folder's, in the form
     * too (it applied only in the inbox): the field cannot be changed and a value sent anyway is not used.
     */
    public function test_retention_follows_the_folder_unless_the_setting_allows_a_choice(): void
    {
        Storage::fake('local');
        \App\Models\Keepvalue::create(['fakelos' => 'Φ.960', 'keep' => 5, 'describe' => 'Πενταετία']);
        $save = function (string $thema) {
            return Livewire::actingAs($this->writer())->test(ProtocolForm::class)
                ->set('form.thema', $thema)->set('form.fakelos', 'Φ.960')->assertSet('form.keep', '5')
                ->set('form.keep', '20')
                ->set('uploads', [UploadedFile::fake()->createWithContent('έγγραφο.pdf', '%PDF-1.4 x')])
                ->call('save')->assertHasNoErrors();
        };

        $this->setting('allowUserChangeKeepSelect', 0);
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)->assertSeeHtml('wire:model="form.keep" disabled');
        $save('Του φακέλου');
        $this->assertSame('5', (string) Protocol::where('thema', 'Του φακέλου')->firstOrFail()->attachments()->value('keep'));

        $this->setting('allowUserChangeKeepSelect', 1);
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)->assertDontSeeHtml('wire:model="form.keep" disabled');
        $save('Δική μου επιλογή');
        $this->assertSame('20', (string) Protocol::where('thema', 'Δική μου επιλογή')->firstOrFail()->attachments()->value('keep'));
    }

    /**
     * Review of R6d: editing (or copying) a protocol whose files kept another retention than the folder's now: without
     * free choice the locked field showed the files' old value, while new files got the folder's.
     */
    public function test_a_locked_retention_shows_the_folders_when_editing_or_copying(): void
    {
        \App\Models\Keepvalue::create(['fakelos' => 'Φ.961', 'keep' => 5, 'describe' => 'Πενταετία']);
        $p = Protocol::factory()->number(7)->create(['fakelos' => 'Φ.961']);
        Attachment::factory()->create(['protocol_id' => $p->id, 'keep' => '20']);

        $this->setting('allowUserChangeKeepSelect', 0);
        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])->assertSet('form.keep', '5');
        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p, 'copy' => true])->assertSet('form.keep', '5');

        $this->setting('allowUserChangeKeepSelect', 1);
        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])->assertSet('form.keep', '20');
    }

    /**
     * R2f (owner, 2026-11-19): a date out of order with the numbers around it, or after today, is a warning under the
     * date (as for a number that exists), when creating and when editing; it can still be saved.
     */
    public function test_a_date_out_of_order_or_in_the_future_is_a_warning_that_does_not_block(): void
    {
        $this->setting('allowWriterChangeDate', 1);
        $year = (int) now()->year;
        $base = now()->subDays(10)->startOfDay();
        foreach ([0, 2, 4] as $i => $plus) {
            Protocol::factory()->number(($i + 1) * 10)->create(['etos' => $year, 'protocoldate' => $base->copy()->addDays($plus)->toDateString()]);
        }
        $last = $base->copy()->addDays(4);

        // new: before the last protocol's date → warning; the last one's date → none; tomorrow → after today
        $form = Livewire::actingAs($this->writer())->test(ProtocolForm::class)->set('form.thema', 'Νέο');
        $form->set('form.protocoldate', $last->copy()->subDay()->format('d/m/Y'))
            ->assertSeeHtml('id="date-warning"')->assertSee('ο προηγούμενος (30) έχει '.$last->format('d/m/Y'));
        $form->set('form.protocoldate', $last->format('d/m/Y'))->assertDontSeeHtml('id="date-warning"');
        $form->set('form.protocoldate', now()->addDay()->format('d/m/Y'))->assertSee('Η ημερομηνία είναι μετά τη σημερινή.')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(now()->addDay()->toDateString(), Protocol::where('thema', 'Νέο')->firstOrFail()->dateOf('protocoldate')->toDateString(), 'saved anyway');

        // editing number 20: between 10 and 30 is fine; after 30's date → warning
        $twenty = Protocol::where('protocolnum', 20)->firstOrFail();
        $edit = Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $twenty]);
        $edit->assertDontSeeHtml('id="date-warning"');
        $edit->set('form.protocoldate', $last->copy()->addDay()->format('d/m/Y'))
            ->assertSee('ο προηγούμενος (10) έχει '.$base->format('d/m/Y').' και ο επόμενος (30) '.$last->format('d/m/Y'));

        // whoever cannot change the date is not told
        $this->setting('allowWriterChangeDate', 0);
        Livewire::actingAs($this->writer())->test(ProtocolForm::class)->assertDontSeeHtml('id="date-warning"');
    }

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
