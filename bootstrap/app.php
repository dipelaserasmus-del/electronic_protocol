<?php

use App\Http\Middleware\LimitProtocolAccessList;
use App\Http\Middleware\MustBeAdmin;
use App\Http\Middleware\MustBeWriterOrAdmin;
use App\Http\Middleware\ProtocolEmail;
use App\Http\Middleware\PurgeTrashDaily;
use App\Http\Middleware\RedirectAfterLogin;
use App\Http\Middleware\TouchUserActivity;
use App\Support\Home;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(Home::LIST);   // logged-in user opens /login → protocol list
        $middleware->web(append: [TouchUserActivity::class, PurgeTrashDaily::class]);
        $middleware->alias([
            'admin' => MustBeAdmin::class,
            'writer' => MustBeWriterOrAdmin::class,
            'email' => ProtocolEmail::class,
            'limitList' => LimitProtocolAccessList::class,
            'redirectAfterLogin' => RedirectAfterLogin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();

// B04 error pages (reference code, details for administrators only) live in App\Exceptions\Handler.
$app->singleton(Illuminate\Contracts\Debug\ExceptionHandler::class, App\Exceptions\Handler::class);

return $app;
