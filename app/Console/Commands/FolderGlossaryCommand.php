<?php

namespace App\Console\Commands;

use App\Models\Keepvalue;
use App\Services\Folders\FolderCatalog;
use App\Services\Folders\FolderGlossary;
use App\Services\Folders\FolderMerge;
use Illuminate\Console\Command;

/**
 * D07: apply the owner's folder glossary (database/data/folders.json) to the folders list. Without --apply it only
 * shows what would change. Prints folder codes and counts, never protocol contents.
 */
class FolderGlossaryCommand extends Command
{
    protected $signature = 'folders:glossary
        {--apply : write the descriptions and apply the merges}
        {--docs : write docs/FOLDERS.md from the glossary (for developers)}';

    protected $description = 'Folder glossary: full descriptions and merges from database/data/folders.json (preview unless --apply)';

    public function handle(FolderCatalog $catalog, FolderMerge $merge): int
    {
        $glossary = FolderGlossary::load();
        if ($this->option('docs')) {
            file_put_contents(base_path('docs/FOLDERS.md'), FolderGlossary::markdown($glossary));
            $this->line('Wrote docs/FOLDERS.md');

            return self::SUCCESS;
        }

        ['records' => $records, 'respelled' => $respelled] = FolderGlossary::records($glossary);
        $rows = collect($catalog->parseRecords($records));
        $by = fn (string $status) => $rows->where('status', $status)->pluck('fakelos')->all();
        $listed = array_map([$catalog, 'matchKey'], $rows->pluck('fakelos')->all());
        $untouched = Keepvalue::pluck('fakelos')->reject(fn ($c) => in_array($catalog->matchKey($c), $listed, true))->values()->all();

        $this->line('Glossary: '.count($glossary['folders']).' folders.');
        $this->line('  New:       '.$this->codes($by('new')));
        $this->line('  Changed:   '.$this->codes($by('update')));
        $this->line('  Unchanged: '.count($by('same')));
        foreach ($respelled as $from => $to) {
            $this->line("  $from is written $to in your list: that folder is updated.");
        }
        $this->line('  Not in the glossary, kept as they are: '.$this->codes($untouched));
        foreach ($rows->where('status', 'error') as $row) {
            $this->error("  {$row['fakelos']}: {$row['error']}");
        }

        $glossaryCodes = array_map(fn ($f) => mb_strtolower($f['code']), $glossary['folders']);
        foreach ($glossary['merges'] as $m) {
            $p = $merge->plan($m['from'], $m['into']);
            $addedFirst = ! $p['intoListed'] && in_array(mb_strtolower($p['into']), $glossaryCodes, true);
            $this->line("Merge {$p['from']} → {$p['into']}: ".match (true) {
                $p['error'] !== null && ! $addedFirst => 'ERROR: '.$p['error'],
                $p['done'] => 'already done.',
                default => "{$p['protocols']} protocols, {$p['files']} attachment files, {$p['rules']} automatic assignments"
                    .($addedFirst ? " ({$p['into']} is added first)" : '')
                    .($p['fromListed'] ? "; {$p['from']} is then removed from the list." : '.'),
            });
            if ($p['subfolders']) {
                $this->warn("  Sub-folders of {$p['from']} are not merged (they stay as they are): ".implode(', ', $p['subfolders']));
            }
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->line('Nothing was changed. To apply: php artisan folders:glossary --apply');

            return self::SUCCESS;
        }
        if ($rows->where('status', 'error')->isNotEmpty()) {
            $this->error('Not applied: fix the errors above first.');

            return self::FAILURE;
        }

        $done = $catalog->importRecords($records);
        $this->info("Folders: {$done['created']} added, {$done['updated']} updated.");
        foreach ($glossary['merges'] as $m) {
            $p = $merge->plan($m['from'], $m['into']);   // again: the import may have added the target folder
            if ($p['error'] !== null) {
                $this->error("Merge {$m['from']} → {$m['into']} not applied: {$p['error']}");

                return self::FAILURE;
            }
            $r = $merge->apply($m['from'], $m['into']);
            $this->info("Merge {$m['from']} → {$m['into']}: {$r['protocols']} protocols; files moved {$r['moved']}, renamed {$r['renamed']}"
                ." (name taken), not found {$r['missing']}, could not move {$r['failed']}; automatic assignments added {$r['rules']}.");
        }

        return self::SUCCESS;
    }

    private function codes(array $codes): string
    {
        return $codes === [] ? '—' : count($codes).' ('.implode(', ', $codes).')';
    }
}
