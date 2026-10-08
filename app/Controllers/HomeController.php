<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Review;
use App\Services\RecommendationService;
use App\Services\WeatherService;

final class HomeController extends Controller
{
    private const AREAS = [
        'oslob' => ['name' => 'Oslob', 'tagline' => 'Whale sharks, waterfalls, and quiet coastal escapes in southern Cebu.'],
        'moalboal' => ['name' => 'Moalboal', 'tagline' => 'Sardine runs, sea turtles, and world-class diving on the west coast.'],
        'mactan' => ['name' => 'Mactan', 'tagline' => 'Resorts, island hopping, and easy access from Cebu–Mactan International Airport.'],
        'bantayan' => ['name' => 'Bantayan Island', 'tagline' => 'White-sand beaches and laid-back island life off the north coast.'],
        'cebu-city' => ['name' => 'Cebu City', 'tagline' => 'Heritage sites, food tours, and urban adventures in the Queen City.'],
    ];

    public function index(): void
    {
        $userId = Auth::check() ? (int) Auth::id() : null;
        $favIds = $userId !== null ? Favorite::idsForUser($userId) : [];

        $this->view('home/index', [
            'categories' => Category::withPublicCounts(),
            'featured' => Listing::featured(6),
            'recommended' => RecommendationService::forUser($userId, 6),
            'recommendedTitle' => $userId !== null ? __('home_recommended') : __('home_popular'),
            'recentReviews' => Review::recent(3),
            'favIds' => $favIds,
            'heroVideo' => App::config('hero') ?? [],
        ]);
    }

    public function area(string $slug): void
    {
        if (!isset(self::AREAS[$slug])) {
            abort(404, 'Area not found.');
        }
        $meta = self::AREAS[$slug];
        $favIds = Auth::check() ? Favorite::idsForUser((int) Auth::id()) : [];
        $this->view('home/area', [
            'title' => $meta['name'] . ', Cebu',
            'areaName' => $meta['name'],
            'tagline' => $meta['tagline'],
            'listings' => Listing::byArea($meta['name']),
            'favIds' => $favIds,
        ]);
    }

    public function policy(): void
    {
        $this->view('home/policy', [
            'title' => 'Platform Policy',
        ]);
    }

    public function terms(): void
    {
        $this->view('home/terms', ['title' => 'Terms of Service']);
    }

    public function privacy(): void
    {
        $this->view('home/privacy', ['title' => 'Privacy Policy']);
    }
}
