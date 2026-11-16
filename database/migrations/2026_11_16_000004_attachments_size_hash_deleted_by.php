<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D03: file size, SHA-256 of the stored file (finds identical files, A05; checks a file is intact) and who moved an
 * attachment to the trash (R4b). New and changed files get them on save (Attachment model); existing files with
 * `php artisan attachments:fill-metadata` (not here: hashing thousands of files would make the deployment slow).
 */
return new class extends Migration
{
    /** Idempotent, like the other changes to legacy tables (an adopted backup may run it on a schema that has it). */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('attachments', 'size')) {
                $table->unsignedBigInteger('size')->nullable()->after('mimeType')->comment('bytes of the stored file');
            }
            if (!Schema::hasColumn('attachments', 'sha256')) {
                $table->char('sha256', 64)->nullable()->after('size')->index();
            }
            if (!Schema::hasColumn('attachments', 'deleted_by')) {
                $table->unsignedInteger('deleted_by')->nullable()->after('deleted_at')->comment('user who moved it to the trash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['sha256']);
            $table->dropColumn(['size', 'sha256', 'deleted_by']);
        });
    }
};
