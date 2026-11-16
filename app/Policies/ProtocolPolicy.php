<?php

namespace App\Policies;

use App\Models\Config;
use App\Models\Protocol;
use App\Models\User;
use App\Support\Assignments;

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

    /**
     * User ids assigned for type d (διεκπεραίωση) or e (ενημέρωση). D02: read from protocol_assignments; a protocol
     * not saved yet, or the form for "copy as new" (id cleared), has no rows, so its text "d14,e10" is read instead.
     */
    public static function assignedIds(Protocol $protocol, string $type): array
    {
        $rows = $protocol->exists && $protocol->getKey() !== null
            ? $protocol->assignments->map(fn ($a) => ['user_id' => $a->user_id, 'type' => $a->type])->all()
            : Assignments::parse($protocol->diekperaiosi);
        return array_values(array_map(fn ($a) => (int) $a['user_id'], array_filter($rows, fn ($a) => $a['type'] === $type)));
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
     * The finer rules (time limit, which parts of the form) are in App\Services\Protocols\ProtocolAccess (R1a).
     */
    public function update(User $user, Protocol $protocol): bool
    {
        $role = self::role($user);
        if ($role === self::READER || !$this->view($user, $protocol)) {
            return false;
        }
        if (in_array($role, [self::WRITER, self::ASSIGNER], true)) {
            // same exceptions as the form (ProtocolController::index):
            // - a protocol without subject is open to everyone who can write
            // - the user assigned for διεκπεραίωση may save (the form unlocks only the outgoing fields; R1a
            //   enforces that field restriction on the server too, ProtocolAccess)
            if (!$protocol->thema || self::isAssigned($user, $protocol, 'd')) {
                return true;
            }
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
