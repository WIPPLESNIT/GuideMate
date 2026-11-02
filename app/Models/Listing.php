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
            $catSlug = (string) $filters['category'];
            if ($catSlug === 'attraction-and-tours' || $catSlug === 'attraction') {
                $catSlug = 'attractions';
            }
            if ($catSlug === 'attractions') {
                $where[] = 'c.slug IN ("attractions", "attraction-and-tours")';
            } else {
                $where[] = 'c.slug = ?';
                $params[] = $catSlug;
            }
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
     * Hotel listings owned by a specific hotel partner.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forHotelOwner(int $userId): array
    {
        $sql = self::SELECT . ' WHERE l.user_id = ? AND c.slug = "hotels" GROUP BY l.id ORDER BY l.created_at DESC';
        return Database::all($sql, [$userId]);
    }

    /**
     * Guide tour and experience listings owned by a guide (excludes hotels).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forGuideOwner(int $userId): array
    {
        $sql = self::SELECT . ' WHERE l.user_id = ? AND c.slug != "hotels" GROUP BY l.id ORDER BY l.created_at DESC';
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
        $data['submitted_at'] = $data['submitted_at'] ?? date('Y-m-d H:i:s');
        $data['address'] = $data['address'] ?? null;
        $data['summary'] = $data['summary'] ?? null;
        $data['duration'] = $data['duration'] ?? null;
        $data['capacity'] = isset($data['capacity']) && (int) $data['capacity'] > 0 ? (int) $data['capacity'] : 10;
        $data['included'] = $data['included'] ?? null;
        $data['not_included'] = $data['not_included'] ?? null;
        $data['price_unit'] = !empty($data['price_unit']) ? (string) $data['price_unit'] : 'per person';
        $data['cover_image'] = $data['cover_image'] ?? null;
        $data['status'] = $data['status'] ?? 'pending';

        return Database::insert(
            'INSERT INTO listings
                (user_id, category_id, title, slug, summary, description, area, address, latitude, longitude,
                 price, price_unit, duration, capacity, included, not_included, cover_image, status, submitted_at)
             VALUES (:user_id, :category_id, :title, :slug, :summary, :description, :area, :address, :latitude, :longitude,
                     :price, :price_unit, :duration, :capacity, :included, :not_included, :cover_image, :status, :submitted_at)',
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
        $data['capacity'] = isset($data['capacity']) && (int) $data['capacity'] > 0 ? (int) $data['capacity'] : 10;
        $data['id'] = $id;
        Database::run(
            'UPDATE listings SET category_id = :category_id, title = :title, summary = :summary,
                description = :description, area = :area, address = :address,
                latitude = :latitude, longitude = :longitude,
                price = :price, price_unit = :price_unit, duration = :duration, capacity = :capacity, included = :included,
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
        Database::run('DELETE FROM hotel_rooms WHERE listing_id = ?', [$id]);
        Database::run('DELETE FROM listing_images WHERE listing_id = ?', [$id]);
        Database::run('DELETE FROM listings WHERE id = ?', [$id]);
    }

    /**
     * Return up to 3 distinct photos for a listing.
     *
     * @param int|array<string, mixed> $listing
     * @return array<int, string>
     */
    public static function getPhotos(int|array $listing): array
    {
        if (is_int($listing)) {
            $found = self::find($listing);
            if ($found === null) {
                return [];
            }
            $listing = $found;
        }

        $photos = [];
        $cover = trim((string) ($listing['cover_image'] ?? ''));
        if ($cover !== '') {
            $photos[] = $cover;
        }

        $gallery = self::gallery((int) ($listing['id'] ?? 0));
        foreach ($gallery as $item) {
            $path = trim((string) ($item['image_path'] ?? ''));
            if ($path !== '' && !in_array($path, $photos, true)) {
                $photos[] = $path;
            }
        }

        return array_values(array_slice($photos, 0, 3));
    }

    /**
     * Sync up to 3 images for a listing in listing_images and listings.cover_image.
     *
     * @param int $listingId
     * @param array<int, string> $photos
     */
    public static function syncImages(int $listingId, array $photos): void
    {
        $clean = [];
        foreach ($photos as $p) {
            $p = trim((string) $p);
            if ($p !== '' && !in_array($p, $clean, true)) {
                $clean[] = $p;
            }
        }
        $clean = array_values(array_slice($clean, 0, 3));

        if ($clean !== []) {
            \App\Core\Database::run('UPDATE listings SET cover_image = ? WHERE id = ?', [$clean[0], $listingId]);
            \App\Core\Database::run('DELETE FROM listing_images WHERE listing_id = ?', [$listingId]);
            foreach ($clean as $idx => $p) {
                self::addImage($listingId, $p, $idx + 1);
            }
        }
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

    /** Attractions & tours and hotels support direct online booking. */
    public static function isBookable(array $listing): bool
    {
        $slug = (string) ($listing['category_slug'] ?? '');
        return in_array($slug, ['attractions', 'attraction-and-tours', 'tour-guides', 'hotels'], true);
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

    /**
     * All hotel property listings for administrator review.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function allHotelsForAdmin(?string $status = null): array
    {
        $params = [];
        $where = ['c.slug = "hotels"'];
        if ($status !== null && $status !== '' && $status !== 'all') {
            $where[] = 'l.status = ?';
            $params[] = $status;
        }

        $sql = 'SELECT l.*, c.name AS category_name, c.slug AS category_slug,
                       u.name AS provider_name, u.email AS provider_email,
                       r.name AS reviewer_name,
                       (SELECT COUNT(*) FROM hotel_rooms hr WHERE hr.listing_id = l.id) AS room_count
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                JOIN users u ON u.id = l.user_id
                LEFT JOIN users r ON r.id = l.reviewed_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY CASE WHEN l.status = "pending" THEN 0 ELSE 1 END, COALESCE(l.submitted_at, l.created_at) DESC';

        return Database::all($sql, $params);
    }

    public static function pendingHotelsCount(): int
    {
        $res = Database::first(
            'SELECT COUNT(*) AS cnt FROM listings l
             JOIN categories c ON c.id = l.category_id
             WHERE c.slug = "hotels" AND l.status = "pending"'
        );
        return (int) ($res['cnt'] ?? 0);
    }

    public static function approveHotel(int $id, int $reviewerId): void
    {
        Database::run(
            "UPDATE listings
             SET status = 'approved',
                 reviewed_at = NOW(), reviewed_by = ?, rejection_reason = NULL
             WHERE id = ?",
            [$reviewerId, $id]
        );
    }

    public static function rejectHotel(int $id, int $reviewerId, ?string $reason = null): void
    {
        Database::run(
            "UPDATE listings
             SET status = 'rejected',
                 reviewed_at = NOW(), reviewed_by = ?, rejection_reason = ?
             WHERE id = ?",
            [$reviewerId, $reason, $id]
        );
    }

    public static function resubmitHotel(int $id): void
    {
        Database::run(
            "UPDATE listings
             SET status = 'pending', submitted_at = NOW()
             WHERE id = ?",
            [$id]
        );
    }

    public static function hotelStatusPillClass(string $status): string
    {
        return match ($status) {
            'approved' => 'pill-approved',
            'pending' => 'pill-pending',
            'rejected' => 'pill-cancelled',
            default => 'pill-secondary',
        };
    }

    public static function hotelStatusLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Approved',
            'pending' => 'Pending Approval',
            'rejected' => 'Rejected',
            default => ucfirst($status),
        };
    }

    /**
     * Parse hotel check-in and check-out times from a duration string or return defaults.
     *
     * @param string|null $duration e.g. "10:00PM - 12:00PM", "Check-in 2:00 PM / Check-out 12:00 PM"
     * @return array{
     *   valid: bool,
     *   check_in_time: string,
     *   check_out_time: string,
     *   check_in_time_label: string,
     *   check_out_time_label: string,
     *   check_in_out_label: string,
     *   summary_label: string
     * }
     */
    public static function parseCheckInOutTimes(?string $duration): array
    {
        $defaultIn = '14:00:00';
        $defaultOut = '12:00:00';
        $defaultInLabel = '2:00 PM';
        $defaultOutLabel = '12:00 PM';

        $raw = trim((string) $duration);
        if ($raw === '') {
            return [
                'valid' => true,
                'check_in_time' => $defaultIn,
                'check_out_time' => $defaultOut,
                'check_in_time_label' => $defaultInLabel,
                'check_out_time_label' => $defaultOutLabel,
                'check_in_out_label' => "{$defaultInLabel} / {$defaultOutLabel}",
                'summary_label' => "Check-in from {$defaultInLabel} · Check-out until {$defaultOutLabel}",
            ];
        }

        preg_match_all('/(?:(\d{1,2}(?::\d{2})?\s*(?:am|pm))|(\d{1,2}:\d{2}))/i', $raw, $matches);
        $found = $matches[0] ?? [];

        $inTs = false;
        $outTs = false;

        if (count($found) >= 2) {
            $inTs = strtotime($found[0]);
            $outTs = strtotime($found[1]);
        } elseif (count($found) === 1) {
            $inTs = strtotime($found[0]);
        }

        $inTime = $inTs !== false ? date('H:i:s', $inTs) : $defaultIn;
        $outTime = $outTs !== false ? date('H:i:s', $outTs) : $defaultOut;
        $inLabel = $inTs !== false ? date('g:i A', $inTs) : $defaultInLabel;
        $outLabel = $outTs !== false ? date('g:i A', $outTs) : $defaultOutLabel;

        return [
            'valid' => count($found) >= 1 && $inTs !== false,
            'check_in_time' => $inTime,
            'check_out_time' => $outTime,
            'check_in_time_label' => $inLabel,
            'check_out_time_label' => $outLabel,
            'check_in_out_label' => "{$inLabel} / {$outLabel}",
            'summary_label' => "Check-in from {$inLabel} · Check-out until {$outLabel}",
        ];
    }
}
