<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R6b: automatic assignment by folder. A protocol saved in this folder is assigned to these users, for
 * διεκπεραίωση (type "d") or ενημέρωση (type "e"), as protocol_assignments are (D02). Applied on save (R1d).
 * Rows go with their folder (foreign key). user_id has no foreign key: users.id is INT on installations from 2016 and
 * BIGINT on new ones; UserAccounts::delete removes a deleted user's rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('keepvalue_id');
            $table->unsignedBigInteger('user_id');
            $table->char('type', 1)->comment('d = διεκπεραίωση, e = ενημέρωση');
            $table->timestamps();
            $table->unique(['keepvalue_id', 'user_id', 'type']);
            $table->foreign('keepvalue_id')->references('id')->on('keepvalues')->cascadeOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('folder_assignments');
    }
};
