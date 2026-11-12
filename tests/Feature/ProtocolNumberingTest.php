<?php

namespace Tests\Feature;

use App\Protocol;
use App\Services\ProtocolNumberTaken;
use App\Services\ProtocolNumbering;
use Illuminate\Support\Facades\DB;

/** B08: number allocation is atomic and survives concurrent inserts. */
class ProtocolNumberingTest extends FeatureTestCase
{
    private function make(int $num, int $etos): Protocol
    {
        return Protocol::factory()->number($num, $etos)->create();
    }

    public function test_next_number_is_max_plus_one_per_year(): void
    {
        $this->make(7, 2026);
        $this->make(30, 2025);

        $this->assertSame(8, app(ProtocolNumbering::class)->next(2026));
        $this->assertSame(1, app(ProtocolNumbering::class)->next(2027));
    }

    public function test_duplicate_from_a_concurrent_insert_is_retried_with_next_number(): void
    {
        $this->make(10, 2026);
        $this->make(11, 2026);   // stored by "another request"
        // first read is stale (saw max = 10, as if before the other insert); later reads are correct
        $numbering = new class extends ProtocolNumbering {
            public int $reads = 0;
            public function next(int $etos): int { return ++$this->reads === 1 ? 11 : parent::next($etos); }
        };

        $p = $numbering->create(2026, null, fn (int $num) => Protocol::factory()->number($num, 2026)->create(['thema' => 'δικό μου']));

        $this->assertSame(12, (int) $p->protocolnum);
        $this->assertSame(2, $numbering->reads, 'retried once after the duplicate');
        $this->assertSame(1, Protocol::where('thema', 'δικό μου')->count());
    }

    public function test_manual_number_taken_at_save_time_raises_specific_exception(): void
    {
        $this->make(10, 2026);

        $this->expectException(ProtocolNumberTaken::class);
        app(ProtocolNumbering::class)->create(2026, 10, fn (int $n) => Protocol::factory()->number($n, 2026)->create());
    }

    public function test_lock_is_held_during_allocation_and_released_after(): void
    {
        $heldInside = null;
        app(ProtocolNumbering::class)->create(2026, null, function (int $n) use (&$heldInside) {
            $heldInside = (int) DB::selectOne('SELECT IS_USED_LOCK(?) IS NOT NULL AS u', [ProtocolNumbering::LOCK])->u;
            return Protocol::factory()->number($n, 2026)->create();
        });
        $freeAfter = (int) DB::selectOne('SELECT IS_FREE_LOCK(?) AS f', [ProtocolNumbering::LOCK])->f;

        $this->assertSame(1, $heldInside);
        $this->assertSame(1, $freeAfter);
    }

    public function test_store_with_manual_number_taken_returns_to_form_with_input(): void
    {
        $this->setting('safeNewProtocolNum', 0);
        $this->make(5, (int) now()->year);

        $this->actingAs($this->writer())->from('/home')
            ->post('/home', $this->protocolForm(['protocolnum' => 5, 'thema' => 'κρατήστε με']))
            ->assertRedirect('/home')
            ->assertSessionHasErrors('protocolnum')
            ->assertSessionHasInput('thema', 'κρατήστε με');
    }
}
