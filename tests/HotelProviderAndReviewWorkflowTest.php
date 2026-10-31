<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Controllers\HotelController;
use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Category;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\User;

final class HotelProviderAndReviewWorkflowTest
{
    private int $passCount = 0;
    private int $failCount = 0;

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function run(): void
    {
        echo "\n========================================================\n";
        echo " GuideMate Hotel Provider & Review Workflow Test Suite\n";
        echo "========================================================\n\n";

        $this->testHotelProviderRoleRegistrationAndLegacyCompatibility();
        $this->testHotelSubmissionInitialPendingStatusAndSearchExclusion();
        $this->testRoomCreationStrictlyBlockedOnPendingAndRejectedHotels();
        $this->testAdminHotelReviewListingsAndPendingCount();
        $this->testAdminApproveHotelUnlocksRoomCreation();
        $this->testAdminRejectHotelStoresReasonAndBlocksRooms();
        $this->testHotelProviderResubmitWorkflow();
        $this->testAdminAndProviderTemplatesIntegrity();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passCount} passed, {$this->failCount} failed\n";
        echo "--------------------------------------------------------\n\n";

        if ($this->failCount > 0) {
            exit(1);
        }
    }

    private function testHotelProviderRoleRegistrationAndLegacyCompatibility(): void
    {
        $testName = 'testHotelProviderRoleRegistrationAndLegacyCompatibility';

        // 1. Direct registration as hotel_provider
        $emailA = 'hotel_prov_' . bin2hex(random_bytes(4)) . '@example.com';
        $userIdA = User::create('Grand Resort Provider', $emailA, 'Secret123!', 'hotel_provider');
        $userA = User::find($userIdA);

        $this->assert($userA !== null, "User must be found");
        $this->assert(($userA['role'] ?? '') === 'hotel_provider', "User role should be hotel_provider, got: " . ($userA['role'] ?? ''));
        $this->assert(User::isProviderRole('hotel_provider'), "User::isProviderRole('hotel_provider') must be true");

        // 2. Legacy hotel_admin normalized to hotel_provider
        $emailB = 'hotel_leg_' . bin2hex(random_bytes(4)) . '@example.com';
        $userIdB = User::create('Legacy Hotel Partner', $emailB, 'Secret123!', 'hotel_admin');
        $userB = User::find($userIdB);

        $this->assert($userB !== null, "Legacy user must be found");
        $this->assert(($userB['role'] ?? '') === 'hotel_provider', "Legacy hotel_admin must be normalized to hotel_provider");

        // 3. Auth::hasRole compatibility
        $_SESSION['user_id'] = $userIdA;
        \App\Core\Auth::refreshUser();
        $this->assert(Auth::hasRole('hotel_provider'), "Auth::hasRole('hotel_provider') must be true");
        $this->assert(Auth::hasRole('hotel_admin'), "Auth::hasRole('hotel_admin') must be true for hotel_provider");
        $this->assert(Auth::managesListings(), "Auth::managesListings() must be true for hotel_provider");

        $this->pass($testName);
    }

    private function testHotelSubmissionInitialPendingStatusAndSearchExclusion(): void
    {
        $testName = 'testHotelSubmissionInitialPendingStatusAndSearchExclusion';

        $providerId = User::create('Seaside Oasis Partner', 'seaside_' . bin2hex(random_bytes(4)) . '@example.com', 'Secret123!', 'hotel_provider');
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$providerId]);
        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $cat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'");
        $catId = (int) ($cat['id'] ?? 3);

        $slug = 'seaside-villa-' . bin2hex(random_bytes(4));
        $hotelId = Listing::create([
            'user_id' => $providerId,
            'category_id' => $catId,
            'title' => 'Seaside Villa & Suites',
            'slug' => $slug,
            'summary' => 'Luxurious beachfront hotel.',
            'description' => 'Experience Cebu beachfront luxury with full amenities.',
            'area' => 'Moalboal',
            'address' => 'Panagsama Beach, Moalboal, Cebu',
            'price' => 3500.00,
            'price_unit' => 'night',
            'duration' => null,
            'included' => 'WiFi, Pool access, Breakfast',
            'not_included' => 'Airport transfer, Mini bar',
            'cover_image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800',
            'status' => 'pending',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        $created = Listing::find($hotelId);
        $this->assert($created !== null, "Created hotel listing must exist");
        $this->assert($created['status'] === 'pending', "Newly submitted hotel must have status 'pending', got: " . ($created['status'] ?? ''));
        $this->assert(!empty($created['submitted_at']), "Newly submitted hotel must have submitted_at timestamp");

        // Must NOT appear in public tourist browse
        $searchResults = Listing::search(['category' => 'hotels', 'q' => 'Seaside Villa']);
        $found = false;
        foreach ($searchResults as $res) {
            if ((int) $res['id'] === $hotelId) {
                $found = true;
                break;
            }
        }
        $this->assert(!$found, "Pending hotel must be excluded from public Listing::search results");

        $this->pass($testName);
    }

    private function testRoomCreationStrictlyBlockedOnPendingAndRejectedHotels(): void
    {
        $testName = 'testRoomCreationStrictlyBlockedOnPendingAndRejectedHotels';

        $providerId = User::create('Gated Room Partner', 'gate_room_' . bin2hex(random_bytes(4)) . '@example.com', 'Secret123!', 'hotel_provider');
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$providerId]);
        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $cat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'");
        $catId = (int) ($cat['id'] ?? 3);

        $hotelId = Listing::create([
            'user_id' => $providerId,
            'category_id' => $catId,
            'title' => 'Gated Test Inn',
            'slug' => 'gated-test-inn-' . bin2hex(random_bytes(4)),
            'summary' => 'Summary',
            'description' => 'Description',
            'area' => 'Cebu City',
            'address' => 'Colon St, Cebu City',
            'price' => 1200.00,
            'price_unit' => 'night',
            'duration' => null,
            'included' => null,
            'not_included' => null,
            'cover_image' => null,
            'status' => 'pending',
        ]);

        // Attempt room creation on pending hotel listing
        $controller = new HotelController();
        $token = Auth::csrfToken();
        $_POST = [
            '_token' => $token,
            'listing_id' => (string) $hotelId,
            'name' => 'Deluxe Suite',
            'price_per_night' => '1500',
            'capacity' => '2',
            'bed_type' => 'Queen Bed',
        ];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        ob_start();
        $controller->createRoom();
        $output = ob_get_clean();

        $json = json_decode($output, true);
        $this->assert(isset($json['error']), "Room creation on pending hotel must return error, got: " . $output);
        $this->assert(str_contains((string) $json['error'], 'approved'), "Error must explain approval prerequisite");

        // Verify no room was inserted in DB
        $rooms = HotelRoom::forListing($hotelId);
        $this->assert(count($rooms) === 0, "No rooms should exist for pending hotel");

        // Now test on rejected hotel listing
        Database::run("UPDATE listings SET status = 'rejected', rejection_reason = 'Need business permit' WHERE id = ?", [$hotelId]);

        ob_start();
        $controller->createRoom();
        $output2 = ob_get_clean();

        $json2 = json_decode($output2, true);
        $this->assert(isset($json2['error']), "Room creation on rejected hotel must return error, got: " . $output2);
        $rooms2 = HotelRoom::forListing($hotelId);
        $this->assert(count($rooms2) === 0, "No rooms should exist for rejected hotel");

        $this->pass($testName);
    }

    private function testAdminHotelReviewListingsAndPendingCount(): void
    {
        $testName = 'testAdminHotelReviewListingsAndPendingCount';

        $pendingBefore = Listing::pendingHotelsCount();

        $providerId = User::create('Review Test Partner', 'rev_test_' . bin2hex(random_bytes(4)) . '@example.com', 'Secret123!', 'hotel_provider');
        $cat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'");
        $catId = (int) ($cat['id'] ?? 3);

        $hotelId = Listing::create([
            'user_id' => $providerId,
            'category_id' => $catId,
            'title' => 'Admin Review Candidate Hotel',
            'slug' => 'admin-rev-hotel-' . bin2hex(random_bytes(4)),
            'summary' => 'Summary',
            'description' => 'Description',
            'area' => 'Mactan',
            'address' => 'Punta Engano, Lapu-Lapu City',
            'price' => 4500.00,
            'price_unit' => 'night',
            'duration' => null,
            'included' => 'WiFi, Breakfast, Beach Access',
            'not_included' => null,
            'cover_image' => null,
            'status' => 'pending',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        $pendingAfter = Listing::pendingHotelsCount();
        $this->assert($pendingAfter === $pendingBefore + 1, "Pending hotels count should increment by 1");

        $adminHotels = Listing::allHotelsForAdmin('pending');
        $found = false;
        foreach ($adminHotels as $h) {
            if ((int) $h['id'] === $hotelId) {
                $found = true;
                $this->assert($h['provider_name'] === 'Review Test Partner', "Provider name should match");
                $this->assert($h['area'] === 'Mactan', "Area should match");
                break;
            }
        }
        $this->assert($found, "Listing::allHotelsForAdmin('pending') must include the new hotel listing");

        $this->pass($testName);
    }

    private function testAdminApproveHotelUnlocksRoomCreation(): void
    {
        $testName = 'testAdminApproveHotelUnlocksRoomCreation';

        $providerId = User::create('Approval Partner', 'appr_part_' . bin2hex(random_bytes(4)) . '@example.com', 'Secret123!', 'hotel_provider');
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$providerId]);

        $adminId = User::create('Admin User', 'adm_' . bin2hex(random_bytes(4)) . '@example.com', 'AdminPass1!', 'admin');

        $cat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'");
        $catId = (int) ($cat['id'] ?? 3);

        $hotelId = Listing::create([
            'user_id' => $providerId,
            'category_id' => $catId,
            'title' => 'Approval Success Hotel',
            'slug' => 'appr-success-hotel-' . bin2hex(random_bytes(4)),
            'summary' => 'Summary',
            'description' => 'Description',
            'area' => 'Bantayan',
            'address' => 'Santa Fe, Bantayan Island',
            'price' => 2800.00,
            'price_unit' => 'night',
            'duration' => null,
            'included' => null,
            'not_included' => null,
            'cover_image' => null,
            'status' => 'pending',
        ]);

        // Admin approves hotel
        Listing::approveHotel($hotelId, $adminId);

        $approvedHotel = Listing::find($hotelId);
        $this->assert($approvedHotel['status'] === 'approved', "Status must be 'approved'");
        $this->assert(!empty($approvedHotel['reviewed_at']), "reviewed_at must be populated");
        $this->assert((int) $approvedHotel['reviewed_by'] === $adminId, "reviewed_by must equal admin ID");
        $this->assert($approvedHotel['rejection_reason'] === null, "rejection_reason must be NULL on approval");

        // Now test room creation succeeds as provider
        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $controller = new HotelController();
        $_POST = [
            '_token' => Auth::csrfToken(),
            'listing_id' => (string) $hotelId,
            'name' => 'Ocean Villa Room',
            'price_per_night' => '3200',
            'capacity' => '4',
            'bed_type' => '2 King Beds',
            'room_size' => '45 sqm',
            'description' => 'Spacious villa room overlooking the turquoise sea.',
            'deposit_type' => 'fixed',
            'deposit_amount' => '500',
        ];
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        ob_start();
        $controller->createRoom();
        $output = ob_get_clean();

        $json = json_decode($output, true);
        $this->assert(!empty($json['success']), "Room creation should succeed on approved hotel: " . $output);

        $rooms = HotelRoom::forListing($hotelId);
        $this->assert(count($rooms) === 1, "Exactly 1 room should exist now");
        $this->assert($rooms[0]['name'] === 'Ocean Villa Room', "Room name must match");

        $this->pass($testName);
    }

    private function testAdminRejectHotelStoresReasonAndBlocksRooms(): void
    {
        $testName = 'testAdminRejectHotelStoresReasonAndBlocksRooms';

        $providerId = User::create('Rejectee Partner', 'rej_part_' . bin2hex(random_bytes(4)) . '@example.com', 'Secret123!', 'hotel_provider');
        $adminId = User::create('Strict Admin', 'strict_adm_' . bin2hex(random_bytes(4)) . '@example.com', 'AdminPass1!', 'admin');

        $cat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'");
        $catId = (int) ($cat['id'] ?? 3);

        $hotelId = Listing::create([
            'user_id' => $providerId,
            'category_id' => $catId,
            'title' => 'Needs Improvement Lodge',
            'slug' => 'needs-improvement-' . bin2hex(random_bytes(4)),
            'summary' => 'Summary',
            'description' => 'Description',
            'area' => 'Oslob',
            'address' => 'Tan-awan, Oslob',
            'price' => 1500.00,
            'price_unit' => 'night',
            'duration' => null,
            'included' => null,
            'not_included' => null,
            'cover_image' => null,
            'status' => 'pending',
        ]);

        $reason = "Please upload higher-quality photos of the property and clarify whether breakfast is included in the nightly rate.";
        Listing::rejectHotel($hotelId, $adminId, $reason);

        $rejectedHotel = Listing::find($hotelId);
        $this->assert($rejectedHotel['status'] === 'rejected', "Status must be 'rejected'");
        $this->assert($rejectedHotel['rejection_reason'] === $reason, "rejection_reason must match note provided");
        $this->assert((int) $rejectedHotel['reviewed_by'] === $adminId, "reviewed_by must equal admin ID");

        $this->pass($testName);
    }

    private function testHotelProviderResubmitWorkflow(): void
    {
        $testName = 'testHotelProviderResubmitWorkflow';

        $providerId = User::create('Resubmit Partner', 'resub_part_' . bin2hex(random_bytes(4)) . '@example.com', 'Secret123!', 'hotel_provider');
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$providerId]);
        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $cat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'");
        $catId = (int) ($cat['id'] ?? 3);

        $hotelId = Listing::create([
            'user_id' => $providerId,
            'category_id' => $catId,
            'title' => 'Resubmit Property Resort',
            'slug' => 'resubmit-prop-' . bin2hex(random_bytes(4)),
            'summary' => 'Summary',
            'description' => 'Description',
            'area' => 'Moalboal',
            'address' => 'Panagsama',
            'price' => 2000.00,
            'price_unit' => 'night',
            'duration' => null,
            'included' => null,
            'not_included' => null,
            'cover_image' => null,
            'status' => 'pending',
        ]);
        Database::run("UPDATE listings SET status = 'rejected', rejection_reason = 'Fix description' WHERE id = ?", [$hotelId]);

        // Resubmit hotel
        Listing::resubmitHotel($hotelId);

        $resubmitted = Listing::find($hotelId);
        $this->assert($resubmitted['status'] === 'pending', "Resubmitted hotel status should be pending, got: " . ($resubmitted['status'] ?? ''));

        $this->pass($testName);
    }

    private function testAdminAndProviderTemplatesIntegrity(): void
    {
        $testName = 'testAdminAndProviderTemplatesIntegrity';

        // 1. Admin Hotels table template
        $adminHotelsView = file_get_contents(__DIR__ . '/../app/Views/admin/hotels.php');
        $this->assert(str_contains($adminHotelsView, 'Hotel Listings Review'), "Admin view must contain title 'Hotel Listings Review'");
        $this->assert(str_contains($adminHotelsView, 'Pending Review'), "Admin view must contain 'Pending Review' tab");
        $this->assert(str_contains($adminHotelsView, 'col-vehicle'), "Admin view must contain hotel column");
        $this->assert(str_contains($adminHotelsView, 'col-actions'), "Admin view must contain actions column");
        $this->assert(str_contains($adminHotelsView, 'openRejectModal'), "Admin view must contain openRejectModal trigger");
        $this->assert(str_contains($adminHotelsView, 'adminRejectModal'), "Admin view must contain adminRejectModal dialog");

        // 2. Admin Hotel Inspection show template
        $adminShowView = file_get_contents(__DIR__ . '/../app/Views/admin/hotel-show.php');
        $this->assert(str_contains($adminShowView, 'Back to Hotel Reviews'), "Show view must have back link to hotel reviews");
        $this->assert(str_contains($adminShowView, 'Review Decision'), "Show view must contain review decision panel");

        // 3. Admin Nav
        $adminNav = file_get_contents(__DIR__ . '/../app/Views/partials/admin-nav.php');
        $this->assert(str_contains($adminNav, "url('/admin/hotels')"), "Admin navigation must link to /admin/hotels");
        $this->assert(str_contains($adminNav, 'pendingHotels'), "Admin navigation must show pending hotels count");

        // 4. Hotel partner hotels view
        $hotelsView = file_get_contents(__DIR__ . '/../app/Views/hotel/hotels.php');
        $this->assert(str_contains($hotelsView, 'admin-note-card'), "Hotels view must display admin note card on rejected hotels");
        $this->assert(str_contains($hotelsView, 'hotelStatusPillClass'), "Hotels view must use hotelStatusPillClass");
        $this->assert(str_contains($hotelsView, 'resubmit'), "Hotels view must provide resubmit option");

        // 5. Hotel partner rooms view
        $roomsView = file_get_contents(__DIR__ . '/../app/Views/hotel/rooms.php');
        $this->assert(str_contains($roomsView, 'Hotel Listing Pending Administrator Review'), "Rooms view must show pending banner when not approved");
        $this->assert(str_contains($roomsView, 'Approval Required'), "Rooms view must show approval required lock state");

        // 6. Router and Auth
        $routes = file_get_contents(__DIR__ . '/../routes/web.php');
        $this->assert(str_contains($routes, "['hotel_provider']"), "Routes must use ['hotel_provider'] guard");
        $this->assert(str_contains($routes, '/admin/hotels'), "Routes must define /admin/hotels");

        $this->pass($testName);
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException("Assertion failed: {$message}");
        }
    }

    private function pass(string $name): void
    {
        $this->passCount++;
        echo " [PASS] {$name}\n";
    }
}

(new HotelProviderAndReviewWorkflowTest())->run();
