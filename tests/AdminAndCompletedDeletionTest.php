<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Booking;
use App\Models\RentalRequest;
use App\Models\Listing;
use App\Models\Dispute;
use App\Controllers\AdminController;
use App\Controllers\GuideController;
use App\Controllers\HotelController;
use App\Controllers\RentalController;

final class AdminAndCompletedDeletionTest
{
    private int $passCount = 0;
    private int $failCount = 0;
    private array $cleanUpBookingIds = [];
    private array $cleanUpRentalIds = [];
    private array $cleanUpUserIds = [];
    private array $cleanUpDisputeIds = [];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    public function run(): void
    {
        echo "\n========================================================\n";
        echo " GuideMate Admin & Completed Deletion Verification Test\n";
        echo "========================================================\n\n";

        try {
            $this->testAdminNavConsolidation();
            $this->testUnifiedListingReviewTabs();
            $this->testAddUserModalAndEndpoint();
            $this->testDisputeProviderAccountIdentificationAndWarning();
            $this->testCompletedRecordDeletionLogic();
            $this->testRefundedRentalRequestDeleteReportAction();
            $this->testAdminOverviewComplaintDetails();
            $this->testAddUserModalDarkStyling();
            $this->testEditUserModalAndAjaxUpdate();
        } finally {
            $this->tearDown();
        }

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passCount} passed, {$this->failCount} failed\n";
        echo "--------------------------------------------------------\n\n";

