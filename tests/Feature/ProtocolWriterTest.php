<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Services\Protocols\ProtocolWriter;
use App\Support\TextInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * R1a: saving a protocol through App\Services\Protocols\ProtocolWriter (the legacy form posts to it too).
 */
class ProtocolWriterTest extends FeatureTestCase
{
    /** What users type that broke or could break something: SQL/LIKE/HTML/JS characters, Greek, emoji. */
    public const FUZZ = [
        'Θέμα -- με παύλες --',
        "Ν' Αθηνών \"εισαγωγικά\" ; DROP TABLE protocols; --",
        '50% έκπτωση _και_ \\ ανάποδη κάθετος',
        '<script>alert(1)</script> & <b>έντονα</b> &amp;',
        'Emoji 📎✅ 👩‍💼 και τόνοι: άέήίόύώ ϊϋΐΰ',
        "'); alert('x'); //",
    ];

    private function writer_(): ProtocolWriter
    {
        return app(ProtocolWriter::class);
    }

    public function test_special_characters_round_trip_in_every_text_field(): void
    {
        $this->setting('protocolValidate', 0);
        $admin = $this->admin();
        $fields = ['thema', 'in_num', 'in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis', 'in_perilipsi',
            'out_to', 'out_perilipsi', 'paratiriseis'];
        foreach (self::FUZZ as $i => $text) {
            $input = $this->protocolForm(['in_date' => '01/02/2026', 'out_date' => '02/02/2026']);
            foreach ($fields as $f) {
                $input[$f] = $text;
            }
            $input['keywords'] = $text;
            $p = $this->writer_()->create($admin, $input)->protocol;
            foreach ($fields as $f) {
                $this->assertSame($text, DB::table('protocols')->where('id', $p->id)->value($f), "$f, case $i");
            }
        }
    }

    public function test_special_characters_are_shown_escaped_in_form_list_and_search(): void
    {
        $this->setting('protocolValidate', 0);
        $admin = $this->admin();
        $text = '<script>alert(1)</script> -- Ν\' Αθηνών & 50%';
        $p = $this->writer_()->create($admin, $this->protocolForm(['thema' => $text]))->protocol;
        $escaped = e($text);

        $this->actingAs($admin)->get("/home/{$p->id}")->assertOk()->assertSee($escaped, false)->assertDontSee('<script>alert(1)', false);
        $this->actingAs($admin)->get('/home/list')->assertOk()->assertSee($escaped, false)->assertDontSee('<script>alert(1)', false);
        // search results printed the value and the term raw before R1a
        $this->actingAs($admin)->get('/getFindData?searchField1=thema&searchData1=' . urlencode('<script>') . '&searchField2=fakelos&searchField3=in_num')
            ->assertOk()->assertDontSee('<script>alert(1)', false)
            ->assertSee('<mark><strong>&lt;script&gt;</strong></mark>alert(1)', false);
    }

    public function test_autocomplete_passes_values_with_quotes_as_json(): void
    {
        $this->setting('protocolValidate', 0);
        $admin = $this->admin();
        $this->writer_()->create($admin, $this->protocolForm(['in_topos_ekdosis' => "Ν' Αθηνών\"); alert('x"]));

        $html = $this->actingAs($admin)->get('/getValues/in_topos_ekdosis/in_topos_ekdosis/x/0?term=' . urlencode('Αθ'))->assertOk()->getContent();

        $this->assertStringContainsString('appendValue.apply(null, ', $html);
        $this->assertStringNotContainsString("'Ν' Αθηνών", $html, 'the value was pasted between quotes before');
        preg_match('/apply\(null, (.*?)\)">/', $html, $m);
        $this->assertSame(['in_topos_ekdosis', "Ν' Αθηνών\"); alert('x", 'x', '0'], json_decode(html_entity_decode($m[1], ENT_QUOTES), true));
    }

