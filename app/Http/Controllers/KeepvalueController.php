<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * R6b: the folders page (App\Livewire\FolderAdmin) at the old addresses /keep and /keep/{id} (opens that folder for an
 * administrator). Everybody may look; changes are actions of the page, for administrators
 * (App\Services\Folders\FolderCatalog). The old POST /keep, POST /keep/{id} and GET /keep/del/{id} (a delete by a
 * plain link, without a CSRF check) are gone.
 */
class KeepvalueController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth'];
    }

    public function index(?int $keepvalue = null)
    {
        return view('keep', ['keepvalue' => $keepvalue]);
    }
}
