<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D05: the tables were created as utf8 (= utf8mb3, 3 bytes per character), which refuses emoji and other 4-byte
 * characters: an e-mail with an emoji in its subject or an attachment name could not be registered. Convert the
 * database default and every table that is not utf8mb4 yet (also tables of older installations that no migration
 * knows). Existing text is kept as is: every utf8mb3 character is also a utf8mb4 character.
 *
 * utf8mb4_general_ci compares like the old utf8_general_ci, so unique keys (e.g. folder codes) can't collide.
 * Tables in the old COMPACT/REDUNDANT row format allow index keys of only 767 bytes, too small for a utf8mb4
 * VARCHAR(255) (1020 bytes); those get the DYNAMIC format (MySQL's default since 5.7), which allows 3072.
 * MySQL turns TEXT columns into MEDIUMTEXT where needed so they keep their length in characters.
 */
return new class extends Migration
{
    private const CHARSET = 'utf8mb4';

    private const COLLATION = 'utf8mb4_general_ci';

    public function up(): void
    {
        $database = DB::getDatabaseName();
        DB::statement(sprintf('ALTER DATABASE `%s` CHARACTER SET %s COLLATE %s', str_replace('`', '``', $database), self::CHARSET, self::COLLATION));

        $tables = DB::select(
            "SELECT TABLE_NAME AS name, ROW_FORMAT AS row_format, TABLE_COLLATION AS collation
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'",
            [$database]
        );

        foreach ($tables as $table) {
            if ($table->collation === self::COLLATION && ! $this->hasColumnsInOtherCharsets($database, $table->name)) {
                continue;
            }
            $rowFormat = in_array(strtolower((string) $table->row_format), ['compact', 'redundant'], true) ? ', ROW_FORMAT=DYNAMIC' : '';
            DB::statement(sprintf('ALTER TABLE `%s` CONVERT TO CHARACTER SET %s COLLATE %s%s',
                str_replace('`', '``', $table->name), self::CHARSET, self::COLLATION, $rowFormat));
        }
    }

    /**
     * Not reversible without losing data: converting back to utf8mb3 would fail on (or mangle) any emoji stored
     * since. Restore the backup taken before the upgrade instead.
     */
    public function down(): void
    {
    }

    private function hasColumnsInOtherCharsets(string $database, string $table): bool
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->whereNotNull('CHARACTER_SET_NAME')
            ->where('COLLATION_NAME', '!=', self::COLLATION)
            ->exists();
    }
};