    public function test_text_input_removes_only_invisible_or_broken_characters(): void
    {
        $this->assertSame('Θέμα -- "α" & β', TextInput::clean("\u{FEFF}  Θέμα -- \"α\" & β\u{200B}\u{00A0} "));
        $this->assertSame('γραμμή 1 γραμμή 2', TextInput::clean("γραμμή 1\r\nγραμμή 2"));
        $this->assertSame("γραμμή 1\nγραμμή 2\tτέλος", TextInput::clean("γραμμή 1\r\nγραμμή 2\tτέλος\x07", multiline: true));
        $this->assertSame("ά", TextInput::clean("α\u{0301}"), 'NFC: letter + accent becomes one letter');
        $this->assertSame('👩‍💼', TextInput::clean('👩‍💼'), 'emoji sequences keep their joiner');
        $this->assertSame('ab', TextInput::clean("a\xC3b"), 'invalid UTF-8 removed');
        $this->assertNull(TextInput::clean(" \u{00A0}\t"));
        $this->assertSame('α, β, γ', TextInput::list('α,β ,, γ,'));
    }

    /** Before R1a: the select is disabled for writers, so nothing was posted and saving emptied the assignments. */
    public function test_a_writer_saving_keeps_the_assignments(): void
    {
        $this->setting('allowWriterUpdateProtocolTimeInMinutes', 0);
        [$writer, $handler, $reader] = [$this->writer(), $this->writer(), $this->reader()];
        $p = Protocol::factory()->number(1)->assignedTo($handler, $reader)->create(['user_id' => $writer->id, 'diekp_eos' => 20261231]);

        $this->actingAs($writer)->post("/home/{$p->id}", $this->protocolForm([
            'protocolnum' => 1, 'etos' => $p->etos, 'thema' => 'Αλλαγμένο θέμα', 'diekp_eos' => '',
        ]))->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('Αλλαγμένο θέμα', $p->thema);
        $this->assertSame("d{$handler->id},e{$reader->id}", $p->diekperaiosi, 'assignments kept');
        $this->assertSame(20261231, $p->diekp_eos, 'deadline kept (only assigners change it)');
    }

    public function test_locked_parts_keep_their_stored_values_whatever_is_posted(): void
    {
        $this->setting('protocolValidate', 0);   // number and date stay locked for writers anyway
        [$creator, $handler] = [$this->writer(), $this->writer()];
        $p = Protocol::factory()->number(7)->assignedTo($handler)->create([
            'user_id' => $creator->id, 'thema' => 'Αρχικό', 'in_num' => '12/3', 'protocoldate' => 20260105,
        ]);

        // the handler may change only the outgoing part and the done date; number and date never
        $this->writer_()->update($handler, $p, $this->protocolForm([
            'protocolnum' => 99, 'etos' => 2020, 'protocoldate' => '01/01/2020', 'thema' => 'Άλλο', 'in_num' => 'Άλλο',
            'out_to' => 'Δήμος', 'out_date' => '06/01/2026', 'diekp_date' => '06/01/2026', 'diekperaiosi' => [],
        ]));

        $p->refresh();
        $this->assertSame([7, 2026, 20260105, 'Αρχικό', '12/3', 'Δήμος', 20260106],
            [$p->protocolnum, $p->etos, $p->protocoldate, $p->thema, $p->in_num, $p->out_to, $p->diekp_date]);
        $this->assertSame("d{$handler->id}", $p->diekperaiosi);
    }

