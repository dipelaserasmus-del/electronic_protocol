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

        // R3d: Αναζήτηση is in the search box; R3g (owner, 2026-11-20): Διαχείριση is in the user menu, in groups
        $admin = $this->admin();
        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα'], $this->labels($admin));
        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα'], $this->labels($this->assigner()));
        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα'], $this->labels($this->writer()));
        $this->assertSame(['Νέο', 'Λίστα'], $this->labels($this->reader()), 'readers never register e-mails');

        $groups = fn ($user) => array_map(fn ($links) => array_column($links, 'label'), Navigation::manage($user));
        $this->assertSame([
            'Εκτύπωση' => ['Εκτύπωση πρωτοκόλλου', 'Εκτύπωση συνημμένων'],
            'Φάκελοι' => ['Φάκελοι και διατήρηση', 'Σφραγίδα αρχείων'],   // R4e
            'Διαχείριση' => ['Χρήστες', 'Ρυθμίσεις', 'Backup', 'Εκκαθάριση', 'Κάδος', 'Αρχεία καταγραφής'],
        ], $groups($admin));
        $this->assertSame(['Εκτύπωση', 'Φάκελοι'], array_keys($groups($this->writer())), 'only administrators manage the rest');
        // R4e: the stamp for administrators, assigners and writers, not readers
        $this->assertContains('Σφραγίδα αρχείων', $groups($this->writer())['Φάκελοι']);
        $this->assertContains('Σφραγίδα αρχείων', $groups($this->assigner())['Φάκελοι']);
        $this->assertSame(['Φάκελοι και διατήρηση'], $groups($this->reader())['Φάκελοι']);
        foreach (Navigation::manage($admin) as $links) {
            foreach ($links as $link) {
                $this->assertNotEmpty($link['icon'], "icon for {$link['label']}");
            }
        }
        // drawn in the user menu: Διαχείριση on the left, the user's own choices on the right
        $html = $this->actingAs($admin)->get('/about')->assertOk()->getContent();
        $menu = substr($html, strpos($html, 'class="dropdown-menu dropdown-menu-end user-menu"'));
        $this->assertMatchesRegularExpression('~user-menu-manage.*?Εκτύπωση.*?Φάκελοι.*?Διαχείριση.*?href="'.preg_quote(url('/settings'), '~').'".*?user-menu-own.*?Πλάτος σελίδας.*?Αρχική σελίδα.*?Περί.*?Έξοδος~s', $menu);
        $this->assertStringNotContainsString('id="nav-3"', $html, 'no Διαχείριση in the bar');
        foreach (Navigation::for($admin) as $item) {
            $this->assertNotEmpty($item['icon'] ?? null, "icon for {$item['label']}");
            foreach ($item['children'] ?? [] as $child) {
                if (isset($child['label']) && ! isset($child['links'])) {
                    $this->assertNotEmpty($child['icon'] ?? null, "icon for {$child['label']}");
                }
            }
        }
    }

    /** R1j (owner, 2026-11-20): Λίστα in the top bar leads to the list; a ▾ next to it opens the other lists. */
    public function test_the_list_item_is_a_link_and_its_arrow_opens_the_menu(): void
    {
        $writer = $this->writer();
        $item = collect(Navigation::for($writer))->firstWhere('label', 'Λίστα');
        $this->assertSame(Navigation::landing($writer), $item['url']);
        $this->assertNotEmpty($item['children']);

        $html = $this->actingAs($writer)->get('/about')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<a class="nav-link[^"]*" href="'.preg_quote(Navigation::landing($writer), '~').'"\s*id="nav-1-link"\s*data-home-link\s*>.*?Λίστα</a>~s', $html);
        $this->assertMatchesRegularExpression('~<a class="nav-link dropdown-toggle dropdown-toggle-split[^"]*" href="#" role="button" data-bs-toggle="dropdown"\s*aria-expanded="false" id="nav-1" aria-label="Περισσότερες: Λίστα"~', $html);
        $this->assertSame(1, substr_count($html, 'dropdown-toggle-split'), 'only Λίστα is split');
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

        $manage = fn ($user) => array_column(array_merge(...array_values(Navigation::manage($user))), 'label');
        $this->assertNotContains('Εκτύπωση πρωτοκόλλου', $manage($this->writer()));
        $this->assertNotContains('Εκτύπωση πρωτοκόλλου', $manage($this->reader()));
        $this->assertContains('Φάκελοι και διατήρηση', $manage($this->reader()));
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

    /** R3e (owner, 2026-11-20): each user chooses their home page; the login, the brand and Λίστα lead there. */
    public function test_each_user_chooses_their_home_page(): void
    {
        $this->assertSame('/home/list/d', config('landing-page.page.3'), 'writers start from προς Διεκπεραίωση by default');
        [$a, $b] = [$this->writer(), $this->writer()];
        $this->assertSame(url('/home/list/d'), Navigation::landing($a));
        $this->actingAs($a)->get('/about')->assertOk()
            ->assertSee('data-value="d" data-url="'.url('/preferences').'"', false)
            ->assertSeeInOrder(['data-value="d"', 'aria-pressed="true"'], false)
            ->assertSee('href="'.url('/home/list').'"><i class="bi bi-collection', false);   // Όλα τα πρωτόκολλα: all, always

        foreach (['all' => '/home/list', 'e' => '/home/list/e', 'd' => '/home/list/d', 'all ' => null] as $value => $path) {
            $value = trim($value);
            $this->actingAs($a)->postJson('/preferences', ['key' => 'home', 'value' => $value])->assertOk();
            $a->refresh();
            $home = url($path ?? '/home/list');
            $this->assertSame($home, Navigation::landing($a), $value);
            $html = $this->actingAs($a)->get('/about')->assertOk()->getContent();
            $this->assertMatchesRegularExpression('~class="navbar-brand app-brand" data-home-link href="'.preg_quote($home, '~').'"~', $html);
            $this->assertMatchesRegularExpression('~href="'.preg_quote($home, '~').'"\s*id="nav-1-link"\s*data-home-link\s*>~', $html, 'Λίστα');
            $this->assertMatchesRegularExpression('~data-value="'.$value.'"[^>]*aria-pressed="true"~', $html);
            $this->actingAs($a)->withHeader('referer', url('/login'))->get('/chkForUpdates')->assertRedirect($home);   // after login
            $this->actingAs($a)->get('/login')->assertRedirect($home);
        }
        $this->assertSame(url('/home/list/d'), Navigation::landing($b->fresh()), 'another user keeps the role\'s');
        $this->actingAs($a)->postJson('/preferences', ['key' => 'home', 'value' => '/admin'])->assertStatus(422);
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
}
