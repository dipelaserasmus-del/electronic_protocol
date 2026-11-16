<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D04: dates were stored as integers 20260105 ("Ymd"). They become DATE columns: MySQL then refuses impossible dates,
 * and date functions / ranges work. MySQL converts each valid integer itself (20260105 → 2026-01-05).
 *
 * Old values that are not a real date (0, 20260231, 2026015 …) are not lost: they are copied to the table
 * legacy_invalid_dates (table, row id, column, old value) and the column is left empty. The registration date
 * (protocoldate) is required, so an invalid one stops the migration before anything changes, with the protocol ids.
 *
 * Idempotent: columns that are already DATE are skipped.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'protocols' => ['protocoldate' => false, 'in_date' => true, 'out_date' => true, 'diekp_eos' => true, 'diekp_date' => true],
        'attachments' => ['expires' => true],   // column => nullable
    ];

    public function up(): void
    {
        $todo = [];
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column => $nullable) {
                if (Schema::getColumnType($table, $column) !== 'date') {
                    $todo[$table][$column] = $nullable;
                }
            }
        }
        if (!$todo) {
            return;
        }

        // 1. find values that are not real dates (all checks before any change)
        $invalid = [];
        foreach ($todo as $table => $columns) {
            foreach (array_keys($columns) as $column) {
                DB::table($table)->select(['id', $column])->whereNotNull($column)->orderBy('id')
                    ->chunkById(2000, function ($rows) use ($table, $column, &$invalid) {
                        foreach ($rows as $row) {
                            if (!self::isValidYmd($row->$column)) {
                                $invalid[] = ['table_name' => $table, 'row_id' => $row->id, 'column_name' => $column, 'old_value' => (string) $row->$column];
                            }
                        }
                    });
            }
        }
        $badRequired = array_filter($invalid, fn ($i) => $i['table_name'] === 'protocols' && $i['column_name'] === 'protocoldate');
        if ($badRequired) {
            throw new RuntimeException('D04: ' . count($badRequired) . ' protocol(s) have a registration date that is not a real date '
                . '(protocols.id: ' . implode(', ', array_slice(array_column($badRequired, 'row_id'), 0, 50)) . '). '
                . 'Nothing was changed. Correct protocoldate (format YYYYMMDD) for them and run migrate again.');
        }

        // 2. keep the invalid values, then empty them
        if (!Schema::hasTable('legacy_invalid_dates')) {
            Schema::create('legacy_invalid_dates', function (Blueprint $table) {
                $table->increments('id');
                $table->string('table_name', 64);
                $table->unsignedInteger('row_id');
                $table->string('column_name', 64);
                $table->string('old_value', 32);
                $table->timestamp('created_at')->useCurrent();
            });
        }
        foreach (array_chunk($invalid, 500) as $chunk) {
            DB::table('legacy_invalid_dates')->insert($chunk);
            foreach ($chunk as $i) {
                DB::table($i['table_name'])->where('id', $i['row_id'])->update([$i['column_name'] => null]);
            }
        }

        // 3. change the column types (MySQL converts the integers)
        foreach ($todo as $table => $columns) {
            $parts = [];
            foreach ($columns as $column => $nullable) {
                $parts[] = "MODIFY `$column` DATE " . ($nullable ? 'NULL' : 'NOT NULL');
            }
            DB::statement("ALTER TABLE `$table` " . implode(', ', $parts));
        }
    }

    /** Back to integers Ymd (MySQL converts 2026-01-05 → 20260105). Emptied invalid values stay in legacy_invalid_dates. */
    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $parts = [];
            foreach ($columns as $column => $nullable) {
                $parts[] = "MODIFY `$column` INT UNSIGNED " . ($nullable ? 'NULL' : 'NOT NULL');
            }
            DB::statement("ALTER TABLE `$table` " . implode(', ', $parts));
        }
    }

    private static function isValidYmd(mixed $value): bool
    {
        $s = (string) $value;
        if (!preg_match('/^(\d{4})(\d{2})(\d{2})$/', $s, $m)) {
            return false;
        }
        return (int) $m[1] >= 1000 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
};
