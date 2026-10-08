<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Minimal regex-based router supporting GET/POST routes with
 * named parameters (e.g. /listings/{id}) and middleware-style guards.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, handler:array{0:class-string,1:string}, guards:array<int,string>}> */
    private array $routes = [];

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<int,string> $guards
     */
    public function get(string $pattern, array $handler, array $guards = []): void
    {
        $this->add('GET', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<int,string> $guards
     */
    public function post(string $pattern, array $handler, array $guards = []): void
    {
        $this->add('POST', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<int,string> $guards
     */
    private function add(string $method, string $pattern, array $handler, array $guards): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'guards' => $guards,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize($uri);
        $method = strtoupper($method);
        // Support method spoofing via _method for PUT/DELETE-style forms.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper((string) $_POST['_method']);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, $path, $matches) === 1) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->runGuards($route['guards']);
                $this->invoke($route['handler'], $params);
                return;
            }
        }

        $this->notFound();
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function normalize(string $uri): string
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = App::basePath();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = str_replace('/index.php', '', $path);
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    /**
     * @param array<int,string> $guards
     */
    private function runGuards(array $guards): void
    {
        foreach ($guards as $guard) {
            switch ($guard) {
                case 'auth':
                    if (!Auth::check()) {
                        flash('error', 'Please sign in to continue.');
                        redirect('/login');
                    }
                    // Self-heal: a session pointing to a user that no longer
                    // exists (e.g. account deleted or DB reset) must not crash.
                    if (Auth::user() === null) {
                        Auth::logout();
                        flash('error', 'Your session has expired. Please sign in again.');
                        redirect('/login');
                    }
                    break;
                case 'guest':
                    // Clear a stale session before deciding where to send guests,
                    // otherwise /login would bounce to a broken /dashboard.
                    if (Auth::check() && Auth::user() === null) {
                        Auth::logout();
                    }
                    if (Auth::check()) {
                        // On the public pages a logged-in provider (guide, rental
                        // or hotel partner) is shown Log in /  buttons.
                        // Acting on those means starting a fresh session, so end
                        // the current one and let the form load instead of
                        // bouncing them back into their account.
                        if (Auth::isProvider()) {
                            Auth::logout();
                        } else {
                            redirect('/dashboard');
                        }
                    }
                    break;
                case 'admin':
                    if (!AdminAuth::check()) {
                        flash('error', 'Please sign in to the admin portal.');
                        redirect('/admin/login');
                    }
                    if (!AdminAuth::twoFactorSatisfied()) {
                        redirect('/admin/2fa');
                    }
                    break;
                case 'guide':
                    if (!Auth::hasRole('guide')) {
                        abort(403, 'Tour guides only.');
                    }
                    break;
                case 'provider':
                    // Any service provider (guide, rental partner, hotel partner).
                    if (!Auth::isProvider()) {
                        abort(403, 'Service providers only.');
                    }
                    break;
                case 'listing_provider':
                    // Providers who own & manage listings (guides + hotel partners).
                    if (!Auth::managesListings()) {
                        abort(403, 'Listing providers only.');
                    }
                    break;
                case 'rental_admin':
                    if (!Auth::hasRole('rental_admin')) {
                        abort(403, 'Rental partners only.');
                    }
                    break;
                default:
                    throw new RuntimeException("Unknown route guard: {$guard}");
            }
        }
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<string, string> $params
     */
    private function invoke(array $handler, array $params): void
    {
        [$class, $action] = $handler;
        if (!class_exists($class)) {
            throw new RuntimeException("Controller {$class} not found.");
        }
        $controller = new $class();
        if (!method_exists($controller, $action)) {
            throw new RuntimeException("Action {$class}::{$action} not found.");
        }
        $controller->{$action}(...array_values($params));
    }

    private function notFound(): void
    {
        abort(404, 'The page you are looking for could not be found.');
    }
}
