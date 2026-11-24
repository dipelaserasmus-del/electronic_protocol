<?php

namespace App\Support;

use App\Models\User;

/**
 * R3d: a user's own choices for the screens (owner, 2026-11-18: per user, so they follow the user to every computer),
 * stored in users.preferences. Only known keys with allowed values are kept; anything else is refused.
 * - width: 'full' (the whole window, default) or 'narrow' (about half of a wide screen, centred, as older apps)
 * - form (R1i): the protocol page's sections: one or two columns, where each section is, which are collapsed
 * - home (R3e): the user's home page: 'all', 'd' (προς Διεκπεραίωση) or 'e' (προς Ενημέρωση); none = the role's
 */
final class UserPreferences
{
    public const DEFAULTS = ['width' => 'full', 'stamped' => 'yes'];

    /** allowed values per key */
    public const VALUES = ['width' => ['full', 'narrow'], 'home' => ['all', 'd', 'e'], 'stamped' => ['yes', 'no']];   // R4f: files with the stamp or the original

    /** R3e (owner, 2026-11-20): the home pages a user can choose (key => [label, icon, address]) */
    public const HOMES = [
        'all' => ['Όλα τα πρωτόκολλα', 'collection', Home::LIST],
        'd' => ['προς Διεκπεραίωση', 'pencil-square', '/home/list/d'],   // R2j: the icons of the list's pills
        'e' => ['προς Ενημέρωση', 'eye', '/home/list/e'],
    ];

    public static function get(?User $user, string $key): mixed
    {
        $value = $user?->preferences[$key] ?? null;
        return self::valid($key, $value) ? $value : (self::DEFAULTS[$key] ?? null);
    }

    public static function valid(string $key, mixed $value): bool
    {
        return isset(self::VALUES[$key]) && in_array($value, self::VALUES[$key], true);
    }

    /**
     * R1i: sections of the protocol page, in three places: two columns and a full-width row below them. The default is
     * the layout of R1g (the rarely used Ανάθεση and receipt below everything else).
     */
    public const FORM_SECTIONS = ['general' => 'Γενικά', 'incoming' => 'Εισερχόμενο', 'done' => 'Διεκπεραίωση',
        'outgoing' => 'Εξερχόμενο', 'attachments' => 'Συνημμένα', 'assign' => 'Ανάθεση', 'receipt' => 'Απόδειξη παραλαβής'];

    public const FORM_ZONES = ['left', 'right', 'bottom'];

    public const FORM_DEFAULT = [
        'columns' => 2,
        'zones' => ['left' => ['general', 'incoming'], 'right' => ['done', 'outgoing', 'attachments'], 'bottom' => ['assign', 'receipt']],
        'collapsed' => [],
    ];

    /**
     * R1i: the user's arrangement of the protocol page, always complete and valid: unknown sections are dropped,
     * each section is in one place once, and sections missing from it (e.g. added later) go where the default has them.
     *
     * @return array{columns: int, zones: array<string, list<string>>, collapsed: list<string>}
     */
    public static function formLayout(?User $user): array
    {
        return self::normaliseForm($user?->preferences['form'] ?? null);
    }

    public static function setFormLayout(User $user, array $layout): void
    {
        $user->forceFill(['preferences' => array_merge($user->preferences ?? [], ['form' => self::normaliseForm($layout)])])->save();
    }

    public static function resetFormLayout(User $user): void
    {
        $prefs = $user->preferences ?? [];
        unset($prefs['form']);
        $user->forceFill(['preferences' => $prefs])->save();
    }

    private static function normaliseForm(mixed $layout): array
    {
        $layout = is_array($layout) ? $layout : [];
        $out = ['columns' => in_array($layout['columns'] ?? null, [1, 2], true) ? $layout['columns'] : self::FORM_DEFAULT['columns'], 'zones' => [], 'collapsed' => []];
        $placed = [];
        foreach (self::FORM_ZONES as $zone) {
            $out['zones'][$zone] = [];
            foreach ((array) ($layout['zones'][$zone] ?? []) as $id) {
                if (is_string($id) && isset(self::FORM_SECTIONS[$id]) && ! isset($placed[$id])) {
                    $out['zones'][$zone][] = $id;
                    $placed[$id] = true;
                }
            }
        }
        foreach (self::FORM_DEFAULT['zones'] as $zone => $ids) {
            foreach ($ids as $id) {
                if (! isset($placed[$id])) {
                    $out['zones'][$zone][] = $id;
                }
            }
        }
        $out['collapsed'] = array_values(array_intersect(array_keys(self::FORM_SECTIONS), (array) ($layout['collapsed'] ?? [])));
        return $out;
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
