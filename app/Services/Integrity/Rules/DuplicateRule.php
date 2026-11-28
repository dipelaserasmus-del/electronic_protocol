<?php

namespace App\Services\Integrity\Rules;

use App\Models\Attachment;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\Integrity\Finding;
use App\Services\Integrity\Rule;
use App\Services\Protocols\ProtocolWriter;
use App\Services\Protocols\RelatedNumbers;
use App\Support\TagList;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * A05: two protocols that look like the same document registered twice, and do not name each other in their
 * «Σχετικοί αριθμοί» (a protocol that says it relates to the other is a reply or a follow-up, not a duplicate).
 *
 * Alike when they have
 *  - the same attached file (D03's SHA-256);
 *  - the same incoming document number (Αριθ. εισερχομένου), unless both senders are written and differ;
 *  - the same subject, sender and incoming date (all three written; not a «ΕΛΕΥΘΕΡΟ» placeholder).
 * A value shared by more than MAX_SHARED protocols says nothing (a logo or an e-mail signature picture, a number or a
 * subject that many routine protocols repeat), so it is not compared.
 * Case, accents and spaces around do not matter. One of the two is dated in the range; the other is in the same
 * numbering year or the one before, dated anywhere.
 * The finding is about the later one (by year and number: most likely the second registration).
 */
final class DuplicateRule implements Rule
{
    /** A file, number or subject in more protocols than this is routine (a logo, a signature), not a duplicate */
    public const MAX_SHARED = 3;

    private const COLUMNS = ['id', 'user_id', 'protocolnum', 'etos', 'protocoldate', 'thema', 'in_num', 'in_date', 'in_arxi_ekdosis', 'sxetiko'];

    private const WHY = [
        'file' => 'ίδιο συνημμένο αρχείο',
        'in_num' => 'ίδιος αριθμός εισερχομένου',
        'subject' => 'ίδιο θέμα, αποστολέας και ημερομηνία εισερχομένου',
    ];

    public function key(): string
    {
        return 'duplicates';
    }

    public function title(): string
    {
        return 'Πιθανές διπλές καταχωρίσεις';
    }

    public function explanation(): string
    {
        return 'Δύο πρωτόκολλα με το ίδιο συνημμένο αρχείο, τον ίδιο αριθμό εισερχομένου, ή το ίδιο θέμα, αποστολέα και '
            .'ημερομηνία εισερχομένου, που δεν αναφέρουν το ένα το άλλο στους «Σχετικούς αριθμούς». Αν δεν είναι '
            .'διπλά, γράψτε τον αριθμό του ενός στους Σχετικούς του άλλου.';
    }

    public function inList(): bool
    {
        return true;
    }

    public function findings(CarbonImmutable $from, CarbonImmutable $to, ?User $viewer = null): array
    {
        $base = Protocol::whereBetween('protocoldate', [$from->toDateString(), $to->toDateString()])->get(self::COLUMNS)->keyBy('id');
        if ($base->isEmpty()) {
            return [];
        }

        // the other one is looked for in the same numbering years and the one before (a document registered twice is
        // registered days apart, not years): the list runs this on every load, so it must not read the whole book
        $years = [(int) $base->min('etos') - 1, (int) $base->max('etos')];
        $near = fn () => Protocol::whereBetween('etos', $years);

        /** @var array<string, array{0: Protocol, 1: Protocol, why: list<string>}> $pairs  by "lowId-highId" */
        $pairs = [];
        $add = function (Protocol $a, Protocol $b, string $why) use (&$pairs) {
            if ($a->id === $b->id || self::related($a, $b)) {
                return;
            }
            [$first, $later] = [$a->etos, $a->protocolnum] <= [$b->etos, $b->protocolnum] ? [$a, $b] : [$b, $a];
            $key = min($a->id, $b->id).'-'.max($a->id, $b->id);
            $pairs[$key] ??= [$first, $later, 'why' => []];
            if (! in_array($why, $pairs[$key]['why'], true)) {
                $pairs[$key]['why'][] = $why;
            }
        };
        $eachPair = function (Collection $groups, string $why, ?callable $alike = null) use ($base, $add) {
            foreach ($groups as $group) {
                $group = $group->values();
                if ($group->count() > self::MAX_SHARED) {
                    continue;   // shared by many: routine (a logo, a circular to all), not a second registration
                }
                foreach ($group as $i => $a) {
                    foreach ($group->slice($i + 1) as $b) {
                        if (($base->has($a->id) || $base->has($b->id)) && ($alike === null || $alike($a, $b))) {
                            $add($a, $b, $why);
                        }
                    }
                }
            }
        };

        // the same file
        $hashes = Attachment::whereIn('protocol_id', $base->keys())->whereNotNull('sha256')->distinct()->pluck('sha256');
        if ($hashes->isNotEmpty()) {
            $byHash = Attachment::whereIn('sha256', $hashes)->get(['protocol_id', 'sha256'])
                ->groupBy('sha256')->map(fn ($rows) => $rows->pluck('protocol_id')->unique())
                ->filter(fn ($ids) => $ids->count() >= 2 && $ids->count() <= self::MAX_SHARED);   // see $eachPair
            $others = $near()->whereIn('id', $byHash->flatten()->unique()->diff($base->keys()))->get(self::COLUMNS)->keyBy('id');
            $all = $base->union($others);
            $eachPair($byHash->map(fn ($ids) => $ids->map(fn ($id) => $all->get($id))->filter()), 'file');
        }

        // the same incoming number (senders not different)
        $numbers = $base->pluck('in_num')->map(fn ($n) => trim((string) $n))->filter()->unique()->values();
        if ($numbers->isNotEmpty()) {
            $eachPair(
                $near()->whereIn(DB::raw('TRIM(in_num)'), $numbers)->get(self::COLUMNS)->groupBy(fn ($p) => self::fold($p->in_num)),
                'in_num',
                fn ($a, $b) => self::fold($a->in_arxi_ekdosis) === '' || self::fold($b->in_arxi_ekdosis) === ''
                    || self::fold($a->in_arxi_ekdosis) === self::fold($b->in_arxi_ekdosis),
            );
        }

        // the same subject, sender and incoming date
        $full = fn (Protocol $p) => self::fold($p->thema) !== '' && self::fold($p->in_arxi_ekdosis) !== '' && $p->in_date
            && ! ProtocolWriter::isPlaceholder($p);
        $subjects = $base->filter($full)->pluck('thema')->unique()->values();
        if ($subjects->isNotEmpty()) {
            $eachPair(
                $near()->whereIn('thema', $subjects)->whereNotNull('in_date')->get(self::COLUMNS)->filter($full)
                    ->groupBy(fn ($p) => self::fold($p->thema)."\n".self::fold($p->in_arxi_ekdosis)."\n".$p->dateOf('in_date')?->toDateString()),
                'subject',
            );
        }

        uasort($pairs, fn ($x, $y) => [$x[1]->etos, $x[1]->protocolnum, $x[0]->etos, $x[0]->protocolnum]
            <=> [$y[1]->etos, $y[1]->protocolnum, $y[0]->etos, $y[0]->protocolnum]);
        $later = Protocol::whereIn('id', array_map(fn ($pair) => $pair[1]->id, $pairs))->get()->keyBy('id');

        return array_values(array_map(function (array $pair) use ($later, $viewer) {
            [$first, $second] = $pair;
            $why = implode(', ', array_filter(self::WHY, fn ($w) => in_array($w, $pair['why'], true), ARRAY_FILTER_USE_KEY));   // in WHY's order
            $named = self::visible($viewer, $first)
                ? 'με το '.$first->protocolnum.'/'.$first->etos.' ('.$first->dateOf('protocoldate')?->format('d/m/Y').')'
                : 'με άλλο πρωτόκολλο';

            return Finding::about($later->get($second->id), "Μοιάζει {$named}: {$why}.");
        }, $pairs));
    }

    /** One names the other in its «Σχετικοί αριθμοί» */
    private static function related(Protocol $a, Protocol $b): bool
    {
        return in_array($b->protocolnum.'/'.$b->etos, RelatedNumbers::numbers($a->sxetiko), true)
            || in_array($a->protocolnum.'/'.$a->etos, RelatedNumbers::numbers($b->sxetiko), true);
    }

    private static function fold(?string $text): string
    {
        return trim(TagList::fold(preg_replace('~\s+~u', ' ', (string) $text)));
    }

    /** Whether $viewer may see $p (with «Περιορισμός λίστας» another user's protocol is not named, as R2f) */
    private static function visible(?User $viewer, Protocol $p): bool
    {
        return $viewer === null || ! ProtocolPolicy::isLimited($viewer) || Gate::forUser($viewer)->allows('view', Protocol::find($p->id));
    }
}
