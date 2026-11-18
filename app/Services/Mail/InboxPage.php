<?php

namespace App\Services\Mail;

/** One page of the inbox. */
final class InboxPage
{
    /** @param list<InboxMessage> $messages */
    public function __construct(
        public readonly string $mailboxId,
        public readonly string $uidValidity,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly array $messages,
    ) {}

    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->perPage)));
    }
}
