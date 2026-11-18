<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;

/**
 * R6a: the users page (App\Livewire\UserAdmin) at the old addresses /users and /users/{id} (opens that user).
 * Saving and deleting are actions of the page (App\Services\Users\UserAccounts); the old POST /users,
 * POST /users/{id} and GET /users/del/{id} (a delete by a plain link, without a CSRF check) are gone.
 */
class UserController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['auth', 'admin:home'];
    }

    public function index(?int $user = null)
    {
        return view('users', ['user' => $user]);
    }
}
