<?php

namespace Tests\Feature;

use App\Livewire\ProtocolForm;
use App\Models\Protocol;
use Livewire\Livewire;

/** R3h: direct links /protocol/{num} (this year) and /protocol/{num}/{year}; the form's «Σύνδεσμος» pill (removed in R1q). */
class DirectLinkTest extends FeatureTestCase
{
    public function test_a_link_opens_the_protocols_form(): void
    {
        $year = (int) now()->format('Y');
        $thisYear = Protocol::factory()->number(15, $year)->create();
        $lastYear = Protocol::factory()->number(15, $year - 1)->create();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/protocol/15')->assertRedirect('/home/' . $thisYear->id);
        $this->actingAs($admin)->get("/protocol/15/$year")->assertRedirect('/home/' . $thisYear->id);
        $this->actingAs($admin)->get('/protocol/15/' . ($year - 1))->assertRedirect('/home/' . $lastYear->id);
        $this->actingAs($admin)->get('/protocol/015/' . ($year - 1))->assertRedirect('/home/' . $lastYear->id);
    }

    public function test_a_guest_logs_in_first_and_then_lands_on_the_protocol(): void
    {
        $p = Protocol::factory()->number(16)->create();
        $this->get('/protocol/16')->assertRedirect('/login');
        $admin = $this->admin();
        $this->post('/login', ['username' => $admin->username, 'password' => 'password'])->assertRedirect(url('/protocol/16'));
        $this->get('/protocol/16')->assertRedirect('/home/' . $p->id);
    }

    /** missing, not allowed and malformed: one page, word for word, so the link reveals nothing */
    public function test_missing_forbidden_and_malformed_links_get_the_same_page(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $year = (int) now()->format('Y');
        $hidden = Protocol::factory()->number(17, $year)->create(['user_id' => $this->admin()->id]);
        $writer = $this->writer();
        $this->assertTrue($writer->cannot('view', $hidden));

        $forbidden = $this->actingAs($writer)->get("/protocol/17/$year");
        $missing = $this->actingAs($writer)->get("/protocol/18/$year");
        $forbidden->assertNotFound()->assertSee('Δεν υπάρχει')->assertSee("17/$year");
        $missing->assertNotFound()->assertSee("18/$year");
        $this->assertSame($this->normalised($missing->getContent(), "18/$year"), $this->normalised($forbidden->getContent(), "17/$year"));

        // the search link searches that number (the search itself shows only what the user may see)
        $forbidden->assertSee(e(url('/search?q=' . rawurlencode("αρ:17/$year"))), false);
        $forbidden->assertDontSee('/home/' . $hidden->id, false);

        foreach (['abc', '-1', '1.5', '9999999999', '%3Cb%3E'] as $bad) {
            $this->actingAs($writer)->get("/protocol/$bad")->assertNotFound()->assertSee('Δεν υπάρχει')
                ->assertSee(url('/find'), false)->assertDontSee('<b>', false);
        }
        foreach (['26', '20266', 'abcd'] as $badYear) {
            $this->actingAs($writer)->get("/protocol/17/$badYear")->assertNotFound()->assertSee('Δεν υπάρχει');
        }
    }

    public function test_a_limited_writer_opens_their_own_and_assigned_protocols(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $writer = $this->writer();
        $own = Protocol::factory()->number(21)->create(['user_id' => $writer->id]);
        $assigned = Protocol::factory()->number(22)->assignedTo($writer)->create(['user_id' => $this->admin()->id]);

        $this->actingAs($writer)->get('/protocol/21')->assertRedirect('/home/' . $own->id);
        $this->actingAs($writer)->get('/protocol/22')->assertRedirect('/home/' . $assigned->id);
    }

    /** R1q (owner, 2026-11-27): the form's «Σύνδεσμος» is gone (not needed); the direct links themselves still work */
    public function test_the_form_has_no_link_to_copy(): void
    {
        $year = (int) now()->format('Y');
        $p = Protocol::factory()->number(31, $year)->create();
        Livewire::actingAs($this->admin())->test(ProtocolForm::class, ['protocol' => $p])
            ->assertDontSeeHtml('data-copy-link')->assertDontSee('Σύνδεσμος');
        $this->actingAs($this->admin())->get("/protocol/31/$year")->assertRedirect('/home/' . $p->id);
    }

    /** the page's own content (the layout around it is the same for every page) with the number made generic */
    private function normalised(string $html, string $number): string
    {
        $this->assertSame(1, preg_match('~<div class="card[^"]*" [^>]*id="protocol-missing">.*?</div>\s*</div>\s*</div>~s', $html, $m));
        return str_replace([$number, rawurlencode($number)], 'N/YYYY', $m[0]);
    }
}