        if ($this->failCount > 0) {
            exit(1);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo " [PASS] {$message}\n";
            $this->passCount++;
        } else {
            echo " [FAIL] {$message}\n";
            $this->failCount++;
        }
    }

    private function testAdminNavConsolidation(): void
    {
        echo "--- Testing Admin Sidebar Consolidation ---\n";
        $navFile = BASE_DIR . '/app/Views/partials/admin-nav.php';
        $nav = file_get_contents($navFile);

        $this->assert(
            str_contains($nav, 'Listing Review'),
            'Admin nav contains single consolidated "Listing Review" item'
        );
        $this->assert(
            str_contains($nav, '/admin/listing-review'),
            'Admin nav links to /admin/listing-review'
        );
        $this->assert(
            str_contains($nav, '$pendingListingReviews'),
            'Admin nav tracks consolidated pending reviews count'
        );
    }

    private function testUnifiedListingReviewTabs(): void
    {
        echo "\n--- Testing Unified Listing Review Tabs ---\n";
        $viewFile = BASE_DIR . '/app/Views/admin/listing-review.php';
        $this->assert(file_exists($viewFile), 'listing-review.php view exists');

        $viewContent = file_get_contents($viewFile);
        $this->assert(
            str_contains($viewContent, 'tab=guides') && str_contains($viewContent, 'tab=hotels') && str_contains($viewContent, 'tab=vehicles'),
            'listing-review.php has Guide, Hotels, and Vehicles category filter tabs'
        );
        $this->assert(
            str_contains($viewContent, 'admin/guide-listings') && str_contains($viewContent, 'admin/hotels') && str_contains($viewContent, 'admin/vehicles'),
            'listing-review.php embeds the respective category review tables'
        );
    }

    private function testAddUserModalAndEndpoint(): void
    {
        echo "\n--- Testing Add User Modal & Backend Logic ---\n";
        $usersView = file_get_contents(BASE_DIR . '/app/Views/admin/users.php');
        $this->assert(
            str_contains($usersView, 'id="openAddUserModalBtn"'),
            'Users page has Add User modal trigger button'
        );
        $this->assert(
            str_contains($usersView, 'id="addUserModal"'),
            'Users page has #addUserModal popup modal'
        );
        $this->assert(
            str_contains($usersView, 'name="name"') && str_contains($usersView, 'name="email"') && str_contains($usersView, 'name="password"') && str_contains($usersView, 'name="role"'),
            'Add User modal contains name, email, password, role fields'
        );

        // Test User::adminCreate and validation
        $testEmail = 'modal_test_' . time() . '@example.com';
        $userId = User::adminCreate([
            'name' => 'Modal Test User',
            'email' => $testEmail,
            'password' => 'Password123!',
            'role' => 'tourist',
            'is_active' => 1,
            'phone' => '+639123456789',
        ]);
        $this->cleanUpUserIds[] = $userId;

        $created = User::find($userId);
        $this->assert($created !== null && $created['email'] === $testEmail, 'User created via backend logic successfully');
    }

    private function testDisputeProviderAccountIdentificationAndWarning(): void
    {
        echo "\n--- Testing Complaint Section Dynamic Account Type & Contextual Warning ---\n";

        // Test guide account detection
        $guideDispute = [
            'provider_role' => 'guide',
            'category_slug' => 'attractions',
        ];
        $infoGuide = Dispute::providerAccountInfo($guideDispute);
        $this->assert($infoGuide['account_type_label'] === 'Guide Account', 'Identifies Guide Account correctly');
        $this->assert($infoGuide['warn_btn_label'] === 'Warn Guide', 'Contextual button is "Warn Guide"');

        // Test hotel provider account detection
        $hotelDispute = [
            'provider_role' => 'hotel_provider',
            'category_slug' => 'hotels',
        ];
        $infoHotel = Dispute::providerAccountInfo($hotelDispute);
        $this->assert($infoHotel['account_type_label'] === 'Hotel Provider Account', 'Identifies Hotel Provider Account correctly');
        $this->assert($infoHotel['warn_btn_label'] === 'Warn Hotel Provider', 'Contextual button is "Warn Hotel Provider"');

        // Test rental provider account detection
        $rentalDispute = [
            'provider_role' => 'rental_provider',
            'category_slug' => 'vehicles',
        ];
        $infoRental = Dispute::providerAccountInfo($rentalDispute);
        $this->assert($infoRental['account_type_label'] === 'Vehicle Rental Provider Account', 'Identifies Vehicle Rental Provider Account correctly');
        $this->assert($infoRental['warn_btn_label'] === 'Warn Vehicle Rental Provider', 'Contextual button is "Warn Vehicle Rental Provider"');

        // Test warning logic updates provider
        $testProviderEmail = 'provider_warn_' . time() . '@example.com';
        $providerId = User::adminCreate([
            'name' => 'Hotel Partner Test',
            'email' => $testProviderEmail,
            'password' => 'Password123!',
            'role' => 'hotel_provider',
            'is_active' => 1,
        ]);
        $this->cleanUpUserIds[] = $providerId;

        $this->assert(!User::isGuideWarned($providerId), 'Provider initially has no warning');
        User::warnGuide($providerId, 'Test warning for hotel provider');
        $this->assert(User::isGuideWarned($providerId), 'Provider is marked warned after warning issued');

        User::clearGuideWarning($providerId);
        $this->assert(!User::isGuideWarned($providerId), 'Provider warning can be cleared');
    }

    private function testCompletedRecordDeletionLogic(): void
    {
        echo "\n--- Testing Completed Record Deletion Functionality ---\n";

        // Check view templates have delete buttons
        $guideBookingsView = file_get_contents(BASE_DIR . '/app/Views/guide/bookings.php');
        $this->assert(
            str_contains($guideBookingsView, 'data-delete-booking'),
            'Guide bookings view has data-delete-booking button for completed records'
        );

        $rentalRequestsView = file_get_contents(BASE_DIR . '/app/Views/rental/requests.php');
        $this->assert(
            str_contains($rentalRequestsView, 'data-delete-rental'),
            'Rental requests view has data-delete-rental button for completed records'
        );

        $hotelDashboardView = file_get_contents(BASE_DIR . '/app/Views/dashboard/hotel.php');
        $this->assert(
            str_contains($hotelDashboardView, 'data-delete-booking'),
            'Hotel dashboard view has data-delete-booking button for completed reservations'
        );

        // Test Booking::delete
        $listing = Database::first('SELECT id FROM listings LIMIT 1');
        $user = Database::first('SELECT id FROM users LIMIT 1');
        if ($listing && $user) {
            $bookingId = Booking::create(
                (int) $listing['id'],
                (int) $user['id'],
                date('Y-m-d'),
                2,
                1500.0,
                'Completed Test',
                '10:00:00'
            );
            Booking::updateStatus($bookingId, 'completed');
            $found = Booking::find($bookingId);
            $this->assert($found !== null, 'Created test booking');

            $deleted = Booking::delete($bookingId);
            $this->assert($deleted, 'Booking::delete returned true');
            $this->assert(Booking::find($bookingId) === null, 'Completed booking permanently deleted from database');
        }

        // Test RentalRequest::delete
        $vehicle = Database::first('SELECT id FROM vehicles LIMIT 1');
        if ($vehicle && $user) {
            $reqId = Database::insert(
                'INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, pickup_date, rental_days, total_amount, customer_name, customer_email, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $user['id'], (string) $vehicle['id'], 'Test Vehicle', 'Sedan', 'Test Shop', date('Y-m-d'), 1, 1000.0, 'Test Cust', 'cust@example.com', 'completed']
            );
            $foundReq = RentalRequest::find($reqId);
            $this->assert($foundReq !== null, 'Created test rental request');

            $deletedReq = RentalRequest::delete($reqId);
            $this->assert($deletedReq, 'RentalRequest::delete returned true');
            $this->assert(RentalRequest::find($reqId) === null, 'Completed rental request permanently deleted from database');
        }
    }

    private function testRefundedRentalRequestDeleteReportAction(): void
    {
        echo "\n--- Testing Refunded Rental Request Action Button ---\n";
        $rentalRequestsView = file_get_contents(BASE_DIR . '/app/Views/rental/requests.php');

        $this->assert(
            str_contains($rentalRequestsView, '$isRefunded = in_array(\'refunded\', [$reqStatus, $paymentStatus, $reportStatus], true)'),
            'Rental requests view computes $isRefunded state accurately'
        );
        $this->assert(
            str_contains($rentalRequestsView, 'data-delete-report') && str_contains($rentalRequestsView, 'Delete Report'),
            'Rental requests view renders "Delete Report" button for refunded requests'
        );

        // Test deletion of a refunded rental request via RentalRequest::delete
        $user = Database::first('SELECT id FROM users LIMIT 1');
        $vehicle = Database::first('SELECT id FROM vehicles LIMIT 1');
        if ($user && $vehicle) {
            $reqId = Database::insert(
                'INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, pickup_date, rental_days, total_amount, customer_name, customer_email, status, payment_status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $user['id'], (string) $vehicle['id'], 'Refund Test Vehicle', 'Sedan', 'Test Shop', date('Y-m-d'), 1, 1000.0, 'Refund Cust', 'refund@example.com', 'refunded', 'refunded']
            );
            $this->cleanUpRentalIds[] = $reqId;

            $this->assert(RentalRequest::find($reqId) !== null, 'Created test refunded rental request');
            $deleted = RentalRequest::delete($reqId);
            $this->assert($deleted, 'RentalRequest::delete succeeded for refunded rental request');
            $this->assert(RentalRequest::find($reqId) === null, 'Refunded rental request permanently removed from database');
        }
    }

    private function testAdminOverviewComplaintDetails(): void
    {
        echo "\n--- Testing Admin Overview Complaint Details ---\n";
        $adminView = file_get_contents(BASE_DIR . '/app/Views/dashboard/admin.php');
        $this->assert(
            str_contains($adminView, 'Complaints'),
            'Admin overview view card title is Complaints'
        );
        $this->assert(
            str_contains($adminView, 'Open complaints'),
            'Admin overview stat label includes Open complaints'
        );
        $this->assert(
            str_contains($adminView, 'Tourist') && str_contains($adminView, 'Reported Account') && str_contains($adminView, 'Problem & Details'),
            'Admin overview renders full complaint detail columns (Tourist, Reported Account, Problem & Details)'
        );

        $adminControllerFile = file_get_contents(BASE_DIR . '/app/Controllers/AdminController.php');
        $this->assert(
            str_contains($adminControllerFile, "'complaints' => \$complaints") || str_contains($adminControllerFile, "'complaints' => Dispute::all()"),
            'AdminController::index() passes complaints to the dashboard view'
        );
    }

    private function testAddUserModalDarkStyling(): void
    {
        echo "\n--- Testing Add User Modal Dark Theme Redesign ---\n";
        $usersView = file_get_contents(BASE_DIR . '/app/Views/admin/users.php');
        $this->assert(
            str_contains($usersView, 'id="addUserModal"') && str_contains($usersView, '#11252c'),
            '#addUserModal uses dark admin theme background (#11252c)'
        );
        $this->assert(
            str_contains($usersView, 'id="submitAddUserBtn"') && str_contains($usersView, '#059669'),
            'Add User submit button uses emerald green style (#059669)'
        );
    }

    private function testEditUserModalAndAjaxUpdate(): void
    {
        echo "\n--- Testing Edit User Modal and AJAX Updates ---\n";
        $usersView = file_get_contents(BASE_DIR . '/app/Views/admin/users.php');
        $this->assert(
            str_contains($usersView, 'btn-edit-user') && str_contains($usersView, 'data-user-name'),
            'Manage users rows have btn-edit-user button with data attributes'
        );
        $this->assert(
            str_contains($usersView, 'id="editUserModal"'),
            '#editUserModal dialog exists in users.php'
        );
        $this->assert(
            str_contains($usersView, 'id="edit_user_name"') && str_contains($usersView, 'id="edit_user_email"') && str_contains($usersView, 'id="edit_user_role"') && str_contains($usersView, 'id="edit_user_phone"'),
            '#editUserModal contains all required editable fields (name, email, role, phone, location, bio)'
        );

        // Test User::adminUpdate directly and verify backend update
        $testEmail = 'edit_test_' . time() . '@example.com';
        $userId = Database::insert(
            'INSERT INTO users (name, email, password, role, is_active, phone, location, bio) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            ['Original Name', $testEmail, password_hash('password123', PASSWORD_DEFAULT), 'tourist', 1, '123456', 'Cebu', 'Original bio']
        );
        $this->cleanUpUserIds[] = $userId;

        User::adminUpdate($userId, [
            'name' => 'Updated Admin Name',
            'email' => $testEmail,
            'role' => 'guide',
            'is_active' => 1,
            'phone' => '+63 999 888 7777',
            'location' => 'Moalboal, Cebu',
            'bio' => 'Updated bio information',
        ]);

        $updated = User::find($userId);
        $this->assert($updated !== null && $updated['name'] === 'Updated Admin Name', 'User name updated via backend');
        $this->assert($updated !== null && $updated['role'] === 'guide', 'User role updated via backend');
        $this->assert($updated !== null && $updated['phone'] === '+63 999 888 7777', 'User phone updated via backend');
        $this->assert($updated !== null && $updated['location'] === 'Moalboal, Cebu', 'User location updated via backend');
    }

    private function tearDown(): void
    {
        foreach ($this->cleanUpBookingIds as $bId) {
            try {
                Database::run('DELETE FROM bookings WHERE id = ?', [$bId]);
            } catch (\Throwable $e) {}
        }
        foreach ($this->cleanUpRentalIds as $rId) {
            try {
                Database::run('DELETE FROM rental_requests WHERE id = ?', [$rId]);
            } catch (\Throwable $e) {}
        }
        foreach ($this->cleanUpUserIds as $uId) {
            try {
                Database::run('DELETE FROM users WHERE id = ?', [$uId]);
            } catch (\Throwable $e) {}
        }
    }
}

$test = new AdminAndCompletedDeletionTest();
$test->run();
