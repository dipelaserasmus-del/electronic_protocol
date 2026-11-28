<?php

namespace Tests\Feature;

use App\Livewire\AuditHistory;
use App\Models\AuditEntry;
use App\Models\Protocol;
use App\Services\Audit\AuditLog;
use Carbon\Carbon;
use Livewire\Livewire;

/** X02: the audit log's page for administrators. */
class AuditHistoryTest extends FeatureTestCase
{
    private function at(string $when, callable $do): void
    {
        Carbon::setTestNow($when);
        $do();
        Carbon::setTestNow();
    }

    /** The page with these filters typed and «Αναζήτηση» pressed: its paginator */
    private function found(\App\Models\User $admin, array $filters): \Illuminate\Pagination\LengthAwarePaginator
    {
        $page = Livewire::actingAs($admin)->test(AuditHistory::class);
        foreach ($filters as $field => $value) {
            $page->set($field, $value);
        }

        return $page->call('apply')->assertHasNoErrors()->viewData('entries');
    }

    public function test_only_administrators_open_it(): void
    {
        $this->actingAs($this->admin())->get('/history')->assertOk()->assertSee('Ιστορικό ενεργειών');
        foreach ([$this->assigner(), $this->writer(), $this->reader()] as $user) {
            $this->actingAs($user)->get('/history')->assertForbidden();
        }
        auth()->logout();
        $this->get('/history')->assertRedirect();
    }

    public function test_entries_newest_first_in_greek_with_their_changes(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->at('2026-11-27 09:00:00', fn () => Protocol::factory()->number(5, 2026)->onDate('20261127')->create(['thema' => 'Αίτηση', 'fakelos' => 'Φ.1']));
        $this->at('2026-11-28 10:30:00', fn () => Protocol::where('protocolnum', 5)->first()->update(['thema' => 'Αίτηση γονέα']));

        $page = Livewire::actingAs($admin)->test(AuditHistory::class);
        $html = $page->html();
        $this->assertLessThan(strpos($html, 'data-action="created"'), strpos($html, 'data-action="updated"'), 'newest first');
        $page->assertSee('28/11/2026')->assertSee('10:30:00')->assertSee($admin->username)
            ->assertSee('Αλλαγή')->assertSee('Πρωτόκολλο 5/2026')
            ->assertSeeHtml('<span class="fw-semibold">Θέμα:</span>')->assertSee('Αίτηση γονέα')
            ->assertSeeHtml('href="'.url('/home/'.Protocol::where('protocolnum', 5)->value('id')).'"');
        $text = preg_replace('~\s+~u', ' ', strip_tags($page->html()));
        $this->assertStringContainsString('Χρήστης: '.\App\Models\User::find(Protocol::where('protocolnum', 5)->value('user_id'))->username, $text, 'an id as the name');
        $this->assertStringContainsString('Ημ/νία πρωτοκόλλου: 27/11/2026', $text, 'a date as people write it');
        $this->assertStringContainsString('Ονοματεπώνυμο: '.$admin->name, $text, 'a user\'s name with a user\'s label');
        $this->assertStringContainsString('Ενεργός: ναι', $text);
        $this->assertStringNotContainsString('Όνομα συνημμένου', $text);
    }

    public function test_filters_by_user_action_dates_and_protocol(): void
    {
        $admin = $this->admin();
        $writer = $this->writer();
        $this->actingAs($writer);
        $this->at('2026-11-20 09:00:00', fn () => Protocol::factory()->number(1, 2026)->create(['user_id' => $writer->id]));
        $p2 = null;
        $this->at('2026-11-25 09:00:00', function () use (&$p2, $writer) {
            $p2 = Protocol::factory()->number(2, 2026)->create(['user_id' => $writer->id]);
            \App\Models\Attachment::factory()->create(['protocol_id' => $p2->id, 'name' => 'συνημμένο.pdf']);
        });
        auth()->logout();
        $this->at('2026-11-26 09:00:00', fn () => AuditLog::record('login_failed', 'user', null, context: ['typed' => 'κάποιος'], asSignedIn: false));
        $count = fn (array $filters) => $this->found($admin, $filters)->total();

        $writerEntries = AuditEntry::where('user_id', $writer->id)->count();
        $this->assertSame($writerEntries, $count(['user' => (string) $writer->id]));
        $this->assertSame(AuditEntry::whereNull('user_id')->count(), $count(['user' => AuditHistory::NOBODY]));
        $this->assertSame(1, $count(['action' => 'login_failed']));
        Livewire::actingAs($admin)->test(AuditHistory::class)->set('action', 'login_failed')->call('apply')->assertSee('Χρήστης κάποιος');
        $this->assertSame(AuditEntry::where('created_at', '>=', '2026-11-25')->where('created_at', '<', '2026-11-26')->count(),
            $count(['from' => '25/11/2026', 'to' => '25/11']));
        $this->assertSame(AuditEntry::where('protocol_id', $p2->id)->count(), $count(['protocol' => '2/2026']), 'the protocol and its attachment');
        $this->assertSame(2, $count(['protocol' => '2/2026']));

        // a purged protocol keeps its history by number
        $this->actingAs($admin);
        app(\App\Services\Protocols\ProtocolTrash::class)->purge(app(\App\Services\Protocols\ProtocolTrash::class)->trash($admin, Protocol::where('protocolnum', 1)->first()));
        $this->assertSame(['purged', 'trashed', 'created'], $this->found($admin, ['protocol' => '1/2026'])->pluck('action')->all());
    }

    public function test_wrong_filters_are_refused(): void
    {
        Livewire::actingAs($this->admin())->test(AuditHistory::class)
            ->set('from', '31/02/2026')->set('protocol', '125')->call('apply')
            ->assertHasErrors(['from', 'protocol'])->assertSee('Γράψτε αριθμό/έτος');
    }

    public function test_the_export_has_every_filtered_entry_whole_for_excel(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $long = str_repeat('Πολύ μεγάλο θέμα. ', 20);
        Protocol::factory()->number(9, 2026)->create(['thema' => $long]);

        $response = Livewire::actingAs($admin)->test(AuditHistory::class)->set('action', 'created')->call('export');
        $csv = $response->effects['download']['content'] ?? '';
        $csv = base64_decode($csv, true) ?: $csv;
        $this->assertStringStartsWith("\xEF\xBB\xBF".'Ημερομηνία;Ώρα;Χρήστης;IP;Ενέργεια;Αφορά', $csv);
        $this->assertStringContainsString('Δημιουργία;"Πρωτόκολλο 9/2026"', $csv);
        $this->assertStringContainsString(trim($long), $csv, 'whole, not cut as on the page');
        $this->assertStringNotContainsString('Αλλαγή;', $csv);
    }

    public function test_a_protocol_s_history_from_its_form_for_administrators(): void
    {
        $p = Protocol::factory()->number(3, 2026)->create();
        $this->actingAs($this->admin())->get('/home/'.$p->id)->assertOk()
            ->assertSee('data-action="history"', false)->assertSee(url('/history').'?protocol=3%2F2026', false);
        $this->actingAs($this->writer())->get('/home/'.$p->id)->assertDontSee('data-action="history"', false);
    }
}
