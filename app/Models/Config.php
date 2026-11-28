<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    use \App\Models\Concerns\Audited;   // X01: changes go to the audit log

    /** Rows the application writes for itself, not settings a user changed */
    public const NOT_AUDITED = ['trashLastPurge', 'needsUpdate'];

    public function auditType(): string
    {
        return 'setting';
    }

    public function auditContext(): array
    {
        return ['key' => $this->key];
    }

    protected function auditIgnored(): array
    {
        return ['key'];
    }

    protected function auditSkipped(): bool
    {
        return in_array($this->key, self::NOT_AUDITED, true);
    }

    protected $fillable = ['key', 'value'];

    public static function getConfigValueOf($key) {
        $c = Config::firstOrCreate(['key' => $key]);
        return $c->value;
    }

    public static function setConfigValueOf($key, $value) {
        $c = Config::firstOrCreate(['key' => $key]);
    	$c->update(['value' => $value]);
    	return ;
    }

    public static function getConfigValues() {
        return  Config::pluck('value', 'key');
     }

}
