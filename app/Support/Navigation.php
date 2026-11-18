<?php

namespace App\Support;

use App\Models\Config;
use App\Models\User;

/**
 * Top bar of the rebuilt pages (N03): the same items and role rules as the old layout's menu
 * (resources/views/layouts/app.blade.php), as data so they can be tested per role.
 *
 * An item is ['label' => …, 'url' => …] or ['label' => …, 'children' => [...]]; a child can also be
 * ['header' => …] or ['divider' => true]; a per-user row carries 'links' (short label => url).
 */
final class Navigation
{
    public const ADMIN = 'Διαχειριστής';
    public const ASSIGNER = 'Αναθέτων';
    public const WRITER = 'Συγγραφέας';
    public const READER = 'Αναγνώστης';

    /** Where the brand link and "Όλα τα πρωτόκολλα" lead for this user (config/landing-page.php). */
    public static function landing(User $user): string
    {
        return url(config('landing-page.page.'.$user->role_id, Home::LIST));
    }

    /**
     * R3d (owner, 2026-11-18): four items so the bar fits a 1920 × 1080 screen with the search box: Νέο, Λίστα,
     * Εισερχόμενα and Διαχείριση, which now also holds Εκτύπωση and Διατήρηση (every role) above the administrators'
     * items. Αναζήτηση moved into the search box. Every item has an icon (Bootstrap Icons name in 'icon').
     *
     * @return list<array<string, mixed>>
     */
    public static function for(User $user): array
    {
        $role = $user->role?->role;
        $items = [['label' => 'Νέο', 'icon' => 'file-earmark-plus', 'url' => url('/home')]];
        $items[] = ['label' => 'Λίστα', 'icon' => 'list-ul', 'children' => self::lists($user, $role)];

        if ($role !== self::READER && InboxAccess::allows($user)) {
            $items[] = ['label' => 'Εισερχόμενα', 'icon' => 'inbox', 'url' => url('/inbox')];
        }

        $manage = [];
        // printing lists every protocol, so it is hidden when the list is limited for writers/readers
        // (same rule as ProtocolController::limitProtocolAccessList, but for this user, not the logged-in one)
        if (! (in_array($role, [self::WRITER, self::READER], true) && Config::getConfigValueOf('limitProtocolAccessList'))) {
            $manage[] = ['label' => 'Εκτύπωση πρωτοκόλλου', 'icon' => 'printer', 'url' => url('/print')];
            $manage[] = ['label' => 'Εκτύπωση συνημμένων', 'icon' => 'paperclip', 'url' => url('/printAttachments')];
        }
        $manage[] = ['label' => 'Φάκελοι και διατήρηση', 'icon' => 'folder2', 'url' => url('/keep')];
        if ($role === self::ADMIN) {
            array_push($manage,
                ['divider' => true],
                ['header' => 'Διαχειριστής'],
                ['label' => 'Χρήστες', 'icon' => 'people', 'url' => url('/users')],
                ['label' => 'Backup', 'icon' => 'database-down', 'url' => url('/backups')],
                ['label' => 'Εκκαθάριση', 'icon' => 'eraser', 'url' => url('/arxeio')],
                ['label' => 'Κάδος', 'icon' => 'trash3', 'url' => url('/trash')],
                ['label' => 'Ρυθμίσεις', 'icon' => 'sliders', 'url' => url('/settings')],
            );
        }
        $items[] = ['label' => 'Διαχείριση', 'icon' => 'gear', 'children' => $manage];

        return $items;
    }

    private static function lists(User $user, ?string $role): array
    {
        $lists = [
            ['label' => 'Όλα τα πρωτόκολλα', 'icon' => 'collection', 'url' => self::landing($user)],
            ['divider' => true],
            ['header' => 'Δικά μου'],
            ['label' => 'προς Διεκπεραίωση', 'icon' => 'hourglass-split', 'url' => url('/home/list/d')],
            ['label' => 'προς Ενημέρωση', 'icon' => 'bell', 'url' => url('/home/list/e')],
            ['label' => 'Διεκπεραιώθηκε', 'icon' => 'check2-circle', 'url' => url('/home/list/f')],
        ];
        if (! in_array($role, [self::ADMIN, self::ASSIGNER], true)) {
            return $lists;
        }

        array_push($lists,
            ['divider' => true],
            ['header' => 'Όλοι οι χρήστες'],
            ['label' => 'προς Διεκπεραίωση', 'icon' => 'hourglass-split', 'url' => url('/home/list/d/a')],
            ['label' => 'προς Ενημέρωση', 'icon' => 'bell', 'url' => url('/home/list/e/a')],   // the old menu's link here went to /d by mistake
            ['label' => 'Διεκπεραιώθηκε', 'icon' => 'check2-circle', 'url' => url('/home/list/f/a')],
            ['divider' => true],
            ['header' => 'Ανά χρήστη'],
        );
        foreach (User::my_active_users() as $u) {
            $lists[] = ['label' => $u->name, 'url' => url('/home/list/a/'.$u->id), 'links' => [
                'Διεκπ.' => url('/home/list/d/'.$u->id),
                'Ενημ.' => url('/home/list/e/'.$u->id),
                'Ολοκλ.' => url('/home/list/f/'.$u->id),
            ]];
        }

        return $lists;
    }
}
