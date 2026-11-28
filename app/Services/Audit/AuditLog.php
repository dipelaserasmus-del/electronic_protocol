<?php

namespace App\Services\Audit;

use App\Models\AuditEntry;
use App\Models\Protocol;
use App\Models\User;
use Carbon\Carbon;

/**
 * X01: writes the audit log (who did what and when). Most entries come by themselves from the models that use
 * App\Models\Concerns\Audited (created / updated / trashed / restored / purged / deleted); the rest are written where
 * they happen: logins (App\Listeners\AuditAuthEvents), downloads, a protocol to and from the trash, e-mail
 * registrations, folder merges. docs/AUDIT-LOG.md lists them.
 *
 * Writing an entry never stops what the user did: a failure is reported (laravel.log) and the work goes on.
 */
final class AuditLog
{
    /** Inside asSystem(): done by the application on its own (the daily clean-up), not by the signed-in user */
    private static bool $system = false;

    /** The actions, as the log stores them (X02 shows them in Greek) */
    public const ACTIONS = ['created', 'updated', 'trashed', 'restored', 'purged', 'deleted',
        'login', 'logout', 'login_failed', 'downloaded', 'email_registered', 'folders_merged'];

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>|null  $changes  field => [old, new]
     * @param  array<string, mixed>  $context  what else helps to read it (a number, a file name, a key)
     * @param  User|null  $user  who did it: the signed-in user unless given (a login, a failed login: none)
     */
    public static function record(string $action, ?string $type = null, ?int $id = null, ?int $protocolId = null,
        ?array $changes = null, array $context = [], ?User $user = null, bool $asSignedIn = true): void
    {
        try {
            $user ??= $asSignedIn && ! self::$system ? auth()->user() : null;
            $context += self::$system ? ['automatic' => true] : [];
            AuditEntry::create([
                'created_at' => Carbon::now(),
                'user_id' => $user?->id,
                'username' => $user?->username,
                'action' => $action,
                'entity_type' => $type,
                'entity_id' => $id,
                'protocol_id' => $protocolId,
                'changes' => $changes ?: null,
                'context' => ($context + (app()->runningInConsole() && ! app()->runningUnitTests() ? ['console' => true] : [])) ?: null,
                'ip' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** An entry about one protocol, with its number for reading it after the protocol is gone */
    public static function protocol(string $action, Protocol $p, array $context = [], ?User $user = null): void
    {
        self::record($action, 'protocol', (int) $p->id, (int) $p->id, null, ['ref' => $p->protocolnum.'/'.$p->etos] + $context, $user);
    }

    /**
     * Runs $work as the application's own (the trash's daily clean-up runs during some user's request: the entries
     * must not name that user).
     *
     * @template T
     * @param  callable(): T  $work
     * @return T
     */
    public static function asSystem(callable $work): mixed
    {
        $was = self::$system;
        self::$system = true;
        try {
            return $work();
        } finally {
            self::$system = $was;
        }
    }

    /** AUDIT_KEEP_DAYS: entries older than this many days are removed once a day; empty or 0 keeps them all */
    public static function keepDays(): int
    {
        return max(0, (int) config('app.audit_keep_days'));
    }

    /** The daily clean-up (with the trash's, App\Http\Middleware\PurgeTrashDaily). Returns how many were removed. */
    public static function purgeExpired(?Carbon $now = null): int
    {
        $days = self::keepDays();
        if ($days === 0) {
            return 0;
        }

        return AuditEntry::query()->where('created_at', '<', ($now ?? Carbon::now())->copy()->subDays($days))->toBase()->delete();
    }
}
