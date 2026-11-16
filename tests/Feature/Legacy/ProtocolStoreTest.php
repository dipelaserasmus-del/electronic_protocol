<?php

namespace Tests\Feature\Legacy;

use App\Models\Protocol;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\FeatureTestCase;

/** Characterization of creating / editing / copying protocols (ProtocolController store, update, index). */
class ProtocolStoreTest extends FeatureTestCase
{
    public function test_new_protocol_form_renders_with_next_number(): void
    {
        Protocol::factory()->number(41)->create();

        $this->actingAs($this->writer())->get('/home')
            ->assertOk()
            ->assertSee('id="f-protocolnum"', false)
            ->assertSee('Δίνεται κατά την αποθήκευση (επόμενος: 42)');   // R1b form
    }

    public function test_writer_creates_protocol(): void
    {
        $user = $this->writer();

        $response = $this->actingAs($user)->post('/home', $this->protocolForm([
            'protocolnum' => 1, 'thema' => 'Πρόσκληση σε ημερίδα',
        ]));

        $p = Protocol::firstOrFail();
        $response->assertRedirect("home/{$p->id}");
        $this->assertSame('Πρόσκληση σε ημερίδα', $p->thema);
        $this->assertSame((int) now()->format('Ymd'), (int) $p->protocoldate, 'dates are stored as integer Ymd');
        $this->assertSame($user->id, (int) $p->user_id);
    }

