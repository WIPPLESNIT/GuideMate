<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;

/** Geocode guide listing addresses for accurate tourist maps. */
final class GeocodingService
{
    /** @var array<string, array{latitude: float, longitude: float}|null> */
    private static array $cache = [];

    /**
     * Resolve coordinates using listing title + guide address (same idea as Google Maps search).
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public static function geocodeForListing(string $title, string $address, string $area = ''): ?array
    {
        $title = trim($title);
        $address = trim($address);
        $area = trim($area);

        $queries = [];
        if ($title !== '' && $address !== '') {
            $queries[] = $title . ', ' . $address;
        }
        if ($title !== '' && $area !== '') {
            $queries[] = $title . ', ' . $area . ', Cebu, Philippines';
        }
        if ($title !== '') {
            $queries[] = $title . ', Cebu, Philippines';
        }
        if ($address !== '') {
            $queries[] = $address;
        }

        foreach (array_values(array_unique($queries)) as $query) {
            $result = self::geocode($query);
            if ($result !== null) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public static function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        if (!str_contains(strtolower($address), 'philippines')) {
            $address .= ', Philippines';
        }

        if (array_key_exists($address, self::$cache)) {
            return self::$cache[$address];
        }

        $result = self::lookup($address);
        if ($result === null) {
            foreach (self::alternateQueries($address) as $query) {
                $result = self::lookup($query);
                if ($result !== null) {
                    break;
                }
            }
        }

        self::$cache[$address] = $result;
        return $result;
    }

    /**
     * @return array<int, string>
     */
    private static function alternateQueries(string $address): array
    {
        $parts = array_values(array_filter(
            array_map('trim', explode(',', $address)),
            static function (string $part): bool {
                if ($part === '') {
                    return false;
                }
                if (preg_match('/^\d{4}$/', $part)) {
                    return false;
                }
                return !preg_match('/^philippines$/i', $part);
            }
        ));

        $queries = [];
        $count = count($parts);
        if ($count >= 4) {
            $queries[] = implode(', ', array_slice($parts, -4)) . ', Philippines';
        }
        if ($count >= 3) {
            $queries[] = implode(', ', array_slice($parts, -3)) . ', Philippines';
        }
        if ($count >= 2) {
            $queries[] = implode(', ', array_slice($parts, -2)) . ', Philippines';
        }

        return array_values(array_unique($queries));
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private static function lookup(string $address): ?array
    {
        $key = trim((string) (App::config('maps')['google_api_key'] ?? ''));
        if ($key !== '') {
            $places = self::googlePlacesTextSearch($address, $key);
            if ($places !== null) {
                return $places;
            }
            $geocode = self::googleGeocode($address, $key);
            if ($geocode !== null) {
                return $geocode;
            }
        }

        $mapbox = trim((string) (App::config('maps')['mapbox_access_token'] ?? ''));
        if ($mapbox !== '') {
            $mb = self::mapboxGeocode($address, $mapbox);
            if ($mb !== null) {
                return $mb;
            }
        }

        return self::nominatim($address);
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private static function mapboxGeocode(string $address, string $token): ?array
    {
        $url = 'https://api.mapbox.com/geocoding/v5/mapbox.places/'
            . rawurlencode($address)
            . '.json?' . http_build_query([
                'access_token' => $token,
                'country' => 'ph',
                'limit' => 1,
                'autocomplete' => 'false',
            ]);

        $payload = self::fetchJson($url);
        if (!is_array($payload) || empty($payload['features'][0]['center'])) {
            return null;
        }

        $center = $payload['features'][0]['center'];
        return [
            'latitude' => (float) $center[1],
            'longitude' => (float) $center[0],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private static function googlePlacesTextSearch(string $query, string $key): ?array
    {
        $url = 'https://maps.googleapis.com/maps/api/place/textsearch/json?' . http_build_query([
            'query' => $query,
            'key' => $key,
            'region' => 'ph',
        ]);

        $payload = self::fetchJson($url);
        if ($payload === null || ($payload['status'] ?? '') !== 'OK' || empty($payload['results'][0]['geometry']['location'])) {
            return null;
        }

        $loc = $payload['results'][0]['geometry']['location'];
        return [
            'latitude' => (float) $loc['lat'],
            'longitude' => (float) $loc['lng'],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private static function googleGeocode(string $address, string $key): ?array
    {
        $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
            'address' => $address,
            'key' => $key,
            'region' => 'ph',
            'components' => 'country:PH',
        ]);

        $payload = self::fetchJson($url);
        if ($payload === null || ($payload['status'] ?? '') !== 'OK' || empty($payload['results'][0]['geometry']['location'])) {
            return null;
        }

        $best = self::pickBestGeocodeResult($payload['results']);
        if ($best === null) {
            return null;
        }

        return [
            'latitude' => (float) $best['lat'],
            'longitude' => (float) $best['lng'],
        ];
    }

    /**
     * Prefer rooftop/street-level results over vague region matches.
     *
     * @param array<int, array<string, mixed>> $results
     * @return array{lat: float, lng: float}|null
     */
    private static function pickBestGeocodeResult(array $results): ?array
    {
        $priority = ['ROOFTOP' => 4, 'RANGE_INTERPOLATED' => 3, 'GEOMETRIC_CENTER' => 2, 'APPROXIMATE' => 1];
        $best = null;
        $bestScore = -1;

        foreach ($results as $row) {
            if (empty($row['geometry']['location'])) {
                continue;
            }
            $type = (string) ($row['geometry']['location_type'] ?? 'APPROXIMATE');
            $score = $priority[$type] ?? 0;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $row['geometry']['location'];
            }
        }

        if ($best === null) {
            return null;
        }

        return [
            'lat' => (float) $best['lat'],
            'lng' => (float) $best['lng'],
        ];
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private static function nominatim(string $address): ?array
    {
        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $address,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'ph',
            'addressdetails' => 1,
        ]);

        $payload = self::fetchJson($url, [
            'User-Agent: GuideMate/1.0 (Cebu travel platform; contact@guidemate.local)',
            'Accept: application/json',
        ]);

        if (!is_array($payload) || $payload === [] || !isset($payload[0]['lat'], $payload[0]['lon'])) {
            return null;
        }

        return [
            'latitude' => (float) $payload[0]['lat'],
            'longitude' => (float) $payload[0]['lon'],
        ];
    }

    /**
     * @param array<int, string> $headers
     * @return mixed
     */
    private static function fetchJson(string $url, array $headers = []): mixed
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => implode("\r\n", $headers),
                    'timeout' => 12,
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
        }

        if (!is_string($body) || $body === '') {
            return null;
        }

        return json_decode($body, true);
    }
}
