<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * D04: the migration from integer dates (20260105) to DATE columns, on data shaped like a production backup.
 * Schema changes can't be rolled back in a transaction: no RefreshDatabase; each test leaves the schema migrated
 * and removes its rows.
 */
class DateColumnsMigrationTest extends TestCase
{
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('migrations') || ! Schema::hasTable('roles')) {
            $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->migration = require database_path('migrations/2026_11_16_000005_integer_dates_to_date_columns.php');
        $this->migration->down();   // back to the legacy integer columns
    }

    protected function tearDown(): void
    {
        DB::table('attachments')->where('name', 'like', 'D04-%')->delete();
        DB::table('protocols')->where('protocolnum', '>=', 990100)->delete();
        if (Schema::hasTable('legacy_invalid_dates')) {
            DB::table('legacy_invalid_dates')->delete();
        }
        $this->migration->up();
        parent::tearDown();
    }

    private function protocol(int $num, array $dates): int
    {
        return DB::table('protocols')->insertGetId($dates + ['user_id' => 1, 'protocolnum' => $num, 'etos' => 2026,
            'protocoldate' => 20260105, 'thema' => 'D04', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_integers_become_dates_and_invalid_values_are_kept_aside(): void
    {
        $ok = $this->protocol(990101, ['in_date' => 20260103, 'out_date' => 20260107, 'diekp_eos' => 20260201, 'diekp_date' => 20260110]);
        $bad = $this->protocol(990102, ['in_date' => 0, 'out_date' => 20260231, 'diekp_eos' => 2026015, 'diekp_date' => null]);
        $att = DB::table('attachments')->insertGetId(['protocol_id' => $ok, 'name' => 'D04-a.pdf', 'expires' => 20251301]);

        $this->migration->up();

        $row = DB::table('protocols')->find($ok);
        $this->assertSame(['2026-01-05', '2026-01-03', '2026-01-07', '2026-02-01', '2026-01-10'],
            [$row->protocoldate, $row->in_date, $row->out_date, $row->diekp_eos, $row->diekp_date]);
        $row = DB::table('protocols')->find($bad);
        $this->assertSame([null, null, null, null], [$row->in_date, $row->out_date, $row->diekp_eos, $row->diekp_date]);
        $this->assertNull(DB::table('attachments')->where('id', $att)->value('expires'));

        $kept = DB::table('legacy_invalid_dates')->orderBy('id')->get()
            ->map(fn ($r) => "$r->table_name.$r->column_name#$r->row_id=$r->old_value")->all();
        $this->assertEqualsCanonicalizing([
            "protocols.in_date#$bad=0", "protocols.out_date#$bad=20260231", "protocols.diekp_eos#$bad=2026015",
            "attachments.expires#$att=20251301",
        ], $kept, 'every invalid value is kept with its row');
    }

    public function test_invalid_registration_date_stops_before_any_change(): void
    {
        $id = $this->protocol(990103, ['protocoldate' => 20260230, 'in_date' => 0]);

        try {
            $this->migration->up();
            $this->fail('expected the migration to stop');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("protocols.id: $id", $e->getMessage());
            $this->assertStringContainsString('Nothing was changed', $e->getMessage());
        }
        $this->assertNotSame('date', Schema::getColumnType('protocols', 'protocoldate'));
        $this->assertSame(0, (int) DB::table('protocols')->where('id', $id)->value('in_date'), 'other columns untouched too');

        DB::table('protocols')->where('id', $id)->update(['protocoldate' => 20260228]);   // the owner corrects it
        $this->migration->up();
        $this->assertSame('2026-02-28', DB::table('protocols')->where('id', $id)->value('protocoldate'));
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $id = $this->protocol(990104, ['in_date' => 20260103]);
        $this->migration->up();
        $this->migration->up();
        $this->assertSame('2026-01-03', DB::table('protocols')->where('id', $id)->value('in_date'));
    }

    public function test_down_restores_integers(): void
    {
        $id = $this->protocol(990105, ['in_date' => 20260103]);
        $this->migration->up();
        $this->migration->down();
        $this->assertSame(20260103, (int) DB::table('protocols')->where('id', $id)->value('in_date'));
        $this->assertSame(20260105, (int) DB::table('protocols')->where('id', $id)->value('protocoldate'));
    }
}
