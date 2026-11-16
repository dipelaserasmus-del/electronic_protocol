<?php

namespace Tests\Feature;

use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use App\Models\User;
use App\Support\Assignments;
use Illuminate\Support\Facades\DB;

/** D02: assignments live in protocol_assignments (filled from / kept in step with the legacy text "d14,e10"). */
class AssignmentsTest extends FeatureTestCase
{
    private function rows(Protocol $p): array
    {
        return ProtocolAssignment::where('protocol_id', $p->id)->orderBy('type')->orderBy('user_id')
            ->get()->map(fn ($a) => $a->type . $a->user_id)->all();
    }

    private function find(array $q): string
    {
        $q += ['searchField1' => 'diekperaiosi', 'searchField2' => 'thema', 'searchField3' => 'ada',
               'searchData1' => '', 'searchData2' => '', 'searchData3' => ''];
        return '/getFindData?' . http_build_query($q);
    }

    public function test_text_is_parsed_tolerantly(): void
    {
        $this->assertSame(
            [['user_id' => 14, 'type' => 'd'], ['user_id' => 10, 'type' => 'e'], ['user_id' => 3, 'type' => 'e']],
            Assignments::parse(' d14, e10,,d14,x,d,e0,7,dd5, e 3 ,d-2,'),
        );
        $this->assertSame([], Assignments::parse(null));
        $this->assertSame([], Assignments::parse(''));
    }

    public function test_creating_and_editing_a_protocol_keeps_the_table_in_step(): void
    {
        [$a, $b] = [$this->writer(), $this->reader()];
        $p = Protocol::factory()->number(1)->assignedTo($a, $b)->create();
        $this->assertSame(["d{$a->id}", "e{$b->id}"], $this->rows($p));

        $p->update(['diekperaiosi' => "e{$a->id}"]);
        $this->assertSame(["e{$a->id}"], $this->rows($p));

        $p->update(['diekperaiosi' => '']);
        $this->assertSame([], $this->rows($p));
    }

    public function test_saving_the_protocol_form_updates_the_assignments(): void
    {
        [$d, $e] = [$this->writer(), $this->reader()];
        $admin = $this->admin();
        $this->actingAs($admin)->post('/home', $this->protocolForm(['protocolnum' => 1, 'diekperaiosi' => ["d{$d->id}", "e{$e->id}"]]))
            ->assertRedirect();
        $p = Protocol::where('protocolnum', 1)->firstOrFail();
        $this->assertSame(["d{$d->id}", "e{$e->id}"], $this->rows($p));

        // editing goes through a query that skips the model events: the table must follow anyway
        $this->actingAs($admin)->post("/home/{$p->id}", $this->protocolForm([
            'protocolnum' => 1, 'protocoldate' => \Carbon\Carbon::createFromFormat('Ymd', $p->protocoldate)->format('d/m/Y'),
            'diekperaiosi' => ["d{$e->id}"],
        ]))->assertRedirect();
        $this->assertSame(["d{$e->id}"], $this->rows($p));
    }

    /** Review finding: two reassignments at the same time; the later sync must follow the stored text */
    /** R1a: replace() sets exactly the rows asked for, keeps the unchanged ones and says which ones are new. */
    public function test_replace_sets_the_rows_and_reports_the_added_ones(): void
    {
        [$a, $b, $c] = [$this->writer(), $this->writer(), $this->reader()];
        $p = Protocol::factory()->number(1)->assignedTo($a)->create();
        $kept = ProtocolAssignment::where('protocol_id', $p->id)->value('id');

        $added = Assignments::replace($p, [['user_id' => $a->id, 'type' => 'd'], ['user_id' => $b->id, 'type' => 'd'], ['user_id' => $c->id, 'type' => 'e']]);

        $this->assertSame([['user_id' => $b->id, 'type' => 'd'], ['user_id' => $c->id, 'type' => 'e']], $added);
        $this->assertSame($kept, ProtocolAssignment::where('protocol_id', $p->id)->where('user_id', $a->id)->value('id'), 'unchanged row kept');
        $this->assertSame("d{$a->id},d{$b->id},e{$c->id}", Protocol::find($p->id)->diekperaiosi, 'legacy text form');

        Assignments::replace($p, []);
        $this->assertSame([], $this->rows($p));
    }

    /** R1a: the text form set on the model (legacy screens) is applied on save, also through mass assignment. */
    public function test_legacy_text_form_is_applied_on_save(): void
    {
        [$a, $b] = [$this->writer(), $this->reader()];
        $p = Protocol::factory()->number(1)->create(['diekperaiosi' => "d{$a->id},e{$b->id},"]);
        $this->assertSame(["d{$a->id}", "e{$b->id}"], $this->rows($p));

        $p->diekperaiosi = "e{$a->id}";
        $this->assertSame("e{$a->id}", $p->diekperaiosi, 'read back before saving');
        $p->save();
        $this->assertSame(["e{$a->id}"], $this->rows($p));
    }

