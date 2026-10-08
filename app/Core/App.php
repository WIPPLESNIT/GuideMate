<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Central application container: holds configuration and bootstraps
 * the request lifecycle (sessions, error handling, routing).
 */
final class App
{
    /** @var array<string, mixed> */
    private static array $config = [];

    private static string $basePath = '';

    /**
     * @param array<string, mixed> $config
     */
    public static function boot(array $config): void
    {
        self::$config = $config;

        date_default_timezone_set($config['app']['timezone'] ?? 'UTC');

        self::configureErrors((bool) ($config['app']['debug'] ?? false));
        self::startSession();
        self::detectBasePath();
    }

    /**
     * Read a config section, e.g. App::config('db').
     */
    public static function config(string $key): mixed
    {
        return self::$config[$key] ?? null;
    }

    /**
     * Base path of the app when served from a subfolder (e.g. /GuideMate/public).
     */
    public static function basePath(): string
    {
        return self::$basePath;
    }

    private static function configureErrors(bool $debug): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $session = self::$config['session'] ?? [];
        session_name($session['name'] ?? 'guidemate_session');
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
        session_set_cookie_params([
            'lifetime' => (int) ($session['lifetime'] ?? 7200),
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    /**
     * Figure out the folder the front controller lives in so links work
     * whether the app is at the domain root or inside /GuideMate/public.
     */
    private static function detectBasePath(): void
    {
        $configuredUrl = self::$config['app']['url'] ?? '';
        if (is_string($configuredUrl) && $configuredUrl !== '') {
            $path = parse_url($configuredUrl, PHP_URL_PATH) ?: '';
            self::$basePath = rtrim($path, '/');
            return;
        }

        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        // Strip the trailing index.php to get the public dir path.
        $dir = str_replace('\\', '/', dirname($script));
        self::$basePath = $dir === '/' ? '' : rtrim($dir, '/');
    }
}
