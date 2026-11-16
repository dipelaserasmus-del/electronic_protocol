<?php

namespace Tests\Feature;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * N02 safety net: which middleware (login, admin, writer, e-mail, list limit, throttle…) guards each route.
 * Restructuring routes/controllers must not silently drop a guard, so the full list per route is compared with
 * a committed snapshot (tests/Feature/snapshots/route-middleware.json). A deliberate change: run the suite with
 * UPDATE_ROUTE_SNAPSHOT=1 and review the diff of the JSON file.
 */
class RouteAccessTest extends FeatureTestCase
{
    private const SNAPSHOT = __DIR__.'/snapshots/route-middleware.json';

    /** Routes a visitor may open without logging in. */
    private const PUBLIC = [
        'GET /', 'GET login', 'POST login', 'POST logout', 'GET register', 'POST register',
        'GET password/resetForm', 'POST password/email', 'GET password/reset', 'POST password/reset',
    ];

    public function test_every_route_keeps_its_middleware(): void
    {
        $actual = $this->routeMiddleware();

        if (getenv('UPDATE_ROUTE_SNAPSHOT')) {
            @mkdir(dirname(self::SNAPSHOT), 0777, true);
            file_put_contents(self::SNAPSHOT, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        }

        $expected = json_decode(file_get_contents(self::SNAPSHOT), true);
        $this->assertSame($expected, $actual);
    }

    public function test_every_non_public_route_requires_login(): void
    {
        foreach ($this->routeMiddleware() as $route => $middleware) {
            if (in_array($route, self::PUBLIC, true)) {
                continue;
            }
            $this->assertContains('auth', $middleware, "$route must require login");
        }
    }

    public function test_guest_is_sent_to_login_from_protected_pages(): void
    {
        foreach ($this->appRoutes() as $key => $route) {
            if (in_array($key, self::PUBLIC, true)) {
                continue;
            }
            $uri = '/'.ltrim(preg_replace('/\{[^}]+\}/', '1', $route->uri()), '/');
            $method = strtolower($route->methods()[0]);
            $this->$method($uri)->assertRedirect('/login');
        }
    }

    /** Was RedirectIfAuthenticated + RouteServiceProvider::HOME; now redirectUsersTo() in bootstrap/app.php. */
    public function test_logged_in_user_opening_login_lands_on_the_protocol_list(): void
    {
        $this->actingAs($this->writer())->get('/login')->assertRedirect('/home/list');
    }

    /** "METHOD uri" => sorted, de-duplicated middleware of every route served by app controllers / closures. */
    private function routeMiddleware(): array
    {
        $map = [];
        foreach ($this->appRoutes() as $key => $route) {
            $middleware = array_values(array_unique(array_map(fn ($m) => is_string($m) ? $m : get_class($m), $route->gatherMiddleware())));
            sort($middleware);
            $map[$key] = $middleware;
        }
        ksort($map);

        return $map;
    }

    /** @return array<string, Route> */
    private function appRoutes(): array
    {
        $routes = [];
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_starts_with($action, 'App\\') && $action !== 'Closure') {
                continue;   // vendor routes (debugbar, storage, health check)
            }
            if ($action === 'Closure' && $route->uri() !== '/') {
                continue;
            }
            $method = collect($route->methods())->reject(fn ($m) => $m === 'HEAD')->first();
            $routes[$method.' '.$route->uri()] = $route;
        }

        return $routes;
    }
}
