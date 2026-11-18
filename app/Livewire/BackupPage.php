<?php

namespace App\Livewire;

use App\Models\Config;
use App\Services\DatabaseBackup;
use App\Support\Roles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * R6c: "Backup" (administrators): make a copy of the database (B10: App\Services\DatabaseBackup), list the copies with
 * size and date, download (GET /downloadBackup/{name}) and delete them. Creating and deleting are actions of the page:
 * before they were plain links (GET /backup, GET /deleteBackup/{name}) without a CSRF check.
 */
class BackupPage extends Component
{
    public function mount(): void
    {
        self::authorizeAdmin();
    }

    private static function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role?->role === Roles::ADMIN, 403);
    }

    public function create(): void
    {
        self::authorizeAdmin();
        try {
            $name = (new DatabaseBackup(Config::getConfigValueOf('mysqldumpPath')))->create();
            $this->dispatch('toast', message: "Δημιουργήθηκε το αντίγραφο $name.", type: 'success');
        } catch (\RuntimeException $e) {
            Log::error('Backup failed: '.$e->getMessage());
            $this->dispatch('toast', message: 'Το αντίγραφο δεν δημιουργήθηκε. '.$e->getMessage(), type: 'error');
        }
    }

    public function delete(string $name): void
    {
        self::authorizeAdmin();
        abort_unless(DatabaseBackup::isValidName($name), 404);
        Storage::disk('local')->delete(DatabaseBackup::DIR.'/'.$name);
        $this->dispatch('toast', message: "Διαγράφηκε το $name.", type: 'success');
    }

    /** @return list<array{name: string, size: int, time: int}> newest first */
    public static function files(): array
    {
        $disk = Storage::disk('local');
        $files = [];
        foreach ($disk->files(DatabaseBackup::DIR) as $path) {
            $name = basename($path);
            if (DatabaseBackup::isValidName($name)) {
                $files[] = ['name' => $name, 'size' => (int) $disk->size($path), 'time' => (int) $disk->lastModified($path)];
            }
        }
        usort($files, fn ($a, $b) => [$b['time'], $b['name']] <=> [$a['time'], $a['name']]);

        return $files;
    }

    public function render()
    {
        return view('livewire.backup-page', ['files' => self::files(), 'dir' => Storage::disk('local')->path(DatabaseBackup::DIR)]);
    }
}
