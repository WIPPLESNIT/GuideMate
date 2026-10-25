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
use App\Controllers\GuideController;
use App\Controllers\DashboardController;

final class HotelListingManagementTest
{
    private array $createdUserIds = [];
    private array $createdListingIds = [];
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Hotel Listing Management Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testNavigationAndDashboardLinkToMyHotels', [$this, 'testNavigationAndDashboardLinkToMyHotels']);
        $this->runTest('testGuideControllerRedirectsHotelPartnerToMyHotels', [$this, 'testGuideControllerRedirectsHotelPartnerToMyHotels']);
        $this->runTest('testHotelPartnerCanCreateHotelListing', [$this, 'testHotelPartnerCanCreateHotelListing']);
        $this->runTest('testHotelListingsStoredAndSeparatedFromGuideListings', [$this, 'testHotelListingsStoredAndSeparatedFromGuideListings']);
        $this->runTest('testHotelPartnerCanEditOwnedHotelListing', [$this, 'testHotelPartnerCanEditOwnedHotelListing']);
        $this->runTest('testHotelPartnerGetEndpointReturnsJsonForModal', [$this, 'testHotelPartnerGetEndpointReturnsJsonForModal']);
        $this->runTest('testUnauthorizedHotelPartnerCannotModifyAnotherHotel', [$this, 'testUnauthorizedHotelPartnerCannotModifyAnotherHotel']);
        $this->runTest('testUnverifiedHotelPartnerBlockedFromCreatingHotel', [$this, 'testUnverifiedHotelPartnerBlockedFromCreatingHotel']);
        $this->runTest('testGuideCannotCreateListingWithHotelsCategory', [$this, 'testGuideCannotCreateListingWithHotelsCategory']);
        $this->runTest('testFrontendTemplatesContainHotelModalAndSeparation', [$this, 'testFrontendTemplatesContainHotelModalAndSeparation']);
        $this->runTest('testPropertyRoomsDedicatedPageAndBreadcrumbs', [$this, 'testPropertyRoomsDedicatedPageAndBreadcrumbs']);
        $this->runTest('testHotelPartnerCanDeleteRoom', [$this, 'testHotelPartnerCanDeleteRoom']);
        $this->runTest('testOverviewSummaryCardsUpdated', [$this, 'testOverviewSummaryCardsUpdated']);
        $this->runTest('testMyHotelsCardsRemovedAndActionsDropdownPresent', [$this, 'testMyHotelsCardsRemovedAndActionsDropdownPresent']);
        $this->runTest('testNavigationActiveWhenKeepsMyHotelsActive', [$this, 'testNavigationActiveWhenKeepsMyHotelsActive']);
        $this->runTest('testRoomStatusUpdateDoesNotRedirectToOverviewPage', [$this, 'testRoomStatusUpdateDoesNotRedirectToOverviewPage']);
        $this->runTest('testAllRoomStatusTransitionsSaveSuccessfullyWithoutLeavingPage', [$this, 'testAllRoomStatusTransitionsSaveSuccessfullyWithoutLeavingPage']);
        $this->runTest('testOverviewRoomInventoryTableStructureAndStatusStyling', [$this, 'testOverviewRoomInventoryTableStructureAndStatusStyling']);
        $this->runTest('testRoomIdentificationAndDepositColumnsUpdatedInBothTables', [$this, 'testRoomIdentificationAndDepositColumnsUpdatedInBothTables']);
        $this->runTest('testRoomStatusUpdateDoesNotPolluteSessionFlash', [$this, 'testRoomStatusUpdateDoesNotPolluteSessionFlash']);
        $this->runTest('testFlashFunctionClearsSessionImmediately', [$this, 'testFlashFunctionClearsSessionImmediately']);
        $this->runTest('testYourHotelPropertiesTableActionButtonsLayout', [$this, 'testYourHotelPropertiesTableActionButtonsLayout']);

        $this->tearDown();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testNavigationAndDashboardLinkToMyHotels(): void
    {
        $dashNav = file_get_contents(BASE_DIR . '/app/Views/partials/dash-nav.php');
        $this->assert(str_contains($dashNav, "e(url('/dashboard/hotels'))"), "dash-nav.php must link My Hotels to /dashboard/hotels");

        $hotelDash = file_get_contents(BASE_DIR . '/app/Views/dashboard/hotel.php');
        $this->assert(str_contains($hotelDash, "url('/dashboard/hotels?create=1')"), "Hotel dashboard '+ New Hotel' button must link to /dashboard/hotels?create=1");
        $this->assert(str_contains($hotelDash, 'data-action="new-hotel"'), "Hotel dashboard '+ New Hotel' must have data-action=new-hotel");
    }

