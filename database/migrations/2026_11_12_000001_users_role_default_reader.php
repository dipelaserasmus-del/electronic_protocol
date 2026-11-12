<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Security fix S00: users.role_id defaulted to 1 (= Διαχειριστής), so any user row created
 * without an explicit role became an administrator. New default: the least-privileged role
 * (Αναγνώστης). Existing users are not changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        $readerId = DB::table('roles')->where('role', 'Αναγνώστης')->value('id') ?? 4;
        Schema::table('users', function (Blueprint $table) use ($readerId) {
            $table->integer('role_id')->unsigned()->default($readerId)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('role_id')->unsigned()->default(1)->change();
        });
    }
};
