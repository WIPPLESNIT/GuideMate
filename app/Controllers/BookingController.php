<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\Booking;
use App\Models\HotelRoom;
use App\Models\Listing;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Services\NotificationService;

final class BookingController extends Controller
{
    public function store(string $id): void
    {
        $this->verifyCsrf();
        $listing = Listing::find((int) $id);
        if ($listing === null || $listing['status'] !== 'approved') {
            abort(404, 'Listing not found.');
        }
        if (!Listing::isBookable($listing)) {
            flash('info', 'This listing cannot be booked here. Message the host instead.');
            redirect('/listing/' . $listing['slug']);
        }

        $isHotel = ($listing['category_slug'] ?? '') === 'hotels';

        if ($isHotel) {
            $roomId = (int) $this->input('room_id', 0);
            $room = HotelRoom::find($roomId);
            if ($room === null || (int) $room['listing_id'] !== (int) $listing['id']) {
                flash('error', 'Please choose an available room.');
                redirect('/listing/' . $listing['slug']);
            }

            if ($room['status'] !== HotelRoom::STATUS_AVAILABLE) {
                flash('error', 'This room is currently ' . str_replace('_', ' ', $room['status']) . ' and cannot be booked.');
                redirect('/listing/' . $listing['slug']);
            }

            $checkIn = (string) $this->input('check_in_date', '');
            $checkOut = (string) $this->input('check_out_date', '');
            $guests = max(1, (int) $this->input('guests', 1));
            $notes = (string) $this->input('notes', '');

            $errors = [];
            if ($checkIn === '' || strtotime($checkIn) === false || strtotime($checkIn) < strtotime('today')) {
                $errors['check_in_date'] = 'Please choose a valid check-in date (today or future).';
            }
            if ($checkOut === '' || strtotime($checkOut) === false || strtotime($checkOut) <= strtotime($checkIn)) {
                $errors['check_out_date'] = 'Check-out date must be after check-in date.';
            }

            if ($errors === [] && !HotelRoom::isAvailableForDates($roomId, $checkIn, $checkOut)) {
                $errors['check_in_date'] = 'Sorry, this room is already booked or blocked for these dates.';
            }

            if ($errors !== []) {
                flash_keep_old($errors, [], '/listing/' . $listing['slug']);
            }

            $nights = max(1, (int) round((strtotime($checkOut) - strtotime($checkIn)) / 86400));
            $breakdown = HotelRoom::calculateBreakdown(
                (float) $room['price_per_night'],
                $nights,
                (int) ($room['deposit_percentage'] ?? 20)
            );

            $bookingId = Booking::createHotelReservation(
                (int) $listing['id'],
                $roomId,
                (int) Auth::id(),
                $checkIn,
                $checkOut,
                $nights,
                $guests,
                $breakdown['total_amount'],
                $breakdown['deposit_amount'],
                $breakdown['balance_amount'],
                $notes
            );

            flash('info', 'Room selected! Complete the partial deposit to secure your room.');
            redirect('/checkout/' . $bookingId);
            return;
        }

        // Tour guide booking flow
        $date = (string) $this->input('booking_date', '');
        $guests = max(1, (int) $this->input('guests', 1));
        $notes = (string) $this->input('notes', '');

        $errors = [];
        if ($date === '' || strtotime($date) === false) {
            $errors['booking_date'] = 'Please choose a valid date.';
        } elseif (strtotime($date) < strtotime('today')) {
            $errors['booking_date'] = 'The date cannot be in the past.';
        } elseif (Booking::isDateBooked((int) $listing['id'], $date)) {
            $errors['booking_date'] = 'Sorry, this guide is already booked on that date. Please pick another date — or browse other guides who are still available.';
        }
        if ($errors !== []) {
            flash_keep_old($errors, [], '/listing/' . $listing['slug']);
        }

        $total = Listing::effectivePrice($listing) * $guests;
        $bookingId = Booking::create((int) $listing['id'], (int) Auth::id(), $date, $guests, $total, $notes);

        flash('info', 'Almost there — complete payment. The partner still needs to confirm after you pay.');
        redirect('/checkout/' . $bookingId);
    }

    public function index(): void
    {
        $user = Auth::user();
        $role = (string) ($user['role'] ?? 'tourist');
        if ($role === 'hotel_admin') {
            redirect('/dashboard/bookings');
        } elseif ($role === 'guide') {
            redirect('/dashboard/bookings');
        } elseif ($role === 'rental_provider' || $role === 'rental_admin') {
            redirect('/dashboard/rentals');
        }

        Booking::reconcileOrphanRefunds();
        $bookings = Booking::forCustomer((int) Auth::id());
        foreach ($bookings as &$b) {
            if (($b['payment_status'] ?? '') === 'paid' && in_array($b['status'], ['confirmed', 'completed'], true)) {
                $b['verify_token'] = Booking::ensureVerifyToken((int) $b['id']);
            }
        }
        unset($b);
        $this->view('bookings/index', [
            'title' => 'My Bookings',
            'bookings' => $bookings,
        ]);
    }

