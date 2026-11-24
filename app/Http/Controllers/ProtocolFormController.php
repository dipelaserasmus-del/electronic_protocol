<?php

namespace App\Http\Controllers;

use App\Models\Protocol;
use App\Services\ProtocolNumbering;
use Illuminate\Support\Facades\Auth;

/**
 * R1b: the rebuilt protocol form (App\Livewire\ProtocolForm) at the old addresses:
 * /home (new), /home/{id} (edit or view), /home/{id}/1 (copy as new), /home?num=N (A02: fill a gap);
 * and the list (R2) at /home/list.
 */
class ProtocolFormController extends Controller
{
    public function create()
    {
        $num = request('num');
        $prefill = ctype_digit((string) $num) && app(ProtocolNumbering::class)->userMayChooseNumber(Auth::user()) ? (int) $num : null;
        return view('protocols.form', ['protocol' => new Protocol(), 'copy' => false, 'prefillNum' => $prefill]);
    }

    /** R2: the protocol list (App\Livewire\ProtocolList); /home/list/{d|e|f|m}/{userId|a} as before */
    public function list(?string $filter = null, ?string $userId = null)
    {
        return view('protocols.list', ['filter' => $filter, 'userId' => $userId]);
    }

    /**
     * R3h: direct link /protocol/{num} (this year) or /protocol/{num}/{year} → the protocol's form. A number that does
     * not exist, a protocol the user may not see and a malformed number or year all get the same «Δεν υπάρχει» page
     * (404), so the link never tells whether a protocol exists.
     */
    public function link(string $num, ?string $year = null)
    {
        $year ??= now()->format('Y');
        $protocol = null;
        if (preg_match('/^\d{1,9}$/', $num) && preg_match('/^\d{4}$/', $year)) {
            $protocol = Protocol::where('etos', (int) $year)->where('protocolnum', (int) $num)->first();
        }
        if ($protocol === null || Auth::user()->cannot('view', $protocol)) {
            // R4h: administrators are told when it is in the trash (others get the same page whatever the reason)
            $trashed = $protocol === null && Auth::user()->role?->role === 'Διαχειριστής' && preg_match('/^\d{1,9}$/', $num) && preg_match('/^\d{4}$/', $year)
                ? \App\Models\TrashedProtocol::where('etos', (int) $year)->where('protocolnum', (int) $num)->latest('deleted_at')->first() : null;
            return response()->view('protocols.missing', ['num' => $num, 'year' => $year, 'trashed' => $trashed], 404);
        }
        return redirect('/home/' . $protocol->id);
    }

    /** R3a: the advanced search (App\Livewire\SearchPage) */
    public function search()
    {
        return view('protocols.search');
    }

    public function show(Protocol $protocol, $copyAsNew = null)
    {
        abort_if(Auth::user()->cannot('view', $protocol), 404);   // S02 (also before a copy)
        return view('protocols.form', ['protocol' => $protocol, 'copy' => (bool) $copyAsNew, 'prefillNum' => null]);
    }

    /** R4b: the trash (administrators); R4h: also Συγγραφέας / Αναθέτων, for the protocols they registered */
    public function trash()
    {
        abort_unless(\App\Services\Protocols\ProtocolTrash::mayOpen(Auth::user()), 403);
        return view('trash');
    }

    /** R4b: a file in the trash (administrators), with the same safe headers as any attachment (R4a) */
    public function trashDownload(int $attachment)
    {
        abort_unless(Auth::user()->role?->role === 'Διαχειριστής', 403);
        $a = \App\Models\Attachment::onlyTrashed()->findOrFail($attachment);
        $path = app(\App\Services\Attachments\Trash::class)->fileInTrash($a);   // only what is in trash/
        abort_if($path === null, 404);
        return \App\Support\SafeFile::download(\Illuminate\Support\Facades\Storage::path($path), $a->name ?: basename($path), $a->mimeType);
    }
}
