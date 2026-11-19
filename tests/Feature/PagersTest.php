<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Protocol;
use App\Models\User;

/**
 * R2g (owner, 2026-11-19): the list pager (R2e: above and below, ‹ ›, any page from a list) on every paged screen, and
 * a page past the last one shows the last page there too (as for the protocol list, review of R2e).
 */
class PagersTest extends FeatureTestCase
{
    /** Both pagers, every page offered, and ?page=999 shows the last page: "Εμφάνιση first–total από total". */
    private function assertPagedAtTheEnd(string $url, int $pages, int $first, int $total, ?User $admin = null): void
    {
        $html = $this->actingAs($admin ?? $this->admin())->get($url.(str_contains($url, '?') ? '&' : '?').'page=999')->assertOk()->getContent();
        $this->assertStringContainsString('id="pager-top"', $html, "$url: pager above");
        $this->assertStringContainsString('id="pager-bottom"', $html, "$url: pager below");
        $this->assertSame(2, substr_count($html, '<option value="'.$pages.'" selected'), "$url: the last page ($pages) chosen in both");
        $this->assertStringContainsString("Εμφάνιση {$first}–{$total} από {$total}", $html, "$url: the last page's rows");
        $this->assertStringNotContainsString('{{ $', $html);
    }

    public function test_search_results_of_the_top_bar_box(): void
    {
        $this->setting('maxRowsInFindPage', 2);
        Protocol::factory()->count(5)->sequence(fn ($s) => ['protocolnum' => $s->index + 1, 'thema' => 'Πρόσκληση '.($s->index + 1)])->create();
        $this->assertPagedAtTheEnd('/search?q='.urlencode('Πρόσκληση'), 3, 5, 5);
    }

    public function test_advanced_search(): void
    {
        $this->setting('maxRowsInFindPage', 2);
        Protocol::factory()->count(5)->sequence(fn ($s) => ['protocolnum' => $s->index + 1, 'thema' => 'Πρόσκληση '.($s->index + 1)])->create();
        $this->assertPagedAtTheEnd('/find?'.http_build_query(['s' => 1, 'year' => '', 't' => ['thema' => 'Πρόσκληση']]), 3, 5, 5);
    }

    public function test_trash(): void
    {
        $p = Protocol::factory()->number(1)->create();
        Attachment::factory()->count(101)->create(['protocol_id' => $p->id, 'deleted_at' => now()]);
        $this->assertPagedAtTheEnd('/trash', 3, 101, 101);
    }

    public function test_users(): void
    {
        $admin = $this->admin();   // made first, so the count includes it
        User::factory()->count(100)->create();
        $total = User::count();
        $this->assertPagedAtTheEnd('/users', (int) ceil($total / 50), 50 * (int) floor(($total - 1) / 50) + 1, $total, $admin);
    }

    public function test_clean_up_of_expired_attachments(): void
    {
        $p = Protocol::factory()->number(1)->create();
        Attachment::factory()->count(201)->create(['protocol_id' => $p->id, 'keep' => '1', 'expires' => now()->subYear()->toDateString()]);
        $this->assertPagedAtTheEnd('/arxeio', 3, 201, 201);
    }
}
