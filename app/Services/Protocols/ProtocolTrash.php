<?php

namespace App\Services\Protocols;

use App\Models\Attachment;
use App\Models\Config;
use App\Models\Protocol;
use App\Models\TrashedProtocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use App\Services\Attachments\Trash;
use App\Services\ProtocolNumbering;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * R4h (owner, 2026-11-24): «Διαγραφή» moves a protocol to the trash instead of deleting it.
 *
 * - trash():   a snapshot (row, assignments, the attachments trashed with it) in trashed_protocols; the protocols row
 *              goes, so its number is free again; its attachments go to the attachments' trash (R4b) with it.
 * - restore(): administrators any protocol; Συγγραφέας and Αναθέτων only those they registered; Αναγνώστης none.
 *              It gets its own id back, and its original number if that number / year is still free, otherwise a
 *              new number (the year in use, taken under the numbering lock as on save; date = today). Its assignments
 *              and attachments come back; Παρατηρήσεις say where it came from.
 * - purge():   for good (administrators; and the daily clean-up after the attachments' retention days).
 */
final class ProtocolTrash
{
    public function __construct(private readonly Trash $files, private readonly ProtocolNumbering $numbering)
    {
    }

    public static function mayRestore(User $user, TrashedProtocol $t): bool
    {
        $role = ProtocolPolicy::role($user);
        return $role === ProtocolPolicy::ADMIN
            || (in_array($role, [ProtocolPolicy::WRITER, ProtocolPolicy::ASSIGNER], true) && $t->user_id !== null && $t->user_id === (int) $user->id);
    }

    public static function mayPurge(User $user): bool
    {
        return ProtocolPolicy::role($user) === ProtocolPolicy::ADMIN;
    }

    /** May open the trash at all (its protocols tab) */
    public static function mayOpen(User $user): bool
    {
        return in_array(ProtocolPolicy::role($user), [ProtocolPolicy::ADMIN, ProtocolPolicy::WRITER, ProtocolPolicy::ASSIGNER], true);
    }

    /** The trashed protocols this user sees: administrators all, others only those they may restore */
    public static function visibleTo(User $user): Builder
    {
        $q = TrashedProtocol::query();
        if (ProtocolPolicy::role($user) !== ProtocolPolicy::ADMIN) {
            self::mayOpen($user) ? $q->where('user_id', $user->id) : $q->whereRaw('1 = 0');
        }
        return $q;
    }

    public function trash(User $by, Protocol $protocol): TrashedProtocol
    {
        $label = $this->files->label($protocol);
        return DB::transaction(function () use ($by, $protocol, $label) {
            $attachments = [];
            foreach (Attachment::withTrashed()->where('protocol_id', $protocol->id)->get() as $a) {
                if ($a->trashed()) {
                    $a->protocol_label ??= $label;   // trashed before, on its own: stays there, does not come back with it
                    $a->saveQuietly();
                } else {
                    $this->files->trash($a, $by, $label);
                    $attachments[] = $a->id;
                }
            }
            $row = DB::table('protocols')->where('id', $protocol->id)->first();
            $t = TrashedProtocol::create([
                'protocol_id' => $protocol->id,
                'protocolnum' => (int) $protocol->protocolnum,
                'etos' => (int) $protocol->etos,
                'protocoldate' => $protocol->dateOf('protocoldate')?->format('Y-m-d'),
                'thema' => $protocol->thema,
                'fakelos' => $protocol->fakelos,
                'user_id' => $protocol->user_id,
                'data' => [
                    'protocol' => (array) $row,
                    'assignments' => DB::table('protocol_assignments')->where('protocol_id', $protocol->id)->get()->map(fn ($r) => (array) $r)->all(),
                    'attachments' => $attachments,
                ],
                'deleted_by' => $by->id,
                'deleted_at' => Carbon::now(),
            ]);
            DB::table('protocol_assignments')->where('protocol_id', $protocol->id)->delete();
            DB::table('protocols')->where('id', $protocol->id)->delete();
            return $t;
        });
    }

    /**
     * @return array{protocol: Protocol, newNumber: bool, original: string}
     * @throws AuthorizationException
     */
    public function restore(User $by, TrashedProtocol $t): array
    {
        if (! self::mayRestore($by, $t)) {
            throw new AuthorizationException('Δεν επιτρέπεται η επαναφορά αυτού του πρωτοκόλλου.');
        }
        $original = $t->label();
        $row = $t->data['protocol'] ?? [];
        $newNumber = false;
        $protocol = $this->numbering->locked(function () use ($by, $t, $row, &$newNumber) {
            if (DB::table('protocols')->where('id', $t->protocol_id)->exists()) {
                throw new \RuntimeException("protocol id {$t->protocol_id} is in use");
            }
            $values = $row;
            $taken = Protocol::where('etos', $t->etos)->where('protocolnum', $t->protocolnum)->lockForUpdate()->exists();
            if ($taken) {
                $newNumber = true;
                $year = (int) (Config::getConfigValueOf('yearInUse') ?: Carbon::now()->year);
                $values['etos'] = $year;
                $values['protocolnum'] = $this->numbering->next($year);
                $values['protocoldate'] = Carbon::now()->format('Y-m-d');
            }
            $date = $t->protocoldate?->format('d/m/Y') ?? '—';
            $note = 'Επαναφορά από τον κάδο ' . Carbon::now()->format('d/m/Y H:i') . " ({$by->name}) — αρχικός αρ. πρωτ. {$t->label()}, ημ/νία $date";
            $values['paratiriseis'] = trim(trim((string) ($row['paratiriseis'] ?? '')) . "\n" . $note);
            $values['id'] = $t->protocol_id;
            $values['updated_at'] = Carbon::now();
            DB::table('protocols')->insert($values);
            foreach ($t->data['assignments'] ?? [] as $a) {
                unset($a['id']);
                DB::table('protocol_assignments')->insert(['protocol_id' => $t->protocol_id] + $a);
            }
            $t->delete();
            return Protocol::findOrFail($t->protocol_id);
        });
        foreach (Attachment::onlyTrashed()->whereIn('id', $t->data['attachments'] ?? [])->where('protocol_id', $protocol->id)->get() as $a) {
            $this->files->restore($a);
        }
        return ['protocol' => $protocol, 'newNumber' => $newNumber, 'original' => $original];
    }

    /** For good: the snapshot and the attachments that were trashed with it */
    public function purge(TrashedProtocol $t): void
    {
        foreach ($t->attachments()->get() as $a) {
            $this->files->purge($a);
        }
        $t->delete();
    }

    /** The daily clean-up: protocols in the trash for longer than the retention days (as attachments). */
    public function purgeExpired(?Carbon $now = null): int
    {
        $days = $this->files->retentionDays();
        if ($days === 0) {
            return 0;
        }
        $count = 0;
        TrashedProtocol::where('deleted_at', '<', ($now ?? Carbon::now())->copy()->subDays($days))->orderBy('id')
            ->chunkById(100, function ($rows) use (&$count) {
                foreach ($rows as $t) {
                    $this->purge($t);
                    $count++;
                }
            });
        return $count;
    }

    public function purgeDate(TrashedProtocol $t): ?Carbon
    {
        $days = $this->files->retentionDays();
        return $days === 0 || ! $t->deleted_at ? null : $t->deleted_at->copy()->addDays($days);
    }
}