    /** D05: emoji and other 4-byte characters typed in the form are stored (utf8mb4), also in searches. */
    public function test_emoji_typed_in_the_form_is_stored_and_found(): void
    {
        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 1, 'thema' => 'Γιορτή 🎄 σχολείου', 'paratiriseis' => 'σημείωση 𝔸 ✅',
        ]));

        $p = Protocol::firstOrFail();
        $this->assertSame('Γιορτή 🎄 σχολείου', $p->thema);
        $this->assertSame('σημείωση 𝔸 ✅', $p->paratiriseis);
        $this->assertSame(1, Protocol::where('thema', 'like', '%γιορτη 🎄%')->count());
    }

    public function test_next_number_is_max_of_year_plus_one(): void
    {
        Protocol::factory()->number(7)->create();
        Protocol::factory()->number(3)->create();

        $this->actingAs($this->writer())->post('/home', $this->protocolForm(['protocolnum' => 8]));

        $this->assertDatabaseHas('protocols', ['protocolnum' => 8, 'etos' => now()->year]);
    }

    /** B03 (was: known_bug_manual_number_is_replaced_when_safe_numbering_is_on) */
    public function test_typed_number_and_date_are_kept_when_safe_numbering_is_on(): void
    {
        Protocol::factory()->number(10)->create();
        $this->setting('safeNewProtocolNum', 1);
        $this->setting('protocolValidate', 0);   // number field editable (admin, checks OFF)

        $this->actingAs($this->admin())->post('/home', $this->protocolForm([
            'protocolnum' => 50, 'protocolnum_proposed' => 11, 'protocoldate' => '02/03/' . now()->year,
        ]));

        $this->assertDatabaseHas('protocols', ['protocolnum' => 50, 'protocoldate' => now()->year . '0302']);
        $this->assertDatabaseMissing('protocols', ['protocolnum' => 11]);
    }

    /** B03/A02: only users who can edit the number field may choose a number (no crafted requests) */
    public function test_writer_cannot_choose_a_number_with_a_crafted_request(): void
    {
        Protocol::factory()->number(10)->create();
        $this->setting('safeNewProtocolNum', 1);
        $this->setting('protocolValidate', 0);

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 50, 'protocolnum_proposed' => 11,
        ]));

        $this->assertDatabaseMissing('protocols', ['protocolnum' => 50]);
        $this->assertDatabaseHas('protocols', ['protocolnum' => 11]);
    }

    /** B03: safe mode still protects users who did not touch the proposed number */
    public function test_untouched_proposed_number_is_replaced_by_next_free_when_taken_meanwhile(): void
    {
        Protocol::factory()->number(10)->create();
        $this->setting('safeNewProtocolNum', 1);
        Protocol::factory()->number(11)->create();   // another user saved 11 after our form was opened

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 11, 'protocolnum_proposed' => 11,
        ]));

        $this->assertDatabaseHas('protocols', ['protocolnum' => 12]);
    }

    /** B03: a typed number that is already used is refused with a clear message; nothing is lost */
    public function test_typed_number_already_used_is_refused_with_message_and_input_kept(): void
    {
        Protocol::factory()->number(10)->create();
        $this->setting('safeNewProtocolNum', 1);
        $this->setting('protocolValidate', 0);   // number field editable (admin, checks OFF)

        $this->actingAs($this->admin())->from('/home')->post('/home', $this->protocolForm([
            'protocolnum' => 10, 'protocolnum_proposed' => 11, 'thema' => 'Να μη χαθεί',
        ]))
            ->assertRedirect('/home')
            ->assertSessionHasErrors(['protocolnum' => 'Ο Αρ. Πρωτοκόλλου 10 υπάρχει ήδη για το έτος ' . now()->year . '. Επιλέξτε άλλον αριθμό.'])
            ->assertSessionHasInput('thema', 'Να μη χαθεί');

        $this->assertSame(1, Protocol::count());
    }

    /** B03: attachment files carry the number that was actually stored */
    public function test_attachment_file_name_uses_the_stored_number(): void
    {
        Protocol::factory()->number(10)->create();
        $this->setting('safeNewProtocolNum', 1);
        Protocol::factory()->number(11)->create();

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 11, 'protocolnum_proposed' => 11, 'fakelos' => 'Φ.1',
            'att1' => \Illuminate\Http\UploadedFile::fake()->create('doc.pdf', 5, 'application/pdf'),
        ]));

        $p = Protocol::where('protocolnum', 12)->firstOrFail();
        $this->assertStringStartsWith('arxeio/Φ.1/12-', $p->attachments()->firstOrFail()->savedPath);
    }

    /** Review finding on B03: after a validation error the form must keep the ORIGINAL proposed number */
    /** B03 in the R1b form: a number typed by someone who may choose it is kept; left empty, the next free one. */
    public function test_new_form_keeps_a_typed_number_and_fills_an_empty_one(): void
    {
        $this->setting('protocolValidate', 0);
        Protocol::factory()->number(41)->create();
        $admin = $this->admin();

        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\ProtocolForm::class)
            ->assertSee('placeholder="42"', false)
            ->set('form.protocolnum', '50')->set('form.thema', 'Επιλεγμένος')->call('save')->assertHasNoErrors();
        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\ProtocolForm::class)
            ->set('form.thema', 'Επόμενος')->call('save')->assertHasNoErrors();

        $this->assertSame([41, 50, 51], Protocol::orderBy('protocolnum')->pluck('protocolnum')->all());
        $this->assertSame('Επιλεγμένος', Protocol::where('protocolnum', 50)->value('thema'));
    }

    public function test_manual_number_is_kept_when_safe_numbering_is_off(): void
    {
        Protocol::factory()->number(10)->create();
        $this->setting('safeNewProtocolNum', 0);

        $this->actingAs($this->writer())->post('/home', $this->protocolForm(['protocolnum' => 50]));

        $this->assertDatabaseHas('protocols', ['protocolnum' => 50]);
    }

    public function test_existing_number_is_rejected_when_safe_numbering_is_off(): void
    {
        Protocol::factory()->number(10)->create();
        $this->setting('safeNewProtocolNum', 0);

        $this->actingAs($this->writer())->from('/home')->post('/home', $this->protocolForm(['protocolnum' => 10]))
            ->assertSessionHasErrors('protocolnum');
        $this->assertSame(1, Protocol::count());
    }

    public function test_manual_date_is_stored_for_an_administrator_with_checks_off(): void
    {
        $this->setting('protocolValidate', 0);

        $this->actingAs($this->admin())->post('/home', $this->protocolForm([
            'protocolnum' => 5, 'protocolnum_proposed' => 1, 'protocoldate' => '03/02/' . now()->year,
        ]));

        $this->assertDatabaseHas('protocols', ['protocolnum' => 5, 'protocoldate' => now()->year . '-02-03']);
    }

    /** R1a: the date field is read-only for writers; a posted date used to be stored anyway. */
    public function test_writer_gets_todays_date_unless_the_setting_allows_changing_it(): void
    {
        $this->setting('safeNewProtocolNum', 0);
        $writer = $this->writer();

        $this->actingAs($writer)->post('/home', $this->protocolForm(['protocolnum' => 5, 'protocoldate' => '03/02/2020']));
        $this->assertSame(now()->format('Y-m-d'), DB::table('protocols')->where('protocolnum', 5)->value('protocoldate'));

        $this->setting('allowWriterChangeDate', 1);
        $this->actingAs($writer)->post('/home', $this->protocolForm(['protocolnum' => 6, 'protocoldate' => '03/02/' . now()->year]));
        $this->assertSame(now()->year . '-02-03', DB::table('protocols')->where('protocolnum', 6)->value('protocoldate'));
    }

    /** R1a: short forms are completed as in the date input (N04); impossible dates are refused (31/02 became 03/03). */
    public function test_date_is_completed_or_refused(): void
    {
        $this->setting('protocolValidate', 0);
        $admin = $this->admin();

        $this->actingAs($admin)->post('/home', $this->protocolForm(['protocolnum' => 1, 'protocolnum_proposed' => 2, 'protocoldate' => '3/2/' . now()->year]))
            ->assertSessionHasNoErrors();
        $this->assertSame(now()->year . '-02-03', DB::table('protocols')->where('protocolnum', 1)->value('protocoldate'));

        $this->actingAs($admin)->from('/home')
            ->post('/home', $this->protocolForm(['protocolnum' => 7, 'protocolnum_proposed' => 2, 'protocoldate' => '31/02/' . now()->year]))
            ->assertSessionHasErrors('protocoldate');
        $this->actingAs($admin)->from('/home')
            ->post('/home', $this->protocolForm(['protocolnum' => 7, 'protocolnum_proposed' => 2, 'in_date' => '2026-01-03']))
            ->assertSessionHasErrors('in_date');
        $this->assertSame(1, Protocol::count());
    }

    public function test_incoming_fields_require_each_other(): void
    {
        $this->actingAs($this->writer())->from('/home')
            ->post('/home', $this->protocolForm(['protocolnum' => 1, 'in_num' => '123']))
            ->assertSessionHasErrors(['in_date', 'in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis']);
    }

    public function test_special_characters_are_stored_verbatim(): void
    {
        $text = 'Θέμα -- με "εισαγωγικά", \'απόστροφο\', & 50% ; <b>x</b> \\ _';
        $this->setting('safeNewProtocolNum', 0);

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 1, 'thema' => $text, 'in_perilipsi' => $text,
        ]));

        $this->assertSame($text, Protocol::firstOrFail()->thema);
    }

    /** F08: a file uploaded with a long Greek name (over 255 bytes with the prefix) is stored with a shortened name. */
    public function test_uploaded_file_with_a_long_greek_name_is_stored(): void
    {
        $this->setting('safeNewProtocolNum', 0);
        $name = str_repeat('Αίτηση', 25).'.pdf';   // 154 characters, 304 bytes

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 12, 'fakelos' => 'Φ.1',
            'att1' => UploadedFile::fake()->createWithContent($name, '%PDF-1.4 long name'),
        ]));

        $a = Protocol::firstOrFail()->attachments()->firstOrFail();
        $this->assertSame($name, $a->name, 'shown with its whole name');
        $this->assertLessThanOrEqual(255, strlen(basename($a->savedPath)));
        $this->assertStringEndsWith('.pdf', $a->savedPath);
        $this->assertSame('%PDF-1.4 long name', Storage::get($a->savedPath));
    }

    public function test_attachment_is_stored_with_protocol(): void
    {
        $this->setting('safeNewProtocolNum', 0);

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 12, 'fakelos' => 'Φ.1', 'keep' => '5',
            'att1' => UploadedFile::fake()->create('Πρόσκληση.pdf', 20, 'application/pdf'),
        ]));

        $p = Protocol::firstOrFail();
        $a = $p->attachments()->firstOrFail();
        $this->assertSame('Πρόσκληση.pdf', $a->name);
        $this->assertStringStartsWith('arxeio/Φ.1/12-', $a->savedPath);
        Storage::disk('local')->assertExists($a->savedPath);
        $this->assertSame((int) now()->addYears(5)->format('Ymd'), (int) $a->expires);
    }

    public function test_writer_edits_protocol(): void
    {
        $user = $this->writer();
        $p = Protocol::factory()->number(3)->create(['user_id' => $user->id, 'thema' => 'Παλιό']);

        $this->actingAs($user)->post("/home/{$p->id}", $this->protocolForm([
            'protocolnum' => 3, 'protocoldate' => \Carbon\Carbon::createFromFormat('Ymd', $p->protocoldate)->format('d/m/Y'),
            'thema' => 'Νέο',
        ]));

        $this->assertSame('Νέο', $p->fresh()->thema);
    }

    /** D01: subject, sender and summaries longer than 255 characters are saved in full, with checks on or off. */
    public function test_long_subject_sender_and_summaries_are_saved_in_full(): void
    {
        $long = fn (string $word) => trim(str_repeat("$word ", 400));   // ≥ 2,400 characters
        $user = $this->writer();
        foreach ([1, 0] as $validate) {
            Protocol::query()->delete();
            $this->setting('protocolValidate', $validate);
            $this->actingAs($user)->post('/home', $this->protocolForm([
                'protocolnum' => 1, 'thema' => $long('Θέμα'),
                'in_num' => '12', 'in_date' => '02/03/'.now()->year, 'in_topos_ekdosis' => 'Αθήνα',
                'in_arxi_ekdosis' => $long('Αρχή'), 'in_paraliptis' => 'Γραμματεία', 'in_perilipsi' => $long('Περίληψη'),
                'out_date' => '03/03/'.now()->year, 'out_to' => 'Διεύθυνση', 'out_perilipsi' => $long('Απάντηση'),
            ]))->assertSessionHasNoErrors();

            $p = Protocol::firstOrFail();
            $this->assertSame($long('Θέμα'), $p->thema, "protocolValidate=$validate");
            $this->assertSame($long('Αρχή'), $p->in_arxi_ekdosis);
            $this->assertSame($long('Περίληψη'), $p->in_perilipsi);
            $this->assertSame($long('Απάντηση'), $p->out_perilipsi);
        }

        // editing keeps it too
        $this->actingAs($user)->post("/home/{$p->id}", $this->protocolForm([
            'protocolnum' => 1, 'protocoldate' => \Carbon\Carbon::createFromFormat('Ymd', $p->protocoldate)->format('d/m/Y'),
            'thema' => $long('Νέο'),
        ]));
        $this->assertSame($long('Νέο'), $p->fresh()->thema);
    }

    /**
     * D01 (was: test_subject_over_255_characters_is_rejected, the VARCHAR(255) limit): with the checks on, a subject
     * over the TEXT limit gets a validation message instead of a database error.
     */
    public function test_subject_over_the_text_limit_is_refused_with_a_message(): void
    {
        $this->setting('protocolValidate', 1);

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 1, 'thema' => str_repeat('α', Protocol::LONG_TEXT_MAX + 1),
        ]))->assertSessionHasErrors('thema');

        $this->assertSame(0, Protocol::count());
    }

    public function test_copy_as_new_clears_number_and_date(): void
    {
        $p = Protocol::factory()->number(3)->incoming()->create(['thema' => 'Προς αντιγραφή']);

        $this->actingAs($this->writer())->get("/home/{$p->id}/1")
            ->assertOk()
            ->assertSee('Προς αντιγραφή');
    }

    public function test_reader_cannot_create_protocols(): void
    {
        $this->actingAs($this->reader())->post('/home', $this->protocolForm(['protocolnum' => 1]))
            ->assertRedirect('home/list');
        $this->assertSame(0, Protocol::count());
    }
}
