<?php

namespace App\Support;

use App\Models\Config;
use App\Models\User;

/**
 * Who may open "Εισερχόμενα Email": an IMAP account is set in Ρυθμίσεις and, if "allowedEmailUsers" is filled in,
 * the user's username appears in it. Used by the ProtocolEmail middleware and the top bar (N03), so both agree.
 * The username check is a substring match, as it always was (e.g. "anna" also matches "annab").
 */
final class InboxAccess
{
    public static function allows(User $user): bool
    {
        if (! Config::getConfigValueOf('defaultImapEmail')) {
            return false;
        }
        $allowed = Config::getConfigValueOf('allowedEmailUsers');

        return ! $allowed || str_contains($allowed, (string) $user->username);
    }
}
