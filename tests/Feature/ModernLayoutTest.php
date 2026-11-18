<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\InboxAccess;
use App\Support\Navigation;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;

/** N03: new layout (Bootstrap 5, top bar) and its menu, which keeps the old menu's role rules. */
class ModernLayoutTest extends FeatureTestCase
{
    public function test_about_page_uses_the_new_layout_with_the_top_bar(): void
    {
        $this->actingAs($this->writer())->get('/about')
            ->assertOk()
            ->assertSee('app-topbar', false)
            ->assertSee('Ομάδα δημιουργίας του Ηλ.Πρωτοκόλλου')
            ->assertSee('action="'.url('/logout').'"', false)   // logout is a POST form with the CSRF token
            ->assertSee('name="_token"', false);
    }

    public function test_menu_items_follow_the_roles(): void
    {
        $this->setting('defaultImapEmail', 'default');
        $this->setting('allowedEmailUsers', '');

        // R3d (owner, 2026-11-18): Εκτύπωση and Διατήρηση are under Διαχείριση; Αναζήτηση is in the search box
        $admin = $this->admin();
        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα', 'Διαχείριση'], $this->labels($admin));
        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα', 'Διαχείριση'], $this->labels($this->assigner()));
        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα', 'Διαχείριση'], $this->labels($this->writer()));
        $this->assertSame(['Νέο', 'Λίστα', 'Διαχείριση'], $this->labels($this->reader()), 'readers never register e-mails');

        $manage = fn ($user) => array_values(array_filter(array_column($this->children($user, 'Διαχείριση'), 'label')));
        $this->assertSame(['Εκτύπωση πρωτοκόλλου', 'Εκτύπωση συνημμένων', 'Διατήρηση', 'Χρήστες', 'Backup', 'Εκκαθάριση', 'Κάδος', 'Ρυθμίσεις'], $manage($admin));
        $this->assertSame(['Εκτύπωση πρωτοκόλλου', 'Εκτύπωση συνημμένων', 'Διατήρηση'], $manage($this->writer()), 'only administrators manage the rest');
        foreach (Navigation::for($admin) as $item) {
            $this->assertNotEmpty($item['icon'] ?? null, "icon for {$item['label']}");
            foreach ($item['children'] ?? [] as $child) {
                if (isset($child['label']) && ! isset($child['links'])) {
                    $this->assertNotEmpty($child['icon'] ?? null, "icon for {$child['label']}");
                }
            }
        }
    }

    public function test_inbox_link_needs_an_account_and_the_user_in_the_allowed_list(): void
    {
        $writer = $this->writer();

        $this->setting('defaultImapEmail', '');
        $this->assertNotContains('Εισερχόμενα', $this->labels($writer));
        $this->assertFalse(InboxAccess::allows($writer));

        $this->setting('defaultImapEmail', 'default');
        $this->setting('allowedEmailUsers', 'someoneelse');
        $this->assertNotContains('Εισερχόμενα', $this->labels($writer));

        $this->setting('allowedEmailUsers', 'someoneelse,'.$writer->username);
        $this->assertContains('Εισερχόμενα', $this->labels($writer));
        $this->assertTrue(InboxAccess::allows($writer), 'the same rule guards /viewEmails');
    }

    public function test_print_is_hidden_when_the_list_is_limited_for_writers_and_readers(): void
    {
        $this->setting('limitProtocolAccessList', 1);

        $manage = fn ($user) => array_column($this->children($user, 'Διαχείριση'), 'label');
        $this->assertNotContains('Εκτύπωση πρωτοκόλλου', $manage($this->writer()));
        $this->assertNotContains('Εκτύπωση πρωτοκόλλου', $manage($this->reader()));
        $this->assertContains('Διατήρηση', $manage($this->reader()));
        $this->assertContains('Εκτύπωση πρωτοκόλλου', $manage($this->assigner()), 'the limit only applies to writers/readers');
    }

