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

    /**
     * R2e (owner, 2026-11-19): the pager above the list too, with previous / next and a choice of any page.
     */
    public function test_pager_above_and_below_with_previous_next_and_any_page(): void
    {
        $this->setting('showRowsInPage', 2);
        Protocol::factory()->count(9)->sequence(fn ($s) => ['protocolnum' => $s->index + 1, 'thema' => 'Γραμμή ' . ($s->index + 1)])->create();

        $html = $this->actingAs($this->admin())->get('/home/list')->assertOk()->getContent();
        $this->assertStringContainsString('id="pager-top"', $html);
        $this->assertStringContainsString('id="pager-bottom"', $html);
        $this->assertSame(2, substr_count($html, 'data-pager="select"'), 'a page choice in both');
        $this->assertSame(2, substr_count($html, '<option value="5"'), 'every page offered: 9 rows by 2 = 5 pages');
        $this->assertStringContainsString('Σελίδα', $html);
        $this->assertStringContainsString('από 5', $html);
        $this->assertStringContainsString('Εμφάνιση 1–2 από 9', $html);

        $list = Livewire::actingAs($this->admin())->test(ProtocolList::class);
        $list->assertSee('Γραμμή 9')->assertDontSee('Γραμμή 7');
        $list->assertSeeHtml('data-pager="previous" disabled');   // on the first page
        $list->call('gotoPage', 4)->assertSee('Γραμμή 3')->assertDontSee('Γραμμή 5')->assertSee('Εμφάνιση 7–8 από 9');
        $list->call('previousPage')->assertSee('Γραμμή 5')->assertSee('Γραμμή 4');
        $list->call('nextPage')->call('nextPage')->assertSee('Γραμμή 1')->assertDontSee('Γραμμή 2')
            ->assertSeeHtml('data-pager="next" disabled');   // on the last page
        $this->assertSame(2, substr_count($list->html(), '<option value="5" selected'), 'both choices show the page');
    }

    /**
     * Review of R2e: a page beyond the last (old bookmark, rows deleted, or typed) made the pager build a reversed
     * window of page numbers; a huge one could use up the memory. It now shows the last page.
     */
    public function test_a_page_beyond_the_last_shows_the_last_page(): void
    {
        $this->setting('showRowsInPage', 2);
        Protocol::factory()->count(9)->sequence(fn ($s) => ['protocolnum' => $s->index + 1, 'thema' => 'Γραμμή ' . ($s->index + 1)])->create();

        foreach ([100, 10_000_000] as $page) {
            $html = $this->actingAs($this->admin())->get("/home/list?page=$page")->assertOk()
                ->assertSee('Γραμμή 1')->assertSee('Εμφάνιση 9–9 από 9')->getContent();
            $this->assertSame(2, substr_count($html, '<option value="5" selected'), "page $page: the last page chosen");
            $this->assertSame(0, substr_count($html, 'data-pager-page="6"'), "page $page: no pages beyond the last");
            $this->assertLessThan(2 * 5 + 20, substr_count($html, 'data-pager-page='), "page $page: a bounded window");
        }
    }

    public function test_no_pager_for_a_single_page(): void
    {
        $this->setting('showRowsInPage', 25);
        Protocol::factory()->count(3)->sequence(fn ($s) => ['protocolnum' => $s->index + 1])->create();

        $this->actingAs($this->admin())->get('/home/list')->assertOk()
            ->assertDontSee('id="pager-top"', false)->assertDontSee('data-pager', false)->assertSee('Σύνολο: 3');
    }

    /** Review of R2: the menu's per-user link /home/list/a/{id} lists what that user registered. */
    public function test_menu_link_lists_what_a_user_registered(): void
    {
        [$a, $b] = [$this->writer(), $this->writer()];
        Protocol::factory()->number(1)->create(['thema' => 'Γραμμένο από Α', 'user_id' => $a->id]);
        Protocol::factory()->number(2)->create(['thema' => 'Γραμμένο από Β', 'user_id' => $b->id]);

        $this->actingAs($this->admin())->get("/home/list/a/{$a->id}")->assertOk()
            ->assertSee('Γραμμένο από Α')->assertDontSee('Γραμμένο από Β')->assertSee($a->name . ' · Καταχωρίσεις');
    }

    /** Review of R2: after changing the filter on an old address, a reload keeps the new choice. */
    public function test_query_string_wins_over_the_old_path(): void
    {
        $me = $this->writer();
        Protocol::factory()->number(1)->assignedTo(null, $me)->create(['thema' => 'Για ενημέρωση']);
        Protocol::factory()->number(2)->assignedTo($me)->create(['thema' => 'Για διεκπεραίωση']);

        $this->actingAs($me)->get('/home/list/d?filter=e')->assertSee('Για ενημέρωση')->assertDontSee('Για διεκπεραίωση');
        $this->actingAs($me)->get('/home/list/d')->assertSee('Για διεκπεραίωση')->assertDontSee('Για ενημέρωση');
    }

    public function test_related_links_are_above_the_row_link(): void
    {
        Protocol::factory()->number(1)->create(['sxetiko' => '3/2025']);
        $this->actingAs($this->admin())->get('/home/list')->assertSee('?find=1" class="position-relative z-2"', false);
    }
    /** R2b (owner): choosing a user under "Όλα" did not filter the list. */
    public function test_choosing_a_user_under_all_shows_that_users_protocols(): void
    {
        [$a, $b] = [$this->writer(), $this->writer()];
        Protocol::factory()->number(1)->assignedTo($a)->create(['thema' => 'Ανάθεση στον Α']);
        Protocol::factory()->number(2)->create(['thema' => 'Γραμμένο από Α', 'user_id' => $a->id]);
        Protocol::factory()->number(3)->assignedTo($b)->create(['thema' => 'Ανάθεση στον Β']);
        $admin = $this->admin();

        $this->actingAs($admin)->get('/home/list')->assertSee('Ανάθεση στον Α')->assertSee('Ανάθεση στον Β');
        $this->actingAs($admin)->get("/home/list?user={$a->id}")
            ->assertSee('Ανάθεση στον Α')->assertSee('Γραμμένο από Α')->assertDontSee('Ανάθεση στον Β')
            ->assertSee($a->name . ' · Πρωτόκολλο');
        Livewire::actingAs($admin)->test(ProtocolList::class)->set('who', 'all')->assertSet('who', 'me')   // same list under "Όλα"
            ->assertSee('Ανάθεση στον Β')->set('filter', 'd')->set('who', 'all')->assertSet('who', 'all');
    }

    /** R2b (owner): users with open handling assignments first, with their number; done ones don't count. */
    public function test_user_menu_groups_users_by_open_assignments(): void
    {
        [$busy, $done, $idle] = [
            \App\Models\User::factory()->writer()->create(['name' => 'Ανοιχτός Χρήστης']),
            \App\Models\User::factory()->writer()->create(['name' => 'Τελειωμένος Χρήστης']),
            \App\Models\User::factory()->writer()->create(['name' => 'Αργός Χρήστης']),
        ];
        Protocol::factory()->number(1)->assignedTo($busy)->create();
        Protocol::factory()->number(2)->assignedTo($busy, $idle)->create();   // informing is not an open assignment
        Protocol::factory()->number(3)->assignedTo($busy)->create(['diekp_date' => 20260105]);
        Protocol::factory()->number(4)->assignedTo($done)->create(['diekp_date' => 20260105]);

        $html = Livewire::actingAs($this->admin())->test(ProtocolList::class)->html();
        $open = substr($html, strpos($html, 'label="Με εκκρεμείς αναθέσεις"'));
        $open = substr($open, 0, strpos($open, '</optgroup>'));
        $rest = substr($html, strpos($html, 'label="Χωρίς εκκρεμείς αναθέσεις"'));
        $this->assertStringContainsString('Ανοιχτός Χρήστης (2)', $open);
        $this->assertStringNotContainsString('Τελειωμένος', $open);
        $this->assertStringContainsString('Τελειωμένος Χρήστης', $rest);
        $this->assertStringContainsString('Αργός Χρήστης', $rest);
    }

    /** R2b (owner): "Αναλυτικά" showed little; the attachments column showed no names. */
    public function test_detailed_view_and_attachment_names(): void
    {
        $writer = \App\Models\User::factory()->writer()->create(['name' => 'Γραμματέας Δοκιμών']);
        $p = Protocol::factory()->number(9)->create([
            'user_id' => $writer->id, 'in_num' => 'Φ.12/345', 'in_arxi_ekdosis' => 'Περιφερειακή Διεύθυνση',
            'in_topos_ekdosis' => 'Λάρισα', 'out_to' => 'Σχολεία',
        ]);
        $long = 'Πρόσκληση_σε_επιμορφωτική_ημερίδα_εκπαιδευτικών.pdf';
        \App\Models\Attachment::factory()->withFile()->create(['protocol_id' => $p->id, 'name' => $long, 'ada' => 'ΩΑΒΓ465ΧΘΞ-1']);

        $page = $this->actingAs($this->admin())->get('/home/list')->assertOk();
        $page->assertSee('Εισερχόμενο:')->assertSee('αρ. Φ.12/345 · Περιφερειακή Διεύθυνση · Λάρισα')
            ->assertSee('Εξερχόμενο:')->assertSee('Καταχώριση:')->assertSee('Γραμματέας Δοκιμών')
            ->assertSee(\App\Support\FileBadge::shortName($long))->assertSee('title="' . $long . '"', false)
            ->assertSee('ΩΑΒΓ465ΧΘΞ-1');
        $this->assertSame('Πρόσκληση_σε_επιμορ….pdf', \App\Support\FileBadge::shortName($long));
        $this->assertSame('σύντομο.pdf', \App\Support\FileBadge::shortName('σύντομο.pdf'));
        $this->assertSame(24, mb_strlen(\App\Support\FileBadge::shortName(str_repeat('α', 40))));
    }
}
