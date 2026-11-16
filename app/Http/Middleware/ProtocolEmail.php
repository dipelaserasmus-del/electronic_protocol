<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Config;
use Auth;

class ProtocolEmail
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // no IMAP account set, or the user is not in "allowedEmailUsers" (rule shared with the top bar)
        if (! \App\Support\InboxAccess::allows(Auth::user())) {
            return redirect()->back();
        }
        return $next($request);
    }
}
