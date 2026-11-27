<?php

namespace App\Support;

use App\Models\Config;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;

/**
 * R1b: values offered while typing (HTML datalist) in the protocol form: the most used values of the latest
 * protocols the user may see (settings rowsToSearchGiaSimplirosi / rowsToShowGiaSimplirosi, as the old autocomplete).
 * R1f: keywords and folders have their own lists (keywords(), folders()), searched in the browser while typing.
 */
final class Suggestions
{
    public const FIELDS = ['in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis', 'out_to'];

    /** R6m: how many of the latest protocols the suggestions come from, and how many values each field offers (when empty) */
    public const DEFAULT_SEARCHED = 1000;

    public const DEFAULT_SHOWN = 50;

    /** R1f: at most this many keywords are offered (the most used). */
    public const KEYWORDS_MAX = 5000;

    /** @return array<string, list<string>> field => values */
    public static function for(User $user): array
    {
        // R6m (owner, 2026-11-27): both on Ρυθμίσεις → Καταχώριση; «shown» means what it says (it was raised to 50 at least)
        $rows = max(1, (int) (Config::getConfigValueOf('rowsToSearchGiaSimplirosi') ?: self::DEFAULT_SEARCHED));
        $show = max(1, (int) (Config::getConfigValueOf('rowsToShowGiaSimplirosi') ?: self::DEFAULT_SHOWN));
        $query = Protocol::query()->latest('id')->take($rows);
        if (ProtocolPolicy::isLimited($user)) {
            $query->ownOrAssigned((int) $user->id);
        }
        $latest = $query->get(self::FIELDS);
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = $latest->pluck($f)->map(fn ($v) => trim((string) $v))->filter(fn ($v) => $v !== '' && mb_strlen($v) <= 255)
                ->countBy()->sortDesc()->keys()->take($show)->sort()->values()->all();
        }
        return $out;
    }

    /**
     * R1f: every keyword used in the protocols the user may see, most used first, with its count. Spellings that differ
     * only in case or accents count as one (the most used spelling is offered).
     *
     * @return list<array{0: string, 1: int}> [keyword, times used]
     */
    public static function keywords(User $user): array
    {
        $query = Protocol::query()->whereNotNull('keywords')->where('keywords', '<>', '');
        if (ProtocolPolicy::isLimited($user)) {
            $query->ownOrAssigned((int) $user->id);
        }
        $counts = [];
        foreach ($query->latest('id')->take(50000)->pluck('keywords') as $value) {
            foreach (TagList::keywords()->tokens($value) as $k) {
                if (mb_strlen($k) <= 100) {
                    $counts[TagList::fold($k)][$k] = ($counts[TagList::fold($k)][$k] ?? 0) + 1;
                }
            }
        }
        $out = [];
        foreach ($counts as $spellings) {
            arsort($spellings);
            $out[] = [array_key_first($spellings), array_sum($spellings)];
        }
        usort($out, fn ($a, $b) => $b[1] <=> $a[1] ?: strcmp($a[0], $b[0]));
        return array_slice($out, 0, self::KEYWORDS_MAX);
    }

    /**
     * R5e: how words are usually spelled, accents and all, in the subjects of the protocols the user may see (the most
     * recent 3000): folded word => its most used lower-case spelling. Gives back the accents of a word written in
     * capitals ("ΓΝΩΜΑΤΕΥΣΗ" → "γνωμάτευση"). Only words of 5 letters or more, written with an accent.
     *
     * @return array<string, string>
     */
    public static function spellings(User $user): array
    {
        $query = Protocol::query()->whereNotNull('thema')->where('thema', '<>', '');
        if (ProtocolPolicy::isLimited($user)) {
            $query->ownOrAssigned((int) $user->id);
        }
        $counts = [];
        foreach ($query->latest('id')->take(3000)->pluck('thema') as $subject) {
            foreach (preg_split('/[^\p{L}]+/u', (string) $subject, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
                if (mb_strlen($word) < 5 || ! preg_match('/\p{Mn}/u', \Normalizer::normalize($word, \Normalizer::FORM_D) ?: $word)) {
                    continue;
                }
                $lower = mb_strtolower($word, 'UTF-8');
                $counts[TagList::fold($word)][$lower] = ($counts[TagList::fold($word)][$lower] ?? 0) + 1;
            }
        }
        $out = [];
        foreach ($counts as $folded => $forms) {
            arsort($forms);
            $out[$folded] = (string) array_key_first($forms);
        }

        return $out;
    }

    /** @return list<array{0: string, 1: string}> R1f: folders in their usual order: [code, description] */
    public static function folders(): array
    {
        return \App\Models\Keepvalue::orderByRaw('SUBSTR(`fakelos`,3,LENGTH(`fakelos`)-3)+0<>0 DESC, SUBSTR(`fakelos`,3,LENGTH(`fakelos`)-(3))+0, `fakelos`')
            ->get(['fakelos', 'describe'])->map(fn ($f) => [(string) $f->fakelos, (string) $f->describe])->all();
    }
}
