<?php

namespace App\Services\Integrity\Rules;

use App\Models\Protocol;
use App\Models\User;
use App\Services\Integrity\Finding;
use App\Services\Integrity\Rule;
use App\Services\Search\ProtocolSearch;
use Carbon\CarbonImmutable;

/**
 * A04: the pending protocols of the range, as the search page's «Έλεγχος εκκρεμοτήτων» (R3a) finds them: the word
 * εκκρεμότητα in the subject (any case or accents, also ΕΚΚΡΕΜΟΤΗΤΕΣ: MySQL's utf8mb4_general_ci, D05) or no folder.
 * Owner (2026-11-18): a protocol without a folder is on hold until its content reaches the department, not an error;
 * so this is a reminder of what is still open, and the list's «Έλεγχοι» line does not count it (the list flags a
 * missing folder itself, R2n).
 */
final class PendingRule implements Rule
{
    public function key(): string
    {
        return 'pending';
    }

    public function title(): string
    {
        return 'Εκκρεμότητες';
    }

    public function explanation(): string
    {
        return 'Πρωτόκολλα σε αναμονή: με τη λέξη «εκκρεμότητα» στο θέμα ή χωρίς φάκελο, όπως τα βρίσκει ο «Έλεγχος '
            .'εκκρεμοτήτων» της αναζήτησης. Όταν έρθει το περιεχόμενο, συμπληρώστε το πρωτόκολλο.';
    }

    public function inList(): bool
    {
        return false;
    }

    public function findings(CarbonImmutable $from, CarbonImmutable $to, ?User $viewer = null): array
    {
        return Protocol::whereBetween('protocoldate', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($w) => $w->whereNull('fakelos')->orWhere('fakelos', '')->orWhere('thema', 'LIKE', '%'.ProtocolSearch::PENDING_WORD.'%'))
            ->orderBy('etos')->orderBy('protocolnum')->get()
            ->map(fn (Protocol $p) => Finding::about($p, self::message(ProtocolSearch::pendingReasons($p))))
            ->all();
    }

    /** @param  list<string>  $reasons  ProtocolSearch::pendingReasons() */
    private static function message(array $reasons): string
    {
        // MySQL matched the subject; PHP's fold (TagList) is the same comparison, so an empty list does not happen
        return implode(' ', array_map(fn (string $why) => $why === 'folder' ? 'Χωρίς φάκελο.' : 'Εκκρεμότητα στο θέμα.', $reasons ?: ['subject']));
    }
}
