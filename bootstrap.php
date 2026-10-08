<?php

declare(strict_types=1);


define('BASE_DIR', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = BASE_DIR . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require BASE_DIR . '/config/config.php';

require BASE_DIR . '/app/Core/helpers.php';

\App\Core\App::boot($config);

// The web portal is fixed to English and the Philippine Peso — the preferences
// (multi-language / multi-currency) chooser has been removed. Force these on
// every request so any older session value can no longer take effect.
$_SESSION['_locale'] = 'en';
$_SESSION['_currency'] = 'PHP';

\App\Core\Lang::init();