    public function test_assign_button_updates_the_assignments(): void
    {
        $w = $this->writer();
        $p = Protocol::factory()->number(1)->create();
        $this->actingAs($this->assigner())->post('/anathesiSe', ['id' => $p->id, 'diekperaiosi' => "d{$w->id}", 'diekp_eos' => ''])
            ->assertSuccessful();
        $this->assertSame(["d{$w->id}"], $this->rows($p));
    }

    public function test_deleting_a_protocol_deletes_its_assignments(): void
    {
        $p = Protocol::factory()->number(1)->assignedTo($this->writer())->create();
        $p->delete();
        $this->assertSame(0, ProtocolAssignment::count());
    }

    public function test_search_by_assignee_finds_protocols_that_also_have_a_user_for_information(): void
    {
        $d = User::factory()->writer()->create(['name' => 'Διεκπεραιωτής Δοκιμής']);
        $e = User::factory()->reader()->create(['name' => 'Ενημερούμενη Δοκιμής']);
        Protocol::factory()->number(1)->assignedTo($d, $e)->create(['thema' => 'Με δύο χρήστες']);
        Protocol::factory()->number(2)->assignedTo($d)->create(['thema' => 'Με έναν χρήστη']);
        Protocol::factory()->number(3)->assignedTo(null, $d)->create(['thema' => 'Μόνο ενημέρωση']);

        $this->actingAs($this->admin())->get($this->find(['searchData1' => 'Διεκπεραιωτής']))
            ->assertOk()
            ->assertSee('Με δύο χρήστες')->assertSee('Με έναν χρήστη')->assertDontSee('Μόνο ενημέρωση')
            // the results list shows the assignee's name also when there is an "e" user
            ->assertSeeText('Διεκπεραιωτής Δοκιμής');
    }

    public function test_search_for_empty_assignee_finds_protocols_saved_with_empty_text(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'Κενό κείμενο', 'diekperaiosi' => '']);
        Protocol::factory()->number(2)->create(['thema' => 'Χωρίς τιμή', 'diekperaiosi' => null]);
        Protocol::factory()->number(3)->assignedTo($this->writer())->create(['thema' => 'Ανατεθειμένο']);

        $this->actingAs($this->admin())->get($this->find(['searchData1chk' => 1]))
            ->assertOk()->assertSee('Κενό κείμενο')->assertSee('Χωρίς τιμή')->assertDontSee('Ανατεθειμένο');
    }

    public function test_list_filters_use_the_assignments(): void
    {
        $me = $this->writer();
        $other = $this->writer();
        Protocol::factory()->number(1)->assignedTo($me)->create(['thema' => 'Εκκρεμεί σε μένα']);
        Protocol::factory()->number(2)->assignedTo($me)->create(['thema' => 'Έγινε από μένα', 'diekp_date' => 20260110]);
        Protocol::factory()->number(3)->assignedTo(null, $me)->create(['thema' => 'Για ενημέρωσή μου']);
        Protocol::factory()->number(4)->assignedTo($other)->create(['thema' => 'Του άλλου']);

        $this->actingAs($me)->get('/home/list/d')->assertOk()
            ->assertSee('Εκκρεμεί σε μένα')->assertDontSee('Έγινε από μένα')->assertDontSee('Για ενημέρωσή μου')->assertDontSee('Του άλλου');
        $this->actingAs($me)->get('/home/list/f')->assertOk()
            ->assertSee('Έγινε από μένα')->assertDontSee('Εκκρεμεί σε μένα');
        $this->actingAs($me)->get('/home/list/e')->assertOk()
            ->assertSee('Για ενημέρωσή μου')->assertDontSee('Εκκρεμεί σε μένα');

        // an administrator looking at another user's list, and at everybody's
        $admin = $this->admin();
        $this->actingAs($admin)->get("/home/list/d/{$other->id}")->assertOk()
            ->assertSee('Του άλλου')->assertDontSee('Εκκρεμεί σε μένα');
        $this->actingAs($admin)->get('/home/list/d/a')->assertOk()
            ->assertSee('Του άλλου')->assertSee('Εκκρεμεί σε μένα')->assertDontSee('Για ενημέρωσή μου');
    }

    public function test_user_ids_that_share_digits_are_not_mixed_up(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        User::factory()->count(10)->create();
        $u1 = User::factory()->writer()->create();
        $u2 = User::factory()->create(['id' => (int) ($u1->id . '1')]);   // e.g. 13 and 131
        $p = Protocol::factory()->number(1)->create(['thema' => 'Για τον άλλο', 'diekperaiosi' => "d{$u2->id}"]);

        $this->actingAs($u1)->get("/home/{$p->id}")->assertNotFound();
        $this->actingAs($u1)->get('/home/list/d')->assertOk()->assertDontSee('Για τον άλλο');
    }
}
