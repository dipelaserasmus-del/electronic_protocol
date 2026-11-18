<?php

namespace App\Services\Mail;

/** A message on the inbox page: its UID and what the page shows of it (MailContent::summary, cached). */
final class InboxMessage
{
    /** @param array<string, mixed> $summary see App\Support\Mail\MailContent::summary */
    public function __construct(public readonly int $uid, public readonly array $summary) {}

    /** Seconds since 1970 from the Date header (0 when missing or unreadable). */
    public function timestamp(): int
    {
        return $this->summary['date'] ? strtotime($this->summary['date']) : 0;
    }
}
