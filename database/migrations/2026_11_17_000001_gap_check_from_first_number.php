<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R2c (owner, 2026-11-17): the missing-number check starts at "Αρ.Πρωτοκόλλου για ξεκίνημα" (firstProtocolNum), so the
 * separate setting gapCheckFromNumber goes. A value set there moves to firstProtocolNum (which otherwise only gives the
 * first number of an empty book). Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('configs')) {
            return;
        }
        $from = DB::table('configs')->where('key', 'gapCheckFromNumber')->value('value');
        if (is_string($from) && ctype_digit(trim($from)) && (int) $from > 0) {
            DB::table('configs')->updateOrInsert(['key' => 'firstProtocolNum'], ['value' => (string) (int) $from, 'updated_at' => now()]);
        }
        DB::table('configs')->where('key', 'gapCheckFromNumber')->delete();
    }

    public function down(): void
    {
        // nothing to restore: firstProtocolNum keeps the value
    }
};
