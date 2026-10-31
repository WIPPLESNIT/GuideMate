<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Upload;
use App\Models\Booking;
use App\Models\Category;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Services\NotificationService;
use RuntimeException;

final class HotelController extends Controller
{
    /**
     * My Hotels page - display hotel listings owned by the logged-in hotel partner.
     */
    public function hotels(): void
    {
        $this->ensureHotelPartner();
        $user = Auth::user();
        $userId = (int) Auth::id();
        $isVerified = (string) ($user['guide_status'] ?? 'none') === 'approved';

        $hotels = Listing::forHotelOwner($userId);

        // Attach room counts for each hotel listing
        $hotelIds = array_column($hotels, 'id');
        $roomCounts = [];
        if ($hotelIds !== []) {
            $placeholders = implode(',', array_fill(0, count($hotelIds), '?'));
            $rows = Database::all("SELECT listing_id, COUNT(*) AS cnt FROM hotel_rooms WHERE listing_id IN ({$placeholders}) GROUP BY listing_id", $hotelIds);
            foreach ($rows as $r) {
                $roomCounts[(int) $r['listing_id']] = (int) $r['cnt'];
            }
        }
        foreach ($hotels as &$h) {
            $h['room_count'] = $roomCounts[(int) $h['id']] ?? 0;
        }
        unset($h);

        $this->view('hotel/hotels', [
            'title' => 'My Hotels',
            'hotels' => $hotels,
            'stats' => [
                'total' => count($hotels),
                'approved' => count(array_filter($hotels, fn($h) => ($h['status'] ?? '') === 'approved')),
                'rooms' => count(HotelRoom::forOwner($userId)),
            ],
            'isVerified' => $isVerified,
            'user' => $user,
            'errors' => errors(),
        ]);
    }

    /**
     * Get JSON data for a specific hotel listing (for the edit modal).
     */
    public function getHotel(string $id): void
    {
        $this->ensureHotelPartner();
        $hotel = $this->ownedHotelListing((int) $id);
        if ($hotel === []) {
            return;
        }
        $this->json([
            'success' => true,
            'hotel' => $hotel,
        ]);
    }

    /**
     * Store a new hotel listing.
     */
    public function storeHotel(): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();
        if (!$this->ensureApproved()) {
            return;
        }

        $userId = (int) Auth::id();
        $data = $this->validateHotelData();

        $cover = $this->uploadedCover() ?? $data['cover_image'];
        if ($cover === null || $cover === '') {
            $cover = 'https://images.unsplash.com/photo-1566073771259-6a8506099945?w=800&q=80';
        }

        $cat = Category::findBySlug('hotels');
        $categoryId = $cat ? (int) $cat['id'] : 3;

        $slug = $this->uniqueSlug($data['title']);

