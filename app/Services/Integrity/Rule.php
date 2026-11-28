<?php

namespace App\Services\Integrity;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A01: one check of the protocol book on the «Έλεγχοι» page (App\Services\Integrity\Checks lists them).
 *
 * A rule only reads: it never changes a protocol. It reports what looks wrong among the protocols dated in a range;
 * the user decides what to do (most findings are warnings, as R2f's dates are).
 */
interface Rule
{
    /** Stable key: the page's anchors and tests use it (e.g. 'gaps') */
    public function key(): string;

    /** Heading on the page, e.g. «Κενά στην αρίθμηση» */
    public function title(): string;

    /** One or two sentences: what is checked and why */
    public function explanation(): string;

    /**
     * Whether the protocol list's head counts this rule's findings in its «Έλεγχοι» line. The gaps have their own
     * notice there (A02), so they do not.
     */
    public function inList(): bool;

    /**
     * $viewer: who will read the findings (A03): a message names another protocol only if they may see it. The engine
     * itself leaves out findings about a protocol they may not see.
     *
     * @return list<Finding>
     */
    public function findings(CarbonImmutable $from, CarbonImmutable $to, ?User $viewer = null): array;
}
