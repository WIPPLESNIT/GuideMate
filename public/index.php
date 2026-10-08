<?php

declare(strict_types=1);

use App\Core\Router;

require dirname(__DIR__) . '/bootstrap.php';

$router = new Router();
require dirname(__DIR__) . '/routes/api.php';
require dirname(__DIR__) . '/routes/web.php';

try {
    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
} catch (Throwable $e) {
    if ((bool) (App\Core\App::config('app')['debug'] ?? false)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "GuideMate error:\n\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString();
        exit;
    }
    abort(500, 'Something went wrong on our end. Please try again later.');
}
