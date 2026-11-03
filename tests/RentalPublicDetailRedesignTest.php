<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\RentalReview;

final class RentalPublicDetailRedesignTest
{
    private int $passed = 0;
    private int $failed = 0;
    private array $createdVehicleIds = [];
    private array $createdUserIds = [];

    public function run(): bool
    {
        echo "\n========================================================\n";
        echo " GuideMate Rental Public Detail Redesign Test Suite\n";
        echo "========================================================\n\n";

        try {
            $this->testShowViewStructureMatchesHotelAndThingsToDo();
            $this->testDetailHeroThreeTileImageGrid();
            $this->testInclusionBoxSpecificationsAndTerms();
            $this->testBookingBoxStickyCardAndHostInfo();
            $this->testMessageHostActionButtonForAuthenticatedAndGuest();
            $this->testFullPageRenderWithMockData();
            $this->testControllerSlugAndIdResolution();
        } finally {
            $this->tearDown();
        }

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function testShowViewStructureMatchesHotelAndThingsToDo(): void
    {
        $viewPath = BASE_DIR . '/app/Views/rental/show.php';
        $this->assert(file_exists($viewPath), 'rental/show.php must exist');
        $content = file_get_contents($viewPath);

        // Core component classes matching listings/show.php and reference image
        $this->assert(str_contains($content, 'class="breadcrumb"'), 'Must use .breadcrumb class');
        $this->assert(str_contains($content, 'class="detail-hero"'), 'Must use .detail-hero component');
        $this->assert(str_contains($content, 'class="main-img"'), 'detail-hero must contain .main-img');
        $this->assert(str_contains($content, 'class="side"'), 'detail-hero must contain .side');
        $this->assert(str_contains($content, 'class="detail-layout"'), 'Must use .detail-layout grid structure');
        $this->assert(str_contains($content, 'class="detail-title'), 'Must use .detail-title typography');
        $this->assert(str_contains($content, 'class="detail-meta"'), 'Must use .detail-meta row');
        $this->assert(str_contains($content, 'class="chips"'), 'Must use .chips wrapper');
        $this->assert(str_contains($content, 'class="chip chip-host"'), 'Must use .chip.chip-host presence pill');
        $this->assert(str_contains($content, 'class="prose"'), 'Must use .prose for rental description');
        $this->assert(str_contains($content, 'class="inclusion-box'), 'Must use .inclusion-box for inclusions and specs');
        $this->assert(str_contains($content, 'class="inclusion-col"'), 'inclusion-box must contain .inclusion-col columns');
        $this->assert(str_contains($content, 'class="booking-box"'), 'Must use .booking-box for sticky sidebar card');
        $this->assert(str_contains($content, 'class="guide-mini"'), 'booking-box must contain .guide-mini host info');
        $this->assert(str_contains($content, 'class="guide-mini-avatar"'), 'guide-mini must have avatar image');
        $this->assert(str_contains($content, 'class="guide-mini-name"'), 'guide-mini must have provider name');
    }

    private function testDetailHeroThreeTileImageGrid(): void
    {
        $viewPath = BASE_DIR . '/app/Views/rental/show.php';
        $content = file_get_contents($viewPath);

        // Verify 3-image layout: 1 large main, 2 smaller stacked side images
        $this->assert(str_contains($content, '$mainPhoto'), 'View must define mainPhoto');
        $this->assert(str_contains($content, '$sidePhoto1'), 'View must define sidePhoto1 for top side slot');
        $this->assert(str_contains($content, '$sidePhoto2'), 'View must define sidePhoto2 for bottom side slot');
        $this->assert(str_contains($content, '<div class="main-img">'), 'detail-hero has main-img container');
        $this->assert(str_contains($content, '<div class="side">'), 'detail-hero has side container');
    }

    private function testInclusionBoxSpecificationsAndTerms(): void
    {
        $viewPath = BASE_DIR . '/app/Views/rental/show.php';
        $content = file_get_contents($viewPath);

        $this->assert(str_contains($content, "What's included"), 'Must include "What\'s included" card');
        $this->assert(str_contains($content, "Available Add-ons &amp; Services") || str_contains($content, "Available Add-ons & Services"), 'Must include secondary spec card');
        $this->assert(str_contains($content, 'class="inclusion-list"'), 'Must use .inclusion-list unordered list');
        $this->assert(str_contains($content, 'class="inclusion-list inclusion-list-muted"'), 'Must use .inclusion-list-muted for specs');
    }

    private function testBookingBoxStickyCardAndHostInfo(): void
    {
        $viewPath = BASE_DIR . '/app/Views/rental/show.php';
        $content = file_get_contents($viewPath);

        $this->assert(str_contains($content, 'id="bookingBox"'), 'Must include #bookingBox ID');
        $this->assert(str_contains($content, 'class="price"'), 'Must include .price container');
        $this->assert(str_contains($content, 'class="price-unit"'), 'Must include .price-unit');
        $this->assert(str_contains($content, 'Your host'), 'Must label provider as Your host');
        $this->assert(str_contains($content, 'Inquire with the host for availability'), 'Must include inquiry prompt text');
    }

    private function testMessageHostActionButtonForAuthenticatedAndGuest(): void
    {
        $viewPath = BASE_DIR . '/app/Views/rental/show.php';
        $content = file_get_contents($viewPath);

        // Action button must have .btn-primary, .btn-block, .btn-lg and say "Message the host"
        $this->assert(str_contains($content, 'Message the host'), 'Must have "Message the host" action text');
        $this->assert(str_contains($content, 'btn btn-primary btn-block btn-lg'), 'Must use full-width large primary button');
        $this->assert(str_contains($content, 'action="<?= e(url(\'/messages\')) ?>"'), 'Authenticated form must submit to /messages');
        $this->assert(str_contains($content, 'login?redirect='), 'Guest link must redirect to login with return path');
    }

    private function testFullPageRenderWithMockData(): void
    {
        $vehicle = [
            'id' => 888,
            'user_id' => 42,
            'name' => 'Yamaha NMAX 155cc ABS Edition',
            'make' => 'Yamaha',
            'model' => 'NMAX 155',
            'year' => 2024,
            'type' => 'Scooter',
            'slug' => 'yamaha-nmax-155cc-abs-edition-moalboal',
            'description' => 'Perfect touring scooter for South Cebu coastal rides. Features large underseat storage, keyless ignition, and dual-channel ABS.',
            'location' => 'Moalboal, Cebu',
            'address' => 'Panagsama Beach Road, Moalboal',
            'price_per_day' => 850.00,
            'image' => 'uploads/rentals/nmax_main.jpg',
            'status' => 'approved',
            'is_available' => 1,
            'provider_name' => 'Marc Repuesto',
            'provider_avatar' => 'uploads/avatars/marc.jpg',
            'specs' => '["Dual Helmets included", "Phone holder with USB-C", "Comprehensive insurance"]',
        ];

        $gallery = [
            ['id' => 1, 'image_path' => 'uploads/rentals/nmax_side.jpg'],
            ['id' => 2, 'image_path' => 'uploads/rentals/nmax_rear.jpg'],
        ];

        $specs = ['Dual Helmets included', 'Phone holder with USB-C', 'Comprehensive insurance'];

        $reviews = [
            [
                'id' => 1,
                'reviewer_id' => 5,
                'reviewer_name' => 'John Doe',
                'reviewer_avatar' => null,
                'rating' => 5,
                'feedback' => 'Smooth ride! Clean scooter and prompt handover in Moalboal.',
                'created_at' => '2026-10-15 10:00:00',
            ]
        ];

        $reviewStats = [
            'rating' => 5.0,
            'reviews_count' => 1,
            'breakdown' => [5 => 1, 4 => 0, 3 => 0, 2 => 0, 1 => 0],
        ];

        $isPreview = false;
        $isOwner = false;

        ob_start();
        include BASE_DIR . '/app/Views/rental/show.php';
        $rendered = ob_get_clean();

        $this->assert(str_contains($rendered, 'Yamaha NMAX 155cc ABS Edition'), 'Rendered title present');
        $this->assert(str_contains($rendered, 'Moalboal, Cebu'), 'Location present');
        $this->assert(str_contains($rendered, 'Marc Repuesto'), 'Provider name present');
        $this->assert(str_contains($rendered, '850.00'), 'Price present');
        $this->assert(str_contains($rendered, 'Message the host'), 'Message the host button present');
        $this->assert(str_contains($rendered, 'Dual Helmets included'), 'Specs item present');
        $this->assert(str_contains($rendered, 'John Doe'), 'Reviewer present');
        $this->assert(str_contains($rendered, 'Smooth ride!'), 'Review text present');
        $this->assert(str_contains($rendered, 'Available for Rent'), 'Availability indicator present');
    }

    private function testControllerSlugAndIdResolution(): void
    {
        // Create test provider user
        $email = 'partner_' . bin2hex(random_bytes(4)) . '@example.com';
        $userId = Database::insert(
            "INSERT INTO users (name, email, password, role, guide_status, created_at)
             VALUES ('Test Rental Provider', ?, 'hash', 'rental_provider', 'approved', NOW())",
            [$email]
        );
        $this->createdUserIds[] = $userId;

        // Create test vehicle
        $slug = 'test-scooter-' . bin2hex(random_bytes(4));
        $vehicleId = Database::insert(
            "INSERT INTO vehicles (user_id, make, model, year, type, name, slug, description, location, price_per_day, status, is_available, created_at)
             VALUES (?, 'Honda', 'Click 125', 2024, 'Scooter', 'Test Honda Click', ?, 'Great ride', 'Cebu City', 500.00, 'approved', 1, NOW())",
            [$userId, $slug]
        );
        $this->createdVehicleIds[] = $vehicleId;

        // Test Vehicle::findBySlug returns provider metadata
        $fetched = Vehicle::findBySlug($slug);
        $this->assert($fetched !== null, 'Vehicle::findBySlug must retrieve vehicle');
        $this->assert($fetched['provider_name'] === 'Test Rental Provider', 'Vehicle must join provider_name');
        $this->assert(array_key_exists('provider_avatar', $fetched), 'Vehicle must select provider_avatar');

        // Test Vehicle::find by numeric ID also selects provider metadata
        $byId = Vehicle::find($vehicleId);
        $this->assert($byId !== null, 'Vehicle::find must retrieve vehicle');
        $this->assert($byId['slug'] === $slug, 'Vehicle by ID must match slug');
    }

    private function tearDown(): void
    {
        foreach ($this->createdVehicleIds as $vid) {
            Database::run('DELETE FROM vehicle_images WHERE vehicle_id = ?', [$vid]);
            Database::run('DELETE FROM vehicles WHERE id = ?', [$vid]);
        }
        foreach ($this->createdUserIds as $uid) {
            Database::run('DELETE FROM users WHERE id = ?', [$uid]);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            $this->failed++;
            throw new \RuntimeException($message);
        }
        $this->passed++;
        echo sprintf(" [\033[32mPASS\033[0m] %s\n", $message);
    }
}

$suite = new RentalPublicDetailRedesignTest();
exit($suite->run() ? 0 : 1);
