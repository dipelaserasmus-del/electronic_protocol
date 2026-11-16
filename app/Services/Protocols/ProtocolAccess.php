<?php

namespace App\Services\Protocols;

use App\Models\Config;
use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\ProtocolNumbering;
use Carbon\Carbon;

/**
 * R1a: which parts of the protocol form a user may change, for a new or an existing protocol.
 *
 * The rules are the legacy form's (ProtocolController::index before R1a), unchanged, but now in one place so the
 * server applies them too: before, they only made fields read-only in the browser and the server stored whatever was
 * posted. Sections: head (folder, subject, related numbers, keywords, notes), in (incoming), out (outgoing), the
 * done date, the number/year and the protocol date.
 *
 * Settings: allowWriterUpdateProtocol (0 nobody / 1 the writer who registered it / 2 all writers),
 * allowWriterUpdateProtocolTimeInMinutes (0 = no limit), protocolValidate, allowWriterChangeDate (R1a).
 */
final class ProtocolAccess
{
    public bool $canSave = true;
    public bool $head = true;
    public bool $in = true;
    public bool $out = true;
    public bool $doneDate = true;
    /** Number and year (only administrators with "Έλεγχοι καταχώρισης" OFF) */
    public bool $number = false;
    /** Protocol date: as the number, or writers/assigners when the setting allowWriterChangeDate is on */
    public bool $date = false;
    public bool $canDelete = false;
    public bool $canAssign = false;
    /** Seconds left to edit (0 = no countdown), as the legacy form showed it */
    public int $secondsLeft = 0;
    /** Legacy colour/title of the form header */
    public string $tone = 'new';

    public static function for(User $user, Protocol $protocol): self
    {
        $a = new self();
        $role = ProtocolPolicy::role($user);
        $minutes = (int) (Config::getConfigValueOf('allowWriterUpdateProtocolTimeInMinutes') ?? 0);
        $allow = (int) Config::getConfigValueOf('allowWriterUpdateProtocol');
        $existing = $protocol->exists;
        $isHandler = $existing && ProtocolPolicy::isAssigned($user, $protocol, 'd');

        $a->number = app(ProtocolNumbering::class)->userMayChooseNumber($user);
        $a->date = $a->number || self::writerMayChangeDate($user);
        $a->canDelete = $a->number && $existing;   // the legacy form showed "delete" with the number unlocked
        $a->canAssign = in_array($role, [ProtocolPolicy::ADMIN, ProtocolPolicy::ASSIGNER], true);
        $a->tone = $existing ? 'edit' : 'new';

        $inTimeFirst = $inTimeLast = true;
        if ($existing) {
            $limit = Carbon::now()->subMinutes($minutes)->getTimestamp();
            $inTimeFirst = $protocol->created_at && $protocol->created_at->getTimestamp() > $limit;
            $inTimeLast = $protocol->updated_at && $protocol->updated_at->getTimestamp() > $limit;
        }
        $secondsSinceEdit = $existing && $protocol->updated_at ? Carbon::now()->getTimestamp() - $protocol->updated_at->getTimestamp() : 0;

        if ($role === ProtocolPolicy::READER) {
            $a->lockAll();
            $a->tone = 'readonly';
            // a reader assigned for διεκπεραίωση may only set the done date ("Διεκπεραιώθηκε", own action, not a
            // save of the form), within the time limit on own entries
            if ($isHandler) {
                $a->doneDate = ! ($minutes && (int) $protocol->user_id === (int) $user->id && ! $inTimeLast);
                if ($a->doneDate && $protocol->diekp_date) {
                    $a->secondsLeft = $minutes * 60 - $secondsSinceEdit;
                }
            }
        }

        if (in_array($role, [ProtocolPolicy::WRITER, ProtocolPolicy::ASSIGNER], true) && $existing) {
            // allowWriterUpdateProtocol 0 / 1 decides first. The legacy form then opened everything when no time limit
            // was set, even with "nobody may edit", while the server (S02 policy) refused that save: now both refuse.
            if ($allow === 0 || ($allow === 1 && (int) $protocol->user_id !== (int) $user->id)) {
                $a->lockAll();
            } elseif ($minutes) {
                if (! $inTimeFirst && ! $inTimeLast) {
                    $a->lockAll();
                }
                if (! $inTimeFirst && $inTimeLast) {
                    $hasIn = self::hasIncoming($protocol);
                    $hasOut = self::hasOutgoing($protocol);
                    if ($hasIn && $hasOut) {
                        $a->head = false;
                        $a->in = false;
                    }
                    if (! $hasIn && $hasOut) {
                        $a->in = false;
                    }
                }
            }
            if ($a->canSave) {
                $a->secondsLeft = $minutes ? $minutes * 60 - $secondsSinceEdit : 0;
                $a->tone = 'edit';
            } else {
                $a->tone = 'readonly';
            }
            // a protocol without subject is open to everyone who can write
            if (! $protocol->thema) {
                $a->openAll();
            }
            // the user assigned for διεκπεραίωση: only the outgoing part and the done date until it is done
            if ($isHandler && ! $protocol->diekp_date) {
                $a->openAll();
                $a->head = false;
                $a->in = false;
            } elseif ($isHandler && $protocol->diekp_date && $minutes && ! $inTimeLast) {
                $a->lockAll();
                $a->tone = 'readonly';
            }
        }

        if (! $a->canSave) {
            $a->number = false;
            $a->date = false;
        }
        if ($a->number && $existing) {
            $a->tone = 'admin';
        }
        if ($a->secondsLeft < 0) {
            $a->secondsLeft = 0;
        }

        return $a;
    }

    /** R1a setting "Ο Συγγραφέας μπορεί να αλλάξει την ημερομηνία πρωτοκόλλου" (writers and assigners). */
    public static function writerMayChangeDate(User $user): bool
    {
        return in_array(ProtocolPolicy::role($user), [ProtocolPolicy::WRITER, ProtocolPolicy::ASSIGNER, ProtocolPolicy::ADMIN], true)
            && (bool) Config::getConfigValueOf('allowWriterChangeDate');
    }

    public static function hasIncoming(Protocol $p): bool
    {
        return (bool) ($p->in_num || $p->in_date || $p->in_topos_ekdosis || $p->in_arxi_ekdosis || $p->in_paraliptis || $p->in_perilipsi);
    }

    public static function hasOutgoing(Protocol $p): bool
    {
        return (bool) ($p->out_to || $p->out_date || $p->out_perilipsi);
    }

    private function lockAll(): void
    {
        $this->canSave = $this->head = $this->in = $this->out = $this->doneDate = false;
        $this->secondsLeft = 0;
    }

    private function openAll(): void
    {
        $this->canSave = $this->head = $this->in = $this->out = $this->doneDate = true;
        $this->secondsLeft = 0;
        $this->tone = 'edit';
    }
}
