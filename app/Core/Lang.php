<?php

declare(strict_types=1);

namespace App\Core;

final class Lang
{
    /** @var array<string, string> */
    private static array $strings = [];

    /** @var array<string, string> */
    private static array $fallback = [];

    public static function init(?string $locale = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['_locale'])) {
            $locale = (string) $_SESSION['_locale'];
        }
        $locale ??= (string) (App::config('app')['locale'] ?? 'en');
        $locale = strtolower($locale);
        if (!LocaleCatalog::isValidLocale($locale)) {
            $locale = 'en';
        }
        $_SESSION['_locale'] = $locale;

        $enFile = dirname(__DIR__) . '/lang/en.php';
        self::$fallback = is_file($enFile) ? (require $enFile) : [];

        $langFile = LocaleCatalog::langFileFor($locale);
        $file = dirname(__DIR__) . '/lang/' . $langFile . '.php';
        self::$strings = is_file($file) ? (require $file) : self::$fallback;
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::$strings[$key]
            ?? self::$fallback[$key]
            ?? ($default !== '' ? $default : $key);
    }

    public static function setLocale(string $locale): void
    {
        $locale = strtolower($locale);
        $_SESSION['_locale'] = LocaleCatalog::isValidLocale($locale) ? $locale : 'en';
        self::init($_SESSION['_locale']);
    }

    public static function current(): string
    {
        return (string) ($_SESSION['_locale'] ?? 'en');
    }
}
