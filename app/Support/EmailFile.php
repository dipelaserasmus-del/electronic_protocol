<?php

namespace App\Support;

use App\Models\Config;
use Illuminate\Support\Collection;

/**
 * R2r (owner, 2026-11-25): the e-mail itself, kept with a protocol registered from the inbox (EmailRegistration names it
 * «email_YYYYMMDD_HHMMSS.eml», or .html). In the list it is shown by its date and time, and after the real attachments
 * unless the administrator puts it first (setting emailFileFirst) or it is the protocol's only file.
 */
final class EmailFile
{
    private const PATTERN = '/^email_(\d{4})(\d{2})(\d{2})_(\d{2})(\d{2})(\d{2})\.(?:eml|html)$/i';

    public static function is(?string $name): bool
    {
        return (bool) preg_match(self::PATTERN, (string) $name);
    }

    /** «email_20261112_103626.eml» → «12/11/2026 · 10:36:26»; any other name as it is */
    public static function label(?string $name): string
    {
        return preg_match(self::PATTERN, (string) $name, $m) ? "$m[3]/$m[2]/$m[1] · $m[4]:$m[5]:$m[6]" : (string) $name;
    }

    /** the attachments in the list's order: the e-mail's own file last (the others keep their order) */
    public static function ordered(Collection $attachments): Collection
    {
        if (Config::getConfigValueOf('emailFileFirst') || $attachments->count() < 2) {
            return $attachments;
        }
        [$mails, $others] = $attachments->partition(fn ($a) => self::is($a->name));
        return $others->values()->concat($mails->values());
    }
}
