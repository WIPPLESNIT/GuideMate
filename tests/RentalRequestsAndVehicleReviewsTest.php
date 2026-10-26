<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\RentalRequest;
use App\Models\RentalReview;
use App\Models\Vehicle;

final class RentalRequestsAndVehicleReviewsTest
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<int, int> */
    private array $createdReviewIds = [];
    /** @var array<int, int> */
    private array $createdRentalIds = [];
    /** @var array<int, int> */
    private array $createdVehicleIds = [];
    /** @var array<int, int> */
    private array $createdUserIds = [];

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Rental Requests & Vehicle Reviews Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testRentalRequestsMarkupAndNoScrollbar', [$this, 'testRentalRequestsMarkupAndNoScrollbar']);
        $this->runTest('testRentalRequestsThreeDotMenuRenderedWithActions', [$this, 'testRentalRequestsThreeDotMenuRenderedWithActions']);
        $this->runTest('testRentalRequestsCssRulesForOverflowAndResponsive', [$this, 'testRentalRequestsCssRulesForOverflowAndResponsive']);
        $this->runTest('testPublicVehicleShowViewIncludesTouristReviewSection', [$this, 'testPublicVehicleShowViewIncludesTouristReviewSection']);
        $this->runTest('testRentalReviewModelForVehicleAndVehicleStats', [$this, 'testRentalReviewModelForVehicleAndVehicleStats']);
        $this->runTest('testDashboardReviewsSupportsRentalProviders', [$this, 'testDashboardReviewsSupportsRentalProviders']);

        $this->cleanup();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function runTest(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo " [PASS] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            echo " [FAIL] {$name}: {$e->getMessage()}\n";
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public function testRentalRequestsMarkupAndNoScrollbar(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/rental/requests.php');
        $this->assert($view !== false, 'Unable to read rental/requests.php');

        // 1. Must not have inline overflow-x:auto
        $this->assert(!str_contains($view, 'overflow-x:auto'), 'rental/requests.php panel-body must not have inline overflow-x:auto');

        // 2. Wrap and table classes
        $this->assert(str_contains($view, 'rental-requests-wrap'), 'rental/requests.php must use rental-requests-wrap class');
        $this->assert(str_contains($view, 'rental-requests-table'), 'rental/requests.php must use rental-requests-table class');

        // 3. Proportional columns present (# column removed for clean balanced layout)
        $this->assert(!str_contains($view, '<th class="col-id">#</th>'), '# (col-id) column must be removed');
        $this->assert(str_contains($view, '<th class="col-vehicle">Vehicle</th>'), 'Must have col-vehicle header');
        $this->assert(str_contains($view, '<th class="col-customer">Customer</th>'), 'Must have col-customer header');
        $this->assert(str_contains($view, '<th class="col-pickup">Pickup</th>'), 'Must have col-pickup header');
        $this->assert(str_contains($view, '<th class="col-payment">Payment</th>'), 'Must have col-payment header');
        $this->assert(str_contains($view, '<th class="col-id-doc">Verification ID</th>'), 'Must have col-id-doc header');
        $this->assert(str_contains($view, '<th class="col-status">Status</th>'), 'Must have col-status header');
        $this->assert(str_contains($view, '<th class="col-actions">Actions</th>'), 'Must have col-actions header');
    }

    public function testRentalRequestsThreeDotMenuRenderedWithActions(): void
    {
        // Mock rental requests data
        $requests = [
            [
                'id' => 771,
                'vehicle_name' => '2023 Honda Beat 110cc',
                'vehicle_type' => 'Scooter',
                'shop_name' => 'Cebu Bike Rental',
                'customer_name' => 'Maria Santos',
                'customer_email' => 'maria@example.com',
                'customer_phone' => '09171234567',
                'pickup_date' => '2026-11-01',
                'rental_days' => 2,
                'total_amount' => 1200.00,
                'payment_status' => 'paid',
                'payment_method' => 'gcash',
                'status' => 'pending',
                'report_status' => 'none',
                'id_document' => 'uploads/docs/sample_id.jpg',
                'id_type' => 'Driver License',
                'id_number' => 'N01-12-345678',
                'notes' => 'Please prepare 2 helmets.',
            ],
            [
                'id' => 772,
                'vehicle_name' => '2022 Toyota Innova 2.8 E',
                'vehicle_type' => 'Van / MPV',
                'shop_name' => 'Cebu Van Rental',
                'customer_name' => 'John Doe',
                'customer_email' => 'john@example.com',
                'customer_phone' => '09187654321',
                'pickup_date' => '2026-11-05',
                'rental_days' => 3,
                'total_amount' => 7500.00,
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'status' => 'approved',
                'report_status' => 'open',
                'report_type' => 'vehicle_issue',
                'report_message' => 'Vehicle air conditioning was not functioning properly.',
                'id_document' => null,
                'id_type' => null,
                'id_number' => null,
                'notes' => null,
            ],
        ];

        ob_start();
        include BASE_DIR . '/app/Views/rental/requests.php';
        $rendered = (string) ob_get_clean();

        // Check Three-Dot Trigger button
        $this->assert(str_contains($rendered, 'class="btn-res-action-trigger"'), 'Must render btn-res-action-trigger');
        $this->assert(str_contains($rendered, 'id="rentalReqBtn_771"'), 'Trigger button must have unique id for request 771');
        $this->assert(str_contains($rendered, 'aria-controls="rentalReqMenu_771"'), 'Trigger button must control rentalReqMenu_771');
        $this->assert(str_contains($rendered, 'id="rentalReqMenu_771"'), 'Dropdown menu must exist for request 771');

        // Check Pending Request actions inside dropdown
        $this->assert(str_contains($rendered, 'Confirm Booking'), 'Pending request 771 must have Confirm Booking in dropdown');
        $this->assert(str_contains($rendered, 'Decline Booking'), 'Pending request 771 must have Decline Booking in dropdown');

        // Check Open Problem Report actions inside dropdown
        $this->assert(str_contains($rendered, 'id="rentalReqMenu_772"'), 'Dropdown menu must exist for request 772');
        $this->assert(str_contains($rendered, 'Refund Customer'), 'Reported request 772 must offer Refund Customer in dropdown');
        $this->assert(str_contains($rendered, 'Dismiss Report'), 'Reported request 772 must offer Dismiss Report in dropdown');

        // Check Customer note and Problem warning are visible
        $this->assert(str_contains($rendered, 'Please prepare 2 helmets'), 'Customer note must be visible in request 771');
        $this->assert(str_contains($rendered, 'Problem Reported'), 'Problem warning must be displayed for request 772');
    }

    public function testRentalRequestsCssRulesForOverflowAndResponsive(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');
        $this->assert($css !== false, 'Unable to read style.css');

        $this->assert(str_contains($css, '.rental-requests-wrap'), 'style.css must define .rental-requests-wrap');
        $this->assert(str_contains($css, '.rental-requests-table'), 'style.css must define .rental-requests-table');
        $this->assert(str_contains($css, '.rental-requests-table th.col-actions'), 'style.css must define col-actions');
        $this->assert(str_contains($css, '@media (max-width: 820px)'), 'style.css must define responsive media queries for requests table');
    }

    public function testPublicVehicleShowViewIncludesTouristReviewSection(): void
    {
        $vehicle = [
            'id' => 991,
            'user_id' => 50,
            'name' => '2023 Yamaha NMAX 155 ABS',
            'type' => 'Scooter',
            'location' => 'Cebu City',
            'address' => 'Escario St, Cebu City',
            'year' => 2023,
            'make' => 'Yamaha',
            'model' => 'NMAX 155',
            'price_per_day' => 1500.00,
            'status' => 'approved',
            'is_available' => 1,
            'slug' => 'yamaha-nmax-155-test',
            'description' => 'Great scooter in mint condition for Cebu cruising.',
            'provider_name' => 'Cebu Ride Rentals',
            'image' => null,
            'specs' => 'Helmet included, Phone mount, ABS braking',
        ];
        $gallery = [];
        $specs = ['Helmet included', 'Phone mount', 'ABS braking'];
        $reviews = [
            [
                'id' => 1,
                'reviewer_id' => 12,
                'reviewer_name' => 'Alex Tourister',
                'reviewer_avatar' => null,
                'rating' => 5,
                'feedback' => 'Amazing scooter! Very well maintained and the pickup process in Cebu City was super fast.',
                'created_at' => '2026-10-20 14:30:00',
            ],
            [
                'id' => 2,
                'reviewer_id' => 14,
                'reviewer_name' => 'Elena Cruz',
                'reviewer_avatar' => null,
                'rating' => 4,
                'feedback' => 'Great vehicle for driving around Moalboal and Oslob. Highly recommended.',
                'created_at' => '2026-10-22 09:15:00',
            ],
        ];
        $reviewStats = [
            'rating' => 4.5,
            'reviews_count' => 2,
            'breakdown' => [5 => 1, 4 => 1, 3 => 0, 2 => 0, 1 => 0],
        ];
        $isPreview = false;
        $isOwner = false;

        ob_start();
        include BASE_DIR . '/app/Views/rental/show.php';
        $rendered = (string) ob_get_clean();

        // 1. Review section must be rendered
        $this->assert(str_contains($rendered, 'id="reviews"'), 'Vehicle show page must contain #reviews section');
        $this->assert(str_contains($rendered, 'Tourist Reviews'), 'Reviews section title must be present');

        // 2. Reviewer info and rating must be present
        $this->assert(str_contains($rendered, 'Alex Tourister'), 'Reviewer name Alex Tourister must be displayed');
        $this->assert(str_contains($rendered, 'Elena Cruz'), 'Reviewer name Elena Cruz must be displayed');
        $this->assert(str_contains($rendered, 'Amazing scooter! Very well maintained'), 'Review feedback text must be displayed');
        $this->assert(str_contains($rendered, '4.5'), 'Average rating 4.5 must be displayed');
        $this->assert(str_contains($rendered, '2 reviews') || str_contains($rendered, '2 verified reviews'), 'Review count must be displayed');

        // 3. Date must be present
        $this->assert(str_contains($rendered, 'Oct 20, 2026'), 'Formatted review date must be displayed');
        $this->assert(str_contains($rendered, 'Oct 22, 2026'), 'Formatted review date must be displayed');
    }

    public function testRentalReviewModelForVehicleAndVehicleStats(): void
    {
        // Insert test provider and tourist
        $providerEmail = 'test_provider_' . bin2hex(random_bytes(4)) . '@example.com';
        $providerId = Database::insert(
            "INSERT INTO users (name, email, password, role, guide_status, created_at) VALUES ('Test Rental Co', ?, 'pwd', 'rental_provider', 'approved', NOW())",
            [$providerEmail]
        );
        $this->createdUserIds[] = $providerId;

        $touristEmail = 'test_tourist_' . bin2hex(random_bytes(4)) . '@example.com';
        $touristId = Database::insert(
            "INSERT INTO users (name, email, password, role, created_at) VALUES ('Sarah Tourist', ?, 'pwd', 'tourist', NOW())",
            [$touristEmail]
        );
        $this->createdUserIds[] = $touristId;

        // Insert vehicle
        $vSlug = 'test-vehicle-' . bin2hex(random_bytes(4));
        $vehicleId = Database::insert(
            "INSERT INTO vehicles (user_id, name, type, location, year, make, model, price_per_day, is_available, status, slug, created_at)
             VALUES (?, 'Test Review Scooter', 'Scooter', 'Cebu City', 2023, 'Honda', 'Click', 800.00, 1, 'approved', ?, NOW())",
            [$providerId, $vSlug]
        );
        $this->createdVehicleIds[] = $vehicleId;

        // Insert completed rental request
        $rentalId = Database::insert(
            "INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, customer_name, customer_email, pickup_date, rental_days, total_amount, payment_status, status, created_at)
             VALUES (?, ?, 'Test Review Scooter', 'Scooter', 'Test Rental Co', 'Sarah Tourist', ?, '2026-11-01', 3, 2400.00, 'paid', 'completed', NOW())",
            [$touristId, (string) $vehicleId, $touristEmail]
        );
        $this->createdRentalIds[] = $rentalId;

        // Create rental review
        $review = RentalReview::createOrUpdate($rentalId, $touristId, $providerId, (string) $vehicleId, 5, 'Smooth ride and excellent service from start to finish!');
        $this->createdReviewIds[] = (int) $review['id'];

        // Test RentalReview::forVehicle
        $vehicleReviews = RentalReview::forVehicle($vehicleId);
        $this->assert(count($vehicleReviews) >= 1, 'RentalReview::forVehicle must return at least 1 review');
        $this->assert((string) $vehicleReviews[0]['reviewer_name'] === 'Sarah Tourist', 'Reviewer name must match Sarah Tourist');
        $this->assert((int) $vehicleReviews[0]['rating'] === 5, 'Rating must be 5');
        $this->assert(str_contains((string) $vehicleReviews[0]['feedback'], 'Smooth ride'), 'Feedback must be present');

        // Test RentalReview::vehicleStats
        $stats = RentalReview::vehicleStats((string) $vehicleId);
        $this->assert((float) $stats['rating'] === 5.0, 'Average rating must be 5.0');
        $this->assert((int) $stats['reviews_count'] === 1, 'Review count must be 1');
        $this->assert(isset($stats['breakdown'][5]) && $stats['breakdown'][5] === 1, 'Breakdown for 5 stars must be 1');
    }

    public function testDashboardReviewsSupportsRentalProviders(): void
    {
        $dashTpl = file_get_contents(BASE_DIR . '/app/Views/dashboard/reviews.php');
        $this->assert(str_contains($dashTpl, 'vehicle_name') || str_contains($dashTpl, 'reviewer_name'), 'dashboard/reviews.php must support vehicle reviews and reviewer info');
        $this->assert(str_contains($dashTpl, 'vehicle_slug') || str_contains($dashTpl, '/rentals/'), 'dashboard/reviews.php must link to public vehicle listing');
    }

    private function cleanup(): void
    {
        if ($this->createdReviewIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdReviewIds), '?'));
            Database::run("DELETE FROM rental_reviews WHERE id IN ({$placeholders})", $this->createdReviewIds);
        }
        if ($this->createdRentalIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdRentalIds), '?'));
            Database::run("DELETE FROM rental_requests WHERE id IN ({$placeholders})", $this->createdRentalIds);
        }
        if ($this->createdVehicleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdVehicleIds), '?'));
            Database::run("DELETE FROM vehicles WHERE id IN ({$placeholders})", $this->createdVehicleIds);
        }
        if ($this->createdUserIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            Database::run("DELETE FROM users WHERE id IN ({$placeholders})", $this->createdUserIds);
        }
    }
}

$suite = new RentalRequestsAndVehicleReviewsTest();
exit($suite->run() ? 0 : 1);
