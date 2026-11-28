<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * X01: the audit log («who did what and when»). Append-only: the application only inserts; rows leave it only by the
 * retention (AUDIT_KEEP_DAYS, App\Services\Audit\AuditLog::purgeExpired). No foreign keys: an entry stays when its
 * user or protocol is deleted, with the user's name as it was.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_log')) {
            return;
        }
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('username')->nullable();       // as it was then (the user may be renamed or deleted)
            $table->string('action', 40)->index();        // created, updated, trashed, restored, purged, deleted, login …
            $table->string('entity_type', 40)->nullable(); // protocol, attachment, assignment, user, folder, setting …
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('protocol_id')->nullable()->index();   // the protocol it is about (X02's «Ιστορικό»)
            $table->json('changes')->nullable();          // field => [old, new]
            $table->json('context')->nullable();          // what else helps to read it (a number, a file name, a key)
            $table->string('ip', 45)->nullable();
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
