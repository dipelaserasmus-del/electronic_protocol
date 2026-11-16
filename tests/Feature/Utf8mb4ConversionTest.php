<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * D05: tables → utf8mb4. The production database (and every backup the owner loads) has utf8mb3 tables, which
 * refuse emoji. The migration must convert them without changing the stored text and keep searches the same.
 *
 * Schema changes can't be rolled back in a MySQL transaction, so this test does not use RefreshDatabase;
 * every test leaves the database fully migrated with its seed data intact.
 */
class Utf8mb4ConversionTest extends TestCase
{
    private const MIGRATION = '2026_11_16_000001_convert_tables_to_utf8mb4';

    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('migrations') || ! Schema::hasTable('roles')) {
            $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_a_new_installation_has_only_utf8mb4_tables_and_columns(): void
    {
        $this->assertSame([], $this->notUtf8mb4());
    }

    public function test_legacy_utf8mb3_tables_are_converted_keeping_their_text(): void
    {
        $this->makeLegacy();
        DB::table('keepvalues')->insert(['fakelos' => 'Φ.10.ΑΒΓ-test', 'describe' => 'Ευχαριστήριες επιστολές ΄΄ « » € ‰']);
        $this->assertNotSame([], $this->notUtf8mb4(), 'legacy-shaped database');

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertSame([], $this->notUtf8mb4());
        $this->assertSame('Ευχαριστήριες επιστολές ΄΄ « » € ‰', DB::table('keepvalues')->where('fakelos', 'Φ.10.ΑΒΓ-test')->value('describe'),
            'existing Greek text and symbols unchanged');
        $this->assertSame('Dynamic', $this->rowFormat('keepvalues'), 'old COMPACT table converted to a format that fits utf8mb4 keys');

        DB::table('keepvalues')->where('fakelos', 'Φ.10.ΑΒΓ-test')->update(['describe' => 'Με emoji 📎🎉']);
        $this->assertSame('Με emoji 📎🎉', DB::table('keepvalues')->where('fakelos', 'Φ.10.ΑΒΓ-test')->value('describe'));
        DB::table('keepvalues')->where('fakelos', 'Φ.10.ΑΒΓ-test')->delete();
    }

    public function test_searches_compare_greek_as_before(): void
    {
        DB::table('keepvalues')->insert(['fakelos' => 'Φ.99-test', 'describe' => 'Σύσκεψη Διευθυντών']);
        // the old utf8_general_ci folded case, accents and final sigma; utf8mb4_general_ci does the same
        $this->assertSame(1, DB::table('keepvalues')->where('describe', 'like', '%συσκεψη διευθυντων%')->count());
        $this->assertSame(1, DB::table('keepvalues')->where('describe', 'like', '%ΣΎΣΚΕΨΗ%')->count());
        $this->assertSame(1, DB::table('keepvalues')->where('fakelos', 'φ.99-TEST')->count(), 'case-insensitive unique key, as before');
        DB::table('keepvalues')->where('fakelos', 'Φ.99-test')->delete();
    }

    /** What a production backup looks like: utf8mb3 tables (one in the old COMPACT format), D05 not applied. */
    private function makeLegacy(): void
    {
        foreach ($this->tables() as $table) {
            DB::statement("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci");
        }
        DB::statement('ALTER TABLE keepvalues ROW_FORMAT=COMPACT');
        DB::statement(sprintf('ALTER DATABASE `%s` CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci', DB::getDatabaseName()));
        DB::table('migrations')->where('migration', self::MIGRATION)->delete();
    }

    /** @return list<string> */
    private function tables(): array
    {
        return array_map(fn ($t) => $t->name, DB::select(
            "SELECT TABLE_NAME AS name FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"));
    }

    /** Database, tables and text columns whose collation is not utf8mb4_general_ci. @return list<string> */
    private function notUtf8mb4(): array
    {
        $db = DB::selectOne('SELECT DEFAULT_COLLATION_NAME AS c FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()')->c;
        $tables = DB::select("SELECT TABLE_NAME AS t, TABLE_COLLATION AS c FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_COLLATION != 'utf8mb4_general_ci'");
        $columns = DB::select("SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) AS t, COLLATION_NAME AS c FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND COLLATION_NAME IS NOT NULL AND COLLATION_NAME != 'utf8mb4_general_ci'");

        return array_merge($db === 'utf8mb4_general_ci' ? [] : ["database: $db"],
            array_map(fn ($r) => "$r->t: $r->c", [...$tables, ...$columns]));
    }

    private function rowFormat(string $table): string
    {
        return DB::selectOne('SELECT ROW_FORMAT AS f FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table])->f;
    }
}
