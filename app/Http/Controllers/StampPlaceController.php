<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\Protocols\Stamp;
use App\Services\Stamp\StampedCopy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * R4n (owner, 2026-11-26): where the stamp goes on one file (top left, centre or right), chosen in the preview when the
 * usual place hides something. Every user who may see the protocol may choose (owner: «all users»); the stored file is
 * never changed, only the stamped copy shown, downloaded and printed (made again with the new place).
 */
class StampPlaceController extends Controller
{
    public function update(int $attachment): JsonResponse
    {
        $a = Attachment::find($attachment);
        abort_if($a === null || $a->protocol === null || Gate::denies('view', $a->protocol), 404);
        abort_unless(StampedCopy::applies($a), 422, 'Αυτό το αρχείο δεν παίρνει σφραγίδα.');
        $place = (string) request('place');
        abort_unless(isset(Stamp::PLACES[$place]), 422, 'Άγνωστη θέση σφραγίδας.');
        if ($a->stamp_place !== $place) {
            $a->stamp_place = $place;
            $a->saveQuietly();   // the file's own data only: not an edit of the protocol
            StampedCopy::forget($a);
        }
        return response()->json(['place' => $place, 'label' => Stamp::PLACES[$place]]);
    }
}
