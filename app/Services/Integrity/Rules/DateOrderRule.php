<?php

namespace App\Services\Integrity\Rules;

use App\Models\Protocol;
use App\Models\User;
use App\Services\DateOrder;
use App\Services\Integrity\Finding;
use App\Services\Integrity\Rule;
use Carbon\CarbonImmutable;

/**
 * A03: protocols whose date breaks the order of the numbers (a date going backwards as the numbers go up) or is after
 * today: R2f's ⚠ of the list and the form, here for a whole range. DateOrder decides which protocols are out of order
 * (the fewest whose removal leaves the dates in order), over each numbering year (etos) of the protocols dated in the
 * range. A flagged protocol counts when it, or a protocol next to it by number, is dated in the range: a date typed a
 * year wrong (2025 for 2026) is still found among the protocols around it.
 */
final class DateOrderRule implements Rule
{
    public function __construct(private readonly DateOrder $dates) {}

    public function key(): string
    {
        return 'dates';
    }

    public function title(): string
    {
        return 'Ημερομηνίες εκτός σειράς';
    }

    public function explanation(): string
    {
        return 'Οι αριθμοί δίνονται με τη σειρά, άρα όσο ανεβαίνει ο αριθμός η ημερομηνία δεν πάει πίσω. Ένα πρωτόκολλο '
            .'με ημερομηνία που χαλά τη σειρά ή μετά τη σημερινή μάλλον γράφτηκε λάθος. Αν είναι σωστή, μένει όπως είναι.';
    }

    public function inList(): bool
    {
        return true;
    }

    public function findings(CarbonImmutable $from, CarbonImmutable $to, ?User $viewer = null): array
    {
        [$first, $last] = [$from->toDateString(), $to->toDateString()];
        $years = Protocol::whereBetween('protocoldate', [$first, $last])->distinct()->orderBy('etos')->pluck('etos');
        if ($years->isEmpty()) {
            return [];
        }

        $inRange = fn (?array $row) => $row !== null && $row['date']->toDateString() >= $first && $row['date']->toDateString() <= $last;
        $flags = $this->dates->flags($years);
        $protocols = Protocol::whereIn('id', array_keys($flags))->get()->keyBy('id');

        $findings = [];
        foreach ($flags as $id => $flag) {
            $p = $protocols->get($id);
            $own = $p ? ['date' => $p->dateOf('protocoldate')] : null;
            if ($p === null || ! ($inRange($own) || $inRange($flag['prev']) || $inRange($flag['next']))) {
                continue;
            }
            $findings[] = Finding::about($p, DateOrder::message($flag, $viewer));
        }

        return $findings;
    }
}
