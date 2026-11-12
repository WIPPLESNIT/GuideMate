<?php

declare(strict_types=1);

namespace App\Core;

use App\Services\GeocodingService;

/** Resolve map coordinates from guide address and Cebu area names. */
final class Geo
{
    /** Known POI hints when open geocoders miss a specific address (lat, lng). */
    private const ADDRESS_HINTS = [
        'sitio daligdigan' => [9.81922, 123.55321],
        'casay beach huts' => [9.81922, 123.55321],
        'casay beach hut' => [9.81922, 123.55321],
    ];

    /** @var array<string, array{0: float, 1: float}> — longer / specific names matched first */
    private const AREAS = [
        'cebu city' => [10.3157, 123.8854],
        'lapu-lapu' => [10.3103, 124.0180],
        'malapascua' => [11.3278, 124.1150],
        'kawasan' => [9.8087, 123.3747],
        'sitio daligdigan' => [9.81922, 123.55321],
        'casay beach' => [9.81922, 123.55321],
        'dalaguete' => [9.8190, 123.5530],
        'casay' => [9.81922, 123.55321],
        'moalboal' => [9.9431, 123.3972],
        'bantayan' => [11.1634, 123.7278],
        'mandaue' => [10.3236, 123.9225],
        'talisay' => [10.2447, 123.8494],
        'minglanilla' => [10.2440, 123.7960],
        'consolacion' => [10.3750, 123.9570],
        'compostela' => [10.4550, 123.9420],
        'danao' => [10.5208, 124.0275],
        'carcar' => [10.1061, 123.6422],
        'samboan' => [9.5311, 123.3064],
        'boljoon' => [9.6290, 123.4800],
        'oslob' => [9.5103, 123.4312],
        'badian' => [9.8687, 123.3740],
        'sogod' => [10.7500, 124.0000],
        'mactan' => [10.3103, 124.0180],
        'lapu' => [10.3103, 124.0180],
        'cebu' => [10.3157, 123.8854],
    ];

