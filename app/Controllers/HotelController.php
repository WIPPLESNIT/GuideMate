<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Booking;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use App\Services\NotificationService;

final class HotelController extends Controller
{
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
        flash('success', "Room \"{$room['name']}\" was successfully {$label}.");

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'room_id' => $roomId,
                'status' => $newStatus,
                'message' => "Room {$label}.",
            ]);
            return;
        }

        redirect('/dashboard#inventory');
    }

    /**
     * Create a new room type for a hotel listing
     */
    public function createRoom(): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();

        $listingId = (int) $this->input('listing_id', 0);
        $listing = Listing::find($listingId);
        if ($listing === null || (int) $listing['user_id'] !== (int) Auth::id()) {
            abort(403, 'Unauthorized.');
        }

        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            flash('error', 'Room name is required.');
            redirect('/dashboard#inventory');
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

        flash('success', "New room type \"{$name}\" created successfully.");
        redirect('/dashboard#inventory');
    }

    /**
     * Update room details
     */
    public function updateRoom(string $id): void
    {
        $this->verifyCsrf();
        $this->ensureHotelPartner();

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
            redirect('/dashboard#inventory');
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
        redirect('/dashboard#inventory');
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
        $db = \App\Core\Database::connection();

        switch ($action) {
            case 'confirm':
                $db->prepare("UPDATE bookings SET hotel_status = 'confirmed', status = 'confirmed' WHERE id = ?")->execute([$bookingId]);
                flash('success', "Reservation #{$bookingId} confirmed. Room is secured for guest.");
                break;

            case 'check_in':
                $db->prepare("UPDATE bookings SET hotel_status = 'checked_in', payment_stage = 'balance_due' WHERE id = ?")->execute([$bookingId]);
                flash('success', "Guest checked in! Remaining balance of " . money($booking['balance_amount'] ?? 0) . " is due at front desk.");
                break;

            case 'settle_balance':
                // Record the remaining front desk payment if not already recorded
                $balance = (float) ($booking['balance_amount'] ?? 0);
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

        redirect('/dashboard#reservations');
    }

    private function ensureHotelPartner(): void
    {
        $user = Auth::user();
        if ($user === null || !in_array((string) ($user['role'] ?? ''), ['hotel_admin', 'admin'], true)) {
            abort(403, 'Hotel partners only.');
        }
    }
}
