<?php

namespace App\Services\Integrity;

use App\Models\Protocol;
use Carbon\CarbonImmutable;

/**
 * A01: one thing a rule found. $ref is what it is about as the user reads it («88/2026», «100–105/2026»).
 * $protocolId names the protocol (a link to it, and the check of who may see it); null when it is about no single
 * protocol (a missing number). $meta holds what a rule's own part of the page needs (the gaps: year, from, to).
 */
final class Finding
{
    public function __construct(
        public readonly string $message,
        public readonly string $ref,
        public readonly ?int $protocolId = null,
        public readonly ?CarbonImmutable $date = null,
        public readonly ?string $subject = null,
        public readonly array $meta = [],
    ) {}

    /** A finding about one protocol: its number, date and subject */
    public static function about(Protocol $p, string $message): self
    {
        return new self($message, $p->protocolnum.'/'.$p->etos, (int) $p->id, $p->dateOf('protocoldate'), $p->thema);
    }
}
