<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * R4f: files are stored without a stamp and stamped when viewed / downloaded / printed. Files saved before carry the
 * stamp inside them (when the setting was on): stamp_in_file = 1 for every existing PDF, docx and odt, so they are
 * never stamped twice (shown as stored, with a note). Safe to run twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attachments', 'stamp_in_file')) {
            Schema::table('attachments', function (Blueprint $table) {
                $table->boolean('stamp_in_file')->default(false);
            });
            DB::table('attachments')
                ->where(fn ($q) => $q->where('name', 'like', '%.pdf')->orWhere('name', 'like', '%.docx')->orWhere('name', 'like', '%.odt')
                    ->orWhere('savedPath', 'like', '%.pdf')->orWhere('savedPath', 'like', '%.docx')->orWhere('savedPath', 'like', '%.odt'))
                ->update(['stamp_in_file' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attachments', 'stamp_in_file')) {
            Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('stamp_in_file'));
        }
    }
};
