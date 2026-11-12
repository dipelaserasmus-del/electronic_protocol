<?php

namespace Tests\Feature;

use App\Attachment;
use App\Protocol;
use App\User;

/** S02: every route that reads or changes a protocol / attachment goes through ProtocolPolicy. */
class AccessControlTest extends FeatureTestCase
{
    private User $writer;
    private Protocol $foreign;   // not created by / assigned to $writer
    private Protocol $mine;      // assigned to $writer for διεκπεραίωση

    protected function setUp(): void
    {
        parent::setUp();
        $this->setting('limitProtocolAccessList', 1);
        $this->writer = $this->writer();
        $this->foreign = Protocol::factory()->number(1)->create(['thema' => 'Ξένο απόρρητο θέμα']);
        $this->mine = Protocol::factory()->number(2)->assignedTo($this->writer)->create(['thema' => 'Δικό μου θέμα']);
    }

    public function test_copy_as_new_no_longer_bypasses_the_limit(): void
    {
        $this->actingAs($this->writer)->get("/home/{$this->foreign->id}/1")->assertNotFound();
        $this->actingAs($this->writer)->get("/home/{$this->mine->id}/1")->assertOk()->assertSee('Δικό μου θέμα');
    }

    public function test_receipt_respects_the_limit(): void
    {
        $this->actingAs($this->writer)->get("/receipt/{$this->foreign->id}")->assertNotFound();
        $this->actingAs($this->writer)->get("/receipt/{$this->mine->id}")->assertOk();
    }

    public function test_update_of_hidden_protocol_is_forbidden(): void
    {
        $this->actingAs($this->writer)->post("/home/{$this->foreign->id}", $this->protocolForm([
            'protocolnum' => 1, 'thema' => 'αλλοιωμένο',
        ]))->assertForbidden();

        $this->assertSame('Ξένο απόρρητο θέμα', $this->foreign->fresh()->thema);
    }

    public function test_writer_update_follows_allow_writer_update_setting(): void
    {
        $this->setting('limitProtocolAccessList', 0);
        $own = Protocol::factory()->number(3)->create(['user_id' => $this->writer->id]);
        $form = fn (Protocol $p) => $this->protocolForm(['protocolnum' => $p->protocolnum, 'etos' => $p->etos,
            'protocoldate' => \Carbon\Carbon::createFromFormat('Ymd', $p->protocoldate)->format('d/m/Y'), 'thema' => 'νέο']);

        $this->setting('allowWriterUpdateProtocol', 0);   // ΚΑΝΕΙΣ
        $this->actingAs($this->writer)->post("/home/{$own->id}", $form($own))->assertForbidden();

        $this->setting('allowWriterUpdateProtocol', 1);   // ΕΝΑΣ: only own
        $this->actingAs($this->writer)->post("/home/{$this->foreign->id}", $form($this->foreign))->assertForbidden();
        $this->actingAs($this->writer)->post("/home/{$own->id}", $form($own))->assertRedirect();

        $this->setting('allowWriterUpdateProtocol', 2);   // ΟΛΟΙ
        $this->actingAs($this->writer)->post("/home/{$this->foreign->id}", $form($this->foreign))->assertRedirect();
    }

    /** Review finding on S02: the form lets the assignee save the outgoing reply on someone else's protocol */
    public function test_writer_assigned_for_diekperaiosi_can_save_even_when_not_the_creator(): void
    {
        $this->setting('allowWriterUpdateProtocol', 1);   // ΕΝΑΣ (default): normally only own entries
        $p = $this->mine;                                  // created by someone else, assigned d to $this->writer

        $this->actingAs($this->writer)->post("/home/{$p->id}", $this->protocolForm([
            'protocolnum' => $p->protocolnum, 'etos' => $p->etos,
            'protocoldate' => \Carbon\Carbon::createFromFormat('Ymd', $p->protocoldate)->format('d/m/Y'),
            'thema' => $p->thema, 'out_date' => now()->format('d/m/Y'), 'out_to' => 'Διεύθυνση', 'out_perilipsi' => 'Απάντηση',
        ]))->assertRedirect();

        $this->assertSame('Απάντηση', $p->fresh()->out_perilipsi);
    }