    /**
     * @return array{latitude: float, longitude: float, approximate: bool, source: string}|null
     */
    public static function resolveCoordinates(string $area, ?string $address = null, ?string $title = null): ?array
    {
        $address = trim((string) $address);
        $area = trim($area);
        $title = trim((string) $title);

        $fromHint = self::fromAddressHint($title, $address);
        if ($fromHint !== null) {
            return [
                'latitude' => $fromHint['latitude'],
                'longitude' => $fromHint['longitude'],
                'approximate' => false,
                'source' => 'hint',
            ];
        }

        $fromListing = GeocodingService::geocodeForListing($title, $address, $area);
        if ($fromListing !== null) {
            return [
                'latitude' => $fromListing['latitude'],
                'longitude' => $fromListing['longitude'],
                'approximate' => false,
                'source' => 'geocode',
            ];
        }

        $areaHaystack = strtolower(trim($title . ' ' . $address . ' ' . $area));
        if ($areaHaystack !== '') {
            $fromArea = self::fromArea($areaHaystack);
            if ($fromArea !== null) {
                return [
                    'latitude' => $fromArea['latitude'],
                    'longitude' => $fromArea['longitude'],
                    'approximate' => true,
                    'source' => 'area',
                ];
            }
        }

        return null;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private static function fromAddressHint(string $title, string $address): ?array
    {
        $hay = strtolower(trim($title . ' ' . $address));
        if ($hay === '') {
            return null;
        }
        $keys = array_keys(self::ADDRESS_HINTS);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($keys as $key) {
            if (str_contains($hay, $key)) {
                $coords = self::ADDRESS_HINTS[$key];
                return ['latitude' => $coords[0], 'longitude' => $coords[1]];
            }
        }
        return null;
    }

    /**
     * Great-circle distance between two coordinates, in kilometres (haversine).
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public static function fromArea(?string $area): ?array
    {
        if ($area === null || trim($area) === '') {
            return null;
        }
        $hay = strtolower($area);
        $keys = array_keys(self::AREAS);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($keys as $key) {
            if (str_contains($hay, $key)) {
                $coords = self::AREAS[$key];
                return ['latitude' => $coords[0], 'longitude' => $coords[1]];
            }
        }
        return null;
    }

    /**
     * Check if given coordinates are valid and within the regional bounds (Cebu / Central Visayas).
     */
    public static function isValidCoordinate(?float $lat, ?float $lng): bool
    {
        if ($lat === null || $lng === null) {
            return false;
        }
        // Valid regional bounding box for Cebu and Central Visayas
        return $lat >= 9.0 && $lat <= 12.0 && $lng >= 122.5 && $lng <= 125.0;
    }

    /**
     * Return user-friendly missing location message tailored to the listing type.
     */
    public static function missingLocationMessage(string $categorySlug, bool $unresolvable = false): string
    {
        if ($unresolvable) {
            return 'The saved address could not be resolved to a valid map location.';
        }

        $cat = strtolower(trim($categorySlug));
        return match ($cat) {
            'hotels', 'hotel' => 'No address is attached to this hotel.',
            'attractions', 'attraction' => 'No address is attached to this attraction.',
            'things-to-do', 'activity', 'activities', 'packages', 'package' => 'No address is attached to this activity.',
            'rentals', 'rental', 'vehicles', 'vehicle' => 'No pickup location is attached to this vehicle.',
            default => 'No address is attached to this listing.',
        };
    }

    /**
     * Resolve map coordinates for a vehicle rental using its actual saved pickup address / location.
     *
     * @param array<string, mixed> $vehicle
     * @return array{latitude: float, longitude: float, address: string, approximate: bool}|null
     */
    public static function forVehicle(array $vehicle): ?array
    {
        $pickupAddress = trim((string) ($vehicle['address'] ?? ''));
        $location = trim((string) ($vehicle['location'] ?? ''));
        $name = trim((string) ($vehicle['name'] ?? $vehicle['vehicle_name'] ?? ''));

        // Use actual pickup location/address rather than general business address
        $effectiveAddress = $pickupAddress !== '' ? $pickupAddress : $location;
        if ($effectiveAddress === '') {
            return null;
        }

        $resolved = self::resolveCoordinates($location, $pickupAddress, $name);
        if ($resolved !== null && self::isValidCoordinate($resolved['latitude'], $resolved['longitude'])) {
            return [
                'latitude' => $resolved['latitude'],
                'longitude' => $resolved['longitude'],
                'address' => $effectiveAddress,
                'approximate' => $resolved['approximate'],
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $listing
     * @return array{latitude: float, longitude: float, approximate: bool}|null
     */
    public static function forListing(array $listing): ?array
    {
        // 1. If explicit valid saved coordinates exist in database, use them
        $lat = isset($listing['latitude']) && $listing['latitude'] !== '' ? (float) $listing['latitude'] : null;
        $lng = isset($listing['longitude']) && $listing['longitude'] !== '' ? (float) $listing['longitude'] : null;
        if (self::isValidCoordinate($lat, $lng)) {
            return [
                'latitude' => $lat,
                'longitude' => $lng,
                'approximate' => false,
            ];
        }

        // 2. Validate that listing has a usable address or area
        $address = trim((string) ($listing['address'] ?? ''));
        $area = trim((string) ($listing['area'] ?? ''));
        $title = trim((string) ($listing['listing_title'] ?? $listing['title'] ?? ''));

        if ($address === '' && $area === '') {
            return null;
        }

        $resolved = self::resolveCoordinates(
            $area,
            $address !== '' ? $address : null,
            $title !== '' ? $title : null
        );

        if ($resolved !== null && self::isValidCoordinate($resolved['latitude'], $resolved['longitude'])) {
            return [
                'latitude' => $resolved['latitude'],
                'longitude' => $resolved['longitude'],
                'approximate' => $resolved['approximate'],
            ];
        }

        return null;
    }
}
