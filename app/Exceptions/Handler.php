<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * B04: descriptive errors instead of a bare "500 Server Error".
 *
 * - Every unexpected error gets a short reference code (e.g. E-7K2QX9), shown to the user and written to the
 *   log with the full error, so "I got error E-7K2QX9" leads straight to the cause.
 * - Everyone sees a clear Greek explanation; only administrators also see the technical details.
 * - With APP_DEBUG=true (development) Laravel's full debug page is kept.
 */
class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /** Reference code of the current request's error (one per request). */
    public static function errorId(): string
    {
        $request = request();
        if (!$request->attributes->has('error_id')) {
            $request->attributes->set('error_id', 'E-' . strtoupper(Str::random(6)));
        }
        return $request->attributes->get('error_id');
    }

    /** Added to every log entry written by report(). */
    protected function context(): array
    {
        return array_merge(parent::context(), [
            'error_id' => self::errorId(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
        ]);
    }

    public function render($request, Throwable $e)
    {
        $e = $this->mapException($e);
        $response = parent::render($request, $e);

        // keep Laravel's handling for validation, auth redirects, HTTP errors (404, 403, 419, …) and debug mode
        if ($response->getStatusCode() < 500 || $e instanceof HttpExceptionInterface || config('app.debug')) {
            return $response;
        }

        $errorId = self::errorId();
        $details = $this->isAdmin() ? $this->details($e, $request) : null;

        if ($request->expectsJson()) {
            return response()->json(array_filter([
                'message' => 'Παρουσιάστηκε σφάλμα. Κωδικός σφάλματος: ' . $errorId,
                'error_id' => $errorId,
                'details' => $details,
            ]), 500);
        }

        return response()->view('errors.500', ['errorId' => $errorId, 'details' => $details], 500);
    }

    private function isAdmin(): bool
    {
        try {
            return Auth::user()?->role?->role === 'Διαχειριστής';
        } catch (Throwable) {
            return false;   // e.g. database down: never fail while rendering an error page
        }
    }

    /** Technical summary for administrators: no stack of vendor frames, no request data. */
    private function details(Throwable $e, $request): array
    {
        $base = base_path() . DIRECTORY_SEPARATOR;
        $frames = [];
        foreach ($e->getTrace() as $f) {
            if (isset($f['file']) && !str_contains($f['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                $frames[] = str_replace($base, '', $f['file']) . ':' . ($f['line'] ?? '?')
                    . (isset($f['function']) ? ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . $f['function'] . '()' : '');
            }
            if (count($frames) >= 5) {
                break;
            }
        }
        return [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'location' => str_replace($base, '', $e->getFile()) . ':' . $e->getLine(),
            'request' => $request->method() . ' ' . $request->path(),
            'app_frames' => $frames,
        ];
    }
}
