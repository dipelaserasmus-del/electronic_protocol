<?php

namespace App\Support;

use App\Models\Config;
use App\Models\User;

/**
 * Top bar of the rebuilt pages (N03): the same items and role rules as the old layout's menu
 * (resources/views/layouts/app.blade.php), as data so they can be tested per role.
 *
 * An item is ['label' => …, 'url' => …] or ['label' => …, 'children' => [...]] (with a 'url' too, the label is a link
 * and a ▾ next to it opens the children); a child can also be
 * ['header' => …] or ['divider' => true]; a per-user row carries 'links' (short label => url).
 */
final class Navigation
{
    public const ADMIN = 'Διαχειριστής';
    public const ASSIGNER = 'Αναθέτων';
    public const WRITER = 'Συγγραφέας';
    public const READER = 'Αναγνώστης';

    /**
     * The user's home page: where the login, the brand link and Λίστα lead. R3e (owner, 2026-11-20): each user may
     * choose it (user menu → Αρχική σελίδα); without a choice, the role's (config/landing-page.php).
     */
    public static function landing(User $user): string
    {
        $choice = UserPreferences::get($user, 'home');

        return url($choice ? UserPreferences::HOMES[$choice][2] : config('landing-page.page.'.$user->role_id, Home::LIST));
    }

    /** R3e: which of UserPreferences::HOMES the home page is (the choice, or the role's page when it is one of them). */
    public static function homeChoice(User $user): ?string
    {
        foreach (UserPreferences::HOMES as $key => [, , $path]) {
            if (self::landing($user) === url($path)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * R3d (owner, 2026-11-18): few items so the bar fits with the search box: Νέο, Λίστα, Εισερχόμενα. Αναζήτηση
     * moved into the search box. Every item has an icon (Bootstrap Icons name in 'icon'). R3g (owner, 2026-11-20):
     * Διαχείριση moved into the user menu (manage()).
     *
     * @return array<int, array<string, mixed>> keyed by position (0 Νέο, 1 Λίστα, 2 Εισερχόμενα), the same for every role
     */
    public static function for(User $user): array
    {
        $role = $user->role?->role;
        // R3k (owner, 2026-11-26): no «Νέο» for readers, who cannot create (ProtocolPolicy::create). The keys stay the
        // same for every role (they are the items' ids: nav-1 is always Λίστα)
        $items = [];
        if ($role !== self::READER) {
            $items[0] = ['label' => 'Νέο', 'icon' => 'file-earmark-plus', 'url' => url('/home')];
        }
        // R1j (owner, 2026-11-20): Λίστα itself leads to the list; its ▾ opens the other lists
        $items[1] = ['label' => 'Λίστα', 'icon' => 'list-ul', 'url' => self::landing($user), 'home' => true, 'children' => self::lists($user, $role)];

        if ($role !== self::READER && InboxAccess::allows($user)) {
            $items[2] = ['label' => 'Εισερχόμενα', 'icon' => 'inbox', 'url' => url('/inbox'), 'count' => url('/inbox/count')];   // R5f
        }


        return $items;
    }

    /**
     * R3g (owner, 2026-11-20): Διαχείριση, in the user menu at the top right, in groups: Εκτύπωση and Φάκελοι (every
     * role), Διαχείριση (administrators). Printing lists every protocol, so it is hidden when the list is limited
     * for writers/readers (same rule as ProtocolController::limitProtocolAccessList, but for this user).
     *
     * @return array<string, list<array{label: string, icon: string, url: string}>> group title => items
     */
    public static function manage(User $user): array
    {
        $role = $user->role?->role;
        $groups = [];
        if (! (in_array($role, [self::WRITER, self::READER], true) && Config::getConfigValueOf('limitProtocolAccessList'))) {
            $groups['Εκτύπωση'] = [
                ['label' => 'Εκτύπωση πρωτοκόλλου', 'icon' => 'printer', 'url' => url('/print')],
                ['label' => 'Εκτύπωση συνημμένων', 'icon' => 'paperclip', 'url' => url('/printAttachments')],
            ];
        }
        $groups['Φάκελοι'] = [['label' => 'Φάκελοι και διατήρηση', 'icon' => 'folder2', 'url' => url('/keep')]];
        if (\App\Services\Integrity\Checks::allowed($user)) {   // A01: administrators, assigners, writers
            $groups['Φάκελοι'][] = ['label' => 'Έλεγχοι πρωτοκόλλου', 'icon' => 'clipboard-check', 'url' => url('/checks')];
        }
        if (\App\Livewire\StampDesigner::allowed($user)) {   // R4e; administrators only (R4i)
            $groups['Φάκελοι'][] = ['label' => 'Σφραγίδα αρχείων', 'icon' => 'patch-check', 'url' => url('/stamp')];
        }
        if ($role !== self::ADMIN && \App\Services\Protocols\ProtocolTrash::mayOpen($user)) {   // R4h: their own deleted protocols
            $groups['Φάκελοι'][] = ['label' => 'Κάδος', 'icon' => 'trash3', 'url' => url('/trash')];
        }
        if ($role === self::ADMIN) {
            $groups['Διαχείριση'] = [   // owner, 2026-11-20 (was «Διαχειριστής»)
                ['label' => 'Χρήστες', 'icon' => 'people', 'url' => url('/users')],
                ['label' => 'Ρυθμίσεις', 'icon' => 'sliders', 'url' => url('/settings')],
                ['label' => 'Backup', 'icon' => 'database-down', 'url' => url('/backups')],
                ['label' => 'Εκκαθάριση', 'icon' => 'eraser', 'url' => url('/arxeio')],
                ['label' => 'Κάδος', 'icon' => 'trash3', 'url' => url('/trash')],
                ['label' => 'Αρχεία καταγραφής', 'icon' => 'terminal', 'url' => url('/logs')],   // R6e
            ];
        }

        return $groups;
    }

    private static function lists(User $user, ?string $role): array
    {
        $lists = [
            ['label' => 'Όλα τα πρωτόκολλα', 'icon' => 'collection', 'url' => url(Home::LIST)],   // R3e: all, whatever the home page
            ['divider' => true],
            ['header' => 'Δικά μου'],
            ['label' => 'προς Διεκπεραίωση', 'icon' => 'pencil-square', 'url' => url('/home/list/d')],
            ['label' => 'προς Ενημέρωση', 'icon' => 'eye', 'url' => url('/home/list/e')],
            ['label' => 'Διεκπεραιώθηκε', 'icon' => 'check2-circle', 'url' => url('/home/list/f')],
        ];
        if (! in_array($role, [self::ADMIN, self::ASSIGNER], true)) {
            return $lists;
        }

        array_push($lists,
            ['divider' => true],
            ['header' => 'Όλοι οι χρήστες'],
            ['label' => 'προς Διεκπεραίωση', 'icon' => 'pencil-square', 'url' => url('/home/list/d/a')],
            ['label' => 'προς Ενημέρωση', 'icon' => 'eye', 'url' => url('/home/list/e/a')],   // the old menu's link here went to /d by mistake
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
