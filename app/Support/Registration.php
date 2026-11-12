<?php

namespace App\Support;

use App\Role;
use App\User;

/**
 * Self-registration exists only to create the very first administrator of a new installation.
 *
 * It is open only when BOTH are true:
 *  - the marker file storage/conf/.denyregister does not exist, and
 *  - no active administrator exists in the database.
 * The database check makes the rule safe even if the marker file is deleted or the
 * storage folder is replaced.
 */
class Registration
{
    public static function markerFile(): string
    {
        return storage_path('conf/.denyregister');
    }

    public static function isOpen(): bool
    {
        if (file_exists(self::markerFile())) {
            return false;
        }
        $adminRoleId = Role::whereRole('Διαχειριστής')->value('id');

        return !User::where('role_id', $adminRoleId)->whereNotNull('active')->exists();
    }

    public static function close(): void
    {
        @file_put_contents(self::markerFile(), "Η ύπαρξη του αρχείου .denyregister κλείνει την εγγραφή νέων χρηστών.\n"
            . "Δημιουργείται με την εγγραφή του πρώτου Διαχειριστή. Η εγγραφή ανοίγει ξανά μόνο αν δεν υπάρχει\n"
            . "κανένας ενεργός Διαχειριστής ΚΑΙ διαγραφεί αυτό το αρχείο.\n");
    }

    public static function adminRoleId(): int
    {
        return (int) Role::whereRole('Διαχειριστής')->value('id');
    }
}