    public function testGuideControllerRedirectsHotelPartnerToMyHotels(): void
    {
        $hotelId = User::create('Redirect Hotel Partner', 'red_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelId]);

        $_SESSION['user_id'] = $hotelId;
        Auth::refreshUser();

        // Check GuideController redirects hotel_admin to /dashboard/hotels
        $controller = new GuideController();
        $reflection = new \ReflectionClass($controller);
        $file = file_get_contents($reflection->getFileName());
        $this->assert(str_contains($file, "redirect('/dashboard/hotels')"), "GuideController::listings must redirect hotel_admin to /dashboard/hotels");
        $this->assert(str_contains($file, "redirect('/dashboard/hotels?create=1')"), "GuideController::create must redirect hotel_admin to /dashboard/hotels?create=1");
    }

    public function testHotelPartnerCanCreateHotelListing(): void
    {
        $hotelId = User::create('Owner Hotel Partner', 'owner_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelId]);

        $_SESSION['user_id'] = $hotelId;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['title'] = 'Crimson Mactan Resort Test';
        $_POST['area'] = 'Lapu-Lapu City, Cebu';
        $_POST['address'] = 'Seascapes Resort Town, Mactan';
        $_POST['summary'] = 'Oceanfront luxury villas and infinity pools';
        $_POST['description'] = 'Sprawling 6-hectare beach resort in Mactan with private pools and award-winning dining.';
        $_POST['price'] = '7800';
        $_POST['included'] = "Free Wi-Fi\nPrivate Beach\nInfinity Pool\nBuffet Breakfast";
        $_POST['not_included'] = "Airport Transfer (₱900)\nSpa Treatments";

        $controller = new HotelController();
        ob_start();
        $controller->storeHotel();
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(is_array($data) && ($data['success'] ?? false) === true, "storeHotel must return JSON success");

        $listing = Database::first('SELECT * FROM listings WHERE user_id = ? AND title = ?', [$hotelId, 'Crimson Mactan Resort Test']);
        $this->assert($listing !== null, "Listing must exist in database");
        $this->createdListingIds[] = (int) $listing['id'];

        $this->assert((int) $listing['category_id'] === 3, "Listing category must be 3 (Hotels & Stays)");
        $this->assert($listing['price_unit'] === 'per night', "Price unit must be 'per night'");
        $this->assert((float) $listing['price'] === 7800.0, "Price must be 7800.00");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
    }

    public function testHotelListingsStoredAndSeparatedFromGuideListings(): void
    {
        $hotelId = User::create('Separate Hotel Partner', 'sep_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $guideId = User::create('Separate Guide Partner', 'sep_guide_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $hotelId;
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id IN (?, ?)", [$hotelId, $guideId]);

        // Insert 1 hotel listing for hotelId
        $hotelSlug = 'hotel-stay-' . bin2hex(random_bytes(4));
        $hotelListingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'Private Island Hotel', ?, 'Summary', 'Desc', 'Mactan', 5000, 'per night', 'https://example.com/h.jpg', 'approved')",
            [$hotelId, $hotelSlug]
        );
        $this->createdListingIds[] = $hotelListingId;

        // Insert 1 tour guide listing for guideId
        $guideSlug = 'tour-exp-' . bin2hex(random_bytes(4));
        $guideListingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 2, 'Kawasan Canyoneering Tour', ?, 'Summary', 'Desc', 'Badian', 1500, 'per person', 'https://example.com/g.jpg', 'approved')",
            [$guideId, $guideSlug]
        );
        $this->createdListingIds[] = $guideListingId;

        // 1. Hotel queries must ONLY return hotels
        $hotelListings = Listing::forHotelOwner($hotelId);
        $this->assert(count($hotelListings) === 1, "Hotel owner should see exactly 1 hotel listing");
        $this->assert((int) $hotelListings[0]['id'] === $hotelListingId, "Listing ID must match created hotel");
        $this->assert($hotelListings[0]['category_slug'] === 'hotels', "Category must be hotels");

