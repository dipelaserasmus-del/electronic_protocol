<?php

namespace Tests\Feature;

use App\Livewire\ProtocolList;
use App\Models\Protocol;
use Livewire\Livewire;

/** R2: the rebuilt protocol list (App\Livewire\ProtocolList). */
class ProtocolListTest extends FeatureTestCase
{
    /** R2l (owner, 2026-11-23): the list's own «Αναζήτηση» pill is gone; the top bar's search box has it */
    public function test_the_list_has_no_search_button_of_its_own(): void
    {
        $html = $this->actingAs($this->writer())->get('/home/list')->assertOk()->getContent();
        $list = substr($html, strpos($html, 'class="protocol-list"'));
        $this->assertStringContainsString('>Νέο</a>', $list);
        $this->assertStringContainsString('Ανανέωση', $list);
        $this->assertStringNotContainsString('>Αναζήτηση</a>', $list);
        $this->assertStringContainsString('id="app-search-advanced"', $html);   // the top bar's advanced search stays
    }

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
            ->assertSee('title="Διεκπεραίωση: ' . $handler->name . '"', false)->assertSee('title="Ενημέρωση: ' . $reader->name . '"', false)   // R2j: pills
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

    /** R2f (owner, 2026-11-19): a date out of order with the numbers, or after today, is flagged in its row. */
    public function test_dates_out_of_order_or_in_the_future_are_flagged(): void
    {
        $year = (int) now()->year;
        $day = now()->subDays(5)->startOfDay();
        $dates = [$day, $day, $day->copy()->addDays(3), $day, $day, now()->addDays(10)];   // 3: out of order; 6: future
        foreach ($dates as $i => $d) {
            Protocol::factory()->number($i + 1)->create(['etos' => $year, 'protocoldate' => $d->toDateString(), 'thema' => 'Θέμα '.($i + 1)]);
        }

        $html = $this->actingAs($this->admin())->get('/home/list')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'protocol-row  date-flagged'), 'two rows flagged');
        $row = fn (int $n) => substr($html, strpos($html, 'data-id="'.Protocol::where('protocolnum', $n)->value('id').'"') - 120, 1500);
        $this->assertStringContainsString('date-flagged', $row(3));
        $this->assertStringContainsString('ο προηγούμενος (2) έχει '.$day->format('d/m/Y').' και ο επόμενος (4) '.$day->format('d/m/Y'), $row(3));
        $this->assertStringContainsString('data-kinds="future"', $row(6));
        $this->assertStringContainsString('Η ημερομηνία είναι μετά τη σημερινή.', $row(6));
        $this->assertStringNotContainsString('date-flagged', $row(2));
        $this->assertStringNotContainsString('date-flagged', $row(4));
    }

    /**
     * Review of R2f: with "Περιορισμός λίστας", the explanation does not name (number, date) the protocols around
     * that the user may not see; the check itself still uses all of them.
     */
    public function test_the_explanation_hides_protocols_the_user_may_not_see(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $this->setting('allowWriterChangeDate', 1);
        $writer = $this->writer();
        $year = (int) now()->year;
        $day = now()->subDays(10)->startOfDay();
        Protocol::factory()->number(1)->create(['etos' => $year, 'protocoldate' => $day->toDateString()]);
        Protocol::factory()->number(2)->create(['etos' => $year, 'protocoldate' => $day->copy()->addDays(5)->toDateString(), 'user_id' => $writer->id, 'thema' => 'Δικό μου']);
        Protocol::factory()->number(3)->create(['etos' => $year, 'protocoldate' => $day->toDateString()]);
        $hidden = $day->format('d/m/Y');

        $html = $this->actingAs($writer)->get('/home/list')->assertOk()->assertSee('Δικό μου')->getContent();
        $this->assertStringContainsString('date-flagged', $html, 'still flagged');
        $this->assertStringContainsString('Η ημερομηνία δεν ταιριάζει με τη σειρά των αριθμών γύρω του.', $html);
        $this->assertStringNotContainsString('ο προηγούμενος (1)', $html);
        $this->assertStringNotContainsString($hidden, $html, 'the hidden protocols\' date is not shown');

        $this->actingAs($this->admin())->get('/home/list')->assertSee('ο προηγούμενος (1) έχει '.$hidden, false);

        // the form too: a new protocol dated before the (hidden) last one
        \Livewire\Livewire::actingAs($writer)->test(\App\Livewire\ProtocolForm::class)
            ->set('form.protocoldate', $day->copy()->subDay()->format('d/m/Y'))
            ->assertSee('γύρω του')->assertDontSee('ο προηγούμενος (3)');
    }

    /** R2h (owner, 2026-11-19): the row's actions (copy, receipt) under a ⋮ menu instead of two buttons. */
    public function test_row_actions_are_under_a_menu(): void
    {
        $p = Protocol::factory()->number(12)->create(['etos' => 2026]);
        $html = $this->actingAs($this->admin())->get('/home/list')->assertOk()->getContent();
        $row = substr($html, strpos($html, 'data-id="'.$p->id.'"'));
        $row = substr($row, 0, strpos($row, '</tr>'));

        $this->assertStringContainsString('aria-label="Ενέργειες για το 12/2026"', $row);
        $this->assertStringContainsString('bi-three-dots-vertical', $row);
        $this->assertMatchesRegularExpression('~class="dropdown-item" href="[^"]*/home/'.$p->id.'/1" data-action="copy"~', $row);
        $this->assertMatchesRegularExpression('~class="dropdown-item" href="[^"]*/receipt/'.$p->id.'" target="_blank" data-action="receipt"~', $row);
        $this->assertStringNotContainsString('rounded-pill', $row, 'no buttons in the row any more');

        // R1j (owner, 2026-11-20): readers only view: no copy as new, the receipt stays
        $html = $this->actingAs($this->reader())->get('/home/list')->assertOk()->getContent();
        $row = substr($html, strpos($html, 'data-id="'.$p->id.'"'));
        $row = substr($row, 0, strpos($row, '</tr>'));
        $this->assertStringNotContainsString('data-action="copy"', $row);
        $this->assertStringContainsString('data-action="receipt"', $row);
    }

    /** R2i (owner, 2026-11-20): fixed columns; a short row with ▾ to open it; R2k: more than one file: "+N". */
    public function test_rows_are_short_with_a_toggle_to_show_all(): void
    {
        $p = Protocol::factory()->number(12)->create(['etos' => 2026]);
        \App\Models\Attachment::factory()->count(3)->create(['protocol_id' => $p->id]);
        $q = Protocol::factory()->number(13)->create(['etos' => 2026]);
        $html = $this->actingAs($this->admin())->get('/home/list')->assertOk()->getContent();
        $this->assertStringContainsString('protocol-table', $html);
        foreach (['col-number', 'col-folder', 'col-from', 'col-assign', 'col-files', 'col-actions'] as $col) {
            $this->assertStringContainsString($col, $html, $col);
        }
        $row = fn (Protocol $x) => substr($s = substr($html, strpos($html, 'data-id="'.$x->id.'"')), 0, strpos($s, '</tr>'));

        $this->assertStringContainsString('x-data="{ open: false }" :class="{ \'row-open\': open || detailed }"', $row($p));
        $this->assertStringContainsString('aria-label="Όλα τα στοιχεία του 12/2026"', $row($p));
        $this->assertMatchesRegularExpression('~class="text-break row-clamp"~', $row($p));
        $this->assertStringContainsString('x-show="detailed || open"', $row($p));
        $this->assertMatchesRegularExpression('~class="btn btn-link btn-sm p-0 small more-files[^"]*"[^>]*title="Όλα τα συνημμένα \(3\)">\+2</button>~', $row($p));
        $this->assertStringNotContainsString('more-files', $row($q), 'no files, nothing more');
    }

    /** R2j (owner, 2026-11-20): assignments as pills: ✎ Διεκπεραίωση, 👁 Ενημέρωση; the menu uses the same icons. */
    public function test_assignments_are_pills_with_icons(): void
    {
        [$maria, $eleni] = [\App\Models\User::factory()->writer()->create(['name' => 'Μαρία Χ7']), \App\Models\User::factory()->writer()->create(['name' => 'Ελένη Χ7'])];
        $p = Protocol::factory()->number(12)->assignedTo($maria, $eleni)->create(['etos' => 2026]);
        $html = $this->actingAs($this->admin())->get('/home/list')->assertOk()->getContent();
        $row = substr($s = substr($html, strpos($html, 'data-id="'.$p->id.'"')), 0, strpos($s, '</tr>'));

        $this->assertMatchesRegularExpression('~class="badge rounded-pill assignee-pill assignee-handle position-relative z-2" title="Διεκπεραίωση: Μαρία Χ7" data-type="d">\s*<i class="bi bi-pencil-square[^>]*></i><span class="visually-hidden">Διεκπεραίωση: </span>Μαρία Χ7</span>~', $row);
        $this->assertMatchesRegularExpression('~class="badge rounded-pill assignee-pill assignee-inform position-relative z-2" title="Ενημέρωση: Ελένη Χ7" data-type="e">\s*<i class="bi bi-eye[^>]*></i>~', $row);
        $this->assertStringNotContainsString('ενημ.:', $row);

        $lists = collect(\App\Support\Navigation::for($this->admin()))->firstWhere('label', 'Λίστα')['children'];
        $icons = collect($lists)->filter(fn ($c) => isset($c['icon']))->pluck('icon', 'label');
        $this->assertSame('pencil-square', $icons['προς Διεκπεραίωση']);
        $this->assertSame('eye', $icons['προς Ενημέρωση']);
    }

    /** R2k (owner, 2026-11-20): Από / Προς: the sender only, the number and dates in a tooltip; one pill + "+N". */
    public function test_from_to_details_are_in_a_tooltip_and_cells_stay_short(): void
    {
        $p = Protocol::factory()->number(12)->incoming()->create(['etos' => 2026, 'in_arxi_ekdosis' => 'Δήμος Χ7', 'in_num' => '4521',
            'in_date' => '2026-11-03', 'out_to' => 'Σχολείο Χ7', 'out_date' => '2026-11-10']);
        $users = \App\Models\User::factory()->writer()->count(3)->create();
        $p->update(['diekperaiosi' => 'd'.$users[0]->id.',d'.$users[1]->id.',e'.$users[2]->id]);
        $html = $this->actingAs($this->admin())->get('/home/list')->assertOk()->getContent();
        $row = substr($s = substr($html, strpos($html, 'data-id="'.$p->id.'"')), 0, strpos($s, '</tr>'));
        $row = preg_replace('~<!--\[if (END)?BLOCK\]><!\[endif\]-->~', '', $row);   // Livewire's markers

        $this->assertMatchesRegularExpression('~class="from-to-more position-relative z-2" tabindex="0" data-bs-title="Δήμος Χ7 · Αρ. εισερχομένου: 4521 · Ημ/νία: 03/11/2026"[^>]*>← Δήμος Χ7</span>~', $row);
        $this->assertMatchesRegularExpression('~data-bs-title="Σχολείο Χ7 · Ημ/νία εξερχομένου: 10/11/2026"[^>]*>→ Σχολείο Χ7</span>~', $row);
        $this->assertMatchesRegularExpression('~x-show="detailed \|\| open" x-cloak>αρ. 4521 / 03/11/2026</span>~', $row, 'in full when the row is opened');
        $this->assertStringContainsString('class="badge rounded-pill text-bg-light border assignee-more" title="3 χρήστες">+2</span>', $row);
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
