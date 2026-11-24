<?php

namespace Tests\Feature;

use App\Models\UserNote;

/** N05: each user's notepad: their own only, saved with a version, an older copy never overwrites a newer one silently. */
class NotepadTest extends FeatureTestCase
{
    public function test_a_user_reads_and_saves_their_own_note(): void
    {
        $writer = $this->writer();
        $this->actingAs($writer)->getJson('/notes')->assertOk()->assertExactJson(['body' => '', 'version' => 0, 'saved' => null]);

        $this->travelTo(now()->setTime(9, 41));
        $this->actingAs($writer)->postJson('/notes', ['body' => "# Σήμερα\n- [ ] τηλέφωνο", 'version' => 0])
            ->assertOk()->assertJson(['version' => 1, 'saved' => '09:41']);
        $this->actingAs($writer)->postJson('/notes', ['body' => "# Σήμερα\n- [x] τηλέφωνο", 'version' => 1])->assertOk()->assertJson(['version' => 2]);
        $this->actingAs($writer)->getJson('/notes')->assertJson(['body' => "# Σήμερα\n- [x] τηλέφωνο", 'version' => 2]);
        $this->actingAs($writer)->postJson('/notes', ['body' => '', 'version' => 2])->assertOk()->assertJson(['body' => '']);   // emptied
        $this->assertSame(1, UserNote::count());
    }

    /** private: nobody else's note is ever read or written, administrators' neither */
    public function test_notes_are_private(): void
    {
        $writer = $this->writer();
        $this->actingAs($writer)->postJson('/notes', ['body' => 'μυστικό', 'version' => 0])->assertOk();
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/notes')->assertJson(['body' => '', 'version' => 0]);
        $this->actingAs($admin)->postJson('/notes', ['body' => 'του διαχειριστή', 'version' => 0, 'user_id' => $writer->id])->assertOk();
        $this->assertSame('μυστικό', UserNote::firstWhere('user_id', $writer->id)->body);
        $this->assertSame('του διαχειριστή', UserNote::firstWhere('user_id', $admin->id)->body);

        auth()->logout();
        $this->get('/notes')->assertRedirect('/login');
        $this->post('/notes', ['body' => 'x', 'version' => 0])->assertRedirect('/login');
    }

    /** a second tab with an older copy is told, and chooses */
    public function test_an_older_copy_does_not_overwrite_a_newer_one(): void
    {
        $user = $this->reader();   // every role has a notepad
        $this->actingAs($user)->postJson('/notes', ['body' => 'πρώτη', 'version' => 0])->assertOk();
        $this->actingAs($user)->postJson('/notes', ['body' => 'από την καρτέλα Α', 'version' => 1])->assertOk();

        $this->actingAs($user)->postJson('/notes', ['body' => 'από την καρτέλα Β', 'version' => 1])
            ->assertStatus(409)->assertJson(['conflict' => true, 'body' => 'από την καρτέλα Α', 'version' => 2]);
        $this->assertSame('από την καρτέλα Α', UserNote::first()->body);

        $this->actingAs($user)->postJson('/notes', ['body' => 'από την καρτέλα Β', 'version' => 1, 'force' => true])
            ->assertOk()->assertJson(['version' => 3]);
        $this->assertSame('από την καρτέλα Β', UserNote::first()->body);
    }

    public function test_wrong_saves_are_refused(): void
    {
        $user = $this->writer();
        $this->actingAs($user)->postJson('/notes', ['body' => str_repeat('α', 100001), 'version' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('body');
        $this->actingAs($user)->postJson('/notes', ['body' => 'x'])->assertStatus(422)->assertJsonValidationErrors('version');
        $this->actingAs($user)->postJson('/notes', ['body' => ['x'], 'version' => 0])->assertStatus(422);
        $this->assertSame(0, UserNote::count());
    }

    public function test_every_page_has_the_tab(): void
    {
        $html = $this->actingAs($this->reader())->get('/home/list')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'id="notepad-tab"'));
        $this->assertStringContainsString('notepad({ url: ', $html);
        $this->assertStringNotContainsString('id="notepad"', $this->get('/login')->getContent());
    }
}
