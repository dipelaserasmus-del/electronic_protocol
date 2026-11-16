<?php

use App\Support\Assignments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R1a: assignments live only in protocol_assignments (D02). The text column protocols.diekperaiosi ("d14,e10"),
 * which D02 kept in step for the old form, is dropped after one last copy into the table (rows already there are
 * kept; nothing is lost if the text gained a user since D02). The legacy screens read and write the same text form
 * through Protocol::$diekperaiosi, which now uses the table.
 *
 * Safe to run again (no column → nothing to do). down() brings the column back, filled from the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('protocols', 'diekperaiosi')) {
            return;
        }
        Assignments::backfill();
        Schema::table('protocols', fn ($table) => $table->dropColumn('diekperaiosi'));
    }

    public function down(): void
    {
        if (Schema::hasColumn('protocols', 'diekperaiosi')) {
            return;
        }
        Schema::table('protocols', fn ($table) => $table->string('diekperaiosi')->nullable()->after('in_paraliptis'));
        DB::table('protocol_assignments')->orderBy('protocol_id')->get()->groupBy('protocol_id')
            ->each(fn ($rows, $id) => DB::table('protocols')->where('id', $id)->update([
                'diekperaiosi' => Assignments::asText($rows->map(fn ($r) => ['user_id' => $r->user_id, 'type' => $r->type])),
            ]));
    }
};
