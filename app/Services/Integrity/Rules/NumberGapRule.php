<?php

namespace App\Services\Integrity\Rules;

use App\Models\Protocol;
use App\Services\Integrity\Finding;
use App\Services\Integrity\Rule;
use App\Services\NumberGaps;
use Carbon\CarbonImmutable;

/**
 * A01 (absorbs A02's rule): numbers missing between the protocols of the range, one finding per run of missing
 * numbers, with the protocols on either side. The protocol list keeps its own notice and «ΚΕΝΟ» rows (A02, R2c).
 */
final class NumberGapRule implements Rule
{
    public function __construct(private readonly NumberGaps $gaps) {}

    public function key(): string
    {
        return 'gaps';
    }

    public function title(): string
    {
        return 'Κενά στην αρίθμηση';
    }

    public function explanation(): string
    {
        return 'Αριθμοί που λείπουν ανάμεσα στα πρωτόκολλα της περιόδου (π.χ. υπάρχουν το 87 και το 89, όχι το 88). '
            .'Ένα κενό μένει όταν διαγράφεται πρωτόκολλο ή όταν γράφεται αριθμός με το χέρι.';
    }

    public function inList(): bool
    {
        return false;
    }

    public function findings(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $findings = [];
        foreach (NumberGaps::runs($this->gaps->between($from, $to)) as $run) {
            $count = $run['to'] - $run['from'] + 1;
            $before = Protocol::whereEtos($run['year'])->where('protocolnum', $run['from'] - 1)->first();
            $after = Protocol::whereEtos($run['year'])->where('protocolnum', $run['to'] + 1)->first();
            $side = fn (?Protocol $p) => $p ? $p->protocolnum.' ('.$p->dateOf('protocoldate')?->format('d/m/Y').')' : null;
            $around = match (true) {
                $before && $after => ', ανάμεσα στο '.$side($before).' και στο '.$side($after),
                $after !== null => ', πριν από το '.$side($after),
                $before !== null => ', μετά το '.$side($before),
                default => '',
            };
            $message = ($count === 1 ? 'Λείπει 1 αριθμός' : "Λείπουν {$count} αριθμοί").$around.'.';
            $ref = ($count === 1 ? $run['from'] : $run['from'].'–'.$run['to']).'/'.$run['year'];
            $findings[] = new Finding($message, $ref, date: ($before ?? $after)?->dateOf('protocoldate'), meta: $run);
        }

        return $findings;
    }
}
