<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R4h: a deleted protocol goes to the trash: its row and assignments as they were (data, JSON), the number, year,
 * date, subject, folder and who registered it (to list, search and decide who may restore it), and who deleted it and
 * when. The protocols row goes, so the number is free again. Its attachments stay in the attachments' trash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trashed_protocols')) {
            return;
        }
        Schema::create('trashed_protocols', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('protocol_id')->unique();   // the deleted protocol's id (given back on restore)
            $table->unsignedInteger('protocolnum');
            $table->unsignedSmallInteger('etos');
            $table->date('protocoldate')->nullable();
            $table->text('thema')->nullable();
            $table->string('fakelos')->nullable();
            $table->unsignedInteger('user_id')->nullable()->index();   // who registered it
            $table->longText('data');
            $table->unsignedInteger('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trashed_protocols');
    }
};
