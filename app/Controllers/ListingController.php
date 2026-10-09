<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Geo;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use App\Services\WeatherService;

final class ListingController extends Controller
{
    public function index(): void
    {
        $this->renderBrowse(null, 'Explore Cebu', 'All tours, guides and stays across Cebu.');
    }

    public function thingsToDo(): void
    {
        $this->renderBrowse('things-to-do', 'Things to Do', 'Tours, attractions and unforgettable activities.');
    }

    public function tourGuides(): void
    {
        $userId = Auth::id();
        $guides = [];
        foreach (User::guides('approved') as $u) {
            if ($userId !== null && (int) $u['id'] === $userId) {
                continue;
            }
            $stats = Booking::guideRatingStats((int) $u['id']);
            $u['rating'] = (float) ($stats['avg_rating'] ?? 0);
            $u['review_count'] = (int) ($stats['review_count'] ?? 0);
            $u['completed_tours'] = (int) ($stats['completed'] ?? 0);
            $u['available'] = !Booking::isGuideBusyToday((int) $u['id']);
            $u['online'] = User::isOnline((int) $u['id']);
            $guides[] = $u;
        }
        usort($guides, static function (array $a, array $b): int {
            return [$b['available'], $b['rating'], $b['completed_tours']]
                <=> [$a['available'], $a['rating'], $a['completed_tours']];
        });

        $this->view('listings/guides', [
            'title' => 'Tour Guides in Cebu',
            'heading' => 'Tour Guides in Cebu',
            'sub' => 'Message a trusted local guide — the same directory as the GuideMate app.',
            'guides' => $guides,
        ]);
    }

    public function hotels(): void
    {
        $this->renderBrowse('hotels', 'Hotels & Stays in Cebu', 'Resorts, hotels and places to stay.');
    }

    public function restaurants(): void
    {
        redirect('/things-to-do');
    }

    private function renderBrowse(?string $categorySlug, string $heading, string $sub): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'area' => (string) $this->input('area', ''),
            'category' => $categorySlug ?? (string) $this->input('category', ''),
            'sort' => (string) $this->input('sort', ''),
            'min_rating' => (string) $this->input('min_rating', ''),
        ];

        $listings = Listing::search($filters);
        $favIds = Auth::check() ? Favorite::idsForUser((int) Auth::id()) : [];

        $mapListings = [];
        $userId = Auth::id();
        if ($userId !== null && $categorySlug !== null) {
            $mapListings = Booking::mapListingsForCustomer($userId, $categorySlug);
        }

        $this->view('listings/browse', [
            'title' => $heading,
            'heading' => $heading,
            'sub' => $sub,
            'listings' => $listings,
            'mapListings' => $mapListings,
            'categories' => Category::publicAll(),
            'filters' => $filters,
            'activeCategory' => $categorySlug,
            'favIds' => $favIds,
        ]);
    }

    public function show(string $slug): void
    {
        $listing = Listing::findBySlug($slug);
        if ($listing === null || $listing['status'] !== 'approved') {
            // Allow the owner/admin to preview non-approved listings.
            $user = Auth::user();
            $isOwner = $user && $listing && (int) $listing['user_id'] === (int) $user['id'];
            if ($listing === null || (!$isOwner && !Auth::isAdmin())) {
                abort(404, 'This listing is not available.');
            }
        }

        $listingId = (int) $listing['id'];
        $userId = Auth::id();
        $isOwner = $userId !== null && (int) $listing['user_id'] === $userId;
        if (Category::isHiddenSlug((string) ($listing['category_slug'] ?? '')) && !$isOwner && !Auth::isAdmin()) {
            abort(404, 'This listing is not available.');
        }
        $canSeeMapLocation = $isOwner
            || Auth::isAdmin()
            || ($userId !== null && Booking::hasActivePaidBooking($listingId, $userId));

        $reviews = Review::forListing($listingId);
        foreach ($reviews as &$r) {
            $r['images'] = ReviewImage::forReview((int) $r['id']);
        }
        unset($r);

        $mapCoords = Geo::forListing($listing);
        if ($canSeeMapLocation && $mapCoords !== null) {
            $listing['latitude'] = $mapCoords['latitude'];
            $listing['longitude'] = $mapCoords['longitude'];
        }

        $this->view('listings/show', [
            'title' => $listing['title'],
            'listing' => $listing,
            'activeCategory' => (string) ($listing['category_slug'] ?? ''),
            'gallery' => Listing::gallery($listingId),
            'reviews' => $reviews,
            'summary' => Review::summary($listingId),
            'weather' => WeatherService::forListing(
                $mapCoords !== null ? (float) $mapCoords['latitude'] : (isset($listing['latitude']) ? (float) $listing['latitude'] : null),
                $mapCoords !== null ? (float) $mapCoords['longitude'] : (isset($listing['longitude']) ? (float) $listing['longitude'] : null),
                (string) ($listing['area'] ?? '')
            ),
            'guideBadge' => guide_badge((int) $listing['user_id']),
            'displayPrice' => Listing::effectivePrice($listing),
            'canReview' => $userId !== null
                && Booking::hasCompletedBooking($listingId, $userId)
                && !Review::userHasReviewed($listingId, $userId),
            'isFavorited' => $userId !== null && Favorite::exists($userId, $listingId),
            'bookedDates' => Booking::bookedDates($listingId),
            'canSeeMapLocation' => $canSeeMapLocation,
            'isBookable' => Listing::isBookable($listing),
            'errors' => errors(),
        ]);
    }

    public function favorites(): void
    {
        $this->view('listings/favorites', [
            'title' => 'Saved',
            'listings' => Favorite::forUser((int) Auth::id()),
            'favIds' => Favorite::idsForUser((int) Auth::id()),
        ]);
    }

    public function toggleFavorite(): void
    {
        $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';

        if (!Auth::check()) {
            if ($isAjax) {
                $this->json(['authenticated' => false, 'redirect' => url('/login')]);
                return;
            }
            redirect('/login');
        }

        $this->verifyCsrf();
        $listingId = (int) $this->input('listing_id', 0);
        $favorited = Favorite::toggle((int) Auth::id(), $listingId);

        if ($isAjax) {
            $this->json(['favorited' => $favorited, 'authenticated' => true]);
            return;
        }
        redirect('/favorites');
    }
}
