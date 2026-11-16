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

    /** @return list<array<string, mixed>> */
    public static function for(User $user): array
    {
        $role = $user->role?->role;
        $items = [['label' => 'Νέο', 'url' => url('/home')]];
        $items[] = ['label' => 'Λίστα', 'children' => self::lists($user, $role)];

        if ($role !== self::READER && InboxAccess::allows($user)) {
            $items[] = ['label' => 'Εισερχόμενα', 'url' => url('/viewEmails')];
        }
        $items[] = ['label' => 'Αναζήτηση', 'url' => url('/find')];
        // printing lists every protocol, so it is hidden when the list is limited for writers/readers
        // (same rule as ProtocolController::limitProtocolAccessList, but for this user, not the logged-in one)
        if (! (in_array($role, [self::WRITER, self::READER], true) && Config::getConfigValueOf('limitProtocolAccessList'))) {
            $items[] = ['label' => 'Εκτύπωση', 'children' => [
                ['label' => 'Πρωτόκολλο', 'url' => url('/print')],
                ['label' => 'Συνημμένα', 'url' => url('/printAttachments')],
            ]];
        }
        $items[] = ['label' => 'Διατήρηση', 'url' => url('/keep')];
        if ($role === self::ADMIN) {
            $items[] = ['label' => 'Διαχείριση', 'children' => [
                ['label' => 'Χρήστες', 'url' => url('/users')],
                ['label' => 'Backup', 'url' => url('/backups')],
                ['label' => 'Εκκαθάριση', 'url' => url('/arxeio')],
                ['label' => 'Ρυθμίσεις', 'url' => url('/settings')],
            ]];
        }

        return $items;
    }

    private static function lists(User $user, ?string $role): array
    {
        $lists = [
            ['label' => 'Όλα τα πρωτόκολλα', 'url' => self::landing($user)],
            ['divider' => true],
            ['header' => 'Δικά μου'],
            ['label' => 'προς Διεκπεραίωση', 'url' => url('/home/list/d')],
            ['label' => 'προς Ενημέρωση', 'url' => url('/home/list/e')],
            ['label' => 'Διεκπεραιώθηκε', 'url' => url('/home/list/f')],
        ];
        if (! in_array($role, [self::ADMIN, self::ASSIGNER], true)) {
            return $lists;
        }

        array_push($lists,
            ['divider' => true],
            ['header' => 'Όλοι οι χρήστες'],
            ['label' => 'προς Διεκπεραίωση', 'url' => url('/home/list/d/a')],
            ['label' => 'προς Ενημέρωση', 'url' => url('/home/list/e/a')],   // the old menu's link here went to /d by mistake
            ['label' => 'Διεκπεραιώθηκε', 'url' => url('/home/list/f/a')],
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
