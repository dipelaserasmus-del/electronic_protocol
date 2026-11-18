<?php

namespace App\Http\Controllers;

use App\Services\Attachments\Expired;
use App\Services\DatabaseBackup;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Storage;

/**
 * R6c: administration pages (administrators only). The pages are Livewire components; their changes are actions of
 * the page (CSRF-checked), no longer plain links:
 *  - /settings  App\Livewire\SettingsPage (was POST /settings, which stored any posted key)
 *  - /backups   App\Livewire\BackupPage   (was GET /backup and GET /deleteBackup/{name})
 *  - /arxeio    App\Livewire\ArchivePage  (was GET /delExpired and GET /delDeleted; emptying the trash is on /trash)
 *  - /expired   the expired attachments to print
 */
class ConfigController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth', 'admin:home'];
    }

    public function index()
    {
        return view('config');
    }

    public function backups()
    {
        return view('backup');
    }

    public function downloadBackup($name)
    {
        $path = DatabaseBackup::DIR . '/' . $name;
        abort_unless(DatabaseBackup::isValidName($name) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name, [
            'Content-Type' => str_ends_with($name, '.gz') ? 'application/gzip' : 'application/sql',
        ]);
    }

    public function arxeio()
    {
        return view('arxeio');
    }

    /** The list to print; /expired/1 (the old "files in the trash" list) is the trash page now */
    public function expired(Expired $expired, $deleted = null)
    {
        if ($deleted) {
            return redirect('/trash');
        }
        return view('expired', ['items' => $expired->query()->get()]);
    }
}
