<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Simple PHP-template view renderer with a shared layout.
 */
final class View
{
    /**
     * Render a view inside the main layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $content = self::capture($view, $data);

        $layoutFile = self::path("layouts/{$layout}");
        if (!is_file($layoutFile)) {
            echo $content;
            return;
        }

        $data['content'] = $content;
        extract($data, EXTR_SKIP);
        require $layoutFile;
    }

    /**
     * Render a view without a layout and return the string (used for partials/AJAX).
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture($view, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function capture(string $view, array $data): string
    {
        $file = self::path($view);
        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view} ({$file})");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    private static function path(string $view): string
    {
        $view = str_replace(['..', '\\'], '', $view);
        return dirname(__DIR__) . '/Views/' . $view . '.php';
    }
}
