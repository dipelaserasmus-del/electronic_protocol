<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Services\Stamp\Pdf\OldStampRemover;
use App\Services\Stamp\StampedCopy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * R4g: the files saved before R4f (attachments.stamp_in_file) and their old stamp.
 *
 *   php artisan stamp:old            what there is (counts only; --list: one line per file)
 *   php artisan stamp:old --apply    PDFs: the old tool's stamp taken out (the file as stamped is kept in
 *                                    storage/app/stamp-originals/, same path); PDFs the old tool never wrote get
 *                                    the new stamp when shown. Both then follow «Με σφραγίδα» (R4f).
 * Word files (docx, odt) stay as they are: the old tool saved them again, the file before it is not there to restore.
 */
class OldStamps extends Command
{
    protected $signature = 'stamp:old {--apply : take the old stamp out of the PDFs (originals kept)} {--list : one line per file}';

    protected $description = 'Files saved with the old stamp: what there is, and (--apply) take it out of the PDFs';

    public const ORIGINALS = 'stamp-originals';

    public function handle(OldStampRemover $remover): int
    {
        $apply = (bool) $this->option('apply');
        $counts = ['removable' => 0, 'no-old-stamp' => 0, 'unknown' => 0, 'word' => 0, 'missing' => 0, 'done' => 0, 'cleared' => 0];
        Attachment::where('stamp_in_file', true)->orderBy('id')->chunkById(200, function ($chunk) use ($remover, $apply, &$counts) {
            foreach ($chunk as $a) {
                $path = $a->storedFilePath();
                $ext = strtolower(pathinfo((string) ($path ?? $a->name), PATHINFO_EXTENSION));
                if ($ext !== 'pdf') {
                    $counts['word']++;
                    $this->listLine($a, 'Word: μένει ως έχει');
                    continue;
                }
                if ($path === null) {
                    $counts['missing']++;
                    continue;
                }
                $pdf = Storage::get($path);
                $found = $remover->analyse($pdf);
                if ($found['stamped']) {
                    $counts['removable']++;
                    $this->listLine($a, 'η παλιά σφραγίδα αφαιρείται (' . $found['pages'] . ' σελ.)');
                    if ($apply && $this->unstamp($a, $path, $pdf, $remover)) {
                        $counts['done']++;
                    }
                } elseif (str_starts_with($found['reason'], 'δεν το έγραψε')) {
                    $counts['no-old-stamp']++;   // never went through the old tool: no stamp inside
                    $this->listLine($a, 'χωρίς παλιά σφραγίδα');
                    if ($apply) {
                        $a->stamp_in_file = false;
                        $a->saveQuietly();
                        $counts['cleared']++;
                    }
                } else {
                    $counts['unknown']++;
                    $this->listLine($a, 'μένει ως έχει: ' . $found['reason']);
                }
            }
        });

        $this->info('Αρχεία με την παλιά σφραγίδα (αποθηκευμένα πριν από την R4f):');
        $this->line("  PDF όπου η παλιά σφραγίδα αφαιρείται: {$counts['removable']}" . ($apply ? " (αφαιρέθηκε: {$counts['done']})" : ''));
        $this->line("  PDF χωρίς παλιά σφραγίδα: {$counts['no-old-stamp']}" . ($apply ? " (θα παίρνουν τη νέα σφραγίδα όταν ανοίγουν: {$counts['cleared']})" : ''));
        $this->line("  PDF που μένουν ως έχουν (κρυπτογραφημένα, άλλη μορφή): {$counts['unknown']}");
        $this->line("  Word (docx, odt), μένουν ως έχουν: {$counts['word']}");
        $this->line("  Χωρίς αρχείο στον δίσκο: {$counts['missing']}");
        if (! $apply && $counts['removable'] + $counts['no-old-stamp'] > 0) {
            $this->line('Για να γίνει: php artisan stamp:old --apply (τα αρχεία όπως είναι τώρα κρατιούνται στο storage/app/' . self::ORIGINALS . ').');
        }
        return self::SUCCESS;
    }

    private function unstamp(Attachment $a, string $path, string $pdf, OldStampRemover $remover): bool
    {
        try {
            $clean = $remover->remove($pdf);
        } catch (\Throwable $e) {
            report($e);
            return false;
        }
        $keep = self::ORIGINALS . '/' . $path;
        if (! Storage::exists($keep)) {
            Storage::put($keep, $pdf);   // the file as it was, before this command
        }
        Storage::put($path, $clean);
        $a->stamp_in_file = false;
        $a->save();   // size and SHA-256 of the file now stored
        StampedCopy::forget($a);
        return true;
    }

    private function listLine(Attachment $a, string $what): void
    {
        if ($this->option('list')) {
            $this->line("#{$a->id}  {$what}");
        }
    }
}
