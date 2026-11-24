<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Protocol;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/** D10: `attachments:import` copies the old server's files into the app by the database (used by ./dev.sh real load). */
class ServerFilesTest extends FeatureTestCase
{
    private string $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = sys_get_temp_dir().'/server-files-'.uniqid();
        File::makeDirectory($this->server.'/arxeio', 0775, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->server);
        parent::tearDown();
    }

    /** a file on the "server" (the copy of its storage/app) */
    private function onServer(string $path, ?string $content = null): void
    {
        File::ensureDirectoryExists(dirname($this->server.'/'.$path));
        file_put_contents($this->server.'/'.$path, $content ?? "%PDF $path");
    }

    private function row(string $path, int $number = 1): Attachment
    {
        $p = Protocol::factory()->number($number)->create(['fakelos' => 'Φ.12']);

        return Attachment::create(['protocol_id' => $p->id, 'name' => basename($path), 'savedPath' => $path, 'mimeType' => 'application/pdf']);
    }

    public function test_each_attachment_gets_its_file_where_its_row_says(): void
    {
        $this->onServer('arxeio/Φ.12/απόφαση.pdf', 'one');
        $this->onServer('arxeio/emails/μήνυμα.eml', 'two');
        $this->row('arxeio/Φ.12/απόφαση.pdf', 1);
        $this->row('arxeio/emails/μήνυμα.eml', 2);
        $this->row('arxeio/Φ.12/χαμένο.pdf', 3);          // the known gap: the row is there, the file is not
        $this->onServer('arxeio/Φ.12/ξεχασμένο.pdf');     // no row points to it
        $this->onServer('arxeio/backups/old_back.sql.gz');

        $this->artisan('attachments:import', ['source' => $this->server, '--apply' => true])
            ->expectsOutputToContain('Server files: 4 in the server\'s folder; 3 attachments in the database.')
            ->expectsOutputToContain('Copied:      2')
            ->expectsOutputToContain('Not on the server:        1')
            ->expectsOutputToContain('Server files no attachment points to (not copied): 1')
            ->expectsOutputToContain('Other server files, not attachments (placeholders, backups, the copy tool\'s index): 1')
            ->doesntExpectOutputToContain('ξεχασμένο')   // names only with --list
            ->assertSuccessful();

        $this->assertSame('one', Storage::get('arxeio/Φ.12/απόφαση.pdf'));
        $this->assertSame('two', Storage::get('arxeio/emails/μήνυμα.eml'));
        $this->assertFalse(Storage::exists('arxeio/Φ.12/ξεχασμένο.pdf'), 'orphans are not copied');
        $this->assertFalse(Storage::exists('arxeio/backups/old_back.sql.gz'));
        $this->assertTrue(is_file($this->server.'/arxeio/Φ.12/απόφαση.pdf'), 'the server folder is only read');
    }

    public function test_preview_copies_nothing_and_a_second_run_has_nothing_to_do(): void
    {
        $this->onServer('arxeio/Φ.12/a.pdf');
        $this->row('arxeio/Φ.12/a.pdf');

        $this->artisan('attachments:import', ['source' => $this->server])
            ->expectsOutputToContain('To copy:     1')->expectsOutputToContain('Nothing was copied')->assertSuccessful();
        $this->assertFalse(Storage::exists('arxeio/Φ.12/a.pdf'));

        $this->artisan('attachments:import', ['source' => $this->server, '--apply' => true])->assertSuccessful();
        $this->artisan('attachments:import', ['source' => $this->server, '--apply' => true])
            ->expectsOutputToContain('Copied:      0')->expectsOutputToContain('Already here (same file): 1')->assertSuccessful();
    }

    public function test_a_different_file_already_here_is_never_overwritten(): void
    {
        $this->onServer('arxeio/Φ.12/a.pdf', 'server');
        $this->row('arxeio/Φ.12/a.pdf');
        Storage::put('arxeio/Φ.12/a.pdf', 'here');

        $this->artisan('attachments:import', ['source' => $this->server, '--apply' => true])
            ->expectsOutputToContain('Another file already here, not overwritten: 1')->assertSuccessful();

        $this->assertSame('here', Storage::get('arxeio/Φ.12/a.pdf'));
    }

    public function test_the_case_of_the_letters_does_not_count_on_the_windows_server(): void
    {
        // the old app built the path from the folder as typed ("φ.12"); Windows put it in the existing Φ.12 folder
        $this->onServer('arxeio/Φ.12/Απόφαση.PDF', 'x');
        $this->row('arxeio/φ.12/Απόφαση.PDF');

        $this->artisan('attachments:import', ['source' => $this->server, '--apply' => true])
            ->expectsOutputToContain('Copied:      1')->expectsOutputToContain('no attachment points to (not copied): 0')->assertSuccessful();

        $this->assertSame('x', Storage::get('arxeio/φ.12/Απόφαση.PDF'), 'copied to the path the row says, so the app finds it on Linux');
    }

    public function test_attachments_in_the_trash_get_their_file_from_trash(): void
    {
        $this->onServer('trash/Φ.12/old.pdf', 'trashed');     // trashed before R4b: the row still says arxeio/…
        $this->onServer('arxeio/Φ.12/old.pdf', 'newer upload with the same name');
        $old = $this->row('arxeio/Φ.12/old.pdf', 1);
        DB::table('attachments')->where('id', $old->id)->update(['deleted_at' => now()]);
        $this->row('arxeio/Φ.12/old.pdf', 2);

        $this->artisan('attachments:import', ['source' => $this->server, '--apply' => true])
            ->expectsOutputToContain('Copied:      2')->assertSuccessful();

        $this->assertSame('trashed', Storage::get('trash/Φ.12/old.pdf'));
        $this->assertSame('newer upload with the same name', Storage::get('arxeio/Φ.12/old.pdf'));
        $this->assertSame('trash/Φ.12/old.pdf', Attachment::withTrashed()->find($old->id)->storedFilePath());
    }

    public function test_the_trash_comes_first_also_when_only_its_case_differs(): void
    {
        // review of D10: the row says arxeio/φ.12/…; its file went to trash/Φ.12/…; a newer upload sits at the row's path
        $this->onServer('trash/Φ.12/old.pdf', 'trashed');
        $this->onServer('arxeio/φ.12/old.pdf', 'newer upload with the same name');
        $old = $this->row('arxeio/φ.12/old.pdf');
        DB::table('attachments')->where('id', $old->id)->update(['deleted_at' => now()]);

        $this->artisan('attachments:import', ['source' => $this->server, '--apply' => true])
            ->expectsOutputToContain('Copied:      1')->assertSuccessful();

        $this->assertSame('trashed', Storage::get('trash/φ.12/old.pdf'));
        $this->assertFalse(Storage::exists('arxeio/φ.12/old.pdf'), 'the newer upload has no row here: not copied');
    }

    public function test_list_shows_the_names(): void
    {
        $this->row('arxeio/Φ.12/χαμένο.pdf');
        $this->onServer('arxeio/Φ.12/ξεχασμένο.pdf');

        $this->artisan('attachments:import', ['source' => $this->server, '--list' => true])
            ->expectsOutputToContain('arxeio/Φ.12/χαμένο.pdf')->expectsOutputToContain('arxeio/Φ.12/ξεχασμένο.pdf')->assertSuccessful();
    }

    public function test_files_that_are_not_attachments_are_counted_apart(): void
    {
        // D11: placeholders, backups, the old SQLite file, the copy tool's index, Windows' own files
        foreach (['arxeio/dummy', 'arxeio/emails/dummy', 'trash/dummy', 'arxeio/backups/x_back_1.sql', 'arxeio/protocol.sqlite',
            'arxeio/protocol.sqlite.example', '.hist/up2k.db', 'arxeio/Φ.12/.hist/index', 'arxeio/Φ.12/Thumbs.db', 'u1/temp.pdf'] as $f) {
            $this->onServer($f);
        }
        // protocols without a folder put their files in arxeio/emails/ or directly in arxeio/: those still count
        $this->onServer('arxeio/emails/7-20261101_email_1.eml');
        $this->onServer('arxeio/8-20261101_0_έγγραφο.pdf');

        $this->artisan('attachments:import', ['source' => $this->server, '--list' => true])
            ->expectsOutputToContain('Server files no attachment points to (not copied): 2')
            ->expectsOutputToContain('Other server files, not attachments (placeholders, backups, the copy tool\'s index): 10')
            ->expectsOutputToContain('Not attachments:')
            ->expectsOutputToContain('  .hist/up2k.db')
            ->assertSuccessful();
    }

    public function test_list_says_what_the_database_has_for_each_orphan(): void
    {
        // D11: a row with the same name elsewhere (the folder written two ways on the server)
        $this->onServer('arxeio/Φ.43/10-20261103_a.pdf');
        $this->onServer('arxeio/Φ43/10-20261103_a.pdf');
        $this->row('arxeio/Φ.43/10-20261103_a.pdf', 10);
        // the protocol is not in the database: saved after the export
        $this->onServer('arxeio/Φ.12/20-20261124_new.pdf');
        // the protocol is there, with other files
        $this->row('arxeio/Φ.12/30-20261113_other.pdf', 30);
        $this->onServer('arxeio/Φ.12/30-20261113_other.pdf');
        $this->onServer('arxeio/Φ.12/30-20261113_replaced.pdf');
        $this->onServer('arxeio/Φ.12/σημείωση.pdf');
        Protocol::where('protocolnum', 10)->update(['etos' => 2026]);
        Protocol::where('protocolnum', 30)->update(['etos' => 2026]);

        $this->artisan('attachments:import', ['source' => $this->server])
            ->expectsOutputToContain('Server files no attachment points to (not copied): 4')
            ->expectsOutputToContain('of them, their protocol is not in the database (saved after the export?): 1')
            ->doesntExpectOutputToContain('←')
            ->assertSuccessful();
        $this->artisan('attachments:import', ['source' => $this->server, '--list' => true])
            ->expectsOutputToContain('arxeio/Φ43/10-20261103_a.pdf  ← a row has this name at arxeio/Φ.43/10-20261103_a.pdf')
            ->expectsOutputToContain('arxeio/Φ.12/20-20261124_new.pdf  ← protocol 20/2026 is not in the database (saved after the export?)')
            ->expectsOutputToContain('arxeio/Φ.12/30-20261113_replaced.pdf  ← protocol 30/2026 (folder Φ.12) has no attachment with this name')
            ->expectsOutputToContain('arxeio/Φ.12/σημείωση.pdf  ← no protocol number in the name')
            ->assertSuccessful();
    }

    public function test_a_wrong_folder_is_refused(): void
    {
        $this->artisan('attachments:import', ['source' => $this->server.'/arxeio', '--apply' => true])
            ->expectsOutputToContain('has no arxeio folder')->assertFailed();
        $this->artisan('attachments:import', ['source' => $this->server.'/nothing'])
            ->expectsOutputToContain('is not a folder')->assertFailed();
        Storage::makeDirectory('arxeio');
        $this->artisan('attachments:import', ['source' => Storage::path(''), '--apply' => true])
            ->expectsOutputToContain("the app's own storage")->assertFailed();
    }

    public function test_load_brief_glossary_prints_counts_only(): void
    {
        $this->artisan('folders:glossary', ['--brief' => true])
            ->expectsOutputToContain('Written differently in your list (the same folder, it keeps its code):')
            ->doesntExpectOutputToContain('Φ.1.1')
            ->assertSuccessful();
    }
}
