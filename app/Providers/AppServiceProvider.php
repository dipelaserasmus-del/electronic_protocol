<?php

namespace App\Providers;

use App\Models\Config;
use App\Models\Protocol;
use App\Policies\ProtocolPolicy;
use Illuminate\Support\Facades\Gate;
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
        // X01: sign-ins in the audit log
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, [\App\Listeners\AuditAuthEvents::class, 'login']);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Logout::class, [\App\Listeners\AuditAuthEvents::class, 'logout']);
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, [\App\Listeners\AuditAuthEvents::class, 'failed']);

        error_reporting(E_ALL ^ E_NOTICE);
        try {
            // the e-mail templates (receipts, assignments) write the institution's name; Z01b: the old layout's other
            // shared values (users, inbox settings, wideListProtocol) went with it
            View::share('ipiresiasName', Config::getConfigValueOf('ipiresiasName'));

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
