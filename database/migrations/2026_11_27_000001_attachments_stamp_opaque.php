<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R4p (owner, 2026-11-27): the stamp on this file drawn opaque (all of it on white, so nothing under it shows through),
 * chosen by a user in the preview. False: as it is (see-through, the number and date lines on white: R4k).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attachments', 'stamp_opaque')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->boolean('stamp_opaque')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attachments', 'stamp_opaque')) {
            Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('stamp_opaque'));
        }
    }
};
