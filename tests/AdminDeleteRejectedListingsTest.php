<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\AdminAuth;
use App\Core\Auth;
use App\Core\Database;
use App\Controllers\AdminController;
use App\Models\Listing;
use App\Models\Vehicle;
use App\Models\HotelRoom;
use App\Models\User;
use App\Models\AuditLog;

final class AdminDeleteRejectedListingsTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Admin Delete Rejected Listings Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testDeleteModalStructureInBothViews', [$this, 'testDeleteModalStructureInBothViews']);
        $this->runTest('testHotelViewDeleteButtonOnlyOnRejectedListings', [$this, 'testHotelViewDeleteButtonOnlyOnRejectedListings']);
        $this->runTest('testVehicleViewDeleteButtonOnlyOnRejectedListings', [$this, 'testVehicleViewDeleteButtonOnlyOnRejectedListings']);
        $this->runTest('testRouterSupportsDeleteAndLoadsWebRoutesWithoutFatalError', [$this, 'testRouterSupportsDeleteAndLoadsWebRoutesWithoutFatalError']);
        $this->runTest('testRoutesRegisteredForDeletingHotelsAndVehicles', [$this, 'testRoutesRegisteredForDeletingHotelsAndVehicles']);
        $this->runTest('testBackendRejectsDeletingNonRejectedHotel', [$this, 'testBackendRejectsDeletingNonRejectedHotel']);
        $this->runTest('testBackendPermanentlyDeletesRejectedHotel', [$this, 'testBackendPermanentlyDeletesRejectedHotel']);
        $this->runTest('testBackendRejectsDeletingNonRejectedVehicle', [$this, 'testBackendRejectsDeletingNonRejectedVehicle']);
        $this->runTest('testBackendPermanentlyDeletesRejectedVehicle', [$this, 'testBackendPermanentlyDeletesRejectedVehicle']);

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testDeleteModalStructureInBothViews(): void
    {
        $hotelView = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');
        $vehicleView = file_get_contents(BASE_DIR . '/app/Views/admin/vehicles.php');

        // Hotel view assertions
        $this->assert(str_contains($hotelView, 'id="adminDeleteModal"'), 'Hotel view must include id="adminDeleteModal"');
        $this->assert(str_contains($hotelView, 'admin-delete-modal'), 'Hotel view must have admin-delete-modal class');
        $this->assert(str_contains($hotelView, 'admin-delete-warning-box'), 'Hotel view must include warning box');
        $this->assert(str_contains($hotelView, 'action is permanent'), 'Hotel view modal must explain action is permanent');
        $this->assert(str_contains($hotelView, 'btn-confirm-delete'), 'Hotel view must have btn-confirm-delete');
        $this->assert(str_contains($hotelView, 'handleDeleteSubmit'), 'Hotel view must handle deletion asynchronously via handleDeleteSubmit');
        $this->assert(str_contains($hotelView, 'showAdminAlert'), 'Hotel view must include showAdminAlert for success/error feedback');

        // Vehicle view assertions
        $this->assert(str_contains($vehicleView, 'id="adminDeleteModal"'), 'Vehicle view must include id="adminDeleteModal"');
        $this->assert(str_contains($vehicleView, 'admin-delete-modal'), 'Vehicle view must have admin-delete-modal class');
        $this->assert(str_contains($vehicleView, 'admin-delete-warning-box'), 'Vehicle view must include warning box');
        $this->assert(str_contains($vehicleView, 'action is permanent'), 'Vehicle view modal must explain action is permanent');
        $this->assert(str_contains($vehicleView, 'btn-confirm-delete'), 'Vehicle view must have btn-confirm-delete');
        $this->assert(str_contains($vehicleView, 'handleDeleteSubmit'), 'Vehicle view must handle deletion asynchronously via handleDeleteSubmit');
        $this->assert(str_contains($vehicleView, 'showAdminAlert'), 'Vehicle view must include showAdminAlert for success/error feedback');
    }

    public function testHotelViewDeleteButtonOnlyOnRejectedListings(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');

        // Assert condition: Delete button is rendered strictly when $hStatus === 'rejected'
        $this->assert(
            preg_match('/<\?php\s+if\s*\(\$hStatus\s*===\s*\'rejected\'\)\s*:\s*\?>\s*<button[^>]+class="[^"]*btn-action-delete[^"]*"[^>]*onclick="openDeleteModal\(/', $view) === 1,
            'Hotel view must render Delete button only when listing status is rejected'
        );

        // Render mockup of hotels view with 3 statuses: pending, approved, rejected
        $hotels = [
            [
                'id' => 8801,
                'title' => 'Pending Beach Resort',
                'area' => 'Moalboal',
                'address' => 'Panagsama',
                'price' => 2500,
                'status' => 'pending',
                'provider_name' => 'Host One',
                'provider_email' => 'host1@example.com',
                'room_count' => 0,
                'included' => 'WiFi',
                'cover_image' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'submitted_at' => date('Y-m-d H:i:s'),
                'reviewed_at' => null,
                'reviewer_name' => null,
            ],
            [
                'id' => 8802,
                'title' => 'Approved Mountain Inn',
                'area' => 'Oslob',
                'address' => 'Main Road',
                'price' => 3000,
                'status' => 'approved',
                'provider_name' => 'Host Two',
                'provider_email' => 'host2@example.com',
                'room_count' => 3,
                'included' => 'Pool',
                'cover_image' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'submitted_at' => date('Y-m-d H:i:s'),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewer_name' => 'Admin',
            ],
            [
                'id' => 8803,
                'title' => 'Rejected Budget Hostel',
                'area' => 'Cebu City',
                'address' => 'Downtown',
                'price' => 800,
                'status' => 'rejected',
                'provider_name' => 'Host Three',
                'provider_email' => 'host3@example.com',
                'room_count' => 0,
                'included' => '',
                'cover_image' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'submitted_at' => date('Y-m-d H:i:s'),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewer_name' => 'Admin',
            ],
        ];

        $status = 'all';
        $pendingCount = 1;

        ob_start();
        include BASE_DIR . '/app/Views/admin/hotels.php';
        $html = ob_get_clean();

        // Check Pending row (8801) has no Delete button
        $this->assert(
            !str_contains($html, "openDeleteModal(8801"),
            'Pending hotel row must NOT have Delete button'
        );

        // Check Approved row (8802) has no Delete button
        $this->assert(
            !str_contains($html, "openDeleteModal(8802"),
            'Approved hotel row must NOT have Delete button'
        );

        // Check Rejected row (8803) DOES have Delete button
        $this->assert(
            str_contains($html, "openDeleteModal(8803"),
            'Rejected hotel row MUST have Delete button'
        );
    }

    public function testVehicleViewDeleteButtonOnlyOnRejectedListings(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/vehicles.php');

        // Assert condition: Delete button is rendered strictly when $vStatus === 'rejected'
        $this->assert(
            preg_match('/<\?php\s+if\s*\(\$vStatus\s*===\s*\'rejected\'\)\s*:\s*\?>\s*<button[^>]+class="[^"]*btn-action-delete[^"]*"[^>]*onclick="openDeleteModal\(/', $view) === 1,
            'Vehicle view must render Delete button only when listing status is rejected'
        );

        // Render mockup of vehicles view with 3 statuses: pending, approved, rejected
        $vehicles = [
            [
                'id' => 9901,
                'name' => 'Pending Scooter',
                'location' => 'Moalboal',
                'type' => 'Scooter',
                'year' => 2024,
                'make' => 'Honda',
                'price_per_day' => 500,
                'status' => 'pending',
                'provider_name' => 'Partner One',
                'provider_email' => 'partner1@example.com',
                'specs' => null,
                'image' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'submitted_at' => date('Y-m-d H:i:s'),
                'reviewed_at' => null,
                'reviewer_name' => null,
                'rejection_reason' => null,
            ],
            [
                'id' => 9902,
                'name' => 'Approved Sedan',
                'location' => 'Cebu City',
                'type' => 'Car',
                'year' => 2023,
                'make' => 'Toyota',
                'price_per_day' => 2000,
                'status' => 'approved',
                'provider_name' => 'Partner Two',
                'provider_email' => 'partner2@example.com',
                'specs' => null,
                'image' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'submitted_at' => date('Y-m-d H:i:s'),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewer_name' => 'Admin',
                'rejection_reason' => null,
            ],
            [
                'id' => 9903,
                'name' => 'Rejected Van',
                'location' => 'Mactan',
                'type' => 'Van',
                'year' => 2021,
                'make' => 'Nissan',
                'price_per_day' => 3500,
                'status' => 'rejected',
                'provider_name' => 'Partner Three',
                'provider_email' => 'partner3@example.com',
                'specs' => null,
                'image' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'submitted_at' => date('Y-m-d H:i:s'),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'reviewer_name' => 'Admin',
                'rejection_reason' => 'Missing registration document',
            ],
        ];

        $status = 'all';
        $pendingCount = 1;

        ob_start();
        include BASE_DIR . '/app/Views/admin/vehicles.php';
        $html = ob_get_clean();

        // Check Pending row (9901) has no Delete button
        $this->assert(
            !str_contains($html, "openDeleteModal(9901"),
            'Pending vehicle row must NOT have Delete button'
        );

        // Check Approved row (9902) has no Delete button
        $this->assert(
            !str_contains($html, "openDeleteModal(9902"),
            'Approved vehicle row must NOT have Delete button'
        );

        // Check Rejected row (9903) DOES have Delete button
        $this->assert(
            str_contains($html, "openDeleteModal(9903"),
            'Rejected vehicle row MUST have Delete button'
        );
    }

    public function testRouterSupportsDeleteAndLoadsWebRoutesWithoutFatalError(): void
    {
        $router = new \App\Core\Router();
        $this->assert(method_exists($router, 'delete'), 'Router must define delete() method');
        $this->assert(method_exists($router, 'put'), 'Router must define put() method');
        $this->assert(method_exists($router, 'patch'), 'Router must define patch() method');

        // Test loading routes/web.php directly with new router instance
        ob_start();
        require BASE_DIR . '/routes/web.php';
        ob_end_clean();
        $this->assert(true, 'routes/web.php must require and register routes without throwing fatal errors');
    }

    public function testRoutesRegisteredForDeletingHotelsAndVehicles(): void
    {
        $routes = file_get_contents(BASE_DIR . '/routes/web.php');

        $this->assert(
            str_contains($routes, "'/admin/hotels/{id}/delete', [AdminController::class, 'deleteHotel']"),
            'web.php must register route POST /admin/hotels/{id}/delete pointing to deleteHotel'
        );
        $this->assert(
            str_contains($routes, "'/admin/vehicles/{id}/delete', [AdminController::class, 'deleteVehicle']"),
            'web.php must register route POST /admin/vehicles/{id}/delete pointing to deleteVehicle'
        );
    }

    public function testBackendRejectsDeletingNonRejectedHotel(): void
    {
        $admin = $this->getOrCreateAdmin();
        $this->simulateAdminAuth($admin);

        // Create a hotel with pending status
        $slug = 'test-pending-hotel-' . bin2hex(random_bytes(4));
        $hotelId = (int) Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, price, status, created_at, updated_at)
             VALUES (?, (SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1), 'Pending Hotel Test', ?, 2000, 'pending', NOW(), NOW())",
            [$admin['id'], $slug]
        );

        try {
            $_POST['_token'] = Auth::csrfToken();
            $_SERVER['HTTP_ACCEPT'] = 'application/json';

            $controller = new AdminController();
            ob_start();
            $controller->deleteHotel((string) $hotelId);
            $output = ob_get_clean();

            $response = json_decode((string) $output, true);
            $this->assert(is_array($response), 'deleteHotel must return JSON');
            $this->assert($response['success'] === false, 'deleteHotel must return success: false for pending hotel');
            $this->assert(str_contains((string) $response['message'], 'Only rejected'), 'Message must indicate only rejected listings can be deleted');

            // Verify hotel still exists in database
            $stillExists = Listing::find($hotelId);
            $this->assert($stillExists !== null, 'Pending hotel must not be removed from database');
        } finally {
            Database::run('DELETE FROM listings WHERE id = ?', [$hotelId]);
            unset($_POST['_token'], $_SERVER['HTTP_ACCEPT']);
        }
    }

    public function testBackendPermanentlyDeletesRejectedHotel(): void
    {
        $admin = $this->getOrCreateAdmin();
        $this->simulateAdminAuth($admin);

        // Create a hotel with rejected status
        $slug = 'test-rejected-hotel-' . bin2hex(random_bytes(4));
        $hotelId = (int) Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, price, status, rejection_reason, created_at, updated_at)
             VALUES (?, (SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1), 'Rejected Hotel Test', ?, 2000, 'rejected', 'Missing exterior photo', NOW(), NOW())",
            [$admin['id'], $slug]
        );

        // Add a child hotel room and listing image
        Database::insert(
            "INSERT INTO hotel_rooms (listing_id, name, bed_type, max_guests, price_per_night, status, created_at, updated_at)
             VALUES (?, 'Deluxe Suite', '1 King Bed', 2, 2500, 'available', NOW(), NOW())",
            [$hotelId]
        );
        Database::insert(
            "INSERT INTO listing_images (listing_id, image_path, sort_order)
             VALUES (?, 'uploads/test-hotel.jpg', 0)",
            [$hotelId]
        );

        try {
            $_POST['_token'] = Auth::csrfToken();
            $_SERVER['HTTP_ACCEPT'] = 'application/json';

            $controller = new AdminController();
            ob_start();
            $controller->deleteHotel((string) $hotelId);
            $output = ob_get_clean();

            $response = json_decode((string) $output, true);
            $this->assert(is_array($response), 'deleteHotel must return JSON on AJAX');
            $this->assert($response['success'] === true, 'deleteHotel must return success: true for rejected hotel');

            // Verify hotel is permanently deleted from database
            $hotelInDb = Listing::find($hotelId);
            $this->assert($hotelInDb === null, 'Rejected hotel must be completely removed from listings table');

            // Verify child rooms and images are deleted
            $rooms = Database::all('SELECT id FROM hotel_rooms WHERE listing_id = ?', [$hotelId]);
            $this->assert(empty($rooms), 'Child hotel_rooms must be completely removed');

            $images = Database::all('SELECT id FROM listing_images WHERE listing_id = ?', [$hotelId]);
            $this->assert(empty($images), 'Child listing_images must be completely removed');
        } finally {
            Database::run('DELETE FROM hotel_rooms WHERE listing_id = ?', [$hotelId]);
            Database::run('DELETE FROM listing_images WHERE listing_id = ?', [$hotelId]);
            Database::run('DELETE FROM listings WHERE id = ?', [$hotelId]);
            unset($_POST['_token'], $_SERVER['HTTP_ACCEPT']);
        }
    }

    public function testBackendRejectsDeletingNonRejectedVehicle(): void
    {
        $admin = $this->getOrCreateAdmin();
        $this->simulateAdminAuth($admin);

        // Create a vehicle with approved status
        $slug = 'test-approved-vehicle-' . bin2hex(random_bytes(4));
        $vehicleId = (int) Database::insert(
            "INSERT INTO vehicles (user_id, name, slug, type, year, make, price_per_day, status, created_at, updated_at)
             VALUES (?, 'Approved Vehicle Test', ?, 'Scooter', 2024, 'Honda', 500, 'approved', NOW(), NOW())",
            [$admin['id'], $slug]
        );

        try {
            $_POST['_token'] = Auth::csrfToken();
            $_SERVER['HTTP_ACCEPT'] = 'application/json';

            $controller = new AdminController();
            ob_start();
            $controller->deleteVehicle((string) $vehicleId);
            $output = ob_get_clean();

            $response = json_decode((string) $output, true);
            $this->assert(is_array($response), 'deleteVehicle must return JSON');
            $this->assert($response['success'] === false, 'deleteVehicle must return success: false for approved vehicle');
            $this->assert(str_contains((string) $response['message'], 'Only rejected'), 'Message must indicate only rejected vehicles can be deleted');

            // Verify vehicle still exists in database
            $stillExists = Vehicle::find($vehicleId);
            $this->assert($stillExists !== null, 'Approved vehicle must not be removed from database');
        } finally {
            Database::run('DELETE FROM vehicles WHERE id = ?', [$vehicleId]);
            unset($_POST['_token'], $_SERVER['HTTP_ACCEPT']);
        }
    }

    public function testBackendPermanentlyDeletesRejectedVehicle(): void
    {
        $admin = $this->getOrCreateAdmin();
        $this->simulateAdminAuth($admin);

        // Create a vehicle with rejected status
        $slug = 'test-rejected-vehicle-' . bin2hex(random_bytes(4));
        $vehicleId = (int) Database::insert(
            "INSERT INTO vehicles (user_id, name, slug, type, year, make, price_per_day, status, rejection_reason, created_at, updated_at)
             VALUES (?, 'Rejected Vehicle Test', ?, 'Scooter', 2022, 'Yamaha', 600, 'rejected', 'Invalid OR/CR photo', NOW(), NOW())",
            [$admin['id'], $slug]
        );

        // Add child vehicle image
        Database::insert(
            "INSERT INTO vehicle_images (vehicle_id, image_path, sort_order)
             VALUES (?, 'uploads/test-vehicle.jpg', 0)",
            [$vehicleId]
        );

        try {
            $_POST['_token'] = Auth::csrfToken();
            $_SERVER['HTTP_ACCEPT'] = 'application/json';

            $controller = new AdminController();
            ob_start();
            $controller->deleteVehicle((string) $vehicleId);
            $output = ob_get_clean();

            $response = json_decode((string) $output, true);
            $this->assert(is_array($response), 'deleteVehicle must return JSON on AJAX');
            $this->assert($response['success'] === true, 'deleteVehicle must return success: true for rejected vehicle');

            // Verify vehicle is permanently deleted from database
            $vehicleInDb = Vehicle::find($vehicleId);
            $this->assert($vehicleInDb === null, 'Rejected vehicle must be completely removed from vehicles table');

            // Verify child images are removed
            $images = Database::all('SELECT id FROM vehicle_images WHERE vehicle_id = ?', [$vehicleId]);
            $this->assert(empty($images), 'Child vehicle_images must be completely removed');
        } finally {
            Database::run('DELETE FROM vehicle_images WHERE vehicle_id = ?', [$vehicleId]);
            Database::run('DELETE FROM vehicles WHERE id = ?', [$vehicleId]);
            unset($_POST['_token'], $_SERVER['HTTP_ACCEPT']);
        }
    }

    private function getOrCreateAdmin(): array
    {
        $admin = Database::first("SELECT * FROM users WHERE role = 'admin' LIMIT 1");
        if ($admin !== null) {
            return $admin;
        }

        $id = (int) Database::insert(
            "INSERT INTO users (name, email, password_hash, role, status, created_at)
             VALUES ('Test Admin', 'admin_del_test@example.com', 'dummy_hash', 'admin', 'active', NOW())"
        );
        return Database::first("SELECT * FROM users WHERE id = ?", [$id]);
    }

    private function simulateAdminAuth(array $admin): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['admin_user_id'] = $admin['id'];
        $_SESSION['admin_logged_in'] = true;
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
}

$suite = new AdminDeleteRejectedListingsTest();
exit($suite->run() ? 0 : 1);
