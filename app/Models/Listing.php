<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Geo;

final class Listing
{
    private const SELECT = 'SELECT l.*, c.name AS category_name, c.slug AS category_slug, c.icon AS category_icon,
                u.name AS owner_name, u.avatar AS owner_avatar, u.role AS owner_role,
                COALESCE(AVG(r.rating), 0) AS avg_rating, COUNT(DISTINCT r.id) AS review_count
            FROM listings l
            JOIN categories c ON c.id = l.category_id
            JOIN users u ON u.id = l.user_id
            LEFT JOIN reviews r ON r.listing_id = l.id';

    /**
     * Search/browse approved listings with optional filters.
     *
     * @param array<string, mixed> $filters keys: q, category, area, sort, min_rating
     * @return array<int, array<string, mixed>>
     */
    public static function search(array $filters = []): array
    {
        $where = ['l.status = "approved"'];
        $params = [];

        if (!empty($filters['q'])) {
            $where[] = '(l.title LIKE ? OR l.summary LIKE ? OR l.area LIKE ? OR l.description LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['category'])) {
            $where[] = 'c.slug = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['area'])) {
            $where[] = 'l.area LIKE ?';
            $params[] = '%' . $filters['area'] . '%';
        }
        if (!empty($filters['featured'])) {
            $where[] = 'l.is_featured = 1';
        }
        if (Category::isHiddenSlug((string) ($filters['category'] ?? ''))) {
            return [];
        }
        if (empty($filters['include_hidden'])) {
            $where[] = 'c.slug NOT IN ("restaurants")';
        }

        $sql = self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' GROUP BY l.id';

        $minRating = (string) ($filters['min_rating'] ?? '');
        $isAnyRating = $minRating === '' || $minRating === 'any';

        if (!$isAnyRating) {
            $r = (float) $minRating;
            if ($r >= 5.0) {
                $sql .= ' HAVING ROUND(avg_rating, 1) >= 5.0';
            } elseif ($r >= 4.5) {
                $sql .= ' HAVING ROUND(avg_rating, 1) >= 4.5 AND ROUND(avg_rating, 1) < 5.0';
            } elseif ($r >= 4.0) {
                $sql .= ' HAVING ROUND(avg_rating, 1) >= 4.0 AND ROUND(avg_rating, 1) < 4.5';
            } else {
                $sql .= ' HAVING ROUND(avg_rating, 1) >= ' . $r;
            }
        }

        $sort = (string) ($filters['sort'] ?? '');
        $sql .= match ($sort) {
            'price_low' => ' ORDER BY l.price ASC',
            'price_high' => ' ORDER BY l.price DESC',
            'newest' => ' ORDER BY l.created_at DESC',
            'recommended' => ' ORDER BY l.is_featured DESC, avg_rating DESC, l.created_at DESC',
            'rating' => ' ORDER BY avg_rating DESC, review_count DESC',
            default => ' ORDER BY avg_rating DESC, review_count DESC',
        };

        return Database::all($sql, $params);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function featured(int $limit = 6): array
    {
        $sql = self::SELECT . ' WHERE l.status = "approved" AND l.is_featured = 1
                AND c.slug NOT IN ("restaurants")
                GROUP BY l.id ORDER BY avg_rating DESC LIMIT ' . (int) $limit;
        return Database::all($sql);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        $sql = self::SELECT . ' WHERE l.slug = ? GROUP BY l.id';
        return Database::first($sql, [$slug]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        $sql = self::SELECT . ' WHERE l.id = ? GROUP BY l.id';
        return Database::first($sql, [$id]);
    }

    /**
     * Listings owned by a guide (any status).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forOwner(int $userId): array
    {
        $sql = self::SELECT . ' WHERE l.user_id = ? GROUP BY l.id ORDER BY l.created_at DESC';
        return Database::all($sql, [$userId]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function gallery(int $listingId): array
    {
        return Database::all(
            'SELECT * FROM listing_images WHERE listing_id = ? ORDER BY sort_order, id',
            [$listingId]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function create(array $data): int
    {
        $coords = Geo::resolveCoordinates(
            (string) ($data['area'] ?? ''),
            isset($data['address']) ? (string) $data['address'] : null,
            isset($data['title']) ? (string) $data['title'] : null
        );
        $data['latitude'] = $coords['latitude'] ?? null;
        $data['longitude'] = $coords['longitude'] ?? null;

        return Database::insert(
            'INSERT INTO listings
                (user_id, category_id, title, slug, summary, description, area, address, latitude, longitude,
                 price, price_unit, duration, included, not_included, cover_image, status)
             VALUES (:user_id, :category_id, :title, :slug, :summary, :description, :area, :address, :latitude, :longitude,
                     :price, :price_unit, :duration, :included, :not_included, :cover_image, :status)',
            $data
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $coords = Geo::resolveCoordinates(
            (string) ($data['area'] ?? ''),
            isset($data['address']) ? (string) $data['address'] : null,
            isset($data['title']) ? (string) $data['title'] : null
        );
        $data['latitude'] = $coords['latitude'] ?? null;
        $data['longitude'] = $coords['longitude'] ?? null;
        $data['id'] = $id;
        Database::run(
            'UPDATE listings SET category_id = :category_id, title = :title, summary = :summary,
                description = :description, area = :area, address = :address,
                latitude = :latitude, longitude = :longitude,
                price = :price, price_unit = :price_unit, duration = :duration, included = :included,
                not_included = :not_included, cover_image = :cover_image
             WHERE id = :id',
            $data
        );
    }

    /** Refresh coordinates from guide address (and area fallback) for all listings. */
    public static function backfillCoordinates(): int
    {
        $rows = Database::all('SELECT id, title, area, address FROM listings');
        $updated = 0;
        foreach ($rows as $row) {
            $coords = Geo::resolveCoordinates(
                (string) ($row['area'] ?? ''),
                (string) ($row['address'] ?? ''),
                (string) ($row['title'] ?? '')
            );
            if ($coords === null) {
                continue;
            }
            Database::run(
                'UPDATE listings SET latitude = ?, longitude = ? WHERE id = ?',
                [$coords['latitude'], $coords['longitude'], (int) $row['id']]
            );
            $updated++;
        }
        return $updated;
    }

    /** Update coordinates for listings the tourist has booked (trip map). */
    public static function syncCoordinatesForBookings(int $userId): void
    {
        $rows = Database::all(
            'SELECT DISTINCT l.id, l.title, l.area, l.address
             FROM listings l
             JOIN bookings b ON b.listing_id = l.id
             WHERE b.user_id = ?',
            [$userId]
        );
        foreach ($rows as $row) {
            $coords = Geo::resolveCoordinates(
                (string) ($row['area'] ?? ''),
                (string) ($row['address'] ?? ''),
                (string) ($row['title'] ?? '')
            );
            if ($coords === null) {
                continue;
            }
            Database::run(
                'UPDATE listings SET latitude = ?, longitude = ? WHERE id = ?',
                [$coords['latitude'], $coords['longitude'], (int) $row['id']]
            );
        }
    }

    public static function setStatus(int $id, string $status): void
    {
        Database::run('UPDATE listings SET status = ? WHERE id = ?', [$status, $id]);
    }

    public static function setFeatured(int $id, bool $featured): void
    {
        Database::run('UPDATE listings SET is_featured = ? WHERE id = ?', [$featured ? 1 : 0, $id]);
    }

    /** Active sale price when sale_ends_at is today or later. */
    public static function effectivePrice(array $listing): float
    {
        $sale = $listing['sale_price'] ?? null;
        $ends = $listing['sale_ends_at'] ?? null;
        if ($sale !== null && $sale !== '' && ($ends === null || $ends === '' || $ends >= date('Y-m-d'))) {
            return (float) $sale;
        }
        return (float) $listing['price'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function withCoordinates(): array
    {
        return Database::all(
            'SELECT id, title, slug, area, latitude, longitude, price FROM listings
             WHERE status = "approved" AND latitude IS NOT NULL AND longitude IS NOT NULL'
        );
    }

    /**
     * Find the closest approved hotel to a coordinate. Returns the hotel row
     * plus a `distance_km` value, or null when no hotel has coordinates.
     *
     * @return array<string, mixed>|null
     */
    public static function nearestHotel(float $lat, float $lng, int $excludeId = 0): ?array
    {
        $rows = Database::all(
            'SELECT l.id, l.title, l.slug, l.area, l.address, l.cover_image,
                    l.latitude, l.longitude, l.price, l.price_unit
             FROM listings l
             JOIN categories c ON c.id = l.category_id
             WHERE l.status = "approved" AND c.slug = "hotels"
               AND l.latitude IS NOT NULL AND l.longitude IS NOT NULL
               AND l.id <> ?',
            [$excludeId]
        );

        $best = null;
        $bestDist = INF;
        foreach ($rows as $row) {
            $dist = Geo::distanceKm($lat, $lng, (float) $row['latitude'], (float) $row['longitude']);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $row;
            }
        }

        if ($best === null) {
            return null;
        }
        $best['distance_km'] = round($bestDist, 1);
        return $best;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function byArea(string $area): array
    {
        return Database::all(
            'SELECT l.*, c.name AS category_name,
                    (SELECT AVG(r.rating) FROM reviews r WHERE r.listing_id = l.id) AS avg_rating,
                    (SELECT COUNT(*) FROM reviews r WHERE r.listing_id = l.id) AS review_count
             FROM listings l JOIN categories c ON c.id = l.category_id
             WHERE l.status = "approved" AND c.slug NOT IN ("restaurants") AND l.area LIKE ? ORDER BY l.is_featured DESC, l.created_at DESC',
            ['%' . $area . '%']
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM listings WHERE id = ?', [$id]);
    }

    public static function addImage(int $listingId, string $path, int $sort = 0): void
    {
        Database::run('INSERT INTO listing_images (listing_id, image_path, sort_order) VALUES (?, ?, ?)', [$listingId, $path, $sort]);
    }

    public static function slugExists(string $slug): bool
    {
        return Database::first('SELECT id FROM listings WHERE slug = ?', [$slug]) !== null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function pending(): array
    {
        $sql = self::SELECT . ' WHERE l.status = "pending" GROUP BY l.id ORDER BY l.created_at ASC';
        return Database::all($sql);
    }

    public static function count(?string $status = null): int
    {
        if ($status !== null) {
            $row = Database::first(
                'SELECT COUNT(*) AS c FROM listings l
                 JOIN categories c ON c.id = l.category_id
                 WHERE l.status = ? AND c.slug NOT IN ("restaurants")',
                [$status]
            );
        } else {
            $row = Database::first(
                'SELECT COUNT(*) AS c FROM listings l
                 JOIN categories c ON c.id = l.category_id
                 WHERE c.slug NOT IN ("restaurants")'
            );
        }
        return (int) ($row['c'] ?? 0);
    }

    /** Only tour-guide listings can be booked. Hotels and things-to-do are inquire-only. */
    public static function isBookable(array $listing): bool
    {
        return ($listing['category_slug'] ?? '') === 'tour-guides';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function topByBookings(int $limit = 10): array
    {
        return Database::all(
            'SELECT l.id, l.title, l.slug, l.area,
                    COUNT(b.id) AS booking_count,
                    COALESCE(SUM(CASE WHEN p.status = "paid" THEN b.total_amount ELSE 0 END), 0) AS revenue
             FROM listings l
             LEFT JOIN bookings b ON b.listing_id = l.id
             LEFT JOIN payments p ON p.booking_id = b.id
             WHERE l.status = "approved"
             GROUP BY l.id
             ORDER BY booking_count DESC, revenue DESC
             LIMIT ' . (int) $limit
        );
    }
}