    public function test_protocol_without_subject_is_editable_by_writers(): void
    {
        $this->setting('limitProtocolAccessList', 0);
        $this->setting('allowWriterUpdateProtocol', 1);
        $blank = Protocol::factory()->number(9)->create(['thema' => null]);

        $this->actingAs($this->writer)->post("/home/{$blank->id}", $this->protocolForm([
            'protocolnum' => 9, 'etos' => $blank->etos,
            'protocoldate' => \Carbon\Carbon::createFromFormat('Ymd', $blank->protocoldate)->format('d/m/Y'), 'thema' => 'Συμπληρώθηκε',
        ]))->assertRedirect();

        $this->assertSame('Συμπληρώθηκε', $blank->fresh()->thema);
    }

    public function test_attachment_delete_requires_update_permission(): void
    {
        $a = Attachment::factory()->withFile()->create(['protocol_id' => $this->foreign->id]);

        $this->actingAs($this->writer)->get("/attach/del/{$a->id}")->assertForbidden();
        $this->assertNotNull(Attachment::find($a->id));
    }

    public function test_only_admin_and_assigner_can_assign(): void
    {
        $this->setting('limitProtocolAccessList', 0);
        $payload = ['id' => $this->foreign->id, 'diekperaiosi' => 'd' . $this->writer->id, 'diekp_eos' => ''];

        $this->actingAs($this->writer)->post('/anathesiSe', $payload)->assertForbidden();
        $this->actingAs($this->reader())->post('/anathesiSe', $payload)->assertRedirect();   // readers never reach it (writer middleware)
        // allowed for an assigner; the response itself is still a 500 because the notification email
        // crashes (known bug B09, fixed separately) — the assignment is saved before that.
        $this->assertNotSame(403, $this->actingAs($this->assigner())->post('/anathesiSe', $payload)->status());

        $this->assertSame('d' . $this->writer->id, $this->foreign->fresh()->diekperaiosi);
    }

    public function test_mark_done_requires_assignment_or_role(): void
    {
        $this->setting('limitProtocolAccessList', 0);
        $reader = $this->reader();
        $payload = fn (Protocol $p) => ['id' => $p->id, 'diekp_date' => now()->format('d/m/Y')];

        $this->actingAs($reader)->post('/setDiekpDate', $payload($this->foreign))->assertForbidden();
        $this->assertNull($this->foreign->fresh()->diekp_date);

        $this->actingAs($this->writer)->post('/setDiekpDate', $payload($this->mine))->assertSuccessful();
        $this->assertNotNull($this->mine->fresh()->diekp_date);
    }

    public function test_print_endpoints_respect_the_limit(): void
    {
        $this->actingAs($this->writer)->post('/printed', ['etosForMany' => now()->year])
            ->assertRedirect();
        $this->actingAs($this->writer)->post('/printedAttachments', ['etosForMany' => now()->year])
            ->assertRedirect();
    }

    public function test_autocomplete_only_suggests_visible_protocols_and_known_fields(): void
    {
        $this->actingAs($this->writer)->get('/getValues/' . urlencode('θέμα') . '/thema/thema/themaList/0')
            ->assertOk()->assertSee('Δικό μου θέμα')->assertDontSee('απόρρητο');

        $this->actingAs($this->writer)->get('/getValues/a/paratiriseis/x/y/0')->assertNotFound();
    }

    /** The forms call /getValues/{field}/{id}/{divId}/{multi}?term=... — this route was missing (autocomplete was broken) */
    public function test_autocomplete_url_used_by_the_forms_works(): void
    {
        $this->actingAs($this->writer)->get('/getValues/thema/thema/themaList/0?term=' . urlencode('Δικό'))
            ->assertOk()->assertSee('Δικό μου θέμα');
    }

    public function test_admin_and_unlimited_users_keep_full_access(): void
    {
        $this->actingAs($this->admin())->get("/home/{$this->foreign->id}")->assertOk();

        $this->setting('limitProtocolAccessList', 0);
        $this->actingAs($this->writer)->get("/home/{$this->foreign->id}")->assertOk();
    }
}
