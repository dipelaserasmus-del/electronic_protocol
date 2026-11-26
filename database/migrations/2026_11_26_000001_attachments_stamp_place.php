<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R4n (owner, 2026-11-26): where the stamp goes on this file: top-left, top-center or top-right, chosen by a user in the
 * preview when the usual place hides something. Null: the stamp's own place (Ρυθμίσεις → Σφραγίδα).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attachments', 'stamp_place')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->string('stamp_place', 16)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attachments', 'stamp_place')) {
            Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('stamp_place'));
        }
    }
};
