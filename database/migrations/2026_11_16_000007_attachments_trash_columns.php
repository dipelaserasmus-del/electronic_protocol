<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R4b: the trash. trashed_from = where the file was before it went to trash/ (for "restore"); protocol_label = the
 * protocol it belonged to ("123/2026 · subject"), kept when the protocol itself is deleted.
 * Rows trashed before R4b keep the old shape (path still arxeio/…, file in trash/…): the code handles both.
 * trashRetentionStart (setting): the day the retention starts counting. Files already in the trash (production has
 * never emptied it) get their days from then, so nothing is deleted for good on the first day after the upgrade.
 * Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (! Schema::hasColumn('attachments', 'trashed_from')) {
                $table->string('trashed_from')->nullable()->after('savedPath');
            }
            if (! Schema::hasColumn('attachments', 'protocol_label')) {
                $table->string('protocol_label')->nullable()->after('protocol_id');
            }
        });
        if (Schema::hasTable('configs') && ! DB::table('configs')->where('key', 'trashRetentionStart')->exists()) {
            DB::table('configs')->insert(['key' => 'trashRetentionStart', 'value' => now()->toDateTimeString(), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['trashed_from', 'protocol_label']);
        });
    }
};
