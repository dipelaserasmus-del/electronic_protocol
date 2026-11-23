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
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $uids = $this->slice($all, $page, $perPage, $newestFirst);

        $raw = $this->load($mailbox, $validity, $uids, $all);

        $messages = [];
        foreach ($uids as $uid) {
            if (isset($raw[$uid]) && ($summary = $this->summary($mailbox->id(), $validity, $uid, $raw[$uid])) !== null) {
                $messages[] = new InboxMessage($uid, $summary);
            }
        }

        return new InboxPage($mailbox->id(), $validity, count($all), $page, $perPage, $this->sorted($messages, $newestFirst), $all);
    }

    /**
     * R5n: the inbox as the last successful check left it, shared by all users (only the list of UIDs; the messages
     * themselves are in MailCache). Shown at once while the mail server is asked again, so a slow morning server does
     * not leave the page empty. Keyed by account and folder: known before connecting.
     *
     * @return array{mailboxId: string, uidValidity: string, all: list<int>, at: string}|null
     */
    public function snapshot(string $account, string $folder): ?array
    {
        $snapshot = Cache::get($this->snapshotKey($account, $folder));

        return is_array($snapshot) && isset($snapshot['mailboxId'], $snapshot['uidValidity'], $snapshot['all'], $snapshot['at']) ? $snapshot : null;
    }

    /** R5n: after a successful check (any user): what the next visitor sees first */
    public function rememberSnapshot(string $account, string $folder, InboxPage $page): void
    {
        Cache::put($this->snapshotKey($account, $folder), ['mailboxId' => $page->mailboxId, 'uidValidity' => $page->uidValidity,
            'all' => array_values(array_map('intval', $page->all)), 'at' => now()->toIso8601String()], now()->addDays(7));
    }

    /**
     * R5n: a page of the snapshot, read from the cache only (no mail server). Messages no longer cached (expired) are
     * left out; the page's total is the snapshot's.
     *
     * @param array{mailboxId: string, uidValidity: string, all: list<int>, at: string} $snapshot
     */
    public function cachedPage(array $snapshot, int $page, int $perPage, bool $newestFirst): InboxPage
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);
        $messages = [];
        foreach ($this->slice($snapshot['all'], $page, $perPage, $newestFirst) as $uid) {
            if (($summary = $this->summary($snapshot['mailboxId'], $snapshot['uidValidity'], $uid)) !== null) {
                $messages[] = new InboxMessage($uid, $summary);
            }
        }

        return new InboxPage($snapshot['mailboxId'], $snapshot['uidValidity'], count($snapshot['all']), $page, $perPage,
            $this->sorted($messages, $newestFirst), $snapshot['all']);
    }

    /** @param list<int> $all @return list<int> the UIDs of one page, by arrival (newest first if $newestFirst) */
    private function slice(array $all, int $page, int $perPage, bool $newestFirst): array
    {
        $ordered = $newestFirst ? array_reverse($all) : $all;

        return array_slice($ordered, ($page - 1) * $perPage, $perPage);
    }

    /** @param list<InboxMessage> $messages @return list<InboxMessage> within the page, by the e-mails' Date header */
    private function sorted(array $messages, bool $newestFirst): array
    {
        usort($messages, fn (InboxMessage $a, InboxMessage $b) => $newestFirst
            ? $b->timestamp() <=> $a->timestamp()
            : $a->timestamp() <=> $b->timestamp());

        return $messages;
    }

    private function snapshotKey(string $account, string $folder): string
    {
        return 'inbox-snapshot:'.sha1($account.'|'.$folder);
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
