<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

// R4b: the trash's clean-up. The Docker stack has no scheduler; App\Http\Middleware\PurgeTrashDaily runs it once a day.
Schedule::command('attachments:purge-trash')->daily();

// X01: the audit log's retention (AUDIT_KEEP_DAYS); also run by PurgeTrashDaily with the trash's clean-up
Artisan::command('audit:purge', function () {
    $this->info('Εγγραφές ιστορικού που αφαιρέθηκαν: '.\App\Services\Audit\AuditLog::purgeExpired());
})->describe('Remove audit log entries older than AUDIT_KEEP_DAYS');
Schedule::command('audit:purge')->daily();
