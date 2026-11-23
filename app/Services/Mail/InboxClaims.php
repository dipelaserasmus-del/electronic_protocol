<?php

namespace App\Services\Mail;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

/**
 * R5m: one e-mail is registered (or marked read) once, whoever has it on screen. Several users may have the same inbox
 * page open, also an older copy of it; the cached e-mail stays readable until the next visit, so without this two
 * quick clicks could make two protocols of one e-mail.
 *
 *   lock()     — taken while one user registers / marks an e-mail; a second user waits for nothing and is told so
 *   done()     — what happened to an e-mail already (protocol number, by whom), for 7 days
 *   remember() — written right after a registration / "read" succeeded
 *
 * Keys are per mailbox, UIDVALIDITY and UID (a UID means the same message only within one UIDVALIDITY).
 */
final class InboxClaims
{
    /** Longest a registration may hold an e-mail, in seconds (attachments, mail server move); then it expires */
    private const LOCK_SECONDS = 180;

    private const KEEP_DAYS = 7;

    public function lock(string $mailboxId, string $uidValidity, int $uid): Lock
    {
        return Cache::lock('inbox-claim-lock:'.$this->key($mailboxId, $uidValidity, $uid), self::LOCK_SECONDS);
    }

    /** @return array<string, mixed>|null the state the page shows for a done e-mail, plus 'user' (who did it) */
    public function done(string $mailboxId, string $uidValidity, int $uid): ?array
    {
        $state = Cache::get('inbox-claim:'.$this->key($mailboxId, $uidValidity, $uid));

        return is_array($state) ? $state : null;
    }

    /** @param array<string, mixed> $state */
    public function remember(string $mailboxId, string $uidValidity, int $uid, array $state): void
    {
        Cache::put('inbox-claim:'.$this->key($mailboxId, $uidValidity, $uid), $state, now()->addDays(self::KEEP_DAYS));
    }

    private function key(string $mailboxId, string $uidValidity, int $uid): string
    {
        return sha1($mailboxId).":$uidValidity:$uid";
    }
}
