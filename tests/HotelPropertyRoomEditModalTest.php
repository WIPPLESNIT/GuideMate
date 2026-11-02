<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\HotelRoom;
use App\Controllers\HotelController;

echo "========================================================\n";
echo " Hotel Property Room Edit Modal & Workflow Test Suite\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        echo " [PASS] $msg\n";
        $passed++;
    } else {
        echo " [FAIL] $msg\n";
        $failed++;
    }
}

$cleanupUserIds = [];
$cleanupListingIds = [];
$cleanupRoomIds = [];

try {
    // ── Setup: Create a verified hotel admin partner, hotel listing, and a room ──
    $partnerId = User::create('Modal Test Owner', 'modal_owner_' . bin2hex(random_bytes(4)) . '@example.com', 'Pass123!', 'hotel_admin');
    $cleanupUserIds[] = $partnerId;
    Database::run("UPDATE users SET guide_status = 'approved' WHERE id = ?", [$partnerId]);

    $hotelSlug = 'test-hotel-modal-' . bin2hex(random_bytes(4));
    $hotelId = Database::insert(
        "INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, cover_image, status)
         VALUES (?, 3, 'Modal Hotel Test', ?, 'Test Summary', 'Test Description', 'Moalboal', 2500, 'per night', 'https://example.com/cover.jpg', 'approved')",
        [$partnerId, $hotelSlug]
    );
    $cleanupListingIds[] = $hotelId;

    $roomId = HotelRoom::create([
        'listing_id' => $hotelId,
        'name' => 'Deluxe Suite Test',
        'bed_type' => '1 King Bed',
        'max_guests' => 3,
        'price_per_night' => 3200.00,
        'deposit_percentage' => 20,
        'status' => 'available',
        'amenities' => 'Balcony, Sea View',
        'description' => 'Beautiful sunset view from private balcony.',
        'image' => 'https://example.com/room1.jpg',
        'images' => ['https://example.com/room1.jpg', 'https://example.com/room2.jpg'],
    ]);
    $cleanupRoomIds[] = $roomId;

    // Login as the hotel partner
    $_SESSION['user_id'] = $partnerId;
    Auth::refreshUser();

    // ── Test 1: Property Rooms View Renders Dedicated Edit Modal Structure ──
    echo "--- Test 1: Dedicated Edit Modal HTML Structure in Property Rooms View ---\n";
    $controller = new HotelController();
    ob_start();
    $controller->propertyRooms((string) $hotelId);
    $html = ob_get_clean();

    assertCondition(str_contains($html, 'id="modal-edit-property-room"'), 'modal-edit-property-room container exists');
    assertCondition(str_contains($html, 'id="edit-property-room-form"'), 'edit-property-room-form exists with method and enctype');
    assertCondition(str_contains($html, 'id="edit-prop-room-name"'), 'edit-prop-room-name input element exists');
    assertCondition(str_contains($html, 'id="edit-prop-room-bed"'), 'edit-prop-room-bed input element exists');
    assertCondition(str_contains($html, 'id="edit-prop-room-guests"'), 'edit-prop-room-guests input element exists');
    assertCondition(str_contains($html, 'id="edit-prop-room-price"'), 'edit-prop-room-price input element exists');
    assertCondition(str_contains($html, 'id="edit-prop-room-deposit"'), 'edit-prop-room-deposit input element exists');
    assertCondition(str_contains($html, 'id="edit-prop-room-status"'), 'edit-prop-room-status select element exists');
    assertCondition(str_contains($html, 'id="edit-prop-room-amenities"'), 'edit-prop-room-amenities input element exists');
    assertCondition(str_contains($html, 'id="edit-prop-room-desc"'), 'edit-prop-room-desc textarea exists');
    assertCondition(str_contains($html, 'closeEditPropertyRoomModal()'), 'closeEditPropertyRoomModal button click handlers present');

    // ── Test 2: Single Photo Slot in Edit Modal (Slots 1 & 2 Removed) ──
    echo "--- Test 2: Single Photo Slot in Edit Modal (Requirement 1 & 4) ---\n";
    assertCondition(str_contains($html, 'id="edit_room_file_0"'), 'edit_room_file_0 input present');
    assertCondition(str_contains($html, 'id="edit_room_url_0"'), 'edit_room_url_0 input present');
    assertCondition(str_contains($html, 'id="edit_room_removed_0"'), 'edit_room_removed_0 hidden input present');
    assertCondition(str_contains($html, 'id="edit_room_preview_img_0"'), 'edit_room_preview_img_0 preview img present');
    assertCondition(str_contains($html, 'removeEditRoomPhoto(0)'), 'removeEditRoomPhoto(0) call present');
    assertCondition(!str_contains($html, 'id="edit_room_file_1"'), 'edit_room_file_1 is REMOVED from Edit Room Type modal');
    assertCondition(!str_contains($html, 'id="edit_room_file_2"'), 'edit_room_file_2 is REMOVED from Edit Room Type modal');
    assertCondition(!str_contains($html, 'id="add_room_file_1"'), 'add_room_file_1 is REMOVED from Add Room Type modal');
    assertCondition(!str_contains($html, 'id="add_room_file_2"'), 'add_room_file_2 is REMOVED from Add Room Type modal');
    assertCondition(str_contains($html, '📷 No photo uploaded'), 'Placeholder text "No photo uploaded" is present in room modals');

    // ── Test 3: JavaScript Functions & Trigger Handler ──
    echo "--- Test 3: JavaScript Modal Handlers & Action Button ---\n";
    assertCondition(str_contains($html, 'function openEditPropertyRoomModal(room)'), 'openEditPropertyRoomModal function defined');
    assertCondition(str_contains($html, 'function closeEditPropertyRoomModal()'), 'closeEditPropertyRoomModal function defined');
    assertCondition(str_contains($html, 'window.handleEditRoomFileChange'), 'handleEditRoomFileChange function defined');
    assertCondition(str_contains($html, 'window.handleEditRoomUrlChange'), 'handleEditRoomUrlChange function defined');
    assertCondition(str_contains($html, 'window.removeEditRoomPhoto'), 'removeEditRoomPhoto function defined');
    assertCondition(str_contains($html, 'openEditPropertyRoomModal('), 'Edit Room Details button triggers openEditPropertyRoomModal with encoded room data');

    // ── Test 4: Add Modal and Edit Modal Coexistence & Separation ──
    echo "--- Test 4: Modal Separation & No ID Collisions ---\n";
    assertCondition(str_contains($html, 'id="modal-add-property-room"'), 'modal-add-property-room exists');
    assertCondition(str_contains($html, 'id="add_room_file_0"'), 'Add room photo slot 0 has distinct add_room_file_0 id');
    assertCondition(str_contains($html, 'closeAddPropertyRoomModal()'), 'Add room modal uses closeAddPropertyRoomModal()');
    assertCondition(str_contains($html, 'Save Room Type'), 'Add room modal submit button has "Save Room Type"');
    assertCondition(str_contains($html, 'Update Room'), 'Edit room modal submit button has "Update Room"');

    // ── Test 5: End-to-End Room Update Post Workflow ──
    echo "--- Test 5: Room Update Post Submission Lifecycle ---\n";
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    $_POST = [
        '_token' => csrf_token(),
        'name' => 'Updated Deluxe Suite Deluxe',
        'bed_type' => '2 Queen Beds',
        'max_guests' => '4',
        'price_per_night' => '3800',
        'deposit_percentage' => '25',
        'status' => 'blocked',
        'amenities' => 'Balcony, Sea View, Fast Wifi, Bathtub',
        'description' => 'Updated luxury beachfront bungalow.',
        'room_photo_url_0' => 'https://example.com/updated_photo.jpg',
        'room_photo_removed_0' => '0',
        'redirect_to' => "/dashboard/hotels/{$hotelId}/rooms",
    ];

    ob_start();
    $controller->updateRoom((string) $roomId);
    $jsonOutput = ob_get_clean();

    $resData = json_decode($jsonOutput ?: '', true);
    assertCondition(is_array($resData) && ($resData['success'] ?? false) === true, 'updateRoom returns JSON success');

    $updatedRoom = HotelRoom::find($roomId);
    assertCondition($updatedRoom !== null, 'Updated room exists in database');
    assertCondition(($updatedRoom['name'] ?? '') === 'Updated Deluxe Suite Deluxe', 'Room name updated');
    assertCondition(($updatedRoom['bed_type'] ?? '') === '2 Queen Beds', 'Bed type updated');
    assertCondition((int) ($updatedRoom['max_guests'] ?? 0) === 4, 'Max guests updated');
    assertCondition((float) ($updatedRoom['price_per_night'] ?? 0) === 3800.0, 'Price per night updated');
    assertCondition((int) ($updatedRoom['deposit_percentage'] ?? 0) === 25, 'Deposit percentage updated');
    assertCondition(($updatedRoom['status'] ?? '') === 'blocked', 'Status updated to blocked');
    assertCondition(str_contains($updatedRoom['amenities'] ?? '', 'Bathtub'), 'Amenities updated');
    assertCondition(($updatedRoom['image'] ?? '') === 'https://example.com/updated_photo.jpg', 'Primary image updated');

    // ── Test 6: Photo Removal Workflow ──
    echo "--- Test 6: Room Photo Removal Workflow (Remove Photo sets image to null) ---\n";
    $_POST = [
        '_token' => csrf_token(),
        'name' => 'Suite With Photo Removed',
        'bed_type' => '2 Queen Beds',
        'max_guests' => '4',
        'price_per_night' => '3800',
        'deposit_percentage' => '25',
        'status' => 'available',
        'room_photo_url_0' => '',
        'room_photo_removed_0' => '1',
        'redirect_to' => "/dashboard/hotels/{$hotelId}/rooms",
    ];

    ob_start();
    $controller->updateRoom((string) $roomId);
    $jsonOutputRemove = ob_get_clean();

    $resRemove = json_decode($jsonOutputRemove ?: '', true);
    assertCondition(is_array($resRemove) && ($resRemove['success'] ?? false) === true, 'updateRoom with removal returns JSON success');

    $roomNoPhoto = HotelRoom::find($roomId);
    assertCondition($roomNoPhoto !== null && empty($roomNoPhoto['image']), 'Room image successfully cleared to null on removal');

    // ── Test 7: View Mode Displays Placeholder When Photo is Absent ──
    echo "--- Test 7: Placeholders Rendered in View Mode ---\n";
    ob_start();
    $controller->propertyRooms((string) $hotelId);
    $viewHtml = ob_get_clean();

    assertCondition(str_contains($viewHtml, 'title="No photo uploaded"'), 'Room row displays "No photo uploaded" title when image is null');
    assertCondition(str_contains($viewHtml, 'No photo</span>'), 'Room row displays styled "No photo" badge');

    // Verify hotel context card placeholder when hotel has no cover photo
    Database::run("UPDATE listings SET cover_image = NULL WHERE id = ?", [$hotelId]);
    ob_start();
    $controller->propertyRooms((string) $hotelId);
    $viewHtmlNoHotelCover = ob_get_clean();
    assertCondition(str_contains($viewHtmlNoHotelCover, '🏨</span>') && str_contains($viewHtmlNoHotelCover, 'No photo uploaded</span>'), 'Hotel context card renders "No photo uploaded" placeholder when property has no cover photo');

} finally {
    // Teardown
    if ($cleanupRoomIds !== []) {
        $p = implode(',', array_fill(0, count($cleanupRoomIds), '?'));
        Database::run("DELETE FROM hotel_rooms WHERE id IN ($p)", $cleanupRoomIds);
    }
    if ($cleanupListingIds !== []) {
        $p = implode(',', array_fill(0, count($cleanupListingIds), '?'));
        Database::run("DELETE FROM listings WHERE id IN ($p)", $cleanupListingIds);
    }
    if ($cleanupUserIds !== []) {
        $p = implode(',', array_fill(0, count($cleanupUserIds), '?'));
        Database::run("DELETE FROM users WHERE id IN ($p)", $cleanupUserIds);
    }
    $_POST = [];
}

echo "\n--------------------------------------------------------\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo "--------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
