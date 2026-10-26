<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Booking;
use App\Models\Favorite;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\Message;
use App\Models\RentalRequest;
use App\Models\RentalReview;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use App\Models\Vehicle;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $user = Auth::user();
        $role = $user['role'] ?? 'tourist';

        // Admins have their own separate portal (/admin) and never reach here
        // through the public user session.
        match ($role) {
            'guide' => $this->guide($user),
            'hotel_admin' => $this->hotelAdmin($user),
            'rental_provider', 'rental_admin' => $this->rentalProvider($user),
            default => $this->tourist($user),
        };
    }

    /**
     * All tourist reviews on this provider's listings (guides, hotel and
     * rental partners).
     */
    public function reviews(): void
    {
        $user = Auth::user();
        if ($user === null || !User::isProviderRole((string) ($user['role'] ?? ''))) {
            abort(403, 'Service providers only.');
        }

        if (($user['role'] ?? '') === 'rental_provider') {
            $reviews = RentalReview::forProvider((int) $user['id']);
            $stats = RentalReview::providerStats((int) $user['id']);
            $summary = [
                'avg' => (float) ($stats['rating'] ?? 0),
                'count' => (int) ($stats['reviews_count'] ?? 0),
            ];
        } else {
            $reviews = Review::forOwner((int) $user['id']);
            foreach ($reviews as &$review) {
                $review['images'] = ReviewImage::forReview((int) $review['id']);
            }
            unset($review);

            $stats = Booking::guideRatingStats((int) $user['id']);
            $summary = [
                'avg' => (float) ($stats['avg_rating'] ?? 0),
                'count' => (int) ($stats['review_count'] ?? 0),
            ];
        }

        $this->view('dashboard/reviews', [
            'title' => 'Reviews',
            'user' => $user,
            'reviews' => $reviews,
            'summary' => $summary,
        ]);
    }

    /**
     * Hotel Partner dashboard — listings, room inventory, reservations, and guest inquiries.
     *
     * @param array<string, mixed> $user
     */
    private function hotelAdmin(array $user): void
    {
        Auth::refreshUser();
        $user = Auth::user() ?? $user;
        $uid = (int) $user['id'];
        $stats = Booking::guideRatingStats($uid);
        $this->view('dashboard/hotel', [
            'title' => 'Hotel Dashboard',
            'user' => $user,
            'isFirstLogin' => User::isFirstLogin($user),
            'rooms' => HotelRoom::forOwner($uid),
            'reservations' => Booking::forGuide($uid),
            'listings' => Listing::forHotelOwner($uid),
            'inquiries' => array_slice(Message::conversations($uid), 0, 6),
            'unread' => Message::unreadCount($uid),
            'reviewStats' => [
                'avg_rating' => $stats['avg_rating'] ?? 0,
                'review_count' => $stats['review_count'] ?? 0,
            ],
        ]);
    }

    /**
     * Rental Partner dashboard — manages incoming vehicle rental requests
     * and provider listings.
     *
     * @param array<string, mixed> $user
     */
    private function rentalProvider(array $user): void
    {
        $providerId = (int) $user['id'];
        $requests = RentalRequest::forProvider($providerId);
        $countBy = static function (array $rows, string $status): int {
            return count(array_filter($rows, static fn ($r) => ($r['status'] ?? '') === $status));
        };

        // Account metrics: directly assigned on creation, isolated per account
        $totalRequests = isset($user['total_requests']) ? (int) $user['total_requests'] : count($requests);
        $revenue = isset($user['revenue']) ? (float) $user['revenue'] : 0.0;

        // If revenue column was not set or fallback needed:
        if ($revenue === 0.0 && $totalRequests === 0 && $requests !== []) {
            $totalRequests = count($requests);
            foreach ($requests as $r) {
                if (in_array($r['status'] ?? '', ['approved', 'contacted', 'completed'], true)) {
                    $revenue += (float) ($r['total_amount'] ?? 0);
                }
            }
        }

        $this->view('dashboard/rental', [
            'title' => 'Rental Dashboard',
            'user' => $user,
            'isFirstLogin' => User::isFirstLogin($user),
            'requests' => array_slice($requests, 0, 6),
            'vehicles' => array_slice(Vehicle::forOwner($providerId), 0, 5),
            'vehicleStats' => Vehicle::countForOwner($providerId),
            'stats' => [
                'total' => $totalRequests,
                'pending' => $countBy($requests, 'pending'),
                'approved' => $countBy($requests, 'approved') + $countBy($requests, 'contacted'),
                'completed' => $countBy($requests, 'completed'),
                'revenue' => $revenue,
            ],
            'guideStats' => Booking::guideRatingStats($providerId),
            'unread' => Message::unreadCount($providerId),
        ]);
    }

    /**
     * Backward-compatibility alias for legacy callers.
     *
     * @param array<string, mixed> $user
     */
    public function rentalAdmin(array $user): void
    {
        $this->rentalProvider($user);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function tourist(array $user): void
    {
        $bookings = Booking::forCustomer((int) $user['id']);
        $this->view('dashboard/tourist', [
            'title' => 'Dashboard',
            'user' => $user,
            'isFirstLogin' => User::isFirstLogin($user),
            'bookings' => array_slice($bookings, 0, 5),
            'bookingCount' => count($bookings),
            'favorites' => Favorite::forUser((int) $user['id']),
            'unread' => Message::unreadCount((int) $user['id']),
        ]);
    }

    /**
     * @param array<string, mixed> $user
     */
    private function guide(array $user): void
    {
        Booking::reconcileOrphanRefunds();
        Auth::refreshUser();
        $user = Auth::user() ?? $user;
        $listings = Listing::forGuideOwner((int) $user['id']);
        $this->view('dashboard/guide', [
            'title' => 'Guide Dashboard',
            'user' => $user,
            'isFirstLogin' => User::isFirstLogin($user),
            'isWarned' => User::isGuideWarned((int) $user['id']),
            'listings' => $listings,
            'recentBookings' => array_slice(Booking::forGuide((int) $user['id']), 0, 6),
            'bookingCount' => Booking::countForGuide((int) $user['id']),
            'revenue' => Booking::revenueForGuide((int) $user['id']),
            'guideStats' => Booking::guideRatingStats((int) $user['id']),
            'guideBadge' => guide_badge((int) $user['id']),
            'earningsByMonth' => Booking::earningsPerMonth((int) $user['id'], 6),
            'unread' => Message::unreadCount((int) $user['id']),
        ]);
    }
}