        $listingId = Listing::create([
            'user_id' => $userId,
            'category_id' => $categoryId,
            'title' => $data['title'],
            'slug' => $slug,
            'summary' => $data['summary'],
            'description' => $data['description'],
            'area' => $data['area'],
            'address' => $data['address'],
            'price' => $data['price'],
            'price_unit' => 'per night',
            'duration' => $data['duration'],
            'included' => $data['included'],
            'not_included' => $data['not_included'],
            'cover_image' => $cover,
            'status' => 'pending',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        if ($cover !== null && $cover !== '') {
            Listing::addImage($listingId, $cover, 1);
        }

        if ($this->isJson()) {
            unset($_SESSION['_flash']['success']);
            $this->json([
                'success' => true,
                'message' => "Hotel property \"{$data['title']}\" submitted successfully and is now pending administrator review.",
                'listing_id' => $listingId,
                'redirect' => url('/dashboard/hotels'),
            ]);
            return;
        }

        flash('success', "Hotel property \"{$data['title']}\" submitted successfully and is now pending administrator review.");
        redirect('/dashboard/hotels');
    }

    /**
     * Update an existing hotel listing.
     */
    public function updateHotel(string $id): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();
        if (!$this->ensureApproved()) {
            return;
        }

        $listingId = (int) $id;
        $hotel = $this->ownedHotelListing($listingId);
        if ($hotel === []) {
            return;
        }
        if ($hotel === []) {
            return;
        }
        $data = $this->validateHotelData(true);

        $cover = $this->uploadedCover() ?? $data['cover_image'];
        if ($cover === null || $cover === '') {
            $cover = $hotel['cover_image'];
        }

        $cat = Category::findBySlug('hotels');
        $categoryId = $cat ? (int) $cat['id'] : 3;

        Listing::update($listingId, [
            'category_id' => $categoryId,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'description' => $data['description'],
            'area' => $data['area'],
            'address' => $data['address'],
            'price' => $data['price'],
            'price_unit' => 'per night',
            'duration' => $data['duration'],
            'included' => $data['included'],
            'not_included' => $data['not_included'],
            'cover_image' => $cover,
        ]);

        if (($hotel['status'] ?? '') === 'rejected') {
            Listing::resubmitHotel($listingId);
        }

        if ($this->isJson()) {
            unset($_SESSION['_flash']['success']);
            $this->json([
                'success' => true,
                'message' => "Hotel property \"{$data['title']}\" updated successfully!",
                'redirect' => url('/dashboard/hotels'),
            ]);
            return;
        }

        flash('success', "Hotel property \"{$data['title']}\" updated successfully!");
        redirect('/dashboard/hotels');
    }

    /**
     * Resubmit a rejected hotel listing for administrator review.
     */
    public function resubmitHotel(string $id): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();
        $hotel = $this->ownedHotelListing((int) $id);
        if ($hotel === []) {
            return;
        }

