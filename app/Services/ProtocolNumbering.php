<?php

namespace App\Services;

use App\Config;
use App\Protocol;

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
}
