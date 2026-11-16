<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * D03: size and SHA-256 for attachments saved before these columns existed. Safe to run again (only rows without a
 * size, unless --all). Prints counts only, never names.
 */
class FillAttachmentMetadata extends Command
{
    protected $signature = 'attachments:fill-metadata {--all : measure again also the files that already have a size}';

    protected $description = 'Record the size and SHA-256 of attachment files that do not have them yet';

    public function handle(): int
    {
        $measured = 0;
        $missing = 0;
        $query = Attachment::withTrashed()->whereNotNull('savedPath')->where('savedPath', '!=', '');
        if (!$this->option('all')) {
            $query->whereNull('size');
        }
        $total = (clone $query)->count();
        $bar = $this->output->createProgressBar($total);
        $query->orderBy('id')->chunkById(500, function ($attachments) use (&$measured, &$missing, $bar) {
            foreach ($attachments as $a) {
                if ($a->fillFileMetadata()) {
                    // straight to the table: updated_at stays as it was (this is not a change by a user)
                    DB::table('attachments')->where('id', $a->id)->update(['size' => $a->size, 'sha256' => $a->sha256]);
                    $measured++;
                } else {
                    $missing++;
                }
                $bar->advance();
            }
        });
        $bar->finish();
        $this->newLine();
        $this->line("Attachments measured: $measured; file not found: $missing.");

        return self::SUCCESS;
    }
}
