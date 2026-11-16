<?php

namespace Tests\Feature;

use App\Livewire\ProtocolList;
use App\Models\Protocol;
use Livewire\Livewire;

/** R2: the rebuilt protocol list (App\Livewire\ProtocolList). */
class ProtocolListTest extends FeatureTestCase
{
    public function test_filters_for_oneself_at_the_old_addresses(): void
    {
        [$me, $other] = [$this->writer(), $this->writer()];
        Protocol::factory()->number(1)->assignedTo($me)->create(['thema' => 'Προς εμένα']);
        Protocol::factory()->number(2)->assignedTo($me)->create(['thema' => 'Έκανα ήδη', 'diekp_date' => 20260105]);
        Protocol::factory()->number(3)->assignedTo(null, $me)->create(['thema' => 'Για ενημέρωσή μου']);
        Protocol::factory()->number(4)->assignedTo($other)->create(['thema' => 'Του άλλου']);
        Protocol::factory()->number(5)->create(['thema' => 'Δικό μου γραμμένο', 'user_id' => $me->id]);

        $this->actingAs($me)->get('/home/list/d')->assertOk()
            ->assertSee('Προς εμένα')->assertDontSee('Έκανα ήδη')->assertDontSee('Του άλλου')->assertSee('προς Διεκπεραίωση');
        $this->actingAs($me)->get('/home/list/e')->assertSee('Για ενημέρωσή μου')->assertDontSee('Προς εμένα');
        $this->actingAs($me)->get('/home/list/f')->assertSee('Έκανα ήδη')->assertDontSee('Προς εμένα');
        $this->actingAs($me)->get('/home/list?filter=m')
            ->assertSee('Δικό μου γραμμένο')->assertSee('Προς εμένα')->assertDontSee('Του άλλου');
        $this->actingAs($me)->get('/home/list')->assertSee('Του άλλου');
    }

    public function test_assigners_see_another_users_or_everybodys_list_writers_never(): void
    {
        [$a, $b] = [$this->writer(), $this->writer()];
        Protocol::factory()->number(1)->assignedTo($a)->create(['thema' => 'Του Α']);
        Protocol::factory()->number(2)->assignedTo($b)->create(['thema' => 'Του Β']);
        $assigner = $this->assigner();

        $this->actingAs($assigner)->get('/home/list/d/a')->assertSee('Του Α')->assertSee('Του Β')->assertSee('Όλοι οι χρήστες');
        $this->actingAs($assigner)->get("/home/list/d/{$b->id}")->assertSee('Του Β')->assertDontSee('Του Α')->assertSee($b->name);

        // a writer asking for everybody's list gets their own
        $this->actingAs($a)->get('/home/list/d/a')->assertSee('Του Α')->assertDontSee('Του Β');
        Livewire::actingAs($a)->test(ProtocolList::class)->set('filter', 'd')->set('who', (string) $b->id)
            ->assertSet('who', 'me')->assertSee('Του Α')->assertDontSee('Του Β');
    }

    public function test_limit_setting_applies_to_every_filter(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $me = $this->writer();
        Protocol::factory()->number(1)->create(['thema' => 'Ξένο κρυφό']);
        Protocol::factory()->number(2)->create(['thema' => 'Δικό μου', 'user_id' => $me->id]);

        $this->actingAs($me)->get('/home/list')->assertSee('Δικό μου')->assertDontSee('Ξένο κρυφό');
    }

    public function test_sorting_by_any_column_and_bad_values_fall_back(): void
    {
        $writer = $this->writer();
        Protocol::factory()->number(1)->assignedTo($writer)->create(['thema' => 'Βήτα']);
        Protocol::factory()->number(2)->create(['thema' => 'Άλφα']);
        Protocol::factory()->number(3)->create(['thema' => 'Γάμμα']);
        $admin = $this->admin();

        $html = $this->actingAs($admin)->get('/home/list?field=thema&sort=asc')->assertOk()->getContent();
        $this->assertTrue(strpos($html, 'Άλφα') < strpos($html, 'Βήτα') && strpos($html, 'Βήτα') < strpos($html, 'Γάμμα'));

        Livewire::actingAs($admin)->test(ProtocolList::class)
            ->call('sortBy', 'thema')->assertSet('sort', 'asc')
            ->call('sortBy', 'thema')->assertSet('sort', 'desc')
            ->call('sortBy', 'diekperaiosi')->assertOk()           // no text column any more (R1a): sorted by assignee
            ->set('field', 'password; DROP TABLE x')->assertSet('field', 'protocolnum')
            ->set('sort', 'sideways')->assertSet('sort', 'asc');
        $this->actingAs($admin)->get('/home/list?field=users.password&sort=x')->assertOk();
    }

    public function test_row_shows_assignees_deadline_attachments_and_related_links(): void
    {
        [$handler, $reader] = [$this->writer(), $this->reader()];
        $soon = (int) now()->addDay()->format('Ymd');
        $p = Protocol::factory()->number(7)->assignedTo($handler, $reader)->create(['diekp_eos' => $soon, 'sxetiko' => '3/2025, κάτι']);
        \App\Models\Attachment::factory()->withFile()->create(['protocol_id' => $p->id, 'name' => 'Έγγραφο.pdf']);

        $this->actingAs($this->admin())->get('/home/list')->assertOk()
            ->assertSee($handler->name)->assertSee('ενημ.: ' . $reader->name, false)
            ->assertSee('badge text-bg-warning', false)->assertSee('έως ' . now()->addDay()->format('d/m/Y'))
            ->assertSee('/download/', false)->assertSee('/goto/2025/3?find=1', false)
            ->assertSee('/home/' . $p->id . '/1', false);
    }

    public function test_rows_per_page_follow_the_setting(): void
    {
        $this->setting('showRowsInPage', 2);
        Protocol::factory()->count(3)->sequence(fn ($s) => ['protocolnum' => $s->index + 1, 'thema' => 'Σελίδα ' . ($s->index + 1)])->create();

        $this->actingAs($this->admin())->get('/home/list')->assertSee('Σελίδα 3')->assertSee('Σελίδα 2')->assertDontSee('Σελίδα 1')
            ->assertSee('Σύνολο: 3')->assertSee('Εμφάνιση')->assertDontSee('Showing');   // Greek pagination text
        $this->actingAs($this->admin())->get('/home/list?page=2')->assertSee('Σελίδα 1');
    }
}
