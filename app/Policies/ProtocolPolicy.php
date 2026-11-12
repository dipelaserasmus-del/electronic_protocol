<?php

namespace App\Policies;

use App\Config;
use App\Protocol;
use App\User;

/**
 * S02: single source of truth for who may see / change a protocol and its attachments.
 *
 * Roles: Διαχειριστής (admin), Αναθέτων (assigner), Συγγραφέας (writer), Αναγνώστης (reader).
 * Setting "limitProtocolAccessList": writers and readers only see protocols they created or that are
 * assigned to them for διεκπεραίωση (d) or ενημέρωση (e).
 */
class ProtocolPolicy
{
    public const ADMIN = 'Διαχειριστής';
    public const ASSIGNER = 'Αναθέτων';
    public const WRITER = 'Συγγραφέας';
    public const READER = 'Αναγνώστης';

    public static function role(User $user): ?string
    {
        return $user->role?->role;
    }

    /** Is this user restricted to "own / assigned" protocols? */
    public static function isLimited(User $user): bool
    {
        return (bool) Config::getConfigValueOf('limitProtocolAccessList')
            && in_array(self::role($user), [self::WRITER, self::READER], true);
    }

    /** User ids assigned in the legacy text column, e.g. "d14,e10" → type d: [14], type e: [10] */
    public static function assignedIds(Protocol $protocol, string $type): array
    {
        $ids = [];
        foreach (explode(',', (string) $protocol->diekperaiosi) as $part) {
            $part = trim($part);
            if ($part !== '' && $part[0] === $type && ctype_digit(substr($part, 1))) {
                $ids[] = (int) substr($part, 1);
            }
        }
        return $ids;
    }

    public static function isAssigned(User $user, Protocol $protocol, ?string $type = null): bool
    {
        $types = $type ? [$type] : ['d', 'e'];
        foreach ($types as $t) {
            if (in_array((int) $user->id, self::assignedIds($protocol, $t), true)) {
                return true;
            }
        }
        return false;
    }

    public function view(User $user, Protocol $protocol): bool
    {
        if (!self::isLimited($user)) {
            return true;
        }
        return (int) $protocol->user_id === (int) $user->id || self::isAssigned($user, $protocol);
    }

    /**
     * Save changes / delete attachments. Mirrors the form's rules for writers:
     * setting allowWriterUpdateProtocol 0 = writers can't edit, 1 = only their own entries, 2 = all.
     * (The time-window part of that setting is still enforced by the form only; see task R1a.)
     */
    public function update(User $user, Protocol $protocol): bool
    {
        $role = self::role($user);
        if ($role === self::READER || !$this->view($user, $protocol)) {
            return false;
        }
        if (in_array($role, [self::WRITER, self::ASSIGNER], true)) {
            $allow = (int) Config::getConfigValueOf('allowWriterUpdateProtocol');
            if ($allow === 0) {
                return false;
            }
            if ($allow === 1 && (int) $protocol->user_id !== (int) $user->id) {
                return false;
            }
        }
        return true;
    }

    /** Deleting a whole protocol: administrators only (the form shows the button only to them). */
    public function delete(User $user, Protocol $protocol): bool
    {
        return self::role($user) === self::ADMIN;
    }

    /** Assign for διεκπεραίωση / ενημέρωση: administrators and assigners (as in the form). */
    public function assign(User $user, Protocol $protocol): bool
    {
        return in_array(self::role($user), [self::ADMIN, self::ASSIGNER], true);
    }

    /** Mark as διεκπεραιωμένο: administrators, assigners, the user assigned for διεκπεραίωση, or the creator. */
    public function complete(User $user, Protocol $protocol): bool
    {
        if (!$this->view($user, $protocol)) {
            return false;
        }
        return in_array(self::role($user), [self::ADMIN, self::ASSIGNER], true)
            || self::isAssigned($user, $protocol, 'd')
            || (int) $protocol->user_id === (int) $user->id;
    }
}
