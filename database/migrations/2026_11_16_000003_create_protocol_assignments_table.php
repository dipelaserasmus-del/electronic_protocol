<?php

use App\Support\Assignments;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D02: assignments were only the text "d14,e10" in protocols.diekperaiosi, searched with LIKE. This table holds one
 * row per assigned user and type, filled from the text. The text column stays (the legacy form still writes it) and
 * every save copies it here (Protocol model event + App\Support\Assignments::sync).
 *
 * user_id has no foreign key on purpose: an assignment to a user who was later deleted is kept (the text kept it too).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protocol_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('protocol_id');
            $table->unsignedInteger('user_id');
            $table->char('type', 1)->comment('d = διεκπεραίωση, e = ενημέρωση');
            $table->timestamps();
            $table->unique(['protocol_id', 'user_id', 'type']);
            $table->index(['user_id', 'type']);
            $table->foreign('protocol_id')->references('id')->on('protocols')->cascadeOnDelete();
        });

        Assignments::backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('protocol_assignments');
    }
};
