<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * X01: sign-ins in the audit log. A failed sign-in keeps only the user name that was typed (never the password) and,
 * as its entity, the account it belongs to when there is one.
 */
final class AuditAuthEvents
{
    public function login(Login $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        AuditLog::record('login', 'user', $user?->id, context: array_filter(['remember' => $event->remember]), user: $user);
    }

    public function logout(Logout $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        AuditLog::record('logout', 'user', $user?->id, user: $user, asSignedIn: false);
    }

    public function failed(Failed $event): void
    {
        $user = $event->user instanceof User ? $event->user : null;
        $typed = (string) ($event->credentials['username'] ?? $event->credentials['email'] ?? '');
        // nobody signed in: the entry names the account tried (entity) and what was typed, not who typed it
        AuditLog::record('login_failed', 'user', $user?->id, context: ['typed' => mb_substr($typed, 0, 100)], asSignedIn: false);
    }
}
