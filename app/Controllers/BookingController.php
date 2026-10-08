<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\Booking;
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

        // Someone else may have confirmed this exact date while this booking sat
        // unpaid — don't let two tourists hold the same guide on the same day.
        if (Booking::isDateBooked((int) $booking['listing_id'], (string) $booking['booking_date'], (int) $booking['id'])) {
            Booking::updateStatus((int) $booking['id'], 'cancelled');
            flash('error', 'Sorry, this date was just booked by someone else. Please choose another date or another guide.');
            redirect('/listing/' . $booking['listing_slug']);
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

        $promoCode = strtoupper(trim((string) $this->input('promo_code', '')));
        if ($promoCode !== '') {
            $check = PromoCode::validate($promoCode, (float) $booking['total_amount'], (int) Auth::id());
            if (!$check['valid'] || $check['id'] === null) {
                flash_keep_old(['promo_code' => $check['message']], [], '/checkout/' . $booking['id']);
            }
            Booking::applyPromo((int) $booking['id'], (int) $check['id'], $check['discount']);
            PromoCode::incrementUse((int) $check['id']);
            PromoCode::recordRedemption((int) Auth::id(), $promoCode, (int) $booking['id']);
            $booking = $this->ownedBooking((int) $id);
        }

        Payment::create((int) $booking['id'], (float) $booking['total_amount'], $method, 'paid');
        // Paid but not confirmed — the guide or hotel partner must accept it.
        $customer = Auth::user();
        NotificationService::bookingAwaitingConfirmation(
            (string) $booking['customer_email'],
            (string) $booking['listing_title'],
            (string) $booking['booking_date']
        );
        $owner = \App\Models\User::find((int) $booking['guide_id']);
        if ($owner !== null && !empty($owner['email'])) {
            NotificationService::bookingNeedsConfirmation(
                (string) $owner['email'],
                (string) ($owner['name'] ?? 'Partner'),
                (string) $booking['listing_title'],
                (string) ($customer['name'] ?? 'A tourist'),
                (string) $booking['booking_date']
            );
        }

        flash('success', 'Payment received. Your booking is waiting for the partner to confirm it. You can track it under My Bookings.');
        redirect('/bookings');
    }

    public function cancel(string $id): void
    {
        $this->verifyCsrf();
        $booking = $this->ownedBooking((int) $id);
        Booking::updateStatus((int) $booking['id'], 'cancelled');
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
