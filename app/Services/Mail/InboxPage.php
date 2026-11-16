<?php

namespace App\Services\Mail;

use Illuminate\Pagination\LengthAwarePaginator;

/** One page of the inbox. */
final class InboxPage
{
    /** @param list<InboxMessage> $messages */
    public function __construct(
        public readonly string $uidValidity,
        public readonly int $total,
        public readonly array $messages,
        public readonly LengthAwarePaginator $paginator,
    ) {}
}
