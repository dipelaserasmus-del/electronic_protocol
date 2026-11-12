<?php

namespace Tests\Feature\Legacy;

use App\Attachment;
use App\Protocol;
use Tests\Feature\FeatureTestCase;

/** Characterization of list, search, download and role/assignment-based access. */
class ProtocolAccessTest extends FeatureTestCase
{
    /** The search form always sends the three field selectors (defaults from settings) */
    private function findUrl(string $query): string
    {
        parse_str($query, $q);
        $q += ['searchField1' => 'thema', 'searchField2' => 'in_arxi_ekdosis', 'searchField3' => 'ada',
               'searchData1' => '', 'searchData2' => '', 'searchData3' => ''];
        return '/getFindData?' . http_build_query($q);
    }

    public function test_protocol_list_shows_protocols(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'Πρώτο θέμα']);
        Protocol::factory()->number(2)->create(['thema' => 'Δεύτερο θέμα']);

        $this->actingAs($this->reader())->get('/home/list')
            ->assertOk()->assertSee('Πρώτο θέμα')->assertSee('Δεύτερο θέμα');
    }

    public function test_search_by_subject_matches_part_of_text(): void
    {
        Protocol::factory()->number(1)->create(['thema' => 'Πρόσκληση σε ημερίδα']);
        Protocol::factory()->number(2)->create(['thema' => 'Άσχετο']);

        $this->actingAs($this->reader())
            ->get($this->findUrl('searchField1=thema&searchData1=' . urlencode('ημερίδα')))
            ->assertOk()->assertSee('Πρόσκληση σε')->assertDontSee('Άσχετο');   // match is wrapped in <mark>
    }

    public function test_search_by_incoming_number_matches_middle_of_text(): void
    {
        Protocol::factory()->number(1)->incoming()->create(['in_num' => 'Φ.20/12345/Δ1']);

        $this->actingAs($this->reader())
            ->get($this->findUrl('searchField1=in_num&searchData1=12345'))
            ->assertOk()->assertSee('12345');
    }

    public function test_search_by_number_range(): void
    {
        foreach ([5, 6, 7, 8] as $n) Protocol::factory()->number($n)->create(['thema' => "Θέμα αρ $n"]);

        $this->actingAs($this->reader())
            ->get($this->findUrl('aponum=6&eosnum=7&etosForMany=' . now()->year))
            ->assertOk()->assertSee('Θέμα αρ 6')->assertSee('Θέμα αρ 7')->assertDontSee('Θέμα αρ 5')->assertDontSee('Θέμα αρ 8');
    }

    public function test_writer_without_limit_setting_opens_any_protocol(): void
    {
        $this->setting('limitProtocolAccessList', 0);
        $p = Protocol::factory()->number(1)->create(['thema' => 'Ξένο πρωτόκολλο']);

        $this->actingAs($this->writer())->get("/home/{$p->id}")->assertOk()->assertSee('Ξένο πρωτόκολλο');
    }

    public function test_limit_setting_hides_unassigned_protocol_from_writer(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $p = Protocol::factory()->number(1)->create();

        $this->actingAs($this->writer())->get("/home/{$p->id}")->assertNotFound();
    }

    public function test_limit_setting_allows_assigned_protocol(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $writer = $this->writer();
        $p = Protocol::factory()->number(1)->assignedTo($writer)->create(['thema' => 'Ανατεθειμένο']);

        $this->actingAs($writer)->get("/home/{$p->id}")->assertOk()->assertSee('Ανατεθειμένο');
    }

    public function test_limit_setting_filters_search_results(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $writer = $this->writer();
        Protocol::factory()->number(1)->assignedTo($writer)->create(['thema' => 'Δικό μου θέμα']);
        Protocol::factory()->number(2)->create(['thema' => 'Ξένο θέμα']);

        $this->actingAs($writer)->get($this->findUrl('searchField1=thema&searchData1=' . urlencode('θέμα')))
            ->assertOk()->assertSee('Δικό μου')->assertDontSee('Ξένο');
    }

    public function test_goto_by_number_respects_limit_setting(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $p = Protocol::factory()->number(9)->create();

        $response = $this->actingAs($this->writer())->get("/goto/{$p->etos}/9?find=1");

        $this->assertNotEquals(url("home/{$p->id}"), $response->headers->get('Location'));
    }

    /**
     * KNOWN VULNERABILITY (task S02): attachment download has no access check. With the
     * limit setting ON a writer can still download an attachment of a protocol they can't open.
     */
    public function test_known_vulnerability_download_ignores_access_limits(): void
    {
        $this->setting('limitProtocolAccessList', 1);
        $a = Attachment::factory()->withFile('secret-content')->create();

        ob_start();   // the controller calls ob_end_clean()
        $this->actingAs($this->writer())->get("/download/{$a->id}")
            ->assertOk()->assertSee('secret-content', false);
    }

    /**
     * KNOWN VULNERABILITY (tasks S02/S03): any writer can delete any protocol, including ones
     * created by others, with a plain GET link.
     */
    public function test_known_vulnerability_writer_deletes_others_protocol_via_get(): void
    {
        $p = Protocol::factory()->number(1)->create();

        $this->actingAs($this->writer())->get("/delprotocol/{$p->id}");

        $this->assertNull(Protocol::find($p->id));
    }

    public function test_reader_cannot_delete(): void
    {
        $p = Protocol::factory()->number(1)->create();

        $this->actingAs($this->reader())->get("/delprotocol/{$p->id}");

        $this->assertNotNull(Protocol::find($p->id));
    }

    public function test_admin_pages_are_admin_only(): void
    {
        foreach (['/users', '/settings', '/backups'] as $url) {
            $this->actingAs($this->writer())->get($url)->assertRedirect();
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
    }

    public function test_receipt_page_renders(): void
    {
        $p = Protocol::factory()->number(4)->create(['thema' => 'Απόδειξη θέμα']);

        $this->actingAs($this->writer())->get("/receipt/{$p->id}")->assertOk()->assertSee('Απόδειξη θέμα');
    }

    public function test_main_pages_render_for_each_role(): void
    {
        Protocol::factory()->number(1)->create();
        foreach (['reader', 'writer', 'assigner', 'admin'] as $role) {
            $user = $this->$role();
            foreach (['/home/list', '/find', '/print', '/about', '/keep'] as $url) {
                $this->actingAs($user)->get($url)->assertSuccessful();
            }
        }
    }
}
