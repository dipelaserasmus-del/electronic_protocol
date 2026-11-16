<?php

namespace App\Support;

use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use Illuminate\Support\Facades\DB;

/**
 * D02/R1a: who a protocol is assigned to, in the table protocol_assignments (one row per user and type:
 * d = διεκπεραίωση, e = ενημέρωση). The old text column protocols.diekperaiosi ("d14,e10") was dropped in R1a;
 * the legacy screens still use that text form through the virtual attribute Protocol::$diekperaiosi.
 */
final class Assignments
{
    public const HANDLE = 'd';   // διεκπεραίωση
    public const INFORM = 'e';   // ενημέρωση

    /**
     * "d14, e10,,d14,x" → [['user_id' => 14, 'type' => 'd'], ['user_id' => 10, 'type' => 'e']].
     * Tolerant of the forms' variants: spaces, a trailing comma, repeats; anything else is ignored.
     *
     * @return list<array{user_id:int, type:string}>
     */
    public static function parse(?string $text): array
    {
        $out = [];
        foreach (explode(',', (string) $text) as $part) {
            if (!preg_match('/^\s*([de])\s*(\d{1,10})\s*$/', $part, $m) || (int) $m[2] < 1 || (int) $m[2] > 4294967295) {
                continue;
            }
            $out[$m[1] . (int) $m[2]] = ['user_id' => (int) $m[2], 'type' => $m[1]];
        }
        return array_values($out);
    }

    /**
     * R1a: set who a protocol is assigned to. Adds missing rows, removes rows no longer wanted, keeps the others
     * (their created_at says since when). The protocol row is locked meanwhile, so two users re-assigning the same
     * protocol at the same moment are applied one after the other (D02 review finding).
     *
     * @param list<array{user_id:int, type:string}> $wanted  (see parse())
     * @return list<array{user_id:int, type:string}> the rows that were added
     */
    public static function replace(Protocol|int $protocol, array $wanted): array
    {
        $id = $protocol instanceof Protocol ? (int) $protocol->getKey() : $protocol;
        $key = fn ($row) => $row['type'] . $row['user_id'];
        $wanted = array_values(array_combine(array_map($key, $wanted), $wanted) ?: []);

        return DB::transaction(function () use ($id, $wanted, $key) {
            DB::table('protocols')->where('id', $id)->lockForUpdate()->value('id');
            $existing = ProtocolAssignment::where('protocol_id', $id)->get();
            $wantedKeys = array_map($key, $wanted);
            foreach ($existing as $row) {
                if (!in_array($key($row), $wantedKeys, true)) {
                    $row->delete();
                }
            }
            $existingKeys = $existing->map(fn ($row) => $key($row))->all();
            $added = [];
            foreach ($wanted as $row) {
                if (!in_array($key($row), $existingKeys, true)) {
                    ProtocolAssignment::create(['protocol_id' => $id, 'user_id' => $row['user_id'], 'type' => $row['type']]);
                    $added[] = ['user_id' => $row['user_id'], 'type' => $row['type']];
                }
            }
            return $added;
        });
    }

    /**
     * The legacy text form "d14,e10" of rows (D02 order: by type, then user). Receipts, prints and the inbox page
     * still read that form through Protocol::$diekperaiosi until they are rebuilt.
     *
     * @param iterable<array{user_id:int, type:string}|ProtocolAssignment> $rows
     */
    public static function asText(iterable $rows): string
    {
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = [$row['type'], (int) $row['user_id']];
        }
        usort($parts, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        return implode(',', array_map(fn ($p) => $p[0] . $p[1], $parts));
    }

    /**
     * Fill the table from the text column for all protocols (D02 migration; safe to run again). Returns rows written.
     * Only for migrations that run while the column still exists (R1a drops it after a last backfill).
     */
    public static function backfill(int $chunk = 1000): int
    {
        $written = 0;
        DB::table('protocols')->select(['id', 'diekperaiosi'])
            ->whereNotNull('diekperaiosi')->where('diekperaiosi', '!=', '')
            ->orderBy('id')
            ->chunkById($chunk, function ($protocols) use (&$written) {
                $rows = [];
                $now = now();
                foreach ($protocols as $p) {
                    foreach (self::parse($p->diekperaiosi) as $a) {
                        $rows[] = ['protocol_id' => $p->id] + $a + ['created_at' => $now, 'updated_at' => $now];
                    }
                }
                if ($rows) {
                    $written += DB::table('protocol_assignments')->insertOrIgnore($rows);
                }
            });
        return $written;
    }
}
