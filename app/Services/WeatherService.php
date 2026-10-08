<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;

final class WeatherService
{
    /**
     * @return array<string, mixed>|null
     */
    public static function forListing(?float $lat, ?float $lng, string $area = ''): ?array
    {
        $key = trim((string) (App::config('weather')['api_key'] ?? ''));
        if ($key === '') {
            return null;
        }

        $cacheKey = md5(($lat ?? '') . '|' . ($lng ?? '') . '|' . $area);
        $cacheFile = sys_get_temp_dir() . '/guidemate_weather_' . $cacheKey . '.json';
        if (is_file($cacheFile) && filemtime($cacheFile) > time() - 3600) {
            $data = json_decode((string) file_get_contents($cacheFile), true);
            return is_array($data) ? $data : null;
        }

        if ($lat !== null && $lng !== null) {
            $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lng}&appid={$key}&units=metric";
        } else {
            $q = urlencode($area . ', Cebu, PH');
            $url = "https://api.openweathermap.org/data/2.5/weather?q={$q}&appid={$key}&units=metric";
        }

        $json = @file_get_contents($url);
        if ($json === false) {
            return null;
        }
        $raw = json_decode($json, true);
        if (!is_array($raw) || empty($raw['main'])) {
            return null;
        }

        $data = [
            'temp' => round((float) ($raw['main']['temp'] ?? 0), 1),
            'desc' => (string) ($raw['weather'][0]['description'] ?? ''),
            'icon' => (string) ($raw['weather'][0]['main'] ?? ''),
        ];
        file_put_contents($cacheFile, json_encode($data));
        return $data;
    }
}
