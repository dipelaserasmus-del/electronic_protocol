<?php

namespace App\Support;

use App\Models\User;

/**
 * R3d: a user's own choices for the screens (owner, 2026-11-18: per user, so they follow the user to every computer),
 * stored in users.preferences. Only known keys with allowed values are kept; anything else is refused.
 * - width: 'full' (the whole window, default) or 'narrow' (about half of a wide screen, centred, as older apps)
 */
final class UserPreferences
{
    public const DEFAULTS = ['width' => 'full'];

    /** allowed values per key */
    public const VALUES = ['width' => ['full', 'narrow']];

    public static function get(?User $user, string $key): mixed
    {
        $value = $user?->preferences[$key] ?? null;
        return self::valid($key, $value) ? $value : (self::DEFAULTS[$key] ?? null);
    }

    public static function valid(string $key, mixed $value): bool
    {
        return isset(self::VALUES[$key]) && in_array($value, self::VALUES[$key], true);
    }

    /** Saves one choice; false (nothing saved) for an unknown key or value. */
    public static function set(User $user, string $key, mixed $value): bool
    {
        if (! self::valid($key, $value)) {
            return false;
        }
        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], [$key => $value])])->save();
        return true;
    }
}
