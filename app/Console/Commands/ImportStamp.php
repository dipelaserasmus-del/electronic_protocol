<?php

namespace App\Console\Commands;

use App\Services\Stamp\StampRenderer;
use App\Services\Stamp\StampTemplate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * R4e: load a stamp made outside the application: <dir>/template.json (StampTemplate's fields) and, if there,
 * <dir>/emblem.png. The owner keeps the official stamp in local-data/stamp/ (never committed) and runs:
 *   php artisan stamp:import local-data/stamp
 * --preview writes the drawing to <dir>/preview.png instead of saving it.
 */
class ImportStamp extends Command
{
    protected $signature = 'stamp:import {dir : folder with template.json and emblem.png} {--preview : only draw it, to <dir>/preview.png}';

    protected $description = 'Load the stamp (template.json + emblem.png) from a folder';

    public function handle(): int
    {
        $dir = rtrim((string) $this->argument('dir'), '/');
        $json = @file_get_contents("$dir/template.json");
        $data = $json === false ? null : json_decode($json, true);
        if (! is_array($data)) {
            $this->error("Δεν βρέθηκε ή δεν διαβάζεται το $dir/template.json.");
            return self::FAILURE;
        }
        $template = StampTemplate::fromArray($data);
        $emblem = is_file("$dir/emblem.png") ? "$dir/emblem.png" : null;
        if ($emblem) {
            $info = @getimagesize($emblem);
            if (! $info || $info[2] !== IMAGETYPE_PNG || $info[0] * $info[1] > StampRenderer::MAX_EMBLEM_PIXELS) {
                $this->error("Το $emblem δεν είναι εικόνα PNG έως 6000 × 6000.");
                return self::FAILURE;
            }
            $template->emblemFile = $emblem;
        }
        $template->emblem = $template->emblem && $emblem !== null;

        if ($this->option('preview')) {
            $png = (new StampRenderer())->png($template, StampTemplate::sampleValues(), 300);
            file_put_contents("$dir/preview.png", $png);
            $this->info("Σχέδιο: $dir/preview.png (300 dpi). Δεν αποθηκεύτηκε τίποτα.");
            return self::SUCCESS;
        }
        if ($emblem) {
            Storage::put(StampTemplate::EMBLEM, (string) file_get_contents($emblem));
        } else {
            Storage::delete(StampTemplate::EMBLEM);
        }
        $template->emblemFile = null;
        $template->save();
        $this->info('Η σφραγίδα φορτώθηκε: ' . count($template->lines) . ' γραμμές' . ($emblem ? ', με έμβλημα' : '') . '.');
        return self::SUCCESS;
    }
}
