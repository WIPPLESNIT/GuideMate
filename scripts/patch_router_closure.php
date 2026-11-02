<?php

// 1. Patch app/Core/Router.php
$routerPath = __DIR__ . '/../app/Core/Router.php';
$routerContent = file_get_contents($routerPath);
$routerContent = str_replace("\r\n", "\n", $routerContent);

$oldRouterSection = <<< 'PHP'
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
    public function delete(string $pattern, array $handler, array $guards = []): void
    {
        $this->add('DELETE', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<int,string> $guards
     */
    public function put(string $pattern, array $handler, array $guards = []): void
    {
        $this->add('PUT', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string} $handler
     * @param array<int,string> $guards
     */
    public function patch(string $pattern, array $handler, array $guards = []): void
    {
        $this->add('PATCH', $pattern, $handler, $guards);
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
PHP;

$newRouterSection = <<< 'PHP'
    /** @var array<int, array{method:string, pattern:string, handler:array{0:class-string,1:string}|callable, guards:array<int,string>}> */
    private array $routes = [];

    /**
     * @param array{0:class-string,1:string}|callable $handler
     * @param array<int,string> $guards
     */
    public function get(string $pattern, array|callable $handler, array $guards = []): void
    {
        $this->add('GET', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string}|callable $handler
     * @param array<int,string> $guards
     */
    public function post(string $pattern, array|callable $handler, array $guards = []): void
    {
        $this->add('POST', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string}|callable $handler
     * @param array<int,string> $guards
     */
    public function delete(string $pattern, array|callable $handler, array $guards = []): void
    {
        $this->add('DELETE', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string}|callable $handler
     * @param array<int,string> $guards
     */
    public function put(string $pattern, array|callable $handler, array $guards = []): void
    {
        $this->add('PUT', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string}|callable $handler
     * @param array<int,string> $guards
     */
    public function patch(string $pattern, array|callable $handler, array $guards = []): void
    {
        $this->add('PATCH', $pattern, $handler, $guards);
    }

    /**
     * @param array{0:class-string,1:string}|callable $handler
     * @param array<int,string> $guards
     */
    private function add(string $method, string $pattern, array|callable $handler, array $guards): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'guards' => $guards,
        ];
    }
PHP;

if (!str_contains($routerContent, $oldRouterSection)) {
    echo "ERROR: oldRouterSection not found in Router.php\n";
    exit(1);
}
$routerContent = str_replace($oldRouterSection, $newRouterSection, $routerContent);

$oldInvokeSection = <<< 'PHP'
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
PHP;

$newInvokeSection = <<< 'PHP'
    /**
     * @param array{0:class-string,1:string}|callable $handler
     * @param array<string, string> $params
     */
    private function invoke(array|callable $handler, array $params): void
    {
        if ($handler instanceof \Closure || (!is_array($handler) && is_callable($handler))) {
            $handler(...array_values($params));
            return;
        }
        if (is_array($handler) && isset($handler[0]) && is_object($handler[0]) && is_callable($handler)) {
            $handler(...array_values($params));
            return;
        }
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
PHP;

if (!str_contains($routerContent, $oldInvokeSection)) {
    echo "ERROR: oldInvokeSection not found in Router.php\n";
    exit(1);
}
$routerContent = str_replace($oldInvokeSection, $newInvokeSection, $routerContent);
file_put_contents($routerPath, $routerContent);
echo "Router.php updated successfully.\n";

// 2. Patch app/Core/helpers.php for redirect($path, $status = 302)
$helpersPath = __DIR__ . '/../app/Core/helpers.php';
$helpersContent = file_get_contents($helpersPath);
$helpersContent = str_replace("\r\n", "\n", $helpersContent);

$oldRedirect = <<< 'PHP'
if (!function_exists('redirect')) {
    /**
     * Send a redirect and stop execution.
     */
    function redirect(string $path): void
    {
        $location = str_starts_with($path, 'http') ? $path : url($path);
        header('Location: ' . $location);
        exit;
    }
}
PHP;

$newRedirect = <<< 'PHP'
if (!function_exists('redirect')) {
    /**
     * Send a redirect and stop execution.
     */
    function redirect(string $path, int $status = 302): void
    {
        $location = str_starts_with($path, 'http') ? $path : url($path);
        http_response_code($status);
        header('Location: ' . $location, true, $status);
        exit;
    }
}
PHP;

if (str_contains($helpersContent, $oldRedirect)) {
    $helpersContent = str_replace($oldRedirect, $newRedirect, $helpersContent);
    file_put_contents($helpersPath, $helpersContent);
    echo "helpers.php redirect() updated successfully.\n";
} else {
    echo "NOTE: oldRedirect not found in helpers.php or already updated.\n";
}

// 3. Patch app/Controllers/ListingController.php
$lcPath = __DIR__ . '/../app/Controllers/ListingController.php';
$lcContent = file_get_contents($lcPath);
$lcContent = str_replace("\r\n", "\n", $lcContent);

$oldAttractionsMethod = <<< 'PHP'
    public function attractions(): void
    {
        $this->renderBrowse('attractions', 'Attractions', 'Tours, heritage sites, and premier attractions across Cebu.');
    }
PHP;

$newAttractionsMethod = <<< 'PHP'
    public function attractions(): void
    {
        $this->renderBrowse('attractions', 'Attractions', 'Tours, heritage sites, and premier attractions across Cebu.');
    }

    public function attractionAndTours(): void
    {
        $this->renderBrowse('attractions', 'Attraction & Tours', 'Tours, heritage sites, and premier attractions across Cebu.');
    }
PHP;

if (str_contains($lcContent, $oldAttractionsMethod) && !str_contains($lcContent, 'attractionAndTours')) {
    $lcContent = str_replace($oldAttractionsMethod, $newAttractionsMethod, $lcContent);
    file_put_contents($lcPath, $lcContent);
    echo "ListingController.php attractionAndTours() added.\n";
}

echo "All router and redirect patches finished.\n";