    public function map(): void
    {
        $userId = (int) Auth::id();
        Listing::syncCoordinatesForBookings($userId);
        $bookings = Booking::navigableForCustomer($userId);
        $focusId = (int) $this->input('booking', 0);

        $this->view('bookings/map', [
            'title' => 'Trip Map',
            'bookings' => $bookings,
            'focusId' => $focusId,
            'mapillaryToken' => trim((string) (App::config('maps')['mapillary_access_token'] ?? '')),
            'mapboxToken' => trim((string) (App::config('maps')['mapbox_access_token'] ?? '')),
        ]);
    }

    public function checkout(string $id): void
    {
        $booking = $this->ownedBooking((int) $id);
        if ($booking['status'] === 'cancelled') {
            flash('error', 'This booking was cancelled.');
            redirect('/bookings');
        }

        $this->view('bookings/checkout', [
            'title' => 'Checkout',
            'booking' => $booking,
            'payment' => Payment::forBooking((int) $booking['id']),
        ]);
    }

    public function pay(string $id): void
    {
        $this->verifyCsrf();
        $booking = $this->ownedBooking((int) $id);

        $existing = Payment::forBooking((int) $booking['id']);
        if ($existing && $existing['status'] === 'paid') {
            flash('info', 'This booking is already paid.');
            redirect('/bookings');
        }

        $isHotel = !empty($booking['room_id']) || ((float) ($booking['deposit_amount'] ?? 0) > 0);

        if (!$isHotel) {
            // Tour guide collision check
            if (Booking::isDateBooked((int) $booking['listing_id'], (string) $booking['booking_date'], (int) $booking['id'])) {
                Booking::updateStatus((int) $booking['id'], 'cancelled');
                flash('error', 'Sorry, this date was just booked by someone else. Please choose another date or another guide.');
                redirect('/listing/' . $booking['listing_slug']);
            }
        }

        // Simulated payment gateway — validate the card-shaped fields only.
        $method = (string) $this->input('method', 'card');
        $errors = [];
        if ($method === 'card') {
            $number = preg_replace('/\s+/', '', (string) $this->input('card_number', ''));
            if (strlen((string) $number) < 12) {
                $errors['card_number'] = 'Enter a valid (test) card number.';
            }
        }
        if ($errors !== []) {
            flash_keep_old($errors, [], '/checkout/' . $booking['id']);
        }

        $deposit = (float) ($booking['deposit_amount'] ?? 0);
        $total = (float) $booking['total_amount'];

        $promoCode = strtoupper(trim((string) $this->input('promo_code', '')));
        if ($promoCode !== '') {
            $check = PromoCode::validate($promoCode, $total, (int) Auth::id());
            if (!$check['valid'] || $check['id'] === null) {
                flash_keep_old(['promo_code' => $check['message']], [], '/checkout/' . $booking['id']);
            }
            Booking::applyPromo((int) $booking['id'], (int) $check['id'], $check['discount']);
            PromoCode::incrementUse((int) $check['id']);
            PromoCode::recordRedemption((int) Auth::id(), $promoCode, (int) $booking['id']);
            $booking = $this->ownedBooking((int) $id);
        }

        // Charge deposit for hotels, or full total for tours
        $chargeAmount = ($isHotel && $deposit > 0) ? $deposit : (float) $booking['total_amount'];

        Payment::create((int) $booking['id'], $chargeAmount, $method, 'paid');

        if ($isHotel && $deposit > 0) {
            \App\Core\Database::run(
                "UPDATE bookings SET hotel_status = 'deposit_paid', payment_stage = 'deposit_paid' WHERE id = ?",
                [(int) $booking['id']]
            );
        }

        $customer = Auth::user();
        NotificationService::bookingAwaitingConfirmation(
            (string) $booking['customer_email'],
            (string) $booking['listing_title'],
            (string) ($booking['check_in_date'] ?? $booking['booking_date'])
        );
        $owner = \App\Models\User::find((int) $booking['guide_id']);
        if ($owner !== null && !empty($owner['email'])) {
            NotificationService::bookingNeedsConfirmation(
                (string) $owner['email'],
                (string) ($owner['name'] ?? 'Partner'),
                (string) $booking['listing_title'],
                (string) ($customer['name'] ?? 'A tourist'),
                (string) ($booking['check_in_date'] ?? $booking['booking_date'])
            );
        }

        if ($isHotel && $deposit > 0) {
            flash('success', 'Room deposit of ' . money($chargeAmount) . ' received! The hotel partner will confirm your stay. Remaining ' . money($booking['balance_amount'] ?? 0) . ' is payable at check-in.');
        } else {
            flash('success', 'Payment received. Your booking is waiting for the partner to confirm it. You can track it under My Bookings.');
        }
        redirect('/bookings');
    }

    public function cancel(string $id): void
    {
        $this->verifyCsrf();
        $booking = $this->ownedBooking((int) $id);
        Booking::updateStatus((int) $booking['id'], 'cancelled');
        \App\Core\Database::run("UPDATE bookings SET hotel_status = 'cancelled' WHERE id = ?", [(int) $booking['id']]);
        flash('info', 'Booking cancelled.');
        redirect('/bookings');
    }

    /**
     * Fetch a booking and ensure it belongs to the current user.
     *
     * @return array<string, mixed>
     */
    private function ownedBooking(int $id): array
    {
        $booking = Booking::find($id);
        if ($booking === null || (int) $booking['user_id'] !== (int) Auth::id()) {
            abort(404, 'Booking not found.');
        }
        return $booking;
    }
}
