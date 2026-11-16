<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Schema;

/**
 * Older installations were set up without Laravel's `migrations` table, so `artisan migrate` tries to
 * create `users` etc. again and fails ("Table 'users' already exists"). This records every
 * `create_<table>_table` migration whose table already exists as applied, so that `migrate` only runs
 * the newer changes. It does nothing on a database that already has migration records or is empty.
 */
class AdoptLegacySchema extends Command
{
    protected $signature = 'migrate:adopt-legacy';

    protected $description = 'Record the tables of an installation made without the migrations table as already migrated';

    public function handle(Migrator $migrator): int
    {
        $repository = $migrator->getRepository();

        if ($repository->repositoryExists() && count($repository->getRan()) > 0) {
            $this->line('Migration records exist: nothing to adopt.');

            return self::SUCCESS;
        }
        if (! Schema::hasTable('users')) {
            $this->line('Empty database: nothing to adopt.');

            return self::SUCCESS;
        }
        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        $adopted = 0;
        foreach (array_keys($migrator->getMigrationFiles(database_path('migrations'))) as $name) {
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_create_(\w+)_table$/', $name, $m) && Schema::hasTable($m[1])) {
                $repository->log($name, 1);
                $adopted++;
            }
        }
        $this->info("Existing installation without migration records: {$adopted} existing tables recorded as migrated.");

        return self::SUCCESS;
    }
}
