<?php

namespace App\Livewire;

use App\Models\Config;
use App\Support\Roles;
use App\Support\SettingsCatalog;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * R6c: "Ρυθμίσεις" (administrators), each setting with its explanation (App\Support\SettingsCatalog). Only the
 * catalog's keys are saved, each checked; a wrong value is shown at its field and nothing is saved.
 * R6f (owner, 2026-11-25): as GitHub's settings: a menu of sections on the left, one section on the right
 * (?section=…; ?setting=<key> opens the section of that setting), saved on its own.
 */
class SettingsPage extends Component
{
    /** @var array<string, string> */
    public array $values = [];

    /** R6f: the section shown */
    #[Url(as: 'section')]
    public string $section = '';

    /**
     * R6f: ?setting=<key> opens the section of that setting; review of R6f: used once and cleared, so the address then
     * says the section shown (a reload or a shared link opens that one); a section already in the address wins
     */
    #[Url(as: 'setting', except: '')]
    public string $setting = '';

    public function mount(): void
    {
        self::authorizeAdmin();
        $this->values = SettingsCatalog::values();
        $wanted = SettingsCatalog::sectionOf($this->setting);
        $this->section = isset(SettingsCatalog::SECTIONS[$this->section]) ? $this->section
            : ($wanted ?? array_key_first(SettingsCatalog::SECTIONS));
        $this->setting = '';
    }

    /** R6f: another section; what was typed in this one and not saved is put back as stored */
    public function show(string $section): void
    {
        if (! isset(SettingsCatalog::SECTIONS[$section])) {
            return;
        }
        $this->resetErrorBag();
        $this->values = SettingsCatalog::values();
        $this->section = $section;
    }

    private static function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role?->role === Roles::ADMIN, 403);
    }

    public function save(): void
    {
        self::authorizeAdmin();
        // R6f: only this section's settings (the others are not on the page)
        $section = isset(SettingsCatalog::SECTIONS[$this->section]) ? $this->section : array_key_first(SettingsCatalog::SECTIONS);
        $keys = array_flip(SettingsCatalog::SECTIONS[$section][4]);
        $values = array_intersect_key(array_map(fn ($v) => is_string($v) ? trim($v) : (string) $v, $this->values), $keys);
        $rules = array_intersect_key(SettingsCatalog::rules(), array_flip(array_map(fn ($k) => 'values.'.$k, array_keys($keys))));
        $validator = Validator::make(['values' => $values], $rules, [
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
        $this->values = SettingsCatalog::values();
        $this->dispatch('toast', message: $changed ? "Αποθηκεύτηκαν ($changed ".($changed === 1 ? 'αλλαγή' : 'αλλαγές').').' : 'Καμία αλλαγή.', type: 'success');
    }

    public function render()
    {
        $groups = SettingsCatalog::groups();
        $section = isset($groups[$this->section]) ? $this->section : array_key_first($groups);
        return view('livewire.settings-page', ['groups' => $groups, 'current' => $section, 'g' => $groups[$section]]);
    }
}