    public function test_writer_outside_the_time_limit_can_not_save(): void
    {
        $this->setting('allowWriterUpdateProtocolTimeInMinutes', 10);
        $writer = $this->writer();
        $p = Protocol::factory()->number(1)->create(['user_id' => $writer->id]);
        DB::table('protocols')->where('id', $p->id)->update(['created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);

        $this->actingAs($writer)->post("/home/{$p->id}", $this->protocolForm(['protocolnum' => 1, 'thema' => 'Αργά']))->assertForbidden();
        $this->assertNotSame('Αργά', $p->fresh()->thema);
    }

    public function test_manual_number_held_by_a_placeholder_needs_confirmation_then_replaces_it(): void
    {
        $this->setting('protocolValidate', 0);
        [$admin, $handler] = [$this->admin(), $this->writer()];
        $holder = Protocol::factory()->number(5)->placeholder()->create();
        $input = $this->protocolForm(['protocolnum' => 5, 'protocolnum_proposed' => 6, 'thema' => 'Πραγματικό', 'diekperaiosi' => ["d{$handler->id}"]]);

        try {
            $this->writer_()->create($admin, $input);
            $this->fail('confirmation expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('replace_placeholder', $e->errors());
            $this->assertStringContainsString('ΕΛΕΥΘΕΡΟ', $e->errors()['protocolnum'][0]);
        }
        $this->assertSame('ΕΛΕΥΘΕΡΟ', $holder->fresh()->thema);

        $result = $this->writer_()->create($admin, $input + ['replace_placeholder' => '1']);

        $this->assertTrue($result->replacedPlaceholder);
        $this->assertSame($holder->id, $result->protocol->id, 'same row: number and year stay');
        $this->assertSame(1, Protocol::count());
        $this->assertSame(['Πραγματικό', $admin->id, "d{$handler->id}"], [$result->protocol->thema, $result->protocol->user_id, $result->protocol->diekperaiosi]);
    }

    public function test_placeholder_form_flow_shows_the_confirmation_box(): void
    {
        $this->setting('protocolValidate', 0);
        $admin = $this->admin();
        Protocol::factory()->number(5)->placeholder()->create();

        $this->actingAs($admin)->from('/home')->post('/home', $this->protocolForm(['protocolnum' => 5, 'protocolnum_proposed' => 6, 'thema' => 'Νέο']))
            ->assertRedirect('/home')->assertSessionHasErrors(['protocolnum', 'replace_placeholder']);
        $this->actingAs($admin)->get('/home')->assertSee('name="replace_placeholder"', false);

        $this->actingAs($admin)->post('/home', $this->protocolForm(['protocolnum' => 5, 'protocolnum_proposed' => 6, 'thema' => 'Νέο', 'replace_placeholder' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Νέο', Protocol::where('protocolnum', 5)->value('thema'));
    }

    public function test_a_real_protocol_or_a_placeholder_with_attachments_is_never_replaced(): void
    {
        $this->setting('protocolValidate', 0);
        $admin = $this->admin();
        Protocol::factory()->number(5)->create(['thema' => 'Κανονικό']);
        $withFile = Protocol::factory()->number(6)->placeholder()->create();
        Attachment::factory()->create(['protocol_id' => $withFile->id]);

        foreach ([5 => 'υπάρχει ήδη', 6 => 'έχει συνημμένα'] as $num => $why) {
            try {
                $this->writer_()->create($admin, $this->protocolForm(['protocolnum' => $num, 'protocolnum_proposed' => 9, 'replace_placeholder' => '1']));
                $this->fail("number $num must not be replaced");
            } catch (ValidationException $e) {
                $this->assertStringContainsString($why, $e->errors()['protocolnum'][0]);
            }
        }
        $this->assertSame(['Κανονικό', 'ΕΛΕΥΘΕΡΟ'], Protocol::orderBy('protocolnum')->pluck('thema')->all());
    }

    public function test_copy_keeps_the_date_when_the_user_may_set_it(): void
    {
        $p = Protocol::factory()->number(3)->onDate((now()->year) . '0105')->create();
        $original = '05/01/' . now()->year;

        $this->setting('protocolValidate', 0);
        $this->actingAs($this->admin())->get("/home/{$p->id}/1")->assertOk()->assertSee('value="' . $original . '"', false);

        $this->setting('protocolValidate', 1);
        $writer = $this->writer();
        $this->actingAs($writer)->get("/home/{$p->id}/1")->assertOk()->assertDontSee('value="' . $original . '"', false);
        $this->setting('allowWriterChangeDate', 1);
        $this->actingAs($writer)->get("/home/{$p->id}/1")->assertOk()->assertSee('value="' . $original . '"', false);
    }

    public function test_file_that_can_not_be_stored_is_reported_and_not_recorded(): void
    {
        $this->setting('protocolValidate', 0);
        Storage::put('arxeio/Φ.9', 'a file where the folder should be');   // the folder can't be created

        $result = $this->writer_()->create($this->admin(), $this->protocolForm(['fakelos' => 'Φ.9']),
            [['file' => UploadedFile::fake()->createWithContent('Έγγραφο.pdf', '%PDF-1.4'), 'ada' => null]]);

        $this->assertSame(0, Attachment::count(), 'before R1a: a row with an empty path');
        $this->assertSame(['Το αρχείο «Έγγραφο.pdf» δεν αποθηκεύτηκε. Επισυνάψτε το ξανά.'], $result->warnings);
    }

    public function test_changing_the_folder_moves_the_files(): void
    {
        $this->setting('protocolValidate', 0);
        $admin = $this->admin();
        $p = $this->writer_()->create($admin, $this->protocolForm(['fakelos' => 'Φ.1']),
            [['file' => UploadedFile::fake()->createWithContent('α.pdf', 'A'), 'ada' => null]])->protocol;
        $old = $p->attachments()->value('savedPath');

        $this->writer_()->update($admin, $p, $this->protocolForm(['protocolnum' => $p->protocolnum, 'etos' => $p->etos, 'fakelos' => 'Φ.10']));

        $new = $p->attachments()->value('savedPath');
        $this->assertStringStartsWith('arxeio/Φ.10/', $new);
        Storage::assertMissing($old);
        $this->assertSame('A', Storage::get($new));
    }

    public function test_folder_can_not_lead_out_of_the_archive(): void
    {
        $this->setting('protocolValidate', 0);
        foreach (['../../etc', 'Φ.1/../../x', '/abs', 'a\\b'] as $bad) {
            try {
                $this->writer_()->create($this->admin(), $this->protocolForm(['fakelos' => $bad]));
                $this->fail("accepted $bad");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('fakelos', $e->errors());
            }
        }
        $this->assertSame(0, Protocol::count());
    }

    public function test_assignment_emails_go_only_to_users_added_by_this_save(): void
    {
        $this->setting('sendEmailOnDiekperaiosiChange', 1);
        [$assigner, $a, $b] = [$this->assigner(), $this->writer(), $this->reader()];

        $p = $this->writer_()->create($assigner, $this->protocolForm(['diekperaiosi' => ["d{$a->id}"]]))->protocol;
        $this->assertSame([$a->email], $this->sentMails('intra-mail')->map(fn ($m) => $m->getEnvelope()->getRecipients()[0]->getAddress())->all());

        $result = $this->writer_()->update($assigner, $p->fresh(), $this->protocolForm([
            'protocolnum' => $p->protocolnum, 'etos' => $p->etos, 'diekperaiosi' => ["d{$a->id}", "e{$b->id}"],
        ]));

        $this->assertCount(2, $this->sentMails('intra-mail'));
        $this->assertSame($b->email, $this->sentMails('intra-mail')->last()->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertSame(["Στάλθηκε email ενημέρωσης στον {$b->name}."], $result->messages);
        $this->assertStringContainsString("email ενημέρωσης σε {$b->name}", $p->fresh()->paratiriseis);
    }

    public function test_unknown_users_can_not_be_assigned(): void
    {
        try {
            $this->writer_()->create($this->assigner(), $this->protocolForm(['diekperaiosi' => ['d999999']]));
            $this->fail('unknown user accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('diekperaiosi', $e->errors());
        }
        $this->assertSame(0, ProtocolAssignment::count());
    }

    /** Review of R1a: an old assignment to a user deleted since (kept, D02) must not block the others' saves. */
    public function test_assignment_to_a_deleted_user_does_not_block_saving(): void
    {
        $this->setting('protocolValidate', 0);
        $handler = $this->writer();
        $p = Protocol::factory()->number(1)->assignedTo($handler)->create();
        ProtocolAssignment::create(['protocol_id' => $p->id, 'user_id' => 999999, 'type' => 'e']);

        $this->writer_()->update($handler, $p->fresh(), $this->protocolForm(['out_to' => 'Δήμος', 'out_date' => '06/01/2026']));

        $this->assertSame('Δήμος', $p->fresh()->out_to);
        $this->assertSame("d{$handler->id},e999999", $p->fresh()->diekperaiosi, 'kept as it was');
    }

    /** Review of R1a: the number and year are checked as typed, before becoming integers. */
    public function test_number_and_year_must_be_whole_numbers_as_typed(): void
    {
        $this->setting('protocolValidate', 0);
        $admin = $this->admin();
        $p = Protocol::factory()->number(3)->create();
        foreach ([['protocolnum' => '12.5'], ['protocolnum' => '12', 'etos' => '2026x'], ['protocolnum' => '1e2']] as $bad) {
            try {
                $this->writer_()->create($admin, $this->protocolForm($bad + ['protocolnum_proposed' => '4']));
                $this->fail('accepted ' . json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty(array_intersect(['protocolnum', 'etos'], array_keys($e->errors())));
            }
            try {
                $this->writer_()->update($admin, $p->fresh(), $this->protocolForm($bad + ['etos' => $bad['etos'] ?? (string) $p->etos]));
                $this->fail('update accepted ' . json_encode($bad));
            } catch (ValidationException $e) {
                $this->assertNotEmpty(array_intersect(['protocolnum', 'etos'], array_keys($e->errors())));
            }
        }
        $this->assertSame([3], Protocol::pluck('protocolnum')->all());
    }

    /** Review of R1a: after the protocol is saved, a failure is a warning on it, never "register it again". */
    public function test_failure_after_saving_leads_to_the_saved_protocol_with_a_warning(): void
    {
        Attachment::creating(fn () => throw new \RuntimeException('disk full'));

        $response = $this->actingAs($this->writer())->from('/home')->post('/home', $this->protocolForm(['ada1' => 'ΑΔΑ-1']));

        $p = Protocol::firstOrFail();
        $response->assertRedirect("home/{$p->id}");
        $this->assertSame('warning', session('notification')['alert-type']);
        $this->assertStringContainsString('Το πρωτόκολλο αποθηκεύτηκε', session('notification')['message']);
        $this->assertSame(1, Protocol::count());
    }

    public function test_ada_longer_than_its_column_is_refused_before_saving(): void
    {
        try {
            $this->writer_()->create($this->writer(), $this->protocolForm(), [['file' => null, 'ada' => str_repeat('Α', 256)]]);
            $this->fail('accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('ada', $e->errors());
        }
        $this->assertSame(0, Protocol::count());
    }

    public function test_with_checks_on_an_empty_protocol_needs_the_setting(): void
    {
        $writer = $this->writer();
        try {
            $this->writer_()->create($writer, $this->protocolForm(['thema' => '']));
            $this->fail('empty protocol accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('thema', $e->errors());
        }

        $this->setting('allowEmptyProtocol', 1);
        $this->assertNull($this->writer_()->create($writer, $this->protocolForm(['thema' => '']))->protocol->thema);
    }

    public function test_texts_longer_than_their_column_are_refused_with_a_message(): void
    {
        try {
            $this->writer_()->create($this->writer(), $this->protocolForm(['in_num' => str_repeat('α', 256), 'thema' => str_repeat('β', Protocol::LONG_TEXT_MAX + 1)]));
            $this->fail('too long accepted');
        } catch (ValidationException $e) {
            $this->assertSame(['in_num', 'thema'], array_values(array_intersect(['in_num', 'thema'], array_keys($e->errors()))));
        }
    }
}
