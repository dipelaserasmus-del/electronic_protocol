<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R4e: a setting's value was VARCHAR(255). The stamp template (setting "stampTemplate", JSON with its lines) is
 * longer, so the value becomes TEXT. Safe to run twice (AdoptLegacySchemaTest re-runs migrations on legacy tables).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->text('value')->nullable()->change();
        });
    }

    /** Not reversible without cutting longer values; restore the backup instead. */
    public function down(): void
    {
    }
};
