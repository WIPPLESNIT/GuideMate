<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Short-lived Google/Facebook login tickets so the phone can poll the LAN
 * API for the finished token. That way sign-in still completes if the
 * exp:// deep link is lost after a Wi-Fi IP change or laptop restart.
 */
final class OAuthPending
{
    /**
     * @param array<string, mixed> $data
     */
    public static function put(string $id, array $data): void
    {
        $id = self::safeId($id);
        if ($id === '') {
            return;
        }
        $data['updated'] = time();
        @file_put_contents(self::path($id), json_encode($data), LOCK_EX);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(string $id): ?array
    {
        $id = self::safeId($id);
        if ($id === '') {
            return null;
        }
        $file = self::path($id);
        if (!is_file($file)) {
            return null;
        }
        if (filemtime($file) < time() - 600) {
            @unlink($file);
            return null;
        }
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($data) ? $data : null;
    }

    private static function safeId(string $id): string
    {
        return preg_replace('/[^a-f0-9]/', '', strtolower($id)) ?? '';
    }

    private static function path(string $id): string
    {
        $dir = rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'guidemate_oauth';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir . DIRECTORY_SEPARATOR . $id . '.json';
    }
}
