<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Models\Favorite;
use App\Models\Listing;

final class RecommendationService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forUser(?int $userId, int $limit = 6): array
    {
        if ($userId === null) {
            return Listing::featured($limit);
        }

        $favIds = Favorite::idsForUser($userId);
        $areas = [];
        $categories = [];
        if ($favIds !== []) {
            foreach (Listing::featured(50) as $l) {
                if (in_array((int) $l['id'], $favIds, true)) {
                    $areas[] = (string) $l['area'];
                    $categories[] = (int) $l['category_id'];
                }
            }
        }

        $all = Listing::featured(30);
        $scored = [];
        foreach ($all as $l) {
            if (in_array((int) $l['id'], $favIds, true)) {
                continue;
            }
            $score = (float) ($l['avg_rating'] ?? 0) + ((int) ($l['is_featured'] ?? 0) * 2);
            if ($areas !== [] && in_array($l['area'], $areas, true)) {
                $score += 3;
            }
            if ($categories !== [] && in_array((int) $l['category_id'], $categories, true)) {
                $score += 2;
            }
            $l['_score'] = $score;
            $scored[] = $l;
        }

        usort($scored, static fn($a, $b) => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));
        return array_slice($scored, 0, $limit);
    }
}
