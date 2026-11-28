<?php

namespace App\Services\Integrity\Rules;

use App\Models\Protocol;
use App\Models\User;
use App\Services\Integrity\Finding;
use App\Services\Integrity\Rule;
use App\Services\Protocols\ProtocolWriter;
use App\Services\Search\ProtocolSearch;
use Carbon\CarbonImmutable;

/**
 * A06: protocols with no attachment (no file and no ΑΔΑ row; a deleted attachment does not count) that do not say why. A
 * protocol is left out when it has «Σχετικοί αριθμοί» (the document is in the related protocol), the word εκκρεμότητα in
 * the subject (A04: waiting for it), the «ΕΛΕΥΘΕΡΟ» placeholder (a number kept free) or no folder (owner,
 * 2026-11-18: on hold until the content reaches the department, not an error).
 */
final class MissingAttachmentRule implements Rule
{
    public function key(): string
    {
        return 'attachments';
    }

    public function title(): string
    {
        return 'Χωρίς συνημμένο';
    }

    public function explanation(): string
    {
        return 'Πρωτόκολλα χωρίς αρχείο ή ΑΔΑ, που δεν έχουν Σχετικούς αριθμούς, «εκκρεμότητα» στο θέμα, «ΕΛΕΥΘΕΡΟ» ή '
            .'κενό φάκελο (αυτά περιμένουν το περιεχόμενό τους). Ανεβάστε το έγγραφο, ή γράψτε στους Σχετικούς πού '
            .'βρίσκεται.';
    }

    public function inList(): bool
    {
        return true;
    }

    public function findings(CarbonImmutable $from, CarbonImmutable $to, ?User $viewer = null): array
    {
        return Protocol::whereBetween('protocoldate', [$from->toDateString(), $to->toDateString()])
            ->whereDoesntHave('attachments')   // soft-deleted attachments are left out by the relation
            ->where(fn ($q) => $q->whereNull('sxetiko')->orWhereRaw("TRIM(sxetiko) = ''"))
            ->where(fn ($q) => $q->whereNotNull('fakelos')->whereRaw("TRIM(fakelos) <> ''"))
            ->where(fn ($q) => $q->whereNull('thema')->orWhere('thema', 'NOT LIKE', '%'.ProtocolSearch::PENDING_WORD.'%'))
            ->orderBy('etos')->orderBy('protocolnum')->get()
            ->reject(fn (Protocol $p) => ProtocolWriter::isPlaceholder($p))
            ->map(fn (Protocol $p) => Finding::about($p, 'Δεν έχει συνημμένο αρχείο ούτε ΑΔΑ.'))
            ->values()->all();
    }
}
