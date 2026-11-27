<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * R6m (owner, 2026-11-27): two settings left Ρυθμίσεις for the .env file. Their configs rows stay, but say so, so that
 * an administrator looking at the table knows where the value lives now (the template: .env.example). The old value is
 * kept at the end of the note (e.g. to copy a print limit into PRINT_MAX_ROWS); nothing reads these rows any more.
 */
return new class extends Migration
{
    /** configs key => the .env variable that replaced it */
    public const MOVED = ['mysqldumpPath' => 'MYSQLDUMP_PATH', 'maxRowsInXlsExport' => 'PRINT_MAX_ROWS'];

    public const NOTE = 'Δεν χρησιμοποιείται πια: ορίζεται στο .env ως %s (δείτε το .env.example).';

    public const OLD = ' Παλιά τιμή: ';

    public function up(): void
    {
        $now = now()->format('Y-m-d H:i:s');
        foreach (self::MOVED as $key => $env) {
            $note = sprintf(self::NOTE, $env);
            $row = DB::table('configs')->where('key', $key)->first();
            if ($row === null) {
                DB::table('configs')->insert(['key' => $key, 'value' => $note, 'created_at' => $now, 'updated_at' => $now]);
                continue;
            }
            $old = (string) $row->value;
            if (str_starts_with($old, $note)) {
                continue;   // already noted
            }
            DB::table('configs')->where('key', $key)->update(['value' => $note.($old !== '' ? self::OLD.$old : ''), 'updated_at' => $now]);
        }
    }

    /** The old value back (or no row, if there was none). */
    public function down(): void
    {
        foreach (self::MOVED as $key => $env) {
            $note = sprintf(self::NOTE, $env);
            $value = (string) DB::table('configs')->where('key', $key)->value('value');
            if (! str_starts_with($value, $note)) {
                continue;
            }
            $rest = substr($value, strlen($note));
            if (str_starts_with($rest, self::OLD)) {
                DB::table('configs')->where('key', $key)->update(['value' => substr($rest, strlen(self::OLD))]);
            } else {
                DB::table('configs')->where('key', $key)->delete();
            }
        }
    }
};
