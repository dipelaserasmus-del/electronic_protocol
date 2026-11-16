<?php

namespace App\Http\Middleware;

use App\Models\Config;
use App\Services\Attachments\Trash;
use Closure;
use Illuminate\Http\Request;

/**
 * R4b: the trash's daily clean-up without a scheduler. The stack has no cron container, so the first request of each
 * day runs it, after the response has been sent (the user doesn't wait). The date is claimed with one UPDATE, so two
 * requests at the same moment don't both run it. Where a scheduler runs, routes/console.php does the same.
 */
class PurgeTrashDaily
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        if (! $request->user()) {
            return;
        }
        $today = now()->toDateString();
        try {
            Config::getConfigValueOf('trashLastPurge');   // makes sure the row exists
            $claimed = Config::where('key', 'trashLastPurge')
                ->where(fn ($q) => $q->whereNull('value')->orWhere('value', '!=', $today))
                ->update(['value' => $today]);
            if ($claimed) {
                app(Trash::class)->purgeExpired();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
