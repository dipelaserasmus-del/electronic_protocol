<?php

namespace App\Support;

use App\Models\Config;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;

/**
 * R1b: values offered while typing (HTML datalist) in the protocol form: the most used values of the latest
 * protocols the user may see (settings rowsToSearchGiaSimplirosi / rowsToShowGiaSimplirosi, as the old autocomplete).
 * Fields with several values separated by commas (keywords) offer each value.
 */
final class Suggestions
{
    public const FIELDS = ['in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis', 'out_to', 'keywords'];
    private const MULTI = ['keywords'];

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
            $values = in_array($f, self::MULTI, true)
                ? $latest->pluck($f)->flatMap(fn ($v) => preg_split('/\s*,\s*/', (string) $v))
                : $latest->pluck($f);
            $out[$f] = $values->map(fn ($v) => trim((string) $v))->filter(fn ($v) => $v !== '' && mb_strlen($v) <= 255)
                ->countBy()->sortDesc()->keys()->take($show)->sort()->values()->all();
        }
        return $out;
    }
}
