<?php

namespace App\Livewire;

use App\Models\Config;
use App\Support\Roles;
use App\Support\SettingsCatalog;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * R6c: "Ρυθμίσεις" (administrators), grouped in cards, each setting with its explanation
 * (App\Support\SettingsCatalog). Only the catalog's keys are saved, each checked; a wrong value is shown at its field
 * and nothing is saved.
 */
class SettingsPage extends Component
{
    /** @var array<string, string> */
    public array $values = [];

    public function mount(): void
    {
        self::authorizeAdmin();
        $this->values = SettingsCatalog::values();
    }

    private static function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role?->role === Roles::ADMIN, 403);
    }

    public function save(): void
    {
        self::authorizeAdmin();
        $all = SettingsCatalog::all();
        $values = array_intersect_key(array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $this->values), $all);
        $validator = Validator::make(['values' => $values], SettingsCatalog::rules(), [
            'values.titleColor.regex' => 'Γράψτε ένα χρώμα όπως #1f4e79 ή το όνομά του στα αγγλικά (π.χ. teal).',
            'values.mysqldumpPath.regex' => 'Γίνεται δεκτό μόνο το αρχείο mysqldump ή mariadb-dump.',
        ], SettingsCatalog::attributes());
        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
            $this->dispatch('toast', message: 'Δεν αποθηκεύτηκε: διορθώστε τα πεδία με κόκκινο.', type: 'error');
            return;
        }
        $this->resetErrorBag();
        $changed = 0;
        $stored = Config::getConfigValues();
        foreach ($values as $key => $value) {
            if ((string) ($stored[$key] ?? '') !== $value) {
                Config::setConfigValueOf($key, $value);
                $changed++;
            }
        }
        if (($values['updatesAutoCheck'] ?? '1') === '0') {
            Config::setConfigValueOf('needsUpdate', 0);
        }
        $this->values = SettingsCatalog::values();
        $this->dispatch('toast', message: $changed ? "Αποθηκεύτηκαν ($changed ".($changed === 1 ? 'αλλαγή' : 'αλλαγές').').' : 'Καμία αλλαγή.', type: 'success');
    }

    public function render()
    {
        return view('livewire.settings-page', ['groups' => SettingsCatalog::groups()]);
    }
}
