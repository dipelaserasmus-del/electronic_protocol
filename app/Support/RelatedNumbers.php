<?php

namespace App\Support;

use App\Models\Protocol;
use Illuminate\Support\Facades\Gate;

/**
 * R1e/R1f: the "Σχετικοί αριθμοί" field ("12/2026, 40/2025"), split and edited by TagList::related(). Each reference
 * is "number/year" or a bare number (= the protocol's own year); anything else is kept as plain text.
 */
class RelatedNumbers
{
    /**
     * Every reference in order:
     * - exists: a protocol with that number exists (green tag);
     * - protocol: that protocol, only when the user may open it (link + subject), else null.
     *
     * @return list<array{text: string, exists: bool, protocol: ?Protocol}>
     */
    public static function resolve(?string $value, int $year): array
    {
        $refs = [];
        foreach (TagList::related($year)->tokens($value) as $text) {
            if (preg_match('~^(\d{1,9})(?:/(\d{4}))?$~', $text, $m)) {
                $refs[] = ['text' => $text, 'num' => (int) $m[1], 'year' => isset($m[2]) ? (int) $m[2] : $year];
            } else {
                $refs[] = ['text' => $text, 'num' => null, 'year' => null];
            }
        }
        $wanted = array_filter($refs, fn ($r) => $r['num'] !== null);
        $found = collect();
        if ($wanted) {
            $found = Protocol::query()
                ->where(function ($q) use ($wanted) {
                    foreach ($wanted as $r) {
                        $q->orWhere(fn ($q) => $q->where('etos', $r['year'])->where('protocolnum', $r['num']));
                    }
                })
                ->get()
                ->keyBy(fn ($p) => $p->protocolnum . '/' . $p->etos);
        }
        return array_map(function ($r) use ($found) {
            $p = $r['num'] !== null ? $found->get($r['num'] . '/' . $r['year']) : null;
            return [
                'text' => $r['text'],
                'exists' => $p !== null,
                'protocol' => $p && Gate::allows('view', $p) ? $p : null,   // others' protocols: no link, no subject
            ];
        }, $refs);
    }
}
