<?php

namespace App\Services;

use App\Config;
use App\Protocol;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Protocol number rules in one place (was duplicated in ProtocolController::index/store/storeFromEmail).
 */
class ProtocolNumbering
{
    /** Next free number for the year: max(number of year)+1, or the configured first number on an empty book. */
    public function next(int $etos): int
    {
        if (Protocol::count() === 0) {
            return (int) (Config::getConfigValueOf('firstProtocolNum') ?: 1);
        }
        if (Config::getConfigValueOf('yearInUse')) {
            return (int) Protocol::whereEtos($etos)->max('protocolnum') + 1;
        }
        // legacy mode without yearly numbering: continue from the most recently created entry
        return (int) (Protocol::last()->protocolnum ?? 0) + 1;
    }

    /**
     * Did the user type a number different from the one the form proposed?
     * The new-protocol form sends the proposed number in the hidden field `protocolnum_proposed`.
     */
    public function isManual(?string $submitted, ?string $proposed): bool
    {
        $submitted = trim((string) $submitted);
        return $submitted !== '' && $proposed !== null && $submitted !== trim($proposed);
    }

    public function exists(int $num, int $etos): bool
    {
        return Protocol::whereEtos($etos)->where('protocolnum', $num)->exists();
    }

    /** Name of the MySQL advisory lock that serialises number allocation across all users / requests. */
    public const LOCK = 'electronic_protocol.numbering';

    /**
     * B08: allocate a number and create the protocol atomically.
     *
     * "Read max + 1, then insert" used to run without any lock, so two users saving at the same moment
     * got the same number and the second save failed (the user saw an error and lost the form).
     * Now allocation + insert run under a database-wide lock, and an unexpected duplicate (e.g. a
     * process not using the lock) is retried with the next number.
     *
     * @param int|null $manual  exact number chosen by the user, or null for "next free number"
     * @param callable(int $number): Protocol $create
     * @throws ProtocolNumberTaken when the manual number is already used
     */
    public function create(int $etos, ?int $manual, callable $create, int $attempts = 5): Protocol
    {
        $locked = $this->acquireLock();
        try {
            for ($i = 1; ; $i++) {
                $number = $manual ?? $this->next($etos);
                try {
                    return DB::transaction(fn () => $create($number));
                } catch (UniqueConstraintViolationException $e) {
                    if ($manual !== null) {
                        throw new ProtocolNumberTaken($manual, $etos);
                    }
                    if ($i >= $attempts) {
                        throw $e;
                    }
                }
            }
        } finally {
            if ($locked) {
                $this->releaseLock();
            }
        }
    }

    private function acquireLock(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }
        return (bool) (DB::selectOne('SELECT GET_LOCK(?, 15) AS l', [self::LOCK])->l ?? false);
    }

    private function releaseLock(): void
    {
        DB::selectOne('SELECT RELEASE_LOCK(?) AS l', [self::LOCK]);
    }
}
