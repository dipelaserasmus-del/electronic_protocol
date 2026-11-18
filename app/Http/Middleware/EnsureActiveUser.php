<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * R6a: a user who was deactivated while logged in (or who comes back with "remember me") is logged out on the
 * next request. The login form itself refuses inactive accounts (LoginController::credentials).
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        if ($user && ! $user->active) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson() || $request->hasHeader('X-Livewire')) {
                abort(401);
            }

            return redirect('/login')->withErrors(['username' => 'Ο λογαριασμός σας είναι ανενεργός. Ρωτήστε τον Διαχειριστή.']);
        }

        return $next($request);
    }
}
