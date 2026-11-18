<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D06: a folder's description ("Περιγραφή", keepvalues.describe) and remarks were VARCHAR(255). The owner will load
 * folders with full descriptions (2026-11-18), so both become TEXT (at least 16,383 characters, like D01).
 * TEXT cannot carry the old plain index on `describe`; nothing searches by it, so the index goes.
 * Safe to run twice (migrations on legacy tables are re-run by AdoptLegacySchemaTest).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (Schema::getIndexes('keepvalues') as $index) {
            if ($index['columns'] === ['describe'] && ! $index['primary']) {
                Schema::table('keepvalues', fn (Blueprint $table) => $table->dropIndex($index['name']));
            }
        }
        Schema::table('keepvalues', function (Blueprint $table) {
            $table->text('describe')->nullable()->change();
            $table->text('remarks')->nullable()->change();
        });
    }

    /** Not reversible without cutting longer text; restore the backup instead. */
    public function down(): void
    {
    }
};
