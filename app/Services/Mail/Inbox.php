<?php

namespace App\Services\Mail;

use App\Support\Mail\MailContent;
use App\Support\Mail\MailParser;
use Illuminate\Support\Facades\Cache;

/**
 * The inbox page's e-mails (R5a). Every visit asks the server only for the list of UIDs; messages are downloaded
 * once, in one request for all that are missing, and read from MailCache afterwards.
 *
 * Several users may open the inbox at the same time: downloads into one mailbox's cache take turns (a lock), and
 * whoever waited finds the messages already there instead of downloading them again.
 */
final class Inbox
{
    /** Longest wait for another user's download, in seconds (then this user downloads too: correct, just slower). */
    private const LOCK_WAIT = 60;

    /** Changes when MailContent::summary changes, so older cached summaries are not used. */
    private const SUMMARY_VERSION = 2;   // 2: R5b, winmail.dat opened

    public function __construct(private MailCache $cache) {}

    /**
     * One page of messages received since $since. Order as the inbox always had it: by arrival (UID), newest
     * first if $newestFirst; within the page, by the e-mails' Date header.
     */
    public function page(Mailbox $mailbox, \DateTimeInterface $since, int $page, int $perPage, bool $newestFirst): InboxPage
    {
        $validity = $mailbox->uidValidity();
        $all = $mailbox->uidsSince($since);
        $ordered = $newestFirst ? array_reverse($all) : $all;
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $uids = array_slice($ordered, ($page - 1) * $perPage, $perPage);

        $raw = $this->load($mailbox, $validity, $uids, $all);

        $messages = [];
        foreach ($uids as $uid) {
            if (isset($raw[$uid]) && ($summary = $this->summary($mailbox->id(), $validity, $uid, $raw[$uid])) !== null) {
                $messages[] = new InboxMessage($uid, $summary);
            }
        }
        usort($messages, fn (InboxMessage $a, InboxMessage $b) => $newestFirst
            ? $b->timestamp() <=> $a->timestamp()
            : $a->timestamp() <=> $b->timestamp());

        return new InboxPage($mailbox->id(), $validity, count($all), $page, $perPage, $messages);
    }

    /**
     * R5c: what the page shows of a cached message (MailContent::summary), itself cached: the page re-renders on every
     * click and a large e-mail should be parsed once, not each time. Null when the message is not in the cache.
     */
    public function summary(string $mailboxId, string $uidValidity, int $uid, ?string $raw = null): ?array
    {
        $key = 'inbox-summary:'.self::SUMMARY_VERSION.':'.sha1($mailboxId).":$uidValidity:$uid";
        if (is_array($cached = Cache::get($key))) {
            return $cached;
        }
        $raw ??= $this->raw($mailboxId, $uidValidity, $uid);
        if ($raw === null) {
            return null;
        }
        $summary = MailContent::summary(MailParser::parse($raw));
        Cache::put($key, $summary, now()->addDays(7));

        return $summary;
    }

    /** Raw e-mail of a message shown on the inbox page (null when it is not in the cache). */
    public function raw(string $mailboxId, string $uidValidity, int $uid): ?string
    {
        return $this->cache->get($mailboxId, $uidValidity, $uid);
    }

    /**
     * Downloads what is missing and expires the rest, under the mailbox's lock (one request at a time per mailbox).
     *
     * @param list<int> $uids the page @param list<int> $all every UID in the folder @return array<int, string>
     */
    private function load(Mailbox $mailbox, string $validity, array $uids, array $all): array
    {
        $missing = fn () => array_values(array_filter($uids, fn (int $uid) => ! $this->cache->has($mailbox->id(), $validity, $uid)));

        $work = function () use ($mailbox, $validity, $missing, $all) {
            if (($todo = $missing()) !== []) {   // again inside the lock: another user may have done it meanwhile
                foreach ($mailbox->fetchRaw($todo) as $uid => $raw) {
                    $this->cache->put($mailbox->id(), $validity, (int) $uid, $raw);
                }
            }
            // expiry: drop what is no longer in the folder (registered, moved, deleted) and older UIDVALIDITYs
            $this->cache->keepOnly($mailbox->id(), $validity, $all);
        };
        try {
            Cache::lock($this->cache->lockName($mailbox->id()), self::LOCK_WAIT * 2)->block(self::LOCK_WAIT, $work);
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            // another request holds the lock too long: download what this page needs, skip expiry (next visit does it)
            foreach ($mailbox->fetchRaw($missing()) as $uid => $raw) {
                $this->cache->put($mailbox->id(), $validity, (int) $uid, $raw);
            }
        }

        $raw = [];
        foreach ($uids as $uid) {
            if (($message = $this->cache->get($mailbox->id(), $validity, $uid)) !== null) {
                $raw[$uid] = $message;
            }
        }

        return $raw;
    }
}
