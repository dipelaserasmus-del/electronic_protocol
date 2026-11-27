<?php

namespace App\Support;

use App\Models\Config;
use App\Models\User;

/**
 * Who may open "Εισερχόμενα Email": an IMAP account is set in Ρυθμίσεις and, if "allowedEmailUsers" is filled in,
 * the user's username appears in it. Used by the ProtocolEmail middleware and the top bar (N03), so both agree.
 * The list is usernames separated by spaces or commas, compared whole and without case (R6c; it was a substring
 * match: "anna" also passed with "annab" in the list).
 * R6m (owner, 2026-11-27): administrators always may, whether or not the list names them.
 */
final class InboxAccess
{
    public static function allows(User $user): bool
    {
        if (! Config::getConfigValueOf('defaultImapEmail')) {
            return false;
        }
        if ($user->role?->role === Roles::ADMIN) {
            return true;   // R6m: the list never shuts an administrator out
        }
        $allowed = Config::getConfigValueOf('allowedEmailUsers');

        if (! trim((string) $allowed)) {
            return true;
        }
        // R6c: whole usernames (before: any part of the text, so "nik" passed with "nikos" in the list)
        $names = preg_split('/[\s,;]+/u', mb_strtolower(trim((string) $allowed))) ?: [];

        return in_array(mb_strtolower((string) $user->username), $names, true);
    }
}
