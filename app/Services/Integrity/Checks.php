<?php

namespace App\Services\Integrity;

use App\Models\Protocol;
use App\Models\User;
use App\Policies\ProtocolPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * A01: the integrity checks of the protocol book («Έλεγχοι», /checks). Each rule is a class in RULES; the page runs
 * them all over a date range (the last DEFAULT_DAYS days unless the user picks another), the list's head shows how
 * many findings the last DEFAULT_DAYS days have (rules with inList()).
 *
 * Who: administrators, assigners and writers (the users who register protocols and see the gaps notice, A02).
 * A finding about one protocol is shown only to users who may see that protocol (with «Περιορισμός λίστας» a
 * writer sees only their own); a missing number is shown to all of them, as A02's notice does.
 */
final class Checks
{
    public const DEFAULT_DAYS = 30;

    /** Longest range the page checks at once (some rules read every protocol of the range) */
    public const MAX_DAYS = 3 * 366;

    /** @var list<class-string<Rule>> in the page's order */
    public const RULES = [
        Rules\NumberGapRule::class,
        Rules\DateOrderRule::class,
        Rules\DuplicateRule::class,
        Rules\PendingRule::class,
    ];

    /** @param  list<class-string<Rule>>  $classes  the rules to run (tests give their own) */
    public function __construct(private readonly array $classes = self::RULES) {}

    public static function allowed(?User $user): bool
    {
        return $user !== null && in_array(ProtocolPolicy::role($user), [ProtocolPolicy::ADMIN, ProtocolPolicy::ASSIGNER, ProtocolPolicy::WRITER], true);
    }

    /** @return list<Rule> */
    public function rules(): array
    {
        return array_map(fn (string $class) => app($class), $this->classes);
    }

    /** [from, to] of the default range: the last DEFAULT_DAYS days, today included */
    public static function defaultRange(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();

        return [$today->subDays(self::DEFAULT_DAYS - 1), $today];
    }

    /**
     * Every rule's findings that $user may see.
     *
     * @return array<string, array{rule: Rule, findings: list<Finding>}> by rule key, in RULES order
     */
    public function run(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $out = [];
        foreach ($this->rules() as $rule) {
            $out[$rule->key()] = ['rule' => $rule, 'findings' => $this->visible($user, $rule->findings($from, $to, $user))];
        }

        return $out;
    }

    /** How many findings of the inList() rules the last DEFAULT_DAYS days have (the list's «Έλεγχοι» line) */
    public function listCount(User $user, ?CarbonImmutable $today = null): int
    {
        [$from, $to] = self::defaultRange($today);
        $n = 0;
        foreach ($this->rules() as $rule) {
            if ($rule->inList()) {
                $n += count($this->visible($user, $rule->findings($from, $to, $user)));
            }
        }

        return $n;
    }

    /**
     * @param  list<Finding>  $findings
     * @return list<Finding>
     */
    private function visible(User $user, array $findings): array
    {
        if (! ProtocolPolicy::isLimited($user)) {
            return $findings;
        }
        $ids = array_values(array_unique(array_filter(array_map(fn (Finding $f) => $f->protocolId, $findings))));
        $seen = Protocol::whereIn('id', $ids)->get()->filter(fn ($p) => Gate::forUser($user)->allows('view', $p))->pluck('id')->flip();

        return array_values(array_filter($findings, fn (Finding $f) => $f->protocolId === null || $seen->has($f->protocolId)));
    }
}
