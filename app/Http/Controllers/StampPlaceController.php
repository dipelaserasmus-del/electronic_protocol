<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\Protocols\Stamp;
use App\Services\Stamp\StampedCopy;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/**
 * R4n (owner, 2026-11-26): where the stamp goes on one file (top left, centre or right), chosen in the preview when the
 * usual place hides something. Every user who may see the protocol may choose (owner: «all users»); the stored file is
 * never changed, only the stamped copy shown, downloaded and printed (made again with the new place).
 * R4p (owner, 2026-11-27): in the same way, the stamp on one file as it is (see-through, the number and date on white)
 * or opaque (all of it on white).
 */
class StampPlaceController extends Controller
{
    public function update(int $attachment): JsonResponse
    {
        $a = Attachment::find($attachment);
        abort_if($a === null || $a->protocol === null || Gate::denies('view', $a->protocol), 404);
        abort_unless(StampedCopy::applies($a), 422, 'Αυτό το αρχείο δεν παίρνει σφραγίδα.');
        // R4p (owner, 2026-11-27): «opaque» (true / false) as well, or instead: the whole stamp on white
        abort_unless(request()->has('place') || request()->has('opaque'), 422, 'Τίποτα προς αλλαγή.');
        $place = request()->has('place') ? (string) request('place') : $a->stamp_place;
        abort_unless($place === null || isset(Stamp::PLACES[$place]), 422, 'Άγνωστη θέση σφραγίδας.');
        $opaque = request()->has('opaque') ? filter_var(request('opaque'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : (bool) $a->stamp_opaque;
        abort_if($opaque === null, 422, 'Άγνωστη επιλογή σφραγίδας.');
        if ($a->stamp_place !== $place || (bool) $a->stamp_opaque !== $opaque) {
            // review of R4n: under the lock the stamped copy is made with (StampedCopy::copy), so a copy being made
            // right now is not deleted half-way; busy for too long: try again
            try {
                Cache::lock('stamped-copy-' . $a->id, 30)->block(self::$wait, function () use ($a, $place, $opaque) {
                    $a->stamp_place = $place;
                    $a->stamp_opaque = $opaque;
                    $a->saveQuietly();   // the file's own data only: not an edit of the protocol
                    StampedCopy::forget($a);
                });
            } catch (LockTimeoutException) {
                abort(423, 'Το αρχείο ετοιμάζεται αυτή τη στιγμή· δοκιμάστε ξανά.');
            }
        }
        $place = StampedCopy::place($a);
        return response()->json(['place' => $place, 'label' => Stamp::PLACES[$place] ?? null, 'opaque' => $opaque]);
    }

    /** seconds to wait for a stamped copy being made of the same file (tests: 0) */
    public static int $wait = 20;
}
