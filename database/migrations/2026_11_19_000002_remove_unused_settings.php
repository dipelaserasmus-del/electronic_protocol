<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R6c: settings nothing reads any more (the old search page's three fields, the old user-info and autocomplete
 * options). The rebuilt settings page (App\Support\SettingsCatalog) does not show them. wideListProtocol stays while
 * the old layout exists (Z01).
 */
return new class extends Migration
{
    public const KEYS = ['searchField1', 'searchField2', 'searchField3', 'showUserInfo', 'allowListValuesMatchingInput'];

    public function up(): void
    {
        DB::table('configs')->whereIn('key', self::KEYS)->delete();
    }

    /** Nothing to restore: no code reads them. */
    public function down(): void
    {
    }
};
