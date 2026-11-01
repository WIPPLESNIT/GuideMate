<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\Category;
use App\Models\HotelRoom;
use App\Controllers\HotelController;
use App\Controllers\AdminController;

final class HotelCreationAndListingWorkflowTest
{
    private array $createdUserIds = [];
    private array $createdListingIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Hotel Creation & Listing Workflow Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testHotelCreatedAppearsImmediatelyInMyHotelsWithPendingStatus', [$this, 'testHotelCreatedAppearsImmediatelyInMyHotelsWithPendingStatus']);
        $this->runTest('testPendingHotelRoomButtonIsLockedUntilAdminApproves', [$this, 'testPendingHotelRoomButtonIsLockedUntilAdminApproves']);
        $this->runTest('testCoverPhotoValidationAcceptsUrlsAndUploadPathsAndRejectsOnlyInvalid', [$this, 'testCoverPhotoValidationAcceptsUrlsAndUploadPathsAndRejectsOnlyInvalid']);
        $this->runTest('testHotelAppearsInOverviewDashboardPropertiesTable', [$this, 'testHotelAppearsInOverviewDashboardPropertiesTable']);
        $this->runTest('testAdminReviewApprovalWorkflowUpdatesMyHotelsStatus', [$this, 'testAdminReviewApprovalWorkflowUpdatesMyHotelsStatus']);
        $this->runTest('testAdminReviewRejectionWorkflowAndResubmitFlow', [$this, 'testAdminReviewRejectionWorkflowAndResubmitFlow']);
        $this->runTest('testValidationErrorsRenderInViewAndReopenModal', [$this, 'testValidationErrorsRenderInViewAndReopenModal']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function runTest(string $name, callable $test): void
    {
        try {
            $test();
            echo " [PASS] {$name}\n";
            $this->passed++;
        } catch (\Throwable $e) {
            echo " [FAIL] {$name}: " . $e->getMessage() . " (in " . $e->getFile() . ":" . $e->getLine() . ")\n";
            $this->failed++;
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    private function tearDown(): void
    {
        if ($this->createdListingIds !== []) {
            $in = implode(',', array_fill(0, count($this->createdListingIds), '?'));
            Database::run("DELETE FROM hotel_rooms WHERE listing_id IN ({$in})", $this->createdListingIds);
            Database::run("DELETE FROM listing_images WHERE listing_id IN ({$in})", $this->createdListingIds);
            Database::run("DELETE FROM listings WHERE id IN ({$in})", $this->createdListingIds);
        }
        if ($this->createdUserIds !== []) {
            $in = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            Database::run("DELETE FROM users WHERE id IN ({$in})", $this->createdUserIds);
        }
    }

    public function testHotelCreatedAppearsImmediatelyInMyHotelsWithPendingStatus(): void
    {
        $hotelPartnerId = User::create('Workflow Partner', 'wf_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_provider');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $user = User::find($hotelPartnerId);
        Auth::login($user);

        $_POST['_token'] = csrf_token();
        $_POST['title'] = 'Sunset Paradise Beach Resort';
        $_POST['area'] = 'Moalboal, Cebu';
        $_POST['address'] = 'Panagsama Beach, Moalboal';
        $_POST['duration'] = '10:00PM - 12:00PM';
        $_POST['summary'] = 'Stunning sunset views with beachfront bungalows';
        $_POST['description'] = 'Relax and dive into the crystal clear waters of Moalboal with authentic hospitality.';
        $_POST['included'] = "Free Wi-Fi\nSwimming Pool";
        $_POST['not_included'] = 'Airport Transfer';
        $_POST['cover_image'] = '';

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $controller = new HotelController();
        ob_start();
        $controller->storeHotel();
        $json = ob_get_clean();
        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);

        // 1. Verify listing was created in DB with status pending
        $listing = Database::first('SELECT * FROM listings WHERE user_id = ? AND title = ?', [$hotelPartnerId, 'Sunset Paradise Beach Resort']);
        $this->assert($listing !== null, "New hotel property must exist in the database");
        $this->createdListingIds[] = (int) $listing['id'];

        $this->assert($listing['status'] === 'pending', "Newly added hotel status must be 'pending'");
        $this->assert((int) $listing['category_id'] === 3, "Category ID must be 3 (hotels)");
        $this->assert((float) $listing['price'] === 0.0, "Initial starting rate must be 0.00 until rooms configured");

        // 2. Verify Listing::forHotelOwner returns the newly created hotel
        $hotels = Listing::forHotelOwner($hotelPartnerId);
        $this->assert(count($hotels) === 1, "Listing::forHotelOwner must contain the newly created hotel");
        $this->assert((int) $hotels[0]['id'] === (int) $listing['id'], "Returned hotel ID must match created listing");

        // 3. Render My Hotels view and verify hotel appears in HTML table
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard/hotels';
        ob_start();
        $controller->hotels();
        $html = ob_get_clean();

        $this->assert(str_contains($html, 'Sunset Paradise Beach Resort'), "My Hotels table must display the new hotel name");
        $this->assert(str_contains($html, 'Moalboal, Cebu'), "My Hotels table must display the hotel area");
        $this->assert(str_contains($html, 'Price unavailable'), "My Hotels table must show 'Price unavailable' when no rooms yet");
        $this->assert(str_contains($html, '0 Room Types'), "My Hotels table must show 0 Room Types");
        $this->assert(str_contains($html, 'Pending Approval'), "My Hotels table must show 'Pending Approval' pill");

        unset($_POST);
    }

    public function testPendingHotelRoomButtonIsLockedUntilAdminApproves(): void
    {
        $hotelPartnerId = User::create('Lock Partner', 'lock_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_provider');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $user = User::find($hotelPartnerId);
        Auth::login($user);

        $listingId = Listing::create([
            'user_id' => $hotelPartnerId,
            'category_id' => 3,
            'title' => 'Pending Locked Resort',
            'slug' => 'pending-locked-resort-' . bin2hex(random_bytes(3)),
            'summary' => 'Resort under review',
            'description' => 'Detailed description for test property.',
            'area' => 'Mactan, Cebu',
            'price' => 0.0,
            'price_unit' => 'per night',
            'cover_image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
            'status' => 'pending',
        ]);
        $this->createdListingIds[] = $listingId;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard/hotels';
        $controller = new HotelController();
        ob_start();
        $controller->hotels();
        $html = ob_get_clean();

        // Must display disabled Room button with lock
        $this->assert(str_contains($html, 'btn-hotel-room-disabled'), "Pending hotel must have disabled room button");
        $this->assert(str_contains($html, 'Rooms (Locked until Approved)'), "Dropdown must show Rooms locked message");
    }

    public function testCoverPhotoValidationAcceptsUrlsAndUploadPathsAndRejectsOnlyInvalid(): void
    {
        $hotelPartnerId = User::create('Photo Partner', 'photo_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_provider');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $user = User::find($hotelPartnerId);
        Auth::login($user);

        // Reflection to test private validateHotelData()
        $controller = new HotelController();
        $ref = new \ReflectionMethod($controller, 'validateHotelData');
        $ref->setAccessible(true);

        // Case 1: valid http URL passes
        $_POST = [
            'title' => 'Test URL Hotel',
            'area' => 'Cebu City',
            'description' => 'Test hotel description long enough',
            'cover_image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
        ];
        $res = $ref->invoke($controller, false);
        $this->assert($res['cover_image'] === 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80', "Valid HTTP URL must be accepted");

        // Case 2: relative upload path passes
        $_POST['cover_image'] = '/uploads/listings/18/my_cover.jpg';
        $res = $ref->invoke($controller, false);
        $this->assert($res['cover_image'] === '/uploads/listings/18/my_cover.jpg', "Uploaded asset path must be accepted");

        // Case 3: data:image/preview base64 string is cleaned without throwing validation error
        $_POST['cover_image'] = 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEASABIAAD...';
        $res = $ref->invoke($controller, false);
        $this->assert($res['cover_image'] === null, "Base64 data preview string must be cleanly ignored");

        unset($_POST);
    }

    public function testHotelAppearsInOverviewDashboardPropertiesTable(): void
    {
        $hotelPartnerId = User::create('Overview Partner', 'ov_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_provider');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $user = User::find($hotelPartnerId);
        Auth::login($user);

        $listingId = Listing::create([
            'user_id' => $hotelPartnerId,
            'category_id' => 3,
            'title' => 'Cebu Heritage Boutique Hotel',
            'slug' => 'cebu-heritage-boutique-' . bin2hex(random_bytes(3)),
            'summary' => 'Historic hotel in downtown Cebu',
            'description' => 'Walk to Basilica del Sto. Nino and Magellan Cross with antique furnishings.',
            'area' => 'Cebu City, Cebu',
            'price' => 0.0,
            'price_unit' => 'per night',
            'cover_image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
            'status' => 'pending',
        ]);
        $this->createdListingIds[] = $listingId;

        // Render dashboard hotel overview
        $dashController = new \App\Controllers\DashboardController();
        $ref = new \ReflectionMethod($dashController, 'hotelAdmin');
        $ref->setAccessible(true);

        ob_start();
        $ref->invoke($dashController, $user);
        $html = ob_get_clean();

        $this->assert(str_contains($html, 'Cebu Heritage Boutique Hotel'), "Overview dashboard must display hotel in Section 3 table");
        $this->assert(str_contains($html, 'Price unavailable'), "Overview table must show 'Price unavailable' for 0.00 price");
        $this->assert(str_contains($html, 'pill-pending'), "Overview table must render pending status pill");
        $this->assert(str_contains($html, "href=\"" . url('/dashboard/hotels') . "\""), "Section 3 'View all' must link to /dashboard/hotels");
    }

    public function testAdminReviewApprovalWorkflowUpdatesMyHotelsStatus(): void
    {
        $hotelPartnerId = User::create('Approve Partner', 'app_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_provider');
        $adminId = User::create('Admin Reviewer', 'admin_rev_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'admin');
        $this->createdUserIds[] = $hotelPartnerId;
        $this->createdUserIds[] = $adminId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id IN (?, ?)", [$hotelPartnerId, $adminId]);

        $listingId = Listing::create([
            'user_id' => $hotelPartnerId,
            'category_id' => 3,
            'title' => 'Malapascua Dive Resort',
            'slug' => 'malapascua-dive-resort-' . bin2hex(random_bytes(3)),
            'summary' => 'World-class thresher shark diving resort',
            'description' => 'White sand beaches and daily dive excursions to Monad Shoal.',
            'area' => 'Malapascua, Cebu',
            'price' => 0.0,
            'price_unit' => 'per night',
            'cover_image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
            'status' => 'pending',
        ]);
        $this->createdListingIds[] = $listingId;

        // Admin approves hotel
        Listing::approveHotel($listingId, $adminId);

        $listing = Listing::find($listingId);
        $this->assert($listing['status'] === 'approved', "Listing status must be 'approved' after admin approval");

        // Login back as hotel partner and view My Hotels
        $partnerUser = User::find($hotelPartnerId);
        Auth::login($partnerUser);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard/hotels';

        $hotelCtrl = new HotelController();
        ob_start();
        $hotelCtrl->hotels();
        $html = ob_get_clean();

        $this->assert(str_contains($html, 'pill-approved'), "My Hotels must show green approved pill");
        $this->assert(str_contains($html, 'btn-hotel-room'), "Approved hotel must unlock Room button");
        $this->assert(!str_contains($html, 'btn-hotel-room-disabled'), "Approved hotel must not have locked Room button");

        unset($_POST);
    }

    public function testAdminReviewRejectionWorkflowAndResubmitFlow(): void
    {
        $hotelPartnerId = User::create('Reject Partner', 'rej_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_provider');
        $adminId = User::create('Admin Rejecter', 'admin_rej_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'admin');
        $this->createdUserIds[] = $hotelPartnerId;
        $this->createdUserIds[] = $adminId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id IN (?, ?)", [$hotelPartnerId, $adminId]);

        $listingId = Listing::create([
            'user_id' => $hotelPartnerId,
            'category_id' => 3,
            'title' => 'Badian Mountain Lodge',
            'slug' => 'badian-mountain-lodge-' . bin2hex(random_bytes(3)),
            'summary' => 'Eco lodge in the mountains of Badian',
            'description' => 'Cool mountain breezes and eco-friendly cabins.',
            'area' => 'Badian, Cebu',
            'price' => 0.0,
            'price_unit' => 'per night',
            'cover_image' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80',
            'status' => 'pending',
        ]);
        $this->createdListingIds[] = $listingId;

        // Admin rejects hotel with note
        Listing::rejectHotel($listingId, $adminId, 'Please upload a higher-resolution cover photo and clarify check-in instructions.');

        $listing = Listing::find($listingId);
        $this->assert($listing['status'] === 'rejected', "Listing status must be 'rejected'");
        $this->assert(str_contains($listing['rejection_reason'], 'higher-resolution'), "Rejection note must be stored");

        // Partner sees admin note on My Hotels
        $partnerUser = User::find($hotelPartnerId);
        Auth::login($partnerUser);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard/hotels';

        $hotelCtrl = new HotelController();
        ob_start();
        $hotelCtrl->hotels();
        $html = ob_get_clean();

        $this->assert(str_contains($html, 'admin-note-card'), "My Hotels must render admin note card on rejection");
        $this->assert(str_contains($html, 'Resubmit for Review'), "My Hotels must provide Resubmit for Review action");

        // Partner resubmits
        Listing::resubmitHotel($listingId);

        $resubmitted = Listing::find($listingId);
        $this->assert($resubmitted['status'] === 'pending', "Resubmitted listing status must return to 'pending'");

        unset($_POST);
    }

    public function testValidationErrorsRenderInViewAndReopenModal(): void
    {
        $hotelPartnerId = User::create('Error Partner', 'err_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_provider');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $user = User::find($hotelPartnerId);
        Auth::login($user);

        // Simulate session errors from failed validation
        $_SESSION['_errors'] = ['cover_image' => 'Cover image must be a valid URL.'];
        $_SESSION['_old'] = [
            'title' => 'My Incomplete Hotel',
            'area' => 'Moalboal',
        ];
        request_snapshot(true);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard/hotels';

        $hotelCtrl = new HotelController();
        ob_start();
        $hotelCtrl->hotels();
        $html = ob_get_clean();

        // 1. Error banner rendered above panel
        $this->assert(str_contains($html, 'Unable to save hotel listing'), "Error banner must render when errors exist");
        $this->assert(str_contains($html, 'Cover image must be a valid URL'), "Error text must be displayed in banner");

        // 2. JavaScript auto-reopen modal and pre-fill old input
        $this->assert(str_contains($html, 'My Incomplete Hotel'), "Old title must be preserved");

        unset($_SESSION['_errors'], $_SESSION['_old']);
    }
}
