<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * F07: a production backup has all tables but no `migrations` table (the installation was not made
 * with `artisan migrate`). `migrate` must then only run the newer changes instead of failing on
 * "Table 'users' already exists".
 *
 * Schema changes can't be rolled back in a MySQL transaction, so this test does not use RefreshDatabase;
 * every test leaves the database fully migrated with its seed data intact.
 */
class AdoptLegacySchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! Schema::hasTable('migrations') || ! Schema::hasTable('roles')) {
            $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_backup_without_migrations_table_is_adopted_and_then_migrated(): void
    {
        // what a production backup looks like: all tables, no migration records, pre-S00 role default
        Schema::drop('migrations');
        DB::statement("ALTER TABLE users MODIFY role_id INT UNSIGNED NOT NULL DEFAULT '1'");
        DB::statement('ALTER TABLE protocols MODIFY thema VARCHAR(255) NULL, MODIFY in_perilipsi VARCHAR(255) NULL');   // pre-D01
        DB::statement('ALTER TABLE attachments DROP INDEX attachments_sha256_index, DROP COLUMN size, DROP COLUMN sha256, DROP COLUMN deleted_by');   // pre-D03
        $users = DB::table('users')->count();

        $this->artisan('migrate:adopt-legacy')->assertSuccessful();

        $ran = DB::table('migrations')->pluck('migration')->all();
        $this->assertContains('2014_10_12_000000_create_users_table', $ran);
        $this->assertContains('2016_11_26_003423_create_protocols_table', $ran);
        $this->assertContains('2019_03_17_102513_create_sessions_table', $ran);
        $this->assertNotContains('2026_11_12_000001_users_role_default_reader', $ran, 'newer changes must still run');

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        $this->assertContains('2026_11_12_000001_users_role_default_reader', DB::table('migrations')->pluck('migration')->all());
        $readerId = DB::table('roles')->where('role', 'Αναγνώστης')->value('id');
        $default = DB::selectOne("SELECT COLUMN_DEFAULT AS d FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'role_id'")->d;
        $this->assertSame((string) $readerId, trim((string) $default, "'"));
        $this->assertSame($users, DB::table('users')->count(), 'data is kept');
        $this->assertSame('text', Schema::getColumnType('protocols', 'thema'), 'D01 applied');
        $this->assertSame('text', Schema::getColumnType('protocols', 'in_perilipsi'));
        $this->assertTrue(Schema::hasColumns('attachments', ['size', 'sha256', 'deleted_by']), 'D03 applied');
    }

    public function test_only_tables_that_exist_are_recorded(): void
    {
        Schema::drop('migrations');
        Schema::drop('sessions');   // an older installation may lack a later table

        $this->artisan('migrate:adopt-legacy')->assertSuccessful();
        $this->assertNotContains('2019_03_17_102513_create_sessions_table', DB::table('migrations')->pluck('migration')->all());

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasTable('sessions'), 'the missing table is created by migrate');
    }

    public function test_database_with_migration_records_is_left_alone(): void
    {
        $before = DB::table('migrations')->orderBy('id')->get()->toArray();

        $this->artisan('migrate:adopt-legacy')->expectsOutputToContain('nothing to adopt')->assertSuccessful();

        $this->assertEquals($before, DB::table('migrations')->orderBy('id')->get()->toArray());
    }
}