        Listing::resubmitHotel((int) $id);
        flash('success', "Hotel property \"{$hotel['title']}\" resubmitted for administrator review.");
        redirect('/dashboard/hotels');
    }

    /**
     * Delete a hotel listing owned by this hotel partner.
     */
    public function destroyHotel(string $id): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();
        if (!$this->ensureApproved()) {
            return;
        }

        $listingId = (int) $id;
        $hotel = $this->ownedHotelListing($listingId);
        if ($hotel === []) {
            return;
        }

        // Clean up any rooms associated with this hotel
        Database::run('DELETE FROM hotel_rooms WHERE listing_id = ?', [$listingId]);
        Listing::delete($listingId);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => "Hotel property \"{$hotel['title']}\" removed successfully.",
            ]);
            return;
        }

        flash('info', "Hotel property \"{$hotel['title']}\" removed successfully.");
        redirect('/dashboard/hotels');
    }
    /**
     * Update room status manually: available, unavailable, blocked, fully_booked
     */
    public function updateRoomStatus(string $id): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();

        $roomId = (int) $id;
        $room = HotelRoom::find($roomId);
        if ($room === null) {
            abort(404, 'Room not found.');
        }

        // Verify ownership
        $listing = Listing::find((int) $room['listing_id']);
        if ($listing === null || (int) $listing['user_id'] !== (int) Auth::id()) {
            abort(403, 'You do not own this hotel listing.');
        }

        $newStatus = trim((string) $this->input('status', ''));
        $allowed = [
            HotelRoom::STATUS_AVAILABLE,
            HotelRoom::STATUS_UNAVAILABLE,
            HotelRoom::STATUS_BLOCKED,
            HotelRoom::STATUS_FULLY_BOOKED,
        ];

        if (!in_array($newStatus, $allowed, true)) {
            flash('error', 'Invalid room status.');
            redirect('/dashboard');
            return;
        }

        HotelRoom::updateStatus($roomId, $newStatus);

        $labels = [
            HotelRoom::STATUS_AVAILABLE => 'marked as Available',
            HotelRoom::STATUS_UNAVAILABLE => 'marked as Unavailable',
            HotelRoom::STATUS_BLOCKED => 'marked as Blocked (Maintenance)',
            HotelRoom::STATUS_FULLY_BOOKED => 'marked as Fully Booked',
        ];

        $label = $labels[$newStatus] ?? 'updated';

        if ($this->isJson()) {
            unset($_SESSION['_flash']['success']);
            $this->json([
                'success' => true,
                'room_id' => $roomId,
                'status' => $newStatus,
                'message' => "Room \"{$room['name']}\" was successfully {$label}.",
            ]);
            return;
        }

        flash('success', "Room \"{$room['name']}\" was successfully {$label}.");

        $fallback = '/dashboard/hotels/' . (int) $room['listing_id'] . '/rooms';
        redirect($this->safeRedirectUrl((string) $this->input('redirect_to', ''), $fallback));
    }

    /**
     * Create a new room type for a hotel listing
     */
    public function createRoom(): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();
        if (!$this->ensureApproved()) {
            return;
        }

        $listingId = (int) $this->input('listing_id', 0);
        $listing = Listing::find($listingId);
        if ($listing === null || (int) $listing['user_id'] !== (int) Auth::id()) {
            abort(403, 'Unauthorized.');
        }

        // Requirement 6: Rooms can only be added to approved hotels
        if (($listing['status'] ?? '') !== 'approved') {
            $statusLabel = Listing::hotelStatusLabel((string) ($listing['status'] ?? 'pending'));
            $errMsg = "Rooms can only be added after this hotel property has been approved by an administrator. This property is currently {$statusLabel}.";
            if ($this->isJson()) {
                $this->json(['error' => $errMsg], 403);
                return;
            }
            flash('error', $errMsg);
            $fallback = '/dashboard/hotels/' . $listingId . '/rooms';
            redirect($this->safeRedirectUrl((string) $this->input('redirect_to', ''), $fallback));
            return;
        }

        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            flash('error', 'Room name is required.');
            $fallback = '/dashboard/hotels/' . $listingId . '/rooms';
            redirect($this->safeRedirectUrl((string) $this->input('redirect_to', ''), $fallback));
            return;
        }

        $price = max(0.0, (float) $this->input('price_per_night', 0));
        $depositPct = max(5, min(100, (int) $this->input('deposit_percentage', 20)));

        HotelRoom::create([
            'listing_id' => $listingId,
            'name' => $name,
            'description' => trim((string) $this->input('description', '')),
            'room_size' => trim((string) $this->input('room_size', '32 m²')),
            'bed_type' => trim((string) $this->input('bed_type', '1 King Bed')),
            'max_guests' => max(1, (int) $this->input('max_guests', 2)),
            'price_per_night' => $price,
            'deposit_percentage' => $depositPct,
            'status' => (string) $this->input('status', HotelRoom::STATUS_AVAILABLE),
            'amenities' => trim((string) $this->input('amenities', '')),
            'image' => trim((string) $this->input('image', '')) ?: $listing['cover_image'],
        ]);

        if ($this->isJson()) {
            unset($_SESSION['_flash']['success']);
            $this->json([
                'success' => true,
                'message' => "New room type \"{$name}\" created successfully.",
            ]);
            return;
        }

        flash('success', "New room type \"{$name}\" created successfully.");

        $fallback = '/dashboard/hotels/' . $listingId . '/rooms';
        redirect($this->safeRedirectUrl((string) $this->input('redirect_to', ''), $fallback));
    }

    /**
     * Update room details
     */
    public function updateRoom(string $id): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();
        if (!$this->ensureApproved()) {
            return;
        }

        $roomId = (int) $id;
        $room = HotelRoom::find($roomId);
        if ($room === null) {
            abort(404, 'Room not found.');
        }

        $listing = Listing::find((int) $room['listing_id']);
        if ($listing === null || (int) $listing['user_id'] !== (int) Auth::id()) {
            abort(403, 'Unauthorized.');
        }

        $name = trim((string) $this->input('name', $room['name']));
        if ($name === '') {
            flash('error', 'Room name cannot be empty.');
            $fallback = '/dashboard/hotels/' . (int) $room['listing_id'] . '/rooms';
            redirect($this->safeRedirectUrl((string) $this->input('redirect_to', ''), $fallback));
            return;
        }

        HotelRoom::update($roomId, [
            'name' => $name,
            'description' => trim((string) $this->input('description', $room['description'] ?? '')),
            'room_size' => trim((string) $this->input('room_size', $room['room_size'] ?? '32 m²')),
            'bed_type' => trim((string) $this->input('bed_type', $room['bed_type'] ?? '1 King Bed')),
            'max_guests' => max(1, (int) $this->input('max_guests', $room['max_guests'] ?? 2)),
            'price_per_night' => max(0.0, (float) $this->input('price_per_night', $room['price_per_night'])),
            'deposit_percentage' => max(5, min(100, (int) $this->input('deposit_percentage', $room['deposit_percentage'] ?? 20))),
            'status' => (string) $this->input('status', $room['status']),
            'amenities' => trim((string) $this->input('amenities', $room['amenities'] ?? '')),
            'image' => trim((string) $this->input('image', '')) ?: null,
        ]);

        flash('success', "Room \"{$name}\" updated successfully.");

        $fallback = '/dashboard/hotels/' . (int) $room['listing_id'] . '/rooms';
        redirect($this->safeRedirectUrl((string) $this->input('redirect_to', ''), $fallback));
    }

    /**
     * Delete a room type from a hotel property.
     */
    public function destroyRoom(string $id): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();
        if (!$this->ensureApproved()) {
            return;
        }

        $roomId = (int) $id;
        $room = HotelRoom::find($roomId);
        if ($room === null) {
            abort(404, 'Room not found.');
        }

        $listing = Listing::find((int) $room['listing_id']);
        if ($listing === null || (int) $listing['user_id'] !== (int) Auth::id()) {
            abort(403, 'Unauthorized.');
        }

        $roomName = (string) ($room['name'] ?? 'Room');
        $listingId = (int) $room['listing_id'];
        HotelRoom::delete($roomId);

        if ($this->isJson()) {
            unset($_SESSION['_flash']['success']);
            $this->json([
                'success' => true,
                'message' => "Room \"{$roomName}\" deleted successfully.",
            ]);
            return;
        }

        flash('success', "Room \"{$roomName}\" deleted successfully.");

        $fallback = '/dashboard/hotels/' . $listingId . '/rooms';
        redirect($this->safeRedirectUrl((string) $this->input('redirect_to', ''), $fallback));
    }

    /**
     * Dedicated property rooms page - display room types for a specific hotel property.
     */
    public function propertyRooms(string $id): void
    {
        $this->ensureHotelPartner();
        $hotelId = (int) $id;
        $hotel = $this->ownedHotelListing($hotelId);
        if ($hotel === []) {
            abort(404, 'Hotel property not found.');
        }

        $user = Auth::user();
        $isVerified = (string) ($user['guide_status'] ?? 'none') === 'approved';
        $rooms = HotelRoom::forListing($hotelId);

        $this->view('hotel/rooms', [
            'title' => ($hotel['title'] ?? 'Hotel') . ' - Rooms',
            'hotel' => $hotel,
            'rooms' => $rooms,
            'isVerified' => $isVerified,
            'user' => $user,
            'errors' => errors(),
        ]);
    }

    /**
     * Transition a hotel reservation through its lifecycle states:
     * - confirm (deposit verified, room held)
     * - check_in (guest checked in at front desk, balance is due)
     * - settle_balance (guest paid balance at front desk, checkout complete)
     * - no_show (guest failed to arrive, deposit forfeited)
     * - cancel (reservation cancelled)
     */
    public function updateReservationStatus(string $id): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();

        $bookingId = (int) $id;
        $booking = Booking::find($bookingId);
        if ($booking === null) {
            abort(404, 'Reservation not found.');
        }

        // Verify the listing belongs to this partner
        $listing = Listing::find((int) $booking['listing_id']);
        if ($listing === null || (int) $listing['user_id'] !== (int) Auth::id()) {
            abort(403, 'Unauthorized.');
        }

        $action = trim((string) $this->input('action', ''));
        $redirectTo = trim((string) $this->input('redirect_to', '/dashboard#reservations'));
        $db = \App\Core\Database::connection();

        $balance = (float) ($booking['balance_amount'] ?? 0);
        $deposit = (float) ($booking['deposit_amount'] ?? 0);

        switch ($action) {
            case 'accept':
                $db->prepare("UPDATE bookings SET hotel_status = 'accepted' WHERE id = ?")->execute([$bookingId]);
                flash('success', "Reservation #{$bookingId} accepted successfully! Awaiting guest deposit verification.");
                break;

            case 'confirm_deposit':
            case 'confirm':
                $db->prepare("UPDATE bookings SET hotel_status = 'confirmed', status = 'confirmed', payment_stage = 'balance_due' WHERE id = ?")->execute([$bookingId]);
                // Ensure verified deposit payment record exists
                $existingDepositPayment = \App\Core\Database::first("SELECT id FROM payments WHERE booking_id = ? AND status = 'paid' LIMIT 1", [$bookingId]);
                if ($existingDepositPayment === null && $deposit > 0) {
                    Payment::create($bookingId, $deposit, 'deposit_verified', 'paid');
                }
                Booking::ensureVerifyToken($bookingId);
                flash('success', "Deposit verified! Reservation #{$bookingId} is now Confirmed. Remaining balance of " . money($balance) . " is payable at the front desk upon check-in.");
                break;

            case 'check_in':
                $db->prepare("UPDATE bookings SET hotel_status = 'checked_in', payment_stage = 'balance_due' WHERE id = ?")->execute([$bookingId]);
                flash('success', "Guest checked in! Remaining balance of " . money($balance) . " is due at front desk.");
                break;

            case 'settle_balance':
                // Record the remaining front desk payment if not already recorded
                if ($balance > 0) {
                    Payment::create($bookingId, $balance, 'front_desk_cash', 'paid');
                }
                $db->prepare("UPDATE bookings SET hotel_status = 'completed', payment_stage = 'settled', status = 'completed', unit_completed_at = NOW() WHERE id = ?")->execute([$bookingId]);
                flash('success', "Front desk balance settled! Reservation #{$bookingId} marked completed.");
                break;

            case 'no_show':
                $db->prepare("UPDATE bookings SET hotel_status = 'no_show', status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
                flash('warning', "Reservation #{$bookingId} marked as No-Show. Room returned to availability.");
                break;

            case 'cancel':
                $db->prepare("UPDATE bookings SET hotel_status = 'cancelled', status = 'cancelled' WHERE id = ?")->execute([$bookingId]);
                flash('info', "Reservation #{$bookingId} cancelled.");
                break;

            default:
                flash('error', 'Unknown reservation action.');
                break;
        }

        redirect($redirectTo === '/dashboard/bookings' ? '/dashboard/bookings' : '/dashboard#reservations');
    }

    private function ensureHotelPartner(): void
    {
        $user = Auth::user();
        if ($user === null || !in_array((string) ($user['role'] ?? ''), ['hotel_provider', 'hotel_admin', 'admin'], true)) {
            abort(403, 'Hotel partners only.');
        }
    }

    /**
     * Ensure the hotel partner is approved/verified.
     */
    private function ensureApproved(): bool
    {
        $user = Auth::user();
        $status = (string) ($user['guide_status'] ?? 'none');
        if ($status === 'approved') {
            return true;
        }

        if ($this->isJson()) {
            $this->json([
                'error' => 'Account verification is required before managing hotel room inventory.',
                'guide_status' => $status,
                'is_verified' => false,
                'redirect' => url('/documents'),
            ], 403);
            return false;
        }

        flash('error', 'Account verification is required before managing hotel room inventory.');
        redirect('/dashboard#inventory');
        return false;
    }

    /**
     * Validate hotel creation / editing inputs.
     *
     * @return array<string, mixed>
     */
    private function validateHotelData(bool $isEdit = false): array
    {
        $errors = $this->requireFields(['title', 'area', 'description']);

        $title = trim((string) $this->input('title', ''));
        $area = trim((string) $this->input('area', ''));
        $description = trim((string) $this->input('description', ''));
        $address = trim((string) $this->input('address', ''));
        $summary = trim((string) $this->input('summary', ''));
        $duration = trim((string) $this->input('duration', 'Check-in 2:00 PM / Check-out 12:00 PM'));
        $included = trim((string) $this->input('included', ''));
        $notIncluded = trim((string) $this->input('not_included', ''));

        $price = (float) $this->input('price', 0);
        if ($price <= 0) {
            $errors['price'] = 'Please enter a valid starting nightly rate.';
        }

        $coverUrl = trim((string) $this->input('cover_image', ''));
        if ($coverUrl !== '' && !filter_var($coverUrl, FILTER_VALIDATE_URL)) {
            $errors['cover_image'] = 'Cover image must be a valid URL.';
        }

        if ($errors !== []) {
            if ($this->isJson()) {
                $this->json([
                    'success' => false,
                    'error' => implode(' ', $errors),
                    'errors' => $errors,
                ], 422);
                exit;
            }
            flash_keep_old($errors, $_POST, '/dashboard/hotels');
        }

        return [
            'title' => $title,
            'area' => $area,
            'address' => $address,
            'summary' => $summary,
            'description' => $description,
            'price' => $price,
            'duration' => $duration,
            'included' => $included,
            'not_included' => $notIncluded,
            'cover_image' => $coverUrl !== '' ? $coverUrl : null,
        ];
    }

    /**
     * Retrieve an owned hotel listing, aborting 403/404 if unauthorized.
     *
     * @return array<string, mixed>
     */
    private function ownedHotelListing(int $id): array
    {
        $listing = Listing::find($id);
        if ($listing === null) {
            if ($this->isJson()) {
                $this->json(['error' => 'Hotel property not found.'], 404);
                return [];
            }
            abort(404, 'Hotel property not found.');
        }

        if ((int) $listing['user_id'] !== (int) Auth::id()) {
            if ($this->isJson()) {
                $this->json(['error' => 'Unauthorized. You do not own this hotel property.'], 403);
                return [];
            }
            abort(403, 'Unauthorized. You do not own this hotel property.');
        }

        if (($listing['category_slug'] ?? '') !== 'hotels') {
            if ($this->isJson()) {
                $this->json(['error' => 'This listing is not a hotel property.'], 400);
                return [];
            }
            abort(400, 'This listing is not a hotel property.');
        }

        return $listing;
    }

    /**
     * Upload cover photo for hotel.
     */
    private function uploadedCover(): ?string
    {
        if (!Upload::present($_FILES['cover_file'] ?? null)) {
            return null;
        }
        try {
            return Upload::store($_FILES['cover_file'], 'listings/' . Auth::id(), ['jpg', 'jpeg', 'png', 'webp']);
        } catch (RuntimeException $e) {
            if ($this->isJson()) {
                $this->json(['success' => false, 'error' => $e->getMessage()], 422);
                exit;
            }
            flash_keep_old(['cover_file' => $e->getMessage()], $_POST, '/dashboard/hotels');
        }
        return null;
    }

    /**
     * Generate unique slug for hotel listing.
     */
    private function uniqueSlug(string $title): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? '', '-');
        if ($base === '') {
            $base = 'hotel';
        }
        $slug = $base;
        $i = 2;
        while (Listing::slugExists($slug)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Resolve a safe redirect path, preserving sub-route destination and stripping base path if present.
     */
    private function safeRedirectUrl(string $redirectUrl, string $fallback): string
    {
        if ($redirectUrl === '') {
            return $fallback;
        }
        $base = \App\Core\App::basePath();
        $path = ($base !== '' && str_starts_with($redirectUrl, $base))
            ? substr($redirectUrl, strlen($base))
            : $redirectUrl;
        if (str_starts_with($path, '/dashboard/hotels') || str_starts_with($path, '/dashboard')) {
            return $path;
        }
        return $fallback;
    }
}
