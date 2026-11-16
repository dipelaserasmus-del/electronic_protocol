<?php

namespace App\Http\Controllers;

use App\Models\Protocol;
use App\Services\ProtocolNumbering;
use Illuminate\Support\Facades\Auth;

/**
 * R1b: the rebuilt protocol form (App\Livewire\ProtocolForm) at the old addresses:
 * /home (new), /home/{id} (edit or view), /home/{id}/1 (copy as new), /home?num=N (A02: fill a gap).
 */
class ProtocolFormController extends Controller
{
    public function create()
    {
        $num = request('num');
        $prefill = ctype_digit((string) $num) && app(ProtocolNumbering::class)->userMayChooseNumber(Auth::user()) ? (int) $num : null;
        return view('protocols.form', ['protocol' => new Protocol(), 'copy' => false, 'prefillNum' => $prefill]);
    }

    public function show(Protocol $protocol, $copyAsNew = null)
    {
        abort_if(Auth::user()->cannot('view', $protocol), 404);   // S02 (also before a copy)
        return view('protocols.form', ['protocol' => $protocol, 'copy' => (bool) $copyAsNew, 'prefillNum' => null]);
    }
}
