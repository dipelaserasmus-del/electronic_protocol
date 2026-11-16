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

        $admin = $this->labels($this->admin());
        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα', 'Αναζήτηση', 'Εκτύπωση', 'Διατήρηση', 'Διαχείριση'], $admin);

        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα', 'Αναζήτηση', 'Εκτύπωση', 'Διατήρηση'], $this->labels($this->assigner()));
        $this->assertSame(['Νέο', 'Λίστα', 'Εισερχόμενα', 'Αναζήτηση', 'Εκτύπωση', 'Διατήρηση'], $this->labels($this->writer()));
        $this->assertSame(['Νέο', 'Λίστα', 'Αναζήτηση', 'Εκτύπωση', 'Διατήρηση'], $this->labels($this->reader()), 'readers never register e-mails');
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

        $this->assertNotContains('Εκτύπωση', $this->labels($this->writer()));
        $this->assertNotContains('Εκτύπωση', $this->labels($this->reader()));
        $this->assertContains('Εκτύπωση', $this->labels($this->assigner()), 'the limit only applies to writers/readers');
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
}
