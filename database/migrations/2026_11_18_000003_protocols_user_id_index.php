<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * R6a: the users page counts each user's protocols (protocols.user_id had no index: a full scan per count;
 * review of R6a). Safe to run twice and on legacy databases that may have the index already.
 */
return new class extends Migration
{
    public function up(): void
    {
        $has = collect(Schema::getIndexes('protocols'))->contains(fn ($i) => ($i['columns'][0] ?? null) === 'user_id');
        if (! $has) {
            Schema::table('protocols', fn (Blueprint $table) => $table->index('user_id', 'protocols_user_id_index'));
        }
    }

    public function down(): void
    {
        if (collect(Schema::getIndexes('protocols'))->contains(fn ($i) => $i['name'] === 'protocols_user_id_index')) {
            Schema::table('protocols', fn (Blueprint $table) => $table->dropIndex('protocols_user_id_index'));
        }
    }
};
