<?php

namespace Tests\Feature;

use App\Models\Protocol;
use App\Support\Assignments;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * D02 backfill + R1a: the text column protocols.diekperaiosi ("d14,e10") on data shaped like a production backup,
 * copied into protocol_assignments and then dropped. Schema changes can't be rolled back in a transaction: no
 * RefreshDatabase; each test leaves the schema migrated and removes its rows.
 */
class AssignmentsColumnMigrationTest extends TestCase
{
    private object $migration;

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('migrations') || ! Schema::hasTable('roles')) {
            $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->migration = require database_path('migrations/2026_11_16_000006_drop_protocols_diekperaiosi.php');
        $this->migration->down();   // back to the text column
    }

    protected function tearDown(): void
    {
        DB::table('protocols')->where('protocolnum', '>=', 990200)->delete();
        $this->migration->up();
        parent::tearDown();
    }

    private function rows(int $num): array
    {
        $id = DB::table('protocols')->where('protocolnum', $num)->value('id');
        return DB::table('protocol_assignments')->where('protocol_id', $id)->orderBy('type')->orderBy('user_id')
            ->get()->map(fn ($a) => $a->type . $a->user_id)->all();
    }

    public function test_text_in_every_legacy_variant_is_copied_and_the_column_dropped(): void
    {
        // rows as in a production backup: written without the model (no events)
        $base = ['user_id' => 1, 'protocoldate' => '2026-01-05', 'etos' => 2026, 'thema' => 'Παλιό', 'created_at' => now(), 'updated_at' => now()];
        DB::table('protocols')->insert([
            $base + ['protocolnum' => 990201, 'diekperaiosi' => 'd3,e4,'],
            $base + ['protocolnum' => 990202, 'diekperaiosi' => ' e3 , ,e3'],
            $base + ['protocolnum' => 990203, 'diekperaiosi' => ''],
            $base + ['protocolnum' => 990204, 'diekperaiosi' => null],
            $base + ['protocolnum' => 990205, 'diekperaiosi' => 'd999999,x7'],   // user deleted since: kept; junk ignored
        ]);

        $this->assertSame(4, Assignments::backfill(2));
        $this->assertSame(0, Assignments::backfill(), 'running it again adds nothing');
        DB::table('protocols')->where('protocolnum', 990203)->update(['diekperaiosi' => 'd5']);   // changed after D02

        $this->migration->up();

        $this->assertFalse(Schema::hasColumn('protocols', 'diekperaiosi'));
        $this->assertSame(['d3', 'e4'], $this->rows(990201));
        $this->assertSame(['e3'], $this->rows(990202));
        $this->assertSame(['d5'], $this->rows(990203), 'last copy before the column goes');
        $this->assertSame([], $this->rows(990204));
        $this->assertSame(['d999999'], $this->rows(990205));
        $this->assertSame('d3,e4', Protocol::where('protocolnum', 990201)->first()->diekperaiosi);

        $this->migration->up();   // again: nothing to do
        $this->assertFalse(Schema::hasColumn('protocols', 'diekperaiosi'));
    }

    public function test_down_brings_the_text_back(): void
    {
        $this->migration->up();
        $id = DB::table('protocols')->insertGetId(['user_id' => 1, 'protocolnum' => 990210, 'protocoldate' => '2026-01-05', 'etos' => 2026]);
        DB::table('protocol_assignments')->insert([['protocol_id' => $id, 'user_id' => 9, 'type' => 'e'], ['protocol_id' => $id, 'user_id' => 2, 'type' => 'd']]);

        $this->migration->down();

        $this->assertSame('d2,e9', DB::table('protocols')->where('id', $id)->value('diekperaiosi'));
    }
}
