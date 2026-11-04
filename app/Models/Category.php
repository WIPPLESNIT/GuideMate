<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Category
{
    /** Categories hidden from the tourist catalog (same as the mobile app). */
    public const HIDDEN_SLUGS = ['restaurants', 'packages', 'travel-packages'];

    public static function isHiddenSlug(?string $slug): bool
    {
        if ($slug === null || $slug === '') {
            return false;
        }
        return in_array(strtolower($slug), self::HIDDEN_SLUGS, true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return Database::all('SELECT * FROM categories ORDER BY sort_order, name');
    }

    /**
     * Tourist-facing categories (restaurants are retired, matching mobile).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function publicAll(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (array $row): bool => !self::isHiddenSlug((string) ($row['slug'] ?? ''))
        ));
    }

    /**
     * Ensure the Travel Packages category exists in the database.
     */
    public static function ensurePackagesCategory(): void
    {
        try {
            $exists = Database::first('SELECT id FROM categories WHERE slug = ?', ['packages']);
            if (!$exists) {
                Database::insert(
                    'INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (?, ?, ?, ?, ?)',
                    ['Travel Packages', 'packages', 'cube', 'All-inclusive multi-service trips curated and hosted by local guides', 5]
                );
            }
        } catch (\Throwable) {
            // Safe fallback
        }
    }

    /**
     * Categories available for tour guides (excludes hotels and hidden categories).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function forGuides(): array
    {
        self::ensurePackagesCategory();
        return array_values(array_filter(
            self::publicAll(),
            static fn (array $row): bool => ($row['slug'] ?? '') !== 'hotels'
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findBySlug(string $slug): ?array
    {
        return Database::first('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $id): ?array
    {
        return Database::first('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    /**
     * Categories with a count of approved listings in each.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function withCounts(): array
    {
        return Database::all(
            'SELECT c.*,
                CASE
                    WHEN c.slug IN ("attractions", "attraction-and-tours", "tour-guides") THEN GREATEST(
                        (SELECT COUNT(*) FROM users WHERE role = "guide" AND guide_status = "approved"),
                        COUNT(l.id)
                    )
                    ELSE COUNT(l.id)
                END AS listing_count
             FROM categories c
             LEFT JOIN listings l ON l.category_id = c.id AND l.status = "approved"
             GROUP BY c.id
             ORDER BY c.sort_order, c.name'
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function withPublicCounts(): array
    {
        return array_values(array_filter(
            self::withCounts(),
            static fn (array $row): bool => !self::isHiddenSlug((string) ($row['slug'] ?? ''))
        ));
    }
}
