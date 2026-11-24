<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N05: each user's own notepad (one note per user, private: administrators do not see it either). version grows with
 * every save, so a second tab with an older copy is told instead of overwriting it silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_notes')) {
            return;
        }
        Schema::create('user_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();   // users.id is INT on installations from 2016: no foreign key
            $table->longText('body')->nullable();
            $table->unsignedInteger('version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notes');
    }
};
