<?php

namespace App\Console\Commands;

use App\Services\Attachments\ServerFiles;
use Illuminate\Console\Command;

/**
 * D10: copy the old server's attachment files into the app's storage, by the database (see ServerFiles). Without
 * --apply it only counts. Prints counts; file names only with --list (private: for the owner's eyes).
 */
class ImportServerFiles extends Command
{
    protected $signature = 'attachments:import
        {source : the server\'s storage/app folder (with arxeio/ inside), copied as it is}
        {--apply : copy the files (otherwise only counts)}
        {--list : also list the file names (private: do not send them on)}';

    protected $description = 'Copy the old server\'s attachment files into the app, where the database says (preview unless --apply)';

    public function handle(ServerFiles $files): int
    {
        $apply = (bool) $this->option('apply');
        $r = $files->import((string) $this->argument('source'), $apply);
        if ($r['error'] !== null) {
            $this->error($r['error']);

            return self::FAILURE;
        }

        $this->line("Server files: {$r['serverFiles']} in the server's folder; {$r['rows']} attachments in the database.");
        $this->line('  '.($apply ? 'Copied:      ' : 'To copy:     ').$r['copied']);
        $this->line('  Already here (same file): '.$r['same']);
        $this->line('  Not on the server:        '.$r['missing']);
        if ($r['conflicts']) {
            $this->warn("  Another file already here, not overwritten: {$r['conflicts']}");
        }
        if ($r['failed']) {
            $this->error("  Could not copy: {$r['failed']}");
        }
        $this->line("  Server files no attachment points to (not copied): {$r['orphans']}");
        if ($r['newer']) {
            $this->line("    of them, their protocol is not in the database (saved after the export?): {$r['newer']}");
        }
        $this->line("  Other server files, not attachments (placeholders, backups, the copy tool's index): {$r['other']}");
        if ($this->option('list')) {
            foreach (['missing' => 'Not on the server', 'conflicts' => 'Another file already here', 'failed' => 'Could not copy',
                'orphans' => 'No attachment points to (← what the database has)', 'other' => 'Not attachments'] as $key => $title) {
                if ($r['lists'][$key]) {
                    $this->newLine();
                    $this->line("$title:");
                    foreach ($r['lists'][$key] as $path) {
                        $this->line("  $path");
                    }
                }
            }
        }
        if (! $apply) {
            $this->newLine();
            $this->line('Nothing was copied. To copy: php artisan attachments:import '.$this->argument('source').' --apply');
        }

        return $r['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
