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

    /** R1f: at most this many keywords are offered (the most used). */
    public const KEYWORDS_MAX = 5000;

    /** @return array<string, list<string>> field => values */
    public static function for(User $user): array
    {
        $rows = (int) (Config::getConfigValueOf('rowsToSearchGiaSimplirosi') ?: 1000);
        $show = max(50, (int) (Config::getConfigValueOf('rowsToShowGiaSimplirosi') ?: 10));
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

    /** @return list<array{0: string, 1: string}> R1f: folders in their usual order: [code, description] */
    public static function folders(): array
    {
        return \App\Models\Keepvalue::orderByRaw('SUBSTR(`fakelos`,3,LENGTH(`fakelos`)-3)+0<>0 DESC, SUBSTR(`fakelos`,3,LENGTH(`fakelos`)-(3))+0, `fakelos`')
            ->get(['fakelos', 'describe'])->map(fn ($f) => [(string) $f->fakelos, (string) $f->describe])->all();
    }
}
