<?php

namespace App\Http\Controllers;

use App\Support\UserPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/** R3d: the logged-in user saves one of their own screen choices (App\Support\UserPreferences). */
class PreferencesController extends Controller
{
    public function update(): JsonResponse
    {
        $key = (string) request('key');
        $saved = UserPreferences::set(Auth::user(), $key, request('value'));
        return response()->json(['saved' => $saved, 'value' => UserPreferences::get(Auth::user(), $key)], $saved ? 200 : 422);
    }
}
