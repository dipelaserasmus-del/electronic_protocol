<?php

namespace App\Support;

use App\Models\Role;

/**
 * R6a: the four roles (rows of `roles`, named since 2016) and what each may do, as the users page explains them.
 * The rules themselves live where they apply (ProtocolAccess, ProtocolPolicy, Navigation, the controllers'
 * middleware); this text describes them.
 */
final class Roles
{
    public const ADMIN = 'Διαχειριστής';
    public const ASSIGNER = 'Αναθέτων';
    public const WRITER = 'Συγγραφέας';
    public const READER = 'Αναγνώστης';

    /** Most rights first */
    public const DESCRIPTIONS = [
        self::ADMIN => 'Ό,τι ο Αναθέτων, και επιπλέον Χρήστες, Φάκελοι, Ρυθμίσεις, Backup, Εκκαθάριση και Κάδος. Με τους «Ελέγχους καταχώρισης» κλειστούς επιλέγει αριθμό και ημερομηνία και διαγράφει πρωτόκολλα.',
        self::ASSIGNER => 'Ό,τι ο Συγγραφέας, και επιπλέον αναθέτει πρωτόκολλα για διεκπεραίωση ή ενημέρωση και βλέπει τις λίστες όλων των χρηστών.',
        self::WRITER => 'Καταχωρίζει πρωτόκολλα και e-mail. Διορθώνει πρωτόκολλα όπως ορίζουν οι Ρυθμίσεις. Με τον «Περιορισμό λίστας» βλέπει μόνο όσα έγραψε ή του ανατέθηκαν.',
        self::READER => 'Βλέπει πρωτόκολλα (με τον «Περιορισμό λίστας» μόνο όσα του ανατέθηκαν) και σημειώνει «Διεκπεραιώθηκε» σε όσα του ανατέθηκαν. Δεν καταχωρίζει.',
    ];

    public static function adminId(): int
    {
        return (int) Role::whereRole(self::ADMIN)->value('id');
    }

    public static function describe(?string $role): string
    {
        return self::DESCRIPTIONS[$role] ?? '';
    }
}
