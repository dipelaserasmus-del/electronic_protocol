<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Config;
use App\Models\Protocol;
use App\Policies\ProtocolPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Gate::policy(Protocol::class, ProtocolPolicy::class);
        // L03: the test mailbox's SMTP (local real-data copy) delivers only to TEST_MAIL_ONLY_TO
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, [\App\Support\Mail\TestMailRedirect::class, 'handle']);

        Paginator::useBootstrap();

        error_reporting(E_ALL ^ E_NOTICE);
        try {
            View::share('myActiveUsers', User::my_active_users());
            View::share('myUsers', User::my_users());

            $ipiresiasName = Config::getConfigValueOf('ipiresiasName');
            View::share('ipiresiasName', $ipiresiasName);
            $titleColor = Config::getConfigValueOf('titleColor');
            $titleColorStyle = '';
            if ($titleColor) {
                $titleColorStyle = "style='background:" . $titleColor . "'";
            }
            View::share('titleColorStyle', $titleColorStyle);
            $defaultImapEmail = Config::getConfigValueOf('defaultImapEmail');
            View::share('defaultImapEmail', $defaultImapEmail);

            $allowedEmailUsers =  Config::getConfigValueOf('allowedEmailUsers');
            View::share('allowedEmailUsers', $allowedEmailUsers);

            $wideListProtocol = Config::getConfigValueOf('wideListProtocol');
            View::share('wideListProtocol', $wideListProtocol);

        } catch (\Throwable $e) {
            report($e);
            // καμία ενέργεια απλά πιάνει το λάθος
            // γιατί χτύπαγε στη δημιουργία των πινάκων με php artisan:migrate
        }


        /**
         * Paginate a standard Laravel Collection.
         *
         * @param int $perPage
         * @param int $total
         * @param int $page
         * @param string $pageName
         * @return array
         */
        Collection::macro('paginate', function ($perPage, $total = null, $page = null, $pageName = 'page'): LengthAwarePaginator {
            $page = $page ?: LengthAwarePaginator::resolveCurrentPage($pageName);

            return new LengthAwarePaginator(
                $this->forPage($page, $perPage)->values(),
                $total ?: $this->count(),
                $perPage,
                $page,
                [
                    'path' => LengthAwarePaginator::resolveCurrentPath(),
                    'pageName' => $pageName,
                ]
            );
        });
    }
}
