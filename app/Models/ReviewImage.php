<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ReviewImage
{
    public static function add(int $reviewId, string $path): void
    {
        Database::run('INSERT INTO review_images (review_id, file_path) VALUES (?, ?)', [$reviewId, $path]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forReview(int $reviewId): array
    {
        return Database::all('SELECT * FROM review_images WHERE review_id = ?', [$reviewId]);
    }
}
