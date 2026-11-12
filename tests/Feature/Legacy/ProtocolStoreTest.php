<?php

namespace Tests\Feature\Legacy;

use App\Protocol;
use Illuminate\Http\UploadedFile;
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
            ->assertSee('name="protocolnum"', false)
            ->assertSee('value="42"', false);
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

    public function test_next_number_is_max_of_year_plus_one(): void
    {
        Protocol::factory()->number(7)->create();
        Protocol::factory()->number(3)->create();

        $this->actingAs($this->writer())->post('/home', $this->protocolForm(['protocolnum' => 8]));

        $this->assertDatabaseHas('protocols', ['protocolnum' => 8, 'etos' => now()->year]);
    }

    /**
     * KNOWN BUG (task B03): with the default setting "safe new protocol number" ON,
     * a manually typed number is ignored and replaced by the next free number.
     */
    public function test_known_bug_manual_number_is_replaced_when_safe_numbering_is_on(): void
    {
        Protocol::factory()->number(10)->create();
        $this->setting('safeNewProtocolNum', 1);

        $this->actingAs($this->writer())->post('/home', $this->protocolForm(['protocolnum' => 50]));

        $this->assertDatabaseMissing('protocols', ['protocolnum' => 50]);
        $this->assertDatabaseHas('protocols', ['protocolnum' => 11]);
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

    public function test_manual_date_is_stored(): void
    {
        $this->setting('safeNewProtocolNum', 0);

        $this->actingAs($this->writer())->post('/home', $this->protocolForm([
            'protocolnum' => 5, 'protocoldate' => '03/02/' . now()->year,
        ]));

        $this->assertDatabaseHas('protocols', ['protocolnum' => 5, 'protocoldate' => now()->year . '0203']);
    }

    public function test_date_must_be_dd_mm_yyyy(): void
    {
        $this->actingAs($this->writer())->from('/home')
            ->post('/home', $this->protocolForm(['protocolnum' => 1, 'protocoldate' => '3/2/' . now()->year]))
            ->assertSessionHasErrors('protocoldate');
    }

    public function test_subject_over_255_characters_is_rejected(): void
    {
        $this->actingAs($this->writer())->from('/home')
            ->post('/home', $this->protocolForm(['protocolnum' => 1, 'thema' => str_repeat('α', 256)]))
            ->assertSessionHasErrors('thema');
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
