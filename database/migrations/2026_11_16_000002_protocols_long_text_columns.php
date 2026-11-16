<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D01: subject (θέμα), sender (αρχή έκδοσης) and both summaries were VARCHAR(255). An e-mail whose subject is longer
 * (owner's example, 2026-11-16) could not be registered: MySQL refused it ("Data too long for column 'thema'").
 * TEXT holds 65,535 bytes, at least 16,383 characters (Protocol::LONG_TEXT_MAX leaves room for 4-byte characters).
 * None of these columns is indexed, so searches work as before.
 */
return new class extends Migration
{
    private const COLUMNS = ['thema', 'in_arxi_ekdosis', 'in_perilipsi', 'out_perilipsi'];

    public function up(): void
    {
        Schema::table('protocols', function (Blueprint $table) {
            foreach (self::COLUMNS as $column) {
                $table->text($column)->nullable()->change();
            }
        });
    }

    /** Not reversible without cutting text longer than 255 characters; restore the backup instead. */
    public function down(): void
    {
    }
};