    public function test_admin_and_assigner_get_lists_of_all_users_and_per_user(): void
    {
        $colleague = User::factory()->writer()->create(['name' => 'Ζωή Παπαδοπούλου', 'active' => 1]);

        $lists = $this->children($this->assigner(), 'Λίστα');
        $urls = array_column($lists, 'url');
        $this->assertContains(url('/home/list/e/a'), $urls, '"προς Ενημέρωση" of all users (the old menu linked /d here)');
        $row = collect($lists)->firstWhere('label', 'Ζωή Παπαδοπούλου');
        $this->assertSame(url('/home/list/a/'.$colleague->id), $row['url']);
        $this->assertSame(url('/home/list/e/'.$colleague->id), $row['links']['Ενημ.']);

        $writerLists = array_column($this->children($this->writer(), 'Λίστα'), 'url');
        $this->assertNotContains(url('/home/list/d/a'), $writerLists, 'writers see only their own lists');
    }

    public function test_top_bar_colour_from_settings_is_used_only_if_it_is_a_colour(): void
    {
        $this->setting('titleColor', '#336699');
        $this->actingAs($this->writer())->get('/about')->assertSee('background-color: #336699', false);

        $this->setting('titleColor', 'red;}</style><script>alert(1)</script>');
        $this->actingAs($this->writer())->get('/about')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('background-color: red;', false);
    }

    public function test_notifications_keep_simple_formatting_but_no_attributes_or_scripts(): void
    {
        $this->actingAs($this->writer())
            ->withSession(['notification' => ['alert-type' => 'error',
                'message' => 'Πρόβλημα<br><strong>123</strong><b onclick="x()">!</b><script>alert(1)</script>']])
            ->get('/about')
            ->assertSee('Πρόβλημα<br><strong>123</strong>', false)
            ->assertSee('alert-danger', false)
            ->assertDontSee('<b onclick', false)   // shown as harmless text, not as a tag
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_livewire_components_work_inside_the_new_layout(): void
    {
        Livewire::component('n03-counter', N03Counter::class);

        Livewire::test(N03Counter::class)->assertSee('0')->call('increment')->assertSee('1');

        $html = Blade::render("@extends('layouts.modern') @section('content') <livewire:n03-counter /> @endsection");
        $this->assertStringContainsString('wire:snapshot', $html, 'component rendered by Livewire');
        $this->assertStringContainsString('livewire', $html, 'Livewire script included');
    }

    private function labels(User $user): array
    {
        return array_column(Navigation::for($user), 'label');
    }

    private function children(User $user, string $label): array
    {
        return collect(Navigation::for($user))->firstWhere('label', $label)['children'];
    }
}

/** Minimal component, only to prove the Livewire wiring. */
class N03Counter extends Component
{
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function render(): string
    {
        return '<div><span>{{ $count }}</span><button wire:click="increment">+</button></div>';
    }

    /** R3d: the institution under the name, the search box with its two buttons, icons */
    public function test_top_bar_brand_search_buttons_and_icons(): void
    {
        $this->setting('ipiresiasName', 'Σχολείο Δοκιμών');
        $this->actingAs($this->writer())->get('/about')->assertOk()
            ->assertSeeInOrder(['app-brand', 'brand-institution', 'Σχολείο Δοκιμών'], false)
            ->assertSee('id="app-search-advanced"', false)->assertSee('href="'.url('/find').'"', false)
            ->assertSee('bi bi-file-earmark-plus', false)->assertSee('bi bi-search', false)
            ->assertDontSee('>Αναζήτηση</a>', false);
    }

    /** R3d: page width, saved per user (owner, 2026-11-18) */
    public function test_page_width_is_saved_per_user(): void
    {
        [$a, $b] = [$this->writer(), $this->writer()];
        $this->actingAs($a)->get('/about')->assertDontSee('layout-narrow', false);

        $this->actingAs($a)->postJson('/preferences', ['key' => 'width', 'value' => 'narrow'])->assertOk()->assertJson(['saved' => true, 'value' => 'narrow']);
        $this->actingAs($a)->get('/about')->assertSee('layout-narrow', false)->assertSee('data-value="narrow" data-url="'.url('/preferences').'"', false);
        $this->actingAs($b)->get('/about')->assertDontSee('layout-narrow', false);   // another user keeps the default

        // only known choices are kept
        $this->actingAs($a)->postJson('/preferences', ['key' => 'width', 'value' => '50%'])->assertStatus(422);
        $this->actingAs($a)->postJson('/preferences', ['key' => 'role_id', 'value' => 1])->assertStatus(422);
        $this->assertSame(['width' => 'narrow'], $a->fresh()->preferences);

        auth()->logout();
        $this->postJson('/preferences', ['key' => 'width', 'value' => 'full'])->assertUnauthorized();
    }
}
