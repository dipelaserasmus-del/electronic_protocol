<?php

namespace Tests\Feature;

use App\Services\DatabaseBackup;
use Illuminate\Support\Facades\Storage;

/**
 * B10: Ρυθμίσεις → Backup produced empty files and still reported success
 * (`mysqldump … | gzip` checked only gzip's exit code; the dump itself failed).
 */
class BackupTest extends FeatureTestCase
{
    private function backupFiles(): array
    {
        return Storage::disk('local')->files(DatabaseBackup::DIR);
    }

    public function test_backup_creates_a_complete_compressed_dump(): void
    {
        $this->setting('mysqldumpPath', '/usr/bin/mysqldump');

        $this->actingAs($this->admin())->from('/backups')->get('/backup')
            ->assertRedirect('/backups')
            ->assertSessionHas('notification.alert-type', 'success');

        $files = $this->backupFiles();
        $this->assertCount(1, $files);
        $this->assertMatchesRegularExpression('/^testing_back_\d{14}\.sql\.gz$/', basename($files[0]));
        $sql = gzdecode(Storage::disk('local')->get($files[0]));
        $this->assertStringContainsString('CREATE TABLE `protocols`', $sql);
        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('INSERT INTO `roles`', $sql, 'committed rows are included');
        $this->assertStringContainsString('-- Dump completed', $sql);
    }

    public function test_failed_dump_reports_an_error_and_leaves_no_file(): void
    {
        config(['database.connections.mysql.password' => 'wrong-password']);

        $this->actingAs($this->admin())->from('/backups')->get('/backup')
            ->assertSessionHas('notification.alert-type', 'error');

        $this->assertSame([], $this->backupFiles());
    }

    public function test_password_with_shell_characters_is_not_a_problem(): void
    {
        // the old code put the password unquoted into a shell command; this one would have broken it
        config(['database.connections.mysql.password' => 'a b$c&d"e\\f']);

        $this->actingAs($this->admin())->get('/backup')->assertSessionHas('notification.alert-type', 'error');
        $this->assertSame([], $this->backupFiles(), 'only the login fails; nothing is run by a shell');
    }

    public function test_setting_cannot_run_another_program(): void
    {
        $this->assertSame('/usr/bin/mysqldump', (new DatabaseBackup('/bin/sh'))->binary(), 'falls back to mysqldump on PATH');
        $this->assertSame('/usr/bin/mysqldump', (new DatabaseBackup('/usr/bin/mysqldump; touch /tmp/x'))->binary());
    }

    public function test_wrong_setting_falls_back_to_mysqldump_on_path(): void
    {
        $this->setting('mysqldumpPath', 'c:\xampp\mysql\bin\mysqldump.exe');

        $this->actingAs($this->admin())->get('/backup')->assertSessionHas('notification.alert-type', 'success');
        $this->assertCount(1, $this->backupFiles());
    }

    public function test_download_and_delete_accept_only_backup_names(): void
    {
        Storage::disk('local')->put(DatabaseBackup::DIR.'/testing_back_20261116120000.sql.gz', gzencode('-- x'));
        Storage::disk('local')->put('secret.txt', 'not a backup');
        $admin = $this->admin();

        $this->actingAs($admin)->get('/downloadBackup/testing_back_20261116120000.sql.gz')
            ->assertOk()->assertHeader('Content-Type', 'application/gzip');
        $this->actingAs($admin)->get('/downloadBackup/..%2F..%2Fsecret.txt')->assertNotFound();
        $this->actingAs($admin)->get('/downloadBackup/missing_back_1.sql.gz')->assertNotFound();
        $this->actingAs($admin)->get('/deleteBackup/..%2Fsecret.txt')->assertNotFound();
        $this->assertTrue(Storage::disk('local')->exists('secret.txt'));

        $this->actingAs($admin)->get('/deleteBackup/testing_back_20261116120000.sql.gz')->assertRedirect();
        $this->assertSame([], $this->backupFiles());
    }

    public function test_backup_pages_are_for_admins_only(): void
    {
        $this->actingAs($this->writer())->get('/backup')->assertRedirect();
        $this->assertSame([], $this->backupFiles());
    }
}