        // 2. Guide queries must NEVER return hotel listings
        $guideListings = Listing::forGuideOwner($guideId);
        $this->assert(count($guideListings) === 1, "Guide owner should see exactly 1 guide listing");
        $this->assert((int) $guideListings[0]['id'] === $guideListingId, "Listing ID must match created tour");
        $this->assert($guideListings[0]['category_slug'] !== 'hotels', "Guide listing category must not be hotels");

        // 3. If guide queries hotel owner ID, result must be empty
        $this->assert(count(Listing::forGuideOwner($hotelId)) === 0, "Listing::forGuideOwner on hotelId must return empty array");
        // 4. If hotel queries guide owner ID, result must be empty
        $this->assert(count(Listing::forHotelOwner($guideId)) === 0, "Listing::forHotelOwner on guideId must return empty array");
    }

    public function testHotelPartnerCanEditOwnedHotelListing(): void
    {
        $hotelId = User::create('Edit Hotel Partner', 'edit_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelId]);

        $slug = 'edit-test-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'Original Hotel Name', ?, 'Original Summary', 'Original Desc', 'Cebu City', 3000, 'per night', 'https://example.com/h.jpg', 'approved')",
            [$hotelId, $slug]
        );
        $this->createdListingIds[] = $listingId;

        $_SESSION['user_id'] = $hotelId;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['title'] = 'Updated Hotel Luxury Name';
        $_POST['area'] = 'Mandaue City, Cebu';
        $_POST['address'] = 'A.S. Fortuna St.';
        $_POST['summary'] = 'Updated luxury hotel summary';
        $_POST['description'] = 'Updated full overview description of the hotel.';
        $_POST['price'] = '4500';
        $_POST['included'] = "Free Wi-Fi\nRooftop Pool";
        $_POST['not_included'] = "Valet Parking";

        $controller = new HotelController();
        ob_start();
        $controller->updateHotel((string) $listingId);
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(is_array($data) && ($data['success'] ?? false) === true, "updateHotel must return success JSON");

        $updated = Listing::find($listingId);
        $this->assert($updated['title'] === 'Updated Hotel Luxury Name', "Title must be updated");
        $this->assert($updated['area'] === 'Mandaue City, Cebu', "Area must be updated");
        $this->assert((float) $updated['price'] === 4500.0, "Price must be updated to 4500");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
    }

    public function testHotelPartnerGetEndpointReturnsJsonForModal(): void
    {
        $hotelId = User::create('Json Hotel Partner', 'json_hotel_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelId]);

        $slug = 'json-hotel-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'Modal Data Hotel', ?, 'Summary text', 'Description text', 'Moalboal', 2900, 'per night', 'https://example.com/h.jpg', 'approved')",
            [$hotelId, $slug]
        );
        $this->createdListingIds[] = $listingId;

        $_SESSION['user_id'] = $hotelId;
        Auth::refreshUser();

        $controller = new HotelController();
        ob_start();
        $controller->getHotel((string) $listingId);
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(is_array($data) && ($data['success'] ?? false) === true, "getHotel must return success JSON");
        $this->assert(isset($data['hotel']) && $data['hotel']['title'] === 'Modal Data Hotel', "Hotel title in JSON must match");
        $this->assert((float) $data['hotel']['price'] === 2900.0, "Hotel price in JSON must match");
    }

    public function testUnauthorizedHotelPartnerCannotModifyAnotherHotel(): void
    {
        $hotelA = User::create('Partner A', 'h_a_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $hotelB = User::create('Partner B', 'h_b_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelA;
        $this->createdUserIds[] = $hotelB;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id IN (?, ?)", [$hotelA, $hotelB]);

        // Hotel A owns this listing
        $slug = 'hotel-a-' . bin2hex(random_bytes(4));
        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'Hotel A Property', ?, 'Summary', 'Desc', 'Oslob', 3500, 'per night', 'https://example.com/h.jpg', 'approved')",
            [$hotelA, $slug]
        );
        $this->createdListingIds[] = $listingId;

        // Log in as Hotel B (unauthorized for Hotel A's property)
        $_SESSION['user_id'] = $hotelB;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['title'] = 'Hacked Hotel Name';
        $_POST['area'] = 'Oslob';
        $_POST['description'] = 'Hacked desc';
        $_POST['price'] = '100';

        $controller = new HotelController();
        ob_start();
        $controller->updateHotel((string) $listingId);
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $this->assert($statusCode === 403, "Unauthorized update attempt must return HTTP 403, got {$statusCode}");

        // Verify property was NOT changed
        $check = Listing::find($listingId);
        $this->assert($check['title'] === 'Hotel A Property', "Title must remain unchanged");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
    }

    public function testUnverifiedHotelPartnerBlockedFromCreatingHotel(): void
    {
        $hotelId = User::create('Pending Hotel Partner', 'pending_h_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelId;
        Database::run("UPDATE users SET guide_status = 'pending' WHERE id = ?", [$hotelId]);

        $_SESSION['user_id'] = $hotelId;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['title'] = 'Unverified Hotel';
        $_POST['area'] = 'Cebu';
        $_POST['description'] = 'Desc';
        $_POST['price'] = '2000';

        $controller = new HotelController();
        ob_start();
        $controller->storeHotel();
        $output = ob_get_clean();
        $statusCode = http_response_code();

        $data = json_decode($output ?: '', true);
        $this->assert($statusCode === 403, "Unverified hotel partner storeHotel must return 403, got {$statusCode}");
        $this->assert(is_array($data) && isset($data['error']), "Error response must be present");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
    }

    public function testGuideCannotCreateListingWithHotelsCategory(): void
    {
        $guideId = User::create('Approved Guide', 'g_guide_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'guide');
        $this->createdUserIds[] = $guideId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$guideId]);

        $_SESSION['user_id'] = $guideId;
        Auth::refreshUser();

        // Check Category::forGuides() does not include 'hotels'
        $guideCats = Category::forGuides();
        $slugs = array_column($guideCats, 'slug');
        $this->assert(!in_array('hotels', $slugs, true), "Category::forGuides() must not contain 'hotels'");

        // GuideController validateListing rejection of category_id = 3 (hotels)
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['title'] = 'Fake Hotel by Guide';
        $_POST['category_id'] = '3'; // hotels
        $_POST['area'] = 'Cebu';
        $_POST['description'] = 'Desc';
        $_POST['price'] = '2500';

        $controller = new GuideController();
        // Since flash_keep_old is called on error, errors() will contain category_id error
        ob_start();
        @$controller->store();
        ob_end_clean();

        $hotelCheck = Database::first('SELECT id FROM listings WHERE user_id = ? AND title = ?', [$guideId, 'Fake Hotel by Guide']);
        $this->assert($hotelCheck === null, "Guide must not be able to create a listing in the hotels category");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
    }

    public function testFrontendTemplatesContainHotelModalAndSeparation(): void
    {
        $hotelsView = file_get_contents(BASE_DIR . '/app/Views/hotel/hotels.php');
        $this->assert(str_contains($hotelsView, 'id="hotelModal"'), "hotel/hotels.php must contain shared hotelModal");
        $this->assert(str_contains($hotelsView, 'openCreateHotelModal'), "hotel/hotels.php must have openCreateHotelModal function");
        $this->assert(str_contains($hotelsView, 'openEditHotelModal'), "hotel/hotels.php must have openEditHotelModal function");
        $this->assert(str_contains($hotelsView, 'hotelModalSubmitBtn'), "hotel/hotels.php must have submit button with loading state");
        $this->assert(str_contains($hotelsView, 'hotelFormError'), "hotel/hotels.php must have error alert element");
        $this->assert(str_contains($hotelsView, 'My Hotels'), "hotel/hotels.php must have 'My Hotels' title");

        $mainJs = file_get_contents(BASE_DIR . '/public/assets/js/main.js');
        $this->assert(str_contains($mainJs, 'data-action="new-hotel"'), "main.js must intercept data-action=new-hotel");
        $this->assert(str_contains($mainJs, '/dashboard/hotels?create=1'), "main.js must redirect to /dashboard/hotels?create=1");
    }

    public function testPropertyRoomsDedicatedPageAndBreadcrumbs(): void
    {
        $hotelPartnerId = User::create('Property Room Owner', 'prop_owner_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $slug1 = 'sea-breeze-resort-' . bin2hex(random_bytes(3));
        $hotelId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'Sea Breeze Resort', ?, 'Summary', 'Desc', 'Moalboal', 3200, 'per night', 'https://example.com/sb.jpg', 'approved')",
            [$hotelPartnerId, $slug1]
        );
        $this->createdListingIds[] = $hotelId;

        // Add a room to this hotel
        $roomId = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Ocean Deluxe Suite',
            'bed_type' => '1 King Bed',
            'max_guests' => 2,
            'price_per_night' => 3500.00,
            'deposit_percentage' => 20,
            'status' => 'available',
        ]);

        $_SESSION['user_id'] = $hotelPartnerId;
        Auth::refreshUser();

        $controller = new HotelController();
        ob_start();
        $controller->propertyRooms((string) $hotelId);
        $output = ob_get_clean();

        $this->assert(str_contains($output, 'My Hotels'), "Property rooms page must contain 'My Hotels' in breadcrumbs");
        $this->assert(str_contains($output, 'Sea Breeze Resort'), "Property rooms page must contain property title");
        $this->assert(str_contains($output, 'Rooms'), "Property rooms page must contain 'Rooms' in breadcrumbs");
        $this->assert(str_contains($output, 'Ocean Deluxe Suite'), "Property rooms page must list configured room");
        $this->assert(str_contains($output, 'Specifications'), "Property rooms table must have Specifications column");
        $this->assert(str_contains($output, 'Deposit'), "Property rooms table must have Deposit column");
        $this->assert(str_contains($output, 'btn-res-action-trigger'), "Property rooms table must have vertical action menu trigger");

        // Clean up room
        Database::run("DELETE FROM hotel_rooms WHERE id = ?", [$roomId]);
    }

    public function testHotelPartnerCanDeleteRoom(): void
    {
        $hotelPartnerId = User::create('Room Deleter', 'room_del_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $slug2 = 'palm-tree-hotel-' . bin2hex(random_bytes(3));
        $hotelId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'Palm Tree Hotel', ?, 'Summary', 'Desc', 'Mactan', 2800, 'per night', 'https://example.com/pt.jpg', 'approved')",
            [$hotelPartnerId, $slug2]
        );
        $this->createdListingIds[] = $hotelId;

        $roomId = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Room To Delete',
            'bed_type' => '2 Single Beds',
            'max_guests' => 2,
            'price_per_night' => 2800.00,
            'deposit_percentage' => 20,
            'status' => 'available',
        ]);

        $_SESSION['user_id'] = $hotelPartnerId;
        Auth::refreshUser();

        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();

        $controller = new HotelController();
        ob_start();
        $controller->destroyRoom((string) $roomId);
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(is_array($data) && ($data['success'] ?? false) === true, "destroyRoom must return JSON success");

        $check = Database::first("SELECT id FROM hotel_rooms WHERE id = ?", [$roomId]);
        $this->assert($check === null, "Room must be deleted from hotel_rooms table");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
    }

    public function testOverviewSummaryCardsUpdated(): void
    {
        $hotelView = file_get_contents(BASE_DIR . '/app/Views/dashboard/hotel.php');
        $this->assert(str_contains($hotelView, 'Total Properties'), "Overview stat-grid must contain 'Total Properties'");
        $this->assert(str_contains($hotelView, 'Approved &amp; Live') || str_contains($hotelView, 'Approved & Live'), "Overview stat-grid must contain 'Approved & Live'");
        $this->assert(str_contains($hotelView, 'Total Reviews'), "Overview stat-grid must contain 'Total Reviews'");
    }

    public function testMyHotelsCardsRemovedAndActionsDropdownPresent(): void
    {
        $hotelsView = file_get_contents(BASE_DIR . '/app/Views/hotel/hotels.php');
        $this->assert(!str_contains($hotelsView, 'Total Room Types'), "My Hotels page must NOT contain 'Total Room Types'");
        $this->assert(!str_contains($hotelsView, 'id="btnNewHotel"'), "My Hotels page must NOT contain header '+ New Hotel' button");
        $this->assert(!str_contains($hotelsView, 'Manage</a>'), "My Hotels table must NOT contain 'Manage' link under Rooms Configured");
        $this->assert(str_contains($hotelsView, '/rooms'), "My Hotels table must have link to dedicated rooms page");
        $this->assert(str_contains($hotelsView, 'btn-res-action-trigger'), "My Hotels table must have vertical action menu trigger");
    }

    public function testNavigationActiveWhenKeepsMyHotelsActive(): void
    {
        $_SERVER['REQUEST_URI'] = '/dashboard/hotels/15/rooms';
        $active = active_when('/dashboard/hotels');
        $this->assert($active === 'active', "active_when('/dashboard/hotels') must return 'active' on /dashboard/hotels/15/rooms");
        unset($_SERVER['REQUEST_URI']);
    }

    public function testRoomStatusUpdateDoesNotRedirectToOverviewPage(): void
    {
        $hotelPartnerId = User::create('Status Test Partner', 'status_test_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $slug = 'hotel-status-' . bin2hex(random_bytes(3));
        $hotelId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'Status Test Resort', ?, 'Summary', 'Desc', 'Mactan', 3000, 'per night', 'https://example.com/st.jpg', 'approved')",
            [$hotelPartnerId, $slug]
        );
        $this->createdListingIds[] = $hotelId;

        $roomId = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Garden Villa',
            'bed_type' => '1 Queen Bed',
            'max_guests' => 2,
            'price_per_night' => 3000.00,
            'deposit_percentage' => 20,
            'status' => 'available',
        ]);

        $_SESSION['user_id'] = $hotelPartnerId;
        Auth::refreshUser();

        // 1. Verify JSON update returns 200 JSON with status and no redirect
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['status'] = 'blocked';

        $controller = new HotelController();
        ob_start();
        $controller->updateRoomStatus((string) $roomId);
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(is_array($data) && ($data['success'] ?? false) === true, "JSON update must succeed");
        $this->assert(($data['status'] ?? '') === 'blocked', "JSON update must reflect 'blocked' status");

        $updatedRoom = HotelRoom::find($roomId);
        $this->assert($updatedRoom['status'] === 'blocked', "Room in database must be updated to 'blocked'");

        // 2. Verify template has room-status-form, showToast, and does not navigate away
        $roomsView = file_get_contents(BASE_DIR . '/app/Views/hotel/rooms.php');
        $this->assert(str_contains($roomsView, 'room-status-form'), "rooms.php must contain room-status-form class");
        $this->assert(str_contains($roomsView, 'showToast'), "rooms.php must contain showToast function for feedback");
        $this->assert(str_contains($roomsView, 'roomStatusPill_'), "rooms.php must contain roomStatusPill ID for in-place update");
        $this->assert(str_contains($roomsView, 'e.preventDefault()'), "rooms.php script must prevent default navigation");

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
        Database::run("DELETE FROM hotel_rooms WHERE id = ?", [$roomId]);
    }

    public function testAllRoomStatusTransitionsSaveSuccessfullyWithoutLeavingPage(): void
    {
        $hotelPartnerId = User::create('All Status Partner', 'all_status_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $slug = 'hotel-all-status-' . bin2hex(random_bytes(3));
        $hotelId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'All Status Resort', ?, 'Summary', 'Desc', 'Mactan', 4000, 'per night', 'https://example.com/as.jpg', 'approved')",
            [$hotelPartnerId, $slug]
        );
        $this->createdListingIds[] = $hotelId;

        $roomId = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Presidential Suite',
            'bed_type' => '1 King Bed',
            'max_guests' => 4,
            'price_per_night' => 5000.00,
            'deposit_percentage' => 25,
            'status' => 'available',
        ]);

        $_SESSION['user_id'] = $hotelPartnerId;
        Auth::refreshUser();

        $controller = new HotelController();

        $statuses = ['blocked', 'fully_booked', 'unavailable', 'available'];
        foreach ($statuses as $targetStatus) {
            $_SERVER['HTTP_ACCEPT'] = 'application/json';
            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
            $_POST['_token'] = csrf_token();
            $_POST['status'] = $targetStatus;

            ob_start();
            $controller->updateRoomStatus((string) $roomId);
            $output = ob_get_clean();

            $data = json_decode($output ?: '', true);
            $this->assert(is_array($data) && ($data['success'] ?? false) === true, "Status transition to '{$targetStatus}' must succeed");
            $this->assert(($data['status'] ?? '') === $targetStatus, "Response must report status '{$targetStatus}'");

            $room = HotelRoom::find($roomId);
            $this->assert($room['status'] === $targetStatus, "Database must store status '{$targetStatus}'");
        }

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
        Database::run("DELETE FROM hotel_rooms WHERE id = ?", [$roomId]);
    }

    public function testOverviewRoomInventoryTableStructureAndStatusStyling(): void
    {
        $hotelView = file_get_contents(BASE_DIR . '/app/Views/dashboard/hotel.php');
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        // 1. Verify 3-dot overflow menu replaces old select/edit buttons
        $this->assert(!str_contains($hotelView, '<select name="status" class="input input-sm" onchange="this.form.submit()"'), "Overview table must NOT have old status select dropdown");
        $this->assert(str_contains($hotelView, 'room-inventory-table'), "Overview table must have 'room-inventory-table' class");
        $this->assert(str_contains($hotelView, 'btn-res-action-trigger'), "Overview table must have 3-dot overflow menu trigger 'btn-res-action-trigger'");
        $this->assert(str_contains($hotelView, 'res-action-dropdown'), "Overview table must have 'res-action-dropdown' menu");
        $this->assert(str_contains($hotelView, 'Change Status'), "Overview 3-dot menu must have 'Change Status' group");

        // 2. Verify Specifications visual treatment (no emojis, proper hierarchy)
        $this->assert(!str_contains($hotelView, '👥 Max'), "Overview Specs column must not use emojis");
        $this->assert(!str_contains($hotelView, '📐'), "Overview Specs column must not use 📐 emoji");
        $this->assert(str_contains($hotelView, 'col-specs'), "Overview table must have 'col-specs' column");

        // 3. Verify Deposit values without colored badge and without front desk balance
        $this->assert(!str_contains($hotelView, 'background:#E0F2FE;color:#0369A1'), "Overview Deposit values must NOT use colored blue badge background");
        $this->assert(str_contains($hotelView, 'col-deposit'), "Overview table must have 'col-deposit' column");
        $this->assert(!str_contains($hotelView, 'Balance at desk:'), "Overview table Deposit column must NOT display front desk balance calculation or label");

        // 4. Verify status badge styling (especially Fully Booked not being green)
        $this->assert(str_contains($css, '.pill-fully_booked') || str_contains($css, 'pill-cancelled'), "CSS must define .pill-fully_booked / .pill-cancelled");
        $this->assert(str_contains($css, '#fee2e2') && str_contains($css, '#991b1b'), "CSS must define red background (#fee2e2) and dark red text (#991b1b) for fully booked/cancelled status");
    }

    public function testRoomIdentificationAndDepositColumnsUpdatedInBothTables(): void
    {
        $overviewView = file_get_contents(BASE_DIR . '/app/Views/dashboard/hotel.php');
        $roomsView = file_get_contents(BASE_DIR . '/app/Views/hotel/rooms.php');

        // Overview table:
        // Room Name field must display only the room name, no description
        $this->assert(!str_contains($overviewView, '$rm[\'description\']'), "Overview table rows must not render room description");
        // Deposit column must not display front desk balance
        $this->assert(!str_contains($overviewView, 'Balance at desk'), "Overview table Deposit column must not display 'Balance at desk'");

        // Configured Room Types table:
        // Room Name field must display only the room name, no description
        $this->assert(!str_contains($roomsView, '$rm[\'description\']'), "Configured Room Types table rows must not render room description");
        // Deposit column must not display front desk balance
        $this->assert(!str_contains($roomsView, 'Balance at desk') && !str_contains($roomsView, 'balance at desk'), "Rooms table Deposit column must not display front desk balance");
    }

    public function testRoomStatusUpdateDoesNotPolluteSessionFlash(): void
    {
        $unique = bin2hex(random_bytes(4));
        $hotelPartnerId = User::create('Flash Test Partner', "flash_{$unique}@example.com", 'Pass123!', 'hotel_admin');
        $this->createdUserIds[] = $hotelPartnerId;
        Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$hotelPartnerId]);

        $slug = 'hotel-flash-' . bin2hex(random_bytes(3));
        $hotelId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
             VALUES (?, 3, 'Flash Test Resort', ?, 'Summary', 'Desc', 'Mactan', 3000, 'per night', 'https://example.com/st.jpg', 'approved')",
            [$hotelPartnerId, $slug]
        );
        $this->createdListingIds[] = $hotelId;

        $roomId = HotelRoom::create([
            'listing_id' => $hotelId,
            'name' => 'Garden Villa',
            'bed_type' => '1 Queen Bed',
            'max_guests' => 2,
            'price_per_night' => 3000.00,
            'deposit_percentage' => 20,
            'status' => 'available',
        ]);

        $_SESSION['user_id'] = $hotelPartnerId;
        Auth::refreshUser();
        unset($_SESSION['_flash']);

        // Send AJAX status update request
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $_POST['_token'] = csrf_token();
        $_POST['status'] = 'fully_booked';

        $controller = new HotelController();
        ob_start();
        $controller->updateRoomStatus((string) $roomId);
        $output = ob_get_clean();

        $data = json_decode($output ?: '', true);
        $this->assert(is_array($data) && ($data['success'] ?? false) === true, "updateRoomStatus must return JSON success");

        // Assert that $_SESSION['_flash'] is NOT polluted with a success message for AJAX requests
        $flashSuccess = $_SESSION['_flash']['success'] ?? null;
        $this->assert($flashSuccess === null, 'AJAX status update must NOT persist success message in $_SESSION, preventing duplicate notification on page refresh/revisit');

        unset($_SERVER['HTTP_ACCEPT'], $_SERVER['HTTP_X_REQUESTED_WITH'], $_POST);
        Database::run("DELETE FROM hotel_rooms WHERE id = ?", [$roomId]);
    }

    public function testFlashFunctionClearsSessionImmediately(): void
    {
        // 1. Setting a flash message
        flash('success', 'Operation completed');
        $this->assert(isset($_SESSION['_flash']['success']), "flash() must store message in session");

        // 2. Reading the flash message consumes it
        $msg = flash('success');
        $this->assert($msg === 'Operation completed', "flash() must return stored message");
        $this->assert(!isset($_SESSION['_flash']['success']), "flash() must immediately clear message from session");

        // 3. Second read returns null
        $secondRead = flash('success');
        $this->assert($secondRead === null, "Subsequent flash() read must return null so notification never replays on refresh");
    }

    public function testYourHotelPropertiesTableActionButtonsLayout(): void
    {
        $hotelView = file_get_contents(BASE_DIR . '/app/Views/dashboard/hotel.php');
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        // 1. Table structure in Section 3 uses hotel-properties-table class
        $this->assert(str_contains($hotelView, 'hotel-properties-table'), "Your Hotel Properties table must have 'hotel-properties-table' class");
        
        // 2. Actions column header is right-aligned and uses col-actions class
        $this->assert(str_contains($hotelView, '<th class="col-actions" style="width:12%;text-align:right;'), "Actions header must have col-actions and right alignment");

        // 3. Actions data cell has col-actions and white-space:nowrap
        $this->assert(str_contains($hotelView, '<td class="col-actions" style="text-align:right;white-space:nowrap;">'), "Actions data cell must have col-actions and white-space:nowrap");

        // 4. Flex container hotel-prop-actions wraps the buttons
        $this->assert(str_contains($hotelView, 'class="hotel-prop-actions"'), "Actions must be wrapped in 'hotel-prop-actions' flex container");

        // 5. Preserves functionality, labels, and links
        $this->assert(str_contains($hotelView, 'View Listing ↗'), "View Listing action button label must be preserved");
        $this->assert(str_contains($hotelView, 'Edit</a>'), "Edit action button label must be preserved");
        $this->assert(str_contains($hotelView, 'class="btn btn-ghost btn-sm hotel-prop-btn"'), "Action buttons must use .hotel-prop-btn for consistent sizing and styling");

        // 6. CSS style rules exist and provide consistent spacing, alignment, and responsiveness
        $this->assert(str_contains($css, '.hotel-properties-table'), "CSS must define .hotel-properties-table");
        $this->assert(str_contains($css, '.hotel-prop-actions'), "CSS must define .hotel-prop-actions");
        $this->assert(str_contains($css, 'gap: 0.5rem'), "CSS must specify gap: 0.5rem for consistent spacing between action buttons");
        $this->assert(str_contains($css, '.hotel-prop-btn'), "CSS must define .hotel-prop-btn");
        $this->assert(str_contains($css, 'border-radius: 9999px'), "CSS must specify pill border-radius: 9999px for hotel-prop-btn");
        $this->assert(str_contains($css, 'min-width: 640px'), "CSS must specify responsive min-width for hotel-properties-table");
    }

    private function runTest(string $name, callable $callable): void
    {
        try {
            $callable();
            $this->passed++;
            echo sprintf(" [\033[32mPASS\033[0m] %s\n", $name);
        } catch (\Throwable $e) {
            $this->failed++;
            echo sprintf(" [\033[31mFAIL\033[0m] %s: %s (in %s:%d)\n%s\n", $name, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
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
            $placeholders = implode(',', array_fill(0, count($this->createdListingIds), '?'));
            Database::run("DELETE FROM hotel_rooms WHERE listing_id IN ({$placeholders})", $this->createdListingIds);
            Database::run("DELETE FROM listings WHERE id IN ({$placeholders})", $this->createdListingIds);
        }
        if ($this->createdUserIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->createdUserIds), '?'));
            Database::run("DELETE FROM users WHERE id IN ({$placeholders})", $this->createdUserIds);
        }
    }
}

$test = new HotelListingManagementTest();
if (!$test->run()) {
    exit(1);
}
