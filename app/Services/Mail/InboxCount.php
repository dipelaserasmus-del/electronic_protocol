<?php

namespace App\Services\Mail;

use App\Models\Config;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * R5f (owner, 2026-11-20): how many e-mails the inbox holds (all of them, read or not: the inbox should be empty when
 * the work is done), for the red badge next to Εισερχόμενα. The same messages as the inbox page: those of the last
 * daysToCheckEmailBack days.
 *
 * One count for everybody, kept for a minute: the badges of all users ask the mail server at most once a minute
 * together. Registering or marking an e-mail as read, by any user, refreshes it, so the others see it within their
 * next check (every minute, and when they come back to the tab).
 */
final class InboxCount
{
    public const KEY = 'inbox-count';

    public const SECONDS = 60;

    /** Seconds a request waits for another one counting at the same time (review of R5f); a property for the tests */
    public static int $lockWait = 10;

    public function __construct(private MailboxConnector $connector) {}

    /** The messages since this time are the inbox (also the inbox page's list) */
    public static function since(): Carbon
    {
        return Carbon::now()->subDays((int) Config::getConfigValueOf('daysToCheckEmailBack'))->startOfDay();
    }

    /** The count, from the server at most once a minute; null when the server cannot be reached */
    public function count(): ?int
    {
        $known = self::cached();
        if ($known !== null) {
            return $known;
        }
        // review of R5f: one request asks the server; the others wait for it and take its count
        try {
            return Cache::lock(self::KEY.':lock', self::$lockWait * 2)->block(self::$lockWait, fn () => self::cached() ?? $this->ask());
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            return self::cached();   // still counting elsewhere: what is known (the badge keeps what it shows)
        }
    }

    private function ask(): ?int
    {
        try {
            $mailbox = $this->connector->open((string) Config::getConfigValueOf('defaultImapEmail'), (string) config('imapFolders.inbox'));
            $count = count($mailbox->uidsSince(self::since()));
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
        self::put($count);

        return $count;
    }

    /** What is known without asking the server (for drawing the page) */
    public static function cached(): ?int
    {
        $value = Cache::get(self::KEY);

        return is_int($value) ? $value : null;
    }

    /** The inbox page just counted them */
    public static function put(int $count): void
    {
        Cache::put(self::KEY, $count, self::SECONDS);
    }

    /** An e-mail left the inbox: count again at the next check */
    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }
}
