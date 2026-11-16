<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;

class ForgotPasswordController extends Controller implements HasMiddleware
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
    */

    use SendsPasswordResetEmails;

    /** Who may use this controller (was the constructor's \$this->middleware(), N02). */
    public static function middleware(): array
    {
        return [
            new Middleware('guest'),
        ];
    }

    public function showLinkRequestForm()
    {
        $allowregister = \App\Support\Registration::isOpen();

        return view('auth.passwords.email', compact('allowregister'));
    }
}
