<?php

namespace App\Services\Protocols;

use App\Models\Protocol;
use Illuminate\Support\Facades\DB;

/**
 * «Σχετικοί αριθμοί» (column `sxetiko`) written in both protocols. Full description: docs/RELATED-NUMBERS.md.
 *
 * - linkOnSave(): the setting saveCycleSxetiko (legacy rule), run by ProtocolWriter after every save of a protocol;
 * - plan() / applyToExisting(): R6l (owner, 2026-11-26), the same for the protocols saved before the setting was on.
 *   An administrator runs it from Ρυθμίσεις → Καταχώριση. It only makes each reference mutual: every N/YYYY written
 *   in a protocol's Σχετικοί gets that protocol's number back. Nothing is removed or rewritten; the text already
 *   there stays as it is and the numbers are added at its end; 255 characters (the column) are never exceeded.
 */
final class RelatedNumbers
{
    /** the column's length */
    public const MAX = 255;

    /**
     * The protocol numbers written in a «Σχετικοί» text, as "N/YYYY" (leading zeros dropped). Other words (φ.3, a
     * bare 12 with no year) are not protocol numbers and are skipped.
     *
     * @return list<string>
     */
    public static function numbers(?string $text): array
    {
        $out = [];
        foreach (preg_split('~[\s,;]+~u', trim((string) $text), -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if (preg_match('~^(\d{1,9})/(\d{4})$~', $token, $m)) {
                $out[((int) $m[1]).'/'.$m[2]] = true;
            }
        }
        return array_keys($out);
    }

    /**
     * Setting saveCycleSxetiko (legacy rule, kept as it was): each related protocol "N/YYYY" also gets this one and
     * the others written in this protocol's Σχετικοί. Not written if the other's field would pass 255 characters.
     */
    public function linkOnSave(Protocol $protocol): void
    {
        $self = "{$protocol->protocolnum}/{$protocol->etos}";
        $related = array_map('trim', explode(',', (string) $protocol->sxetiko));
        foreach ($related as $ref) {
            if (! preg_match('~^(\d+)/(\d{4})$~', $ref, $m)) {
                continue;
            }
            $other = Protocol::where('protocolnum', (int) $m[1])->where('etos', (int) $m[2])->first();
            if (! $other || $other->id === $protocol->id) {
                continue;
            }
            $old = $other->sxetiko ? array_map('trim', explode(',', $other->sxetiko)) : [];
            $new = array_values(array_unique(array_merge($old, array_diff($related, [$ref]), [$self])));
            $text = implode(', ', array_filter($new, fn ($s) => $s !== ''));
            if (mb_strlen($text) <= self::MAX && $text !== $other->sxetiko) {
                $other->sxetiko = $text;
                $other->save();
            }
        }
    }

    /**
     * R6l: what applyToExisting() would do now.
     *
     * @return array{protocols: int, numbers: int, left_out: int} protocols that would change, numbers added in all,
     *         protocols where some number does not fit (255 characters) and is left out
     */
    public function plan(): array
    {
        $plan = ['protocols' => 0, 'numbers' => 0, 'left_out' => 0];
        foreach ($this->wanted() as $id => $refs) {
            $target = Protocol::query()->whereKey($id)->first(['id', 'sxetiko']);
            [, $added, $leftOut] = self::add($target?->sxetiko, $refs);
            $plan['protocols'] += $added === [] ? 0 : 1;
            $plan['numbers'] += count($added);
            $plan['left_out'] += $leftOut ? 1 : 0;
        }
        return $plan;
    }

    /**
     * R6l: every protocol named in another's Σχετικοί gets that one's number in its own, if it is not there yet.
     * Each protocol is locked and read again just before it is changed, so a save made meanwhile is kept. Its
     * «updated_at» is left as it was (a data fix, not an edit). No e-mail, no other field.
     *
     * @return array{protocols: int, numbers: int, left_out: int}
     */
    public function applyToExisting(): array
    {
        $done = ['protocols' => 0, 'numbers' => 0, 'left_out' => 0];
        foreach ($this->wanted() as $id => $refs) {
            [$added, $leftOut] = DB::transaction(function () use ($id, $refs) {
                $target = Protocol::query()->whereKey($id)->lockForUpdate()->first();
                if (! $target) {
                    return [[], false];
                }
                [$text, $added, $leftOut] = self::add($target->sxetiko, $refs);
                if ($added !== []) {
                    $target->timestamps = false;
                    $target->sxetiko = $text;
                    $target->save();
                }
                return [$added, $leftOut];
            });
            $done['protocols'] += $added === [] ? 0 : 1;
            $done['numbers'] += count($added);
            $done['left_out'] += $leftOut ? 1 : 0;
        }
        return $done;
    }

    /**
     * For each protocol named in another's Σχετικοί: the numbers of the protocols naming it, in order of id.
     *
     * @return array<int, list<string>> protocol id => numbers "N/YYYY"
     */
    private function wanted(): array
    {
        $refsTo = [];   // "N/YYYY" named => the numbers of the protocols naming it
        Protocol::query()->whereNotNull('sxetiko')->where('sxetiko', '!=', '')->orderBy('id')
            ->select(['id', 'protocolnum', 'etos', 'sxetiko'])
            ->chunkById(1000, function ($protocols) use (&$refsTo) {
                foreach ($protocols as $p) {
                    $self = ((int) $p->protocolnum).'/'.$p->etos;
                    foreach (self::numbers($p->sxetiko) as $ref) {
                        if ($ref !== $self) {
                            $refsTo[$ref][] = $self;
                        }
                    }
                }
            });

        // the protocols named, found by year (a number with no protocol is skipped, as on save)
        $byYear = [];
        foreach (array_keys($refsTo) as $ref) {
            [$num, $year] = explode('/', $ref);
            $byYear[(int) $year][] = (int) $num;
        }
        $wanted = [];
        foreach ($byYear as $year => $nums) {
            foreach (array_chunk($nums, 1000) as $chunk) {
                foreach (Protocol::query()->where('etos', $year)->whereIn('protocolnum', $chunk)->get(['id', 'protocolnum', 'etos']) as $t) {
                    $wanted[$t->id] = $refsTo[((int) $t->protocolnum).'/'.$t->etos];
                }
            }
        }
        ksort($wanted);
        return $wanted;
    }

    /**
     * The numbers not yet in the text, added at its end (", " between), as many as fit in 255 characters.
     *
     * @param  list<string>  $refs
     * @return array{0: string, 1: list<string>, 2: bool} the new text, the numbers added, whether some was left out
     */
    private static function add(?string $text, array $refs): array
    {
        $text = trim((string) $text);
        $have = array_flip(self::numbers($text));
        $added = [];
        $leftOut = false;
        foreach (array_unique($refs) as $ref) {
            if (isset($have[$ref])) {
                continue;
            }
            $next = $text === '' ? $ref : $text.', '.$ref;
            if (mb_strlen($next) > self::MAX) {
                $leftOut = true;
                continue;
            }
            $text = $next;
            $have[$ref] = true;
            $added[] = $ref;
        }
        return [$text, $added, $leftOut];
    }
}
