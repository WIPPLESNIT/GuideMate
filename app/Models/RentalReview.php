<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class RentalReview
{
    /**
     * Get review for a specific rental request if one exists.
     *
     * @return array<string, mixed>|null
     */
    public static function findForRental(int $rentalId): ?array
    {
        return Database::first(
            'SELECT * FROM rental_reviews WHERE rental_id = ?',
            [$rentalId]
        );
    }

    /**
     * Find a review by its primary ID.
     *
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM rental_reviews WHERE id = ?',
            [$id]
        );
    }

    /**
     * Submit a new review or edit an existing one. Enforces single review per rental.
     *
     * @return array<string, mixed>
     */
    public static function createOrUpdate(
        int $rentalId,
        int $reviewerId,
        int $providerId,
        ?string $vehicleId,
        int $rating,
        string $feedback
    ): array {
        $rating = max(1, min(5, $rating));
        $feedback = trim($feedback);

        $existing = self::findForRental($rentalId);

        if ($existing !== null) {
            // Verify ownership: only the original reviewer can edit
            if ((int) $existing['reviewer_id'] !== $reviewerId) {
                throw new \RuntimeException('Unauthorized: only the tourist who created this review can edit it.');
            }

            Database::run(
                'UPDATE rental_reviews
                 SET rating = ?, feedback = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?',
                [$rating, $feedback, (int) $existing['id']]
            );

            return self::find((int) $existing['id']) ?? [];
        }

        $id = Database::insert(
            'INSERT INTO rental_reviews (rental_id, reviewer_id, provider_id, vehicle_id, rating, feedback)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$rentalId, $reviewerId, $providerId, $vehicleId, $rating, $feedback]
        );

        return self::find($id) ?? [];
    }

    /**
     * Calculate dynamic stats for a vehicle rental provider:
     * - Average rating (0.0 if no reviews)
     * - Total reviews count
     * - Completed rentals count
     *
     * @return array{rating: float, reviews_count: int, completed_rentals: int}
     */
    public static function providerStats(int $providerId): array
    {
        // 1. Dynamic review aggregation from rental_reviews
        $reviewRow = Database::first(
            'SELECT AVG(rating) AS avg_rating, COUNT(*) AS count_reviews
             FROM rental_reviews
             WHERE provider_id = ?',
            [$providerId]
        );

        $reviewsCount = (int) ($reviewRow['count_reviews'] ?? 0);
        $avgRating = $reviewsCount > 0 ? round((float) ($reviewRow['avg_rating'] ?? 0.0), 1) : 0.0;

        // 2. Dynamic completed rental count from rental_requests
        $providerUser = Database::first('SELECT name FROM users WHERE id = ?', [$providerId]);
        $providerName = (string) ($providerUser['name'] ?? '');

        $completedRow = Database::first(
            'SELECT COUNT(*) AS count_completed
             FROM rental_requests r
             LEFT JOIN vehicles v ON CAST(v.id AS CHAR) = CAST(r.vehicle_id AS CHAR)
             WHERE (v.user_id = ? OR r.shop_name = ?)
               AND r.status = "completed"',
            [$providerId, $providerName]
        );

        $completedCount = (int) ($completedRow['count_completed'] ?? 0);

        return [
            'rating' => $avgRating,
            'reviews_count' => $reviewsCount,
            'completed_rentals' => $completedCount,
        ];
    }

    /**
     * Calculate dynamic stats for a specific vehicle.
     *
     * @return array{rating: float, reviews_count: int, breakdown: array<int, int>}
     */
    public static function vehicleStats(string $vehicleId): array
    {
        $row = Database::first(
            'SELECT AVG(rv.rating) AS avg_rating, COUNT(*) AS count_reviews
             FROM rental_reviews rv
             WHERE CAST(rv.vehicle_id AS CHAR) = ?
                OR rv.rental_id IN (SELECT id FROM rental_requests WHERE CAST(vehicle_id AS CHAR) = ?)',
            [$vehicleId, $vehicleId]
        );

        $count = (int) ($row['count_reviews'] ?? 0);
        $avg = $count > 0 ? round((float) ($row['avg_rating'] ?? 0.0), 1) : 0.0;

        $breakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $breakdownRows = Database::all(
            'SELECT rv.rating, COUNT(*) AS cnt
             FROM rental_reviews rv
             WHERE CAST(rv.vehicle_id AS CHAR) = ?
                OR rv.rental_id IN (SELECT id FROM rental_requests WHERE CAST(vehicle_id AS CHAR) = ?)
             GROUP BY rv.rating',
            [$vehicleId, $vehicleId]
        );
        foreach ($breakdownRows as $bRow) {
            $star = (int) ($bRow['rating'] ?? 0);
            if (isset($breakdown[$star])) {
                $breakdown[$star] = (int) ($bRow['cnt'] ?? 0);
            }
        }

        return [
            'rating' => $avg,
            'reviews_count' => $count,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Reviews left for a specific vehicle.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forVehicle(int|string $vehicleId, int $limit = 30): array
    {
        $vId = (string) $vehicleId;
        return Database::all(
            'SELECT rv.*, rv.feedback AS comment, u.name AS reviewer_name, u.avatar AS reviewer_avatar,
                    u.name AS user_name, u.avatar AS user_avatar, u.id AS user_id,
                    v.name AS vehicle_name, v.slug AS vehicle_slug
             FROM rental_reviews rv
             JOIN users u ON u.id = rv.reviewer_id
             LEFT JOIN vehicles v ON CAST(v.id AS CHAR) = CAST(rv.vehicle_id AS CHAR)
             WHERE CAST(rv.vehicle_id AS CHAR) = ?
                OR rv.rental_id IN (SELECT id FROM rental_requests WHERE CAST(vehicle_id AS CHAR) = ?)
             ORDER BY rv.created_at DESC
             LIMIT ' . (int) $limit,
            [$vId, $vId]
        );
    }

    /**
     * Reviews left for a provider.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forProvider(int $providerId, int $limit = 20): array
    {
        return Database::all(
            'SELECT rv.*, rv.feedback AS comment, u.name AS reviewer_name, u.avatar AS reviewer_avatar,
                    u.name AS user_name, u.avatar AS user_avatar, u.id AS user_id,
                    rr.vehicle_name, rr.vehicle_name AS listing_title, rr.pickup_date,
                    v.slug AS vehicle_slug, v.slug AS listing_slug
             FROM rental_reviews rv
             JOIN users u ON u.id = rv.reviewer_id
             JOIN rental_requests rr ON rr.id = rv.rental_id
             LEFT JOIN vehicles v ON CAST(v.id AS CHAR) = CAST(rv.vehicle_id AS CHAR)
             WHERE rv.provider_id = ?
             ORDER BY rv.created_at DESC
             LIMIT ' . (int) $limit,
            [$providerId]
        );
    }
}
