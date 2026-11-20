<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Home;

class RedirectAfterLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {

        // μετά από login
        if (basename(request()->headers->get('referer')) == 'login') {

            $landingPage = \App\Support\Navigation::landing(auth()->user());   // R3e: the user's home page

            if ($landingPage !== url(Home::LIST)) {
                return redirect($landingPage);
            }
        }
        
        return $next($request);
    }
}
