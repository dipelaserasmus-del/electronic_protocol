<?php

namespace App\Http\Controllers;

use App\Livewire\StampDesigner;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Auth;

/** R4e: the stamp designer page (Διαχειριστής, Αναθέτων, Συγγραφέας; its actions check the same, StampDesigner::boot) */
class StampController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth'];
    }

    public function show()
    {
        abort_unless(StampDesigner::allowed(Auth::user()), 403);
        return view('stamp');
    }
}
