<?php

namespace App\Support;

use App\Models\Protocol;
use App\Models\ProtocolAssignment;
use Illuminate\Support\Facades\DB;

/**
 * D02: who a protocol is assigned to. The legacy form still posts the old text format "d14,e10"
 * (d = διεκπεραίωση, e = ενημέρωση, followed by the user id) into protocols.diekperaiosi; every save copies it into
 * the table protocol_assignments, which is what searches, filters and access checks read.
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
     * Make the table match the protocol's text column (adds missing rows, removes rows no longer there).
     * The text is read again from the database with the row locked, never from the caller's copy: when two users
     * reassign the same protocol at the same time, the later sync must not write back the earlier text.
     */
    public static function sync(Protocol|int $protocol): void
    {
        $id = $protocol instanceof Protocol ? (int) $protocol->getKey() : $protocol;

        DB::transaction(function () use ($id) {
            $text = DB::table('protocols')->where('id', $id)->lockForUpdate()->value('diekperaiosi');
            $wanted = self::parse($text);
            $existing = ProtocolAssignment::where('protocol_id', $id)->get();
            $key = fn ($row) => $row['type'] . $row['user_id'];
            $wantedKeys = array_map($key, $wanted);
            foreach ($existing as $row) {
                if (!in_array($key($row), $wantedKeys, true)) {
                    $row->delete();
                }
            }
            $existingKeys = $existing->map(fn ($row) => $key($row))->all();
            foreach ($wanted as $row) {
                if (!in_array($key($row), $existingKeys, true)) {
                    ProtocolAssignment::create(['protocol_id' => $id] + $row);
                }
            }
        });
    }

    /** Fill the table from the text column for all protocols (migration; safe to run again). Returns rows written. */
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
