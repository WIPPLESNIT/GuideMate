<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Controllers\GuideController;
use App\Controllers\RentalController;
use App\Controllers\HotelController;
use App\Models\Listing;
use App\Models\HotelRoom;

final class ListingVerificationGatingTest
{
    private array $createdUserIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Listing Verification Gating Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testUnverifiedGuideBlockedFromListingCreation', [$this, 'testUnverifiedGuideBlockedFromListingCreation']);
        $this->runTest('testUnverifiedGuideBlockedFromStoringListing', [$this, 'testUnverifiedGuideBlockedFromStoringListing']);
        $this->runTest('testUnverifiedRentalProviderBlockedFromVehicleCreation', [$this, 'testUnverifiedRentalProviderBlockedFromVehicleCreation']);
        $this->runTest('testUnverifiedRentalProviderBlockedFromStoringVehicle', [$this, 'testUnverifiedRentalProviderBlockedFromStoringVehicle']);
        $this->runTest('testUnverifiedHotelPartnerBlockedFromRoomCreation', [$this, 'testUnverifiedHotelPartnerBlockedFromRoomCreation']);
        $this->runTest('testVerificationStatusEndpoint', [$this, 'testVerificationStatusEndpoint']);
        $this->runTest('testVerifiedGuideAllowedToCreateListing', [$this, 'testVerifiedGuideAllowedToCreateListing']);
        $this->runTest('testVerifiedRentalProviderAllowedToCreateVehicle', [$this, 'testVerifiedRentalProviderAllowedToCreateVehicle']);
        $this->runTest('testVerifiedHotelPartnerAllowedToCreateRoom', [$this, 'testVerifiedHotelPartnerAllowedToCreateRoom']);
        $this->runTest('testFrontendTemplatesContainGatingAttributesAndModal', [$this, 'testFrontendTemplatesContainGatingAttributesAndModal']);
        $this->runTest('testGuideListingCreationFlowAndOverviewIntro', [$this, 'testGuideListingCreationFlowAndOverviewIntro']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testUnverifiedGuideBlockedFromListingCreation(): void
    {
        $guideId = User::create('Pending Guide', 'guide_pending_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'pending' WHERE id = ?", [$guideId]);

        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        // 1. JSON / AJAX request returns 403 Forbidden with redirect URL
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

        $controller = new GuideController();
        ob_start();
        $controller->create();
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);

        $this->assert($statusCode === 403, "Unverified guide create request must return HTTP 403, got {$statusCode}");
        $this->assert(is_array($data) && isset($data['error']), "Response must contain error message");
        $this->assert(isset($data['redirect']) && (str_contains($data['redirect'], '/documents') || str_contains($data['redirect'], '/dashboard/verification')), "Redirect URL must point to /documents");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testUnverifiedGuideBlockedFromStoringListing(): void
    {
        $guideId = User::create('Unverified Store Guide', 'guide_store_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'pending' WHERE id = ?", [$guideId]);

        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new GuideController();
        ob_start();
        $controller->store();
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);

        $this->assert($statusCode === 403, "Unverified guide store request must return HTTP 403, got {$statusCode}");
        $this->assert(is_array($data) && ($data['is_verified'] ?? true) === false, "is_verified must be false");

        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function testUnverifiedRentalProviderBlockedFromVehicleCreation(): void
    {
        $providerId = User::create('Pending Provider', 'p_pending_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        $this->createdUserIds[] = $providerId;
        Database::run("UPDATE users SET guide_status = 'pending' WHERE id = ?", [$providerId]);

        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new RentalController();
        ob_start();
        $controller->createVehicle();
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);

        $this->assert($statusCode === 403, "Unverified rental provider create request must return HTTP 403, got {$statusCode}");
        $this->assert(is_array($data) && isset($data['redirect']), "Response must return redirect parameter");

        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function testUnverifiedRentalProviderBlockedFromStoringVehicle(): void
    {
        $providerId = User::create('Pending Store Provider', 'p_store_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        $this->createdUserIds[] = $providerId;
        Database::run("UPDATE users SET guide_status = 'none' WHERE id = ?", [$providerId]);

        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $controller = new RentalController();
        ob_start();
        $controller->storeVehicle();
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);

        $this->assert($statusCode === 403, "Unverified rental provider store request must return HTTP 403, got {$statusCode}");

        unset($_SERVER['HTTP_ACCEPT']);
    }

    public function testVerificationStatusEndpoint(): void
    {
        $guideId = User::create('Status Test Guide', 'guide_stat_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;

        // 1. Pending status
        Database::run("UPDATE users SET guide_status = 'pending' WHERE id = ?", [$guideId]);
        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        $controller = new GuideController();
        ob_start();
        $controller->verificationStatus();
        $raw = ob_get_clean();
        $data = json_decode($raw ?: '', true);

        $this->assert(is_array($data) && $data['authenticated'] === true, "Status must confirm authentication");
        $this->assert($data['is_verified'] === false, "is_verified must be false for pending guide");
        $this->assert($data['guide_status'] === 'pending', "guide_status must be pending");
        $this->assert(str_contains($data['verification_url'], '/documents') || str_contains($data['verification_url'], '/dashboard/verification'), "verification_url must point to documents or verification page");

        // 2. Rejected status with review note
        Database::run("UPDATE users SET guide_status = 'rejected', guide_review_note = 'ID document expired' WHERE id = ?", [$guideId]);
        Auth::refreshUser();

        ob_start();
        $controller->verificationStatus();
        $raw = ob_get_clean();
        $data = json_decode($raw ?: '', true);

        $this->assert($data['is_verified'] === false, "is_verified must be false for rejected guide");
        $this->assert($data['guide_status'] === 'rejected', "guide_status must be rejected");
        $this->assert($data['admin_note'] === 'ID document expired', "admin_note must match");

        // 3. Approved status
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$guideId]);
        Auth::refreshUser();

        ob_start();
        $controller->verificationStatus();
        $raw = ob_get_clean();
        $data = json_decode($raw ?: '', true);

        $this->assert($data['is_verified'] === true, "is_verified must be true for approved guide");
        $this->assert($data['guide_status'] === 'approved', "guide_status must be approved");
    }

    public function testVerifiedGuideAllowedToCreateListing(): void
    {
        $guideId = User::create('Approved Guide', 'guide_appr_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$guideId]);

        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        // Calling create() directly should render the form view and not redirect or 403
        $controller = new GuideController();
        ob_start();
        $controller->create();
        $html = ob_get_clean();

        $this->assert(str_contains($html, 'Create Listing') || str_contains($html, 'form'), "Verified guide should access listing form HTML");
    }

    public function testVerifiedRentalProviderAllowedToCreateVehicle(): void
    {
        $providerId = User::create('Approved Provider', 'p_appr_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'rental_provider');
        $this->createdUserIds[] = $providerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$providerId]);

        $_SESSION['user_id'] = $providerId;
        Auth::refreshUser();

        $controller = new RentalController();
        ob_start();
        $controller->createVehicle();
        $html = ob_get_clean();

        $this->assert(str_contains($html, 'List a Vehicle'), "Verified rental provider should access vehicle form HTML");
    }

    public function testUnverifiedHotelPartnerBlockedFromRoomCreation(): void
    {
        $hotelId = User::create('Pending Hotel Partner', 'hotel_pending_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelId;
        Database::run("UPDATE users SET guide_status = 'pending' WHERE id = ?", [$hotelId]);

        $_SESSION['user_id'] = $hotelId;
        Auth::refreshUser();

        // 1. JSON / AJAX request returns 403 Forbidden with redirect URL
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();

        $controller = new HotelController();
        ob_start();
        $controller->createRoom();
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);

        $this->assert($statusCode === 403, "Unverified hotel partner createRoom request must return HTTP 403, got {$statusCode}");
        $this->assert(is_array($data) && isset($data['error']), "Response must contain error message");
        $this->assert(isset($data['redirect']) && str_contains($data['redirect'], '/documents'), "Redirect URL must point to /documents");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST['_token']);
    }

    public function testVerifiedHotelPartnerAllowedToCreateRoom(): void
    {
        $hotelId = User::create('Verified Hotel Partner', 'hotel_approved_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelId]);

        // Create a hotel listing owned by this partner
        $slug = 'test-hotel-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 1, 'Test Gated Hotel', ?, 'Summary', 'Hotel description', 'Cebu City', 2500, 'night', 'https://example.com/hotel.jpg', 'approved')",
            [$hotelId, $slug]
        );

        $_SESSION['user_id'] = $hotelId;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['listing_id'] = (string) $listingId;
        $_POST['name'] = 'Deluxe Suite Test';
        $_POST['price_per_night'] = '3500';
        $_POST['deposit_percentage'] = '20';
        $_POST['bed_type'] = '1 King Bed';
        $_POST['max_guests'] = '2';

        $controller = new HotelController();
        ob_start();
        $controller->createRoom();
        $output = ob_get_clean();

        // Verify room was created in database
        $room = Database::first('SELECT * FROM hotel_rooms WHERE listing_id = ? AND name = ?', [$listingId, 'Deluxe Suite Test']);
        $this->assert($room !== null, "Room should be created in database for verified hotel partner");

        // Clean up listing and rooms
        Database::run('DELETE FROM hotel_rooms WHERE listing_id = ?', [$listingId]);
        Database::run('DELETE FROM listings WHERE id = ?', [$listingId]);

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST['_token'], $_POST['listing_id'], $_POST['name'], $_POST['price_per_night'], $_POST['deposit_percentage'], $_POST['bed_type'], $_POST['max_guests']);
    }

    public function testFrontendTemplatesContainGatingAttributesAndModal(): void
    {
        // 1. Check modal partial exists and has required elements
        $modalTpl = file_get_contents(BASE_DIR . '/app/Views/partials/verification-gating-modal.php');
        $this->assert(str_contains($modalTpl, 'id="verificationGatingModal"'), "Modal must have id verificationGatingModal");
        $this->assert(str_contains($modalTpl, 'Account Verification Required'), "Modal must have title Account Verification Required");
        $this->assert(str_contains($modalTpl, 'Why is account verification required?'), "Modal must explain why verification is required");
        $this->assert(str_contains($modalTpl, 'Verify Account'), "Modal must have primary Verify Account button");
        $this->assert(str_contains($modalTpl, 'Cancel'), "Modal must have Cancel button");
        $this->assert(str_contains($modalTpl, 'id="verifLoadingState"'), "Modal must include loading state");
        $this->assert(str_contains($modalTpl, 'id="verifErrorState"'), "Modal must include error state");
        $this->assert(str_contains($modalTpl, 'id="verifApprovedState"'), "Modal must include already-verified state");
        $this->assert(str_contains($modalTpl, 'role="dialog"'), "Modal must have role=dialog for screen readers");
        $this->assert(str_contains($modalTpl, 'aria-modal="true"'), "Modal must have aria-modal=true");

        // 2. Check layout includes verification modal
        $layoutTpl = file_get_contents(BASE_DIR . '/app/Views/layouts/main.php');
        $this->assert(str_contains($layoutTpl, 'partials/verification-gating-modal'), "main layout must include verification-gating-modal partial");
        $this->assert(str_contains($layoutTpl, 'name="auth-user-verified"'), "main layout must include auth-user-verified meta tag");

        // 3. Check guide listings view contains gating attributes on New listing / Create listing buttons
        $listingsTpl = file_get_contents(BASE_DIR . '/app/Views/guide/listings.php');
        $this->assert(str_contains($listingsTpl, 'data-open-new-listing'), "listings.php must have data-open-new-listing attribute");
        $this->assert(str_contains($listingsTpl, 'data-action="new-listing"'), "listings.php must have data-action=new-listing attribute");

        // 4. Check dashboard guide view
        $dashGuideTpl = file_get_contents(BASE_DIR . '/app/Views/dashboard/guide.php');
        $this->assert(str_contains($dashGuideTpl, 'data-open-new-listing'), "dashboard/guide.php must have data-open-new-listing attribute");
        $this->assert(str_contains($dashGuideTpl, 'dash-header-actions'), "dashboard/guide.php must have dash-header-actions class");
        $this->assert(str_contains($dashGuideTpl, 'section-head-vertical'), "dashboard/guide.php must use section-head-vertical to place buttons under greetings");
        $this->assert(str_contains($dashGuideTpl, 'gap:0.75rem'), "dashboard/guide.php button container must have gap:0.75rem matching hotel dashboard");
        $this->assert(str_contains($dashGuideTpl, 'New Listing'), "dashboard/guide.php must have 'New Listing' title-case text");
        $this->assert(str_contains($dashGuideTpl, 'btn btn-ghost'), "dashboard/guide.php must have btn-ghost Documents button");
        $this->assert(str_contains($dashGuideTpl, 'dash-main'), "dashboard/guide.php must have dash-main layout wrapper");

        // 5. Check dashboard hotel view
        $dashHotelTpl = file_get_contents(BASE_DIR . '/app/Views/dashboard/hotel.php');
        $this->assert(str_contains($dashHotelTpl, 'data-open-new-listing'), "dashboard/hotel.php must have data-open-new-listing attribute");
        $this->assert(str_contains($dashHotelTpl, 'data-action="add-room"'), "dashboard/hotel.php must have data-action=add-room attribute");
        $this->assert(str_contains($dashHotelTpl, 'data-open-room-modal'), "dashboard/hotel.php must have data-open-room-modal attribute");
        $this->assert(str_contains($dashHotelTpl, 'btnAddRoomType'), "dashboard/hotel.php must have btnAddRoomType ID");
        $this->assert(str_contains($dashHotelTpl, 'openAddRoomModal'), "dashboard/hotel.php must define openAddRoomModal helper");

        // 6. Check rental vehicles view
        $vehiclesTpl = file_get_contents(BASE_DIR . '/app/Views/rental/vehicles.php');
        $this->assert(str_contains($vehiclesTpl, 'data-open-new-listing'), "rental/vehicles.php must have data-open-new-listing attribute");

        // 7. Check main.js contains verification gating script
        $js = file_get_contents(BASE_DIR . '/public/assets/js/main.js');
        $this->assert(str_contains($js, 'initVerificationGating'), "main.js must contain initVerificationGating");
        $this->assert(str_contains($js, 'verificationGatingModal'), "main.js must reference verificationGatingModal");
        $this->assert(str_contains($js, 'data-action="add-room"'), "main.js must handle add-room data-action");
        $this->assert(str_contains($js, 'modal-add-room'), "main.js must handle modal-add-room");
    }

    public function testGuideListingCreationFlowAndOverviewIntro(): void
    {
        // 1. Overview page contains introductory text near page heading
        $dashGuideTpl = file_get_contents(BASE_DIR . '/app/Views/dashboard/guide.php');
        $this->assert(
            str_contains($dashGuideTpl, 'd_guide_portal_intro') || str_contains($dashGuideTpl, 'Tour Guide Portal'),
            "dashboard/guide.php must include introductory portal text near page heading"
        );

        // 2. Overview page New Listing button links to /dashboard/listings?create=1
        $this->assert(
            str_contains($dashGuideTpl, 'url(\'/dashboard/listings?create=1\')') || str_contains($dashGuideTpl, '/dashboard/listings?create=1'),
            "dashboard/guide.php 'New Listing' button must link to /dashboard/listings?create=1"
        );

        // 3. Navigation active_when highlights My Listings for /dashboard/listings?create=1
        $prevUri = $_SERVER['REQUEST_URI'] ?? '/';
        $_SERVER['REQUEST_URI'] = '/dashboard/listings?create=1';
        $listingsActive = active_when('/dashboard/listings');
        $overviewActive = active_when('/dashboard');
        $_SERVER['REQUEST_URI'] = $prevUri;

        $this->assert($listingsActive === 'active', "active_when('/dashboard/listings') must return 'active' when URI is /dashboard/listings?create=1");
        $this->assert($overviewActive === '', "active_when('/dashboard') must return empty string when URI is /dashboard/listings?create=1");

        // 4. listings.php contains the reusable modal and auto-opens with ?create=1
        $listingsTpl = file_get_contents(BASE_DIR . '/app/Views/guide/listings.php');
        $this->assert(str_contains($listingsTpl, 'id="newListingModal"'), "listings.php must contain newListingModal");
        $this->assert(str_contains($listingsTpl, 'id="newListingForm"'), "listings.php must contain newListingForm");
        $this->assert(str_contains($listingsTpl, 'id="nl_title"'), "newListingModal must contain title field");
        $this->assert(str_contains($listingsTpl, 'id="nl_category"'), "newListingModal must contain category select");
        $this->assert(str_contains($listingsTpl, 'id="nl_area"'), "newListingModal must contain area field");
        $this->assert(str_contains($listingsTpl, 'id="nl_description"'), "newListingModal must contain description textarea");
        $this->assert(str_contains($listingsTpl, 'id="nl_price"'), "newListingModal must contain price input");
        $this->assert(str_contains($listingsTpl, 'id="nl_price_unit"'), "newListingModal must contain price unit select");
        $this->assert(str_contains($listingsTpl, "params.get('create') === '1'"), "listings.php must auto-open modal when ?create=1 is present in URL");
        $this->assert(str_contains($listingsTpl, 'window.history.replaceState'), "listings.php must clean ?create=1 from URL using replaceState");
        $this->assert(str_contains($listingsTpl, 'window.openCreateListingModal'), "listings.php must expose openCreateListingModal function");

        // 5. Check main.js handles /dashboard/listings?create=1
        $js = file_get_contents(BASE_DIR . '/public/assets/js/main.js');
        $this->assert(str_contains($js, '/dashboard/listings?create=1'), "main.js must reference /dashboard/listings?create=1");
    }

    private function runTest(string $name, callable $callable): void
    {
        try {
            $callable();
            $this->passed++;
            echo sprintf(" [\033[32mPASS\033[0m] %s\n", $name);
        } catch (\Throwable $e) {
            $this->failed++;
            echo sprintf(" [\033[31mFAIL\033[0m] %s: %s (in %s:%d)\n", $name, $e->getMessage(), $e->getFile(), $e->getLine());
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
        if ($this->createdUserIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            Database::run("DELETE FROM users WHERE id IN ({$placeholders})", $this->createdUserIds);
        }
    }
}

$test = new ListingVerificationGatingTest();
if (!$test->run()) {
    exit(1);
}
