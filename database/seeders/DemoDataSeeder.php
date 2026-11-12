<?php

namespace Database\Seeders;

use App\Attachment;
use App\Keepvalue;
use App\Protocol;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Synthetic protocol book for the dev/preview stack: ~120 entries of the current year,
 * incoming/outgoing mix, folders, assignments, attachments, a few ΕΛΕΥΘΕΡΟ placeholders and
 * two deliberate gaps in the numbering, 37 and 88 (for the missing-number check, task A02).
 * Dates run from 8 January to today.
 * All content is invented.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Protocol::count() > 0) {
            $this->command?->info('Protocols exist, demo data skipped.');
            return;
        }
        fake()->seed(2026);
        $year = (int) date('Y');
        $writer = User::whereUsername('writer')->first();
        $reader = User::whereUsername('reader')->first();
        $users = User::all();
        $folders = Keepvalue::inRandomOrder()->limit(12)->pluck('fakelos')->all();
        $gaps = [37, 88];

        $start = new \DateTime("$year-01-08");
        $span = max(0, (int) $start->diff(new \DateTime())->days);
        for ($n = 1; $n <= 120; $n++) {
            // dates spread evenly from 8 January to today, never decreasing
            $day = (clone $start)->modify('+' . intdiv(($n - 1) * $span, 119) . ' days');
            if (in_array($n, $gaps, true)) continue;

            $factory = Protocol::factory()->number($n, $year)->onDate($day->format('Ymd'))
                ->state(['user_id' => $users->random()->id, 'fakelos' => fake()->randomElement($folders)]);
            $factory = $n % 3 === 0 ? $factory->outgoing() : $factory->incoming();
            if ($n % 5 === 0 && $writer) $factory = $factory->assignedTo($writer, $n % 10 === 0 ? $reader : null);
            if (in_array($n, [15, 64], true)) $factory = $factory->placeholder();

            $p = $factory->create();

            if ($n % 2 === 0 && $p->thema !== 'ΕΛΕΥΘΕΡΟ') {
                $name = 'Έγγραφο_' . $n . '.pdf';
                $path = "arxeio/{$p->fakelos}/{$n}-{$p->protocoldate}_$name";
                Storage::put($path, "%PDF-1.4\n% synthetic attachment for protocol $n\n");
                Attachment::create(['protocol_id' => $p->id, 'name' => $name, 'mimeType' => 'application/pdf', 'savedPath' => $path]);
            }
        }
    }
}
