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
    /** R6c: "Νέο αντίγραφο" on the backups page (was GET /backup); the message's type (success / error) */
    private function createBackup(string $expected): void
    {
        \Livewire\Livewire::actingAs($this->admin())->test(\App\Livewire\BackupPage::class)->call('create')
            ->assertDispatched('toast', type: $expected);
    }

    private function backupFiles(): array
    {
        return Storage::disk('local')->files(DatabaseBackup::DIR);
    }

    public function test_backup_creates_a_complete_compressed_dump(): void
    {
        $this->setting('mysqldumpPath', '/usr/bin/mysqldump');

        $this->createBackup('success');

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

        $this->createBackup('error');

        $this->assertSame([], $this->backupFiles());
    }

    public function test_password_with_shell_characters_is_not_a_problem(): void
    {
        // the old code put the password unquoted into a shell command; this one would have broken it
        config(['database.connections.mysql.password' => 'a b$c&d"e\\f']);

        $this->createBackup('error');
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

        $this->createBackup('success');
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
        $page = \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\BackupPage::class)->assertSee('testing_back_20261116120000.sql.gz');
        $page->call('delete', '../secret.txt')->assertNotFound();
        $this->assertTrue(Storage::disk('local')->exists('secret.txt'));
        $this->actingAs($admin)->get('/deleteBackup/testing_back_20261116120000.sql.gz')->assertNotFound();   // R6c: no GET delete

        \Livewire\Livewire::actingAs($admin)->test(\App\Livewire\BackupPage::class)->call('delete', 'testing_back_20261116120000.sql.gz')
            ->assertDontSee('testing_back_20261116120000.sql.gz');
        $this->assertSame([], $this->backupFiles());
    }

    /** Codex review on #25: a full disk made gzwrite() fail silently and the backup was still reported as created. */
    public function test_compression_to_a_full_disk_fails_instead_of_reporting_success(): void
    {
        if (! is_writable('/dev/full')) {
            $this->markTestSkipped('/dev/full is not available here');
        }
        $source = tempnam(sys_get_temp_dir(), 'dump');
        file_put_contents($source, str_repeat("INSERT INTO t VALUES (1);\n", 50000)."-- Dump completed\n");
        try {
            $this->expectException(\RuntimeException::class);
            (new DatabaseBackup)->gzip($source, '/dev/full');
        } finally {
            unlink($source);
        }
    }

    public function test_compression_of_a_multi_megabyte_dump_is_verified_and_kept(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'dump');
        $target = $source.'.gz';
        $lines = '';
        for ($i = 0; $i < 60000; $i++) {
            $lines .= "INSERT INTO t VALUES ($i, '".md5((string) $i)."');\n";   // ~3 MB, several 1 MB chunks
        }
        file_put_contents($source, $lines);
        try {
            (new DatabaseBackup)->gzip($source, $target);
            $this->assertSame($lines, gzdecode(file_get_contents($target)));
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    public function test_backup_pages_are_for_admins_only(): void
    {
        $this->actingAs($this->writer())->get('/backups')->assertRedirect();
        \Livewire\Livewire::actingAs($this->writer())->test(\App\Livewire\BackupPage::class)->assertForbidden();
        $this->actingAs($this->admin())->get('/backup')->assertNotFound();   // R6c: creating is an action of the page, not a link
        $this->assertSame([], $this->backupFiles());
    }
}
