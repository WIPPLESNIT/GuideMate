<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\HandlesGuideDocuments;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Upload;
use App\Models\Booking;
use App\Models\Category;
use App\Models\GuideAvailability;
use App\Models\GuideDocument;
use App\Models\Listing;
use App\Models\ListingSchedule;
use App\Models\User;
use App\Services\NotificationService;
use RuntimeException;

final class GuideController extends Controller
{
    use HandlesGuideDocuments;

    public function listings(): void
    {
        if (Auth::hasRole('hotel_provider') || Auth::hasRole('hotel_admin')) {
            redirect('/dashboard/hotels');
            return;
        }

        $user = Auth::user();
        $this->view('guide/listings', [
            'title' => 'My Listings',
            'listings' => Listing::forGuideOwner((int) Auth::id()),
            // For the inline "Add New Listing" pop-up modal.
            'categories' => Category::forGuides(),
            'canCreate' => (string) ($user['guide_status'] ?? 'none') === 'approved',
            'errors' => errors(),
        ]);
    }

    public function create(): void
    {
        if (Auth::hasRole('hotel_provider') || Auth::hasRole('hotel_admin')) {
            redirect('/dashboard/hotels?create=1');
            return;
        }

        if (!$this->ensureApproved()) {
            return;
        }
        $this->view('guide/form', [
            'title' => 'Create Listing',
            'listing' => null,
            'categories' => Category::forGuides(),
            'errors' => errors(),
        ]);
    }

    public function store(): void
    {
        if (!$this->ensureApproved()) {
            return;
        }
        $this->verifyCsrf();
        $data = $this->validateListing();
        if ($data === []) {
            return;
        }

        // Resolve up to 3 photos (Photo 1 Cover, Photo 2 & 3 Gallery)
        $photos = $this->resolveGuidePhotos();
        $cover = $photos[0] ?? $this->uploadedCover() ?? $data['cover_image'] ?? 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80';
        if ($photos === [] && !empty($cover)) {
            $photos = [$cover];
        }

        $slug = $this->uniqueSlug($data['title']);
        $listingId = Listing::create([
            'user_id' => (int) Auth::id(),
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'slug' => $slug,
            'summary' => $data['summary'],
            'description' => $data['description'],
            'area' => $data['area'],
            'address' => $data['address'],
            'price' => $data['price'],
            'price_unit' => $data['price_unit'],
            'duration' => $data['duration'],
            'capacity' => $data['capacity'],
            'included' => $data['included'],
            'not_included' => $data['not_included'],
            'cover_image' => $cover,
            'status' => 'pending',
        ]);

        Listing::syncImages($listingId, $photos);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => 'Listing submitted! It will appear publicly once an admin approves it.',
                'listing' => Listing::find($listingId),
            ]);
            return;
        }

        flash('success', 'Listing submitted! It will appear publicly once an admin approves it.');
        redirect('/dashboard/listings');
    }

    public function edit(string $id): void
    {
        if (Auth::hasRole('hotel_provider') || Auth::hasRole('hotel_admin')) {
            redirect('/dashboard/hotels');
            return;
        }

        if (!$this->ensureApproved()) {
            return;
        }
        $listing = $this->ownedListing((int) $id);
        if (($listing['category_slug'] ?? '') === 'packages') {
            redirect('/dashboard/packages/' . (int) $id . '/edit');
            return;
        }
        if (($listing['category_slug'] ?? '') === 'hotels') {
            flash('error', 'Hotel listings are managed from My Hotels.');
            redirect('/dashboard/hotels');
            return;
        }
        if ($this->isJson()) {
            $listing['photos'] = Listing::getPhotos((int) $id);
            $this->json([
                'success' => true,
                'listing' => $listing,
            ]);
            return;
        }
        $this->view('guide/form', [
            'title' => 'Edit Listing',
            'listing' => $listing,
            'categories' => Category::forGuides(),
            'errors' => errors(),
        ]);
    }

    public function update(string $id): void
    {
        if (!$this->ensureApproved()) {
            return;
        }
        $this->verifyCsrf();
        $listing = $this->ownedListing((int) $id);
        $data = $this->validateListing();
        if ($data === []) {
            return;
        }

        // Resolve up to 3 photos (Photo 1 Cover, Photo 2 & 3 Gallery)
        $photos = $this->resolveGuidePhotos((int) $listing['id']);
        $cover = $photos[0] ?? $this->uploadedCover() ?? (string) ($listing['cover_image'] ?? '') ?: 'https://images.unsplash.com/photo-1544620347-c4fd4a3d5957?w=800&q=80';
        if ($photos === [] && !empty($cover)) {
            $photos = [$cover];
        }

        Listing::update((int) $listing['id'], [
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'summary' => $data['summary'],
            'description' => $data['description'],
            'area' => $data['area'],
            'address' => $data['address'],
            'price' => $data['price'],
            'price_unit' => $data['price_unit'],
            'duration' => $data['duration'],
            'capacity' => $data['capacity'],
            'included' => $data['included'],
            'not_included' => $data['not_included'],
            'cover_image' => $cover,
        ]);

        Listing::syncImages((int) $listing['id'], $photos);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => 'Listing updated successfully.',
                'listing' => Listing::find((int) $listing['id']),
            ]);
            return;
        }

        flash('success', 'Listing updated successfully.');
        redirect('/dashboard/listings');
    }

    public function destroy(string $id): void
    {
        if (!$this->ensureApproved()) {
            return;
        }
        $this->verifyCsrf();
        $listing = $this->ownedListing((int) $id);
        Listing::delete((int) $listing['id']);
        flash('info', 'Listing deleted.');
        redirect('/dashboard/listings');
    }

    /**
     * Verification status page: shows the guide's submitted documents and
     * lets them re-submit if their application was rejected (or still pending).
     */
    public function verification(): void
    {
        redirect('/documents');
    }

    public function submitVerification(): void
    {
        (new DocumentController())->upload();
    }

    /**
     * Check verification status dynamically for modal and listing-creation gating.
     */
    public function verificationStatus(): void
    {
        $user = Auth::user();
        if ($user === null) {
            $this->json(['authenticated' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $userId = (int) $user['id'];
        $fresh = User::find($userId);
        $status = (string) ($fresh['guide_status'] ?? $user['guide_status'] ?? 'none');
        $isVerified = $status === 'approved';

        $role = (string) ($user['role'] ?? 'guide');
        $verificationUrl = url('/documents');

        $message = match ($status) {
            'approved' => 'Your account is verified. You can publish listings.',
            'rejected' => 'Your application was not approved. Please review administrator notes and re-submit your documents.',
            'pending' => 'An administrator is verifying your account documents. You can publish listings once approved.',
            default => 'Account verification is required before creating a listing. Please submit your verification documents.',
        };

        $this->json([
            'authenticated' => true,
            'user_id' => $userId,
            'role' => $role,
            'guide_status' => $status,
            'is_verified' => $isVerified,
            'verification_url' => $verificationUrl,
            'message' => $message,
            'admin_note' => (string) ($fresh['guide_review_note'] ?? ''),
        ]);
    }

    /**
     * Guides can only manage listings once an admin has approved them.
     */
    private function ensureApproved(): bool
    {
        $status = (string) (Auth::user()['guide_status'] ?? 'none');
        if ($status === 'approved') {
            return true;
        }

        if ($this->isJson()) {
            $this->json([
                'error' => 'Account verification is required before creating a listing.',
                'guide_status' => $status,
                'is_verified' => false,
                'redirect' => url('/documents'),
            ], 403);
            return false;
        }

        flash('info', $status === 'rejected'
            ? 'Your guide application was not approved. Please review the notes and re-submit your documents.'
            : 'Your guide application is under review. You can publish listings once an admin approves it.');
        redirect('/documents');
        return false;
    }

    /**
     * Hotel partners list stays for discovery and reply to inquiries.
     * Paid booking, schedule, and availability tools are for tour guides only.
     */
    private function rejectHotelPaidTools(): void
    {
        // Hotel partners manage inventory and reservations directly from their dashboard.
    }

    public function bookings(): void
    {
        Booking::reconcileOrphanRefunds();
        Auth::refreshUser();
        $user = Auth::user();
        $isHotel = in_array($user['role'] ?? '', ['hotel_provider', 'hotel_admin'], true);
        $this->view('guide/bookings', [
            'title' => $isHotel ? 'Hotel Reservations' : 'Bookings Received',
            'bookings' => Booking::forGuide((int) Auth::id()),
            'isWarned' => User::isGuideWarned((int) Auth::id()),
            'warningNote' => (string) ($user['guide_warning_note'] ?? ''),
            'isHotel' => $isHotel,
        ]);
    }

    public function updateBookingStatus(string $id): void
    {
        $this->verifyCsrf();
        Auth::refreshUser();
        if (User::isGuideWarned((int) Auth::id())) {
            $msg = 'Your account has an active administrator warning. Booking actions (Confirm, Complete, and Cancel) are restricted until an administrator clears this warning.';
            if ($this->isJson() || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
                $this->json(['error' => $msg, 'restricted' => true], 403);
                return;
            }
            flash('error', $msg);
            redirect('/dashboard/bookings');
            return;
        }
        $booking = Booking::find((int) $id);
        if ($booking === null || (int) $booking['guide_id'] !== (int) Auth::id()) {
            abort(404, 'Booking not found.');
        }

        $status = (string) $this->input('status', '');
        $current = (string) $booking['status'];

        if ($status === 'cancelled') {
            if (in_array($current, ['cancelled', 'completed', 'refunded', 'disputed'], true)) {
                flash('error', 'This booking can no longer be cancelled.');
                redirect('/dashboard/bookings');
            }
            $result = Booking::cancelByGuide((int) $booking['id']);
            if ($result['blocked'] ?? false) {
                flash('error', 'Your account has an active administrator warning. You cannot cancel or refund bookings until an admin clears it.');
                redirect('/dashboard/bookings');
            }
            \App\Services\PackageBookingService::onBookingUpdated((int) $booking['id'], 'cancelled', (int) Auth::id());
            if ($result['refunded']) {
                NotificationService::bookingCancelled(
                    (string) $booking['customer_email'],
                    (string) $booking['listing_title'],
                    money($result['amount']) . ' has been refunded.'
                );
                flash('success', 'Booking cancelled. ' . money($result['amount']) . ' has been refunded to the tourist.');
            } else {
                flash('success', 'Booking cancelled.');
            }
            redirect('/dashboard/bookings');
        }

        if (in_array($status, ['confirmed', 'completed'], true)) {
            if (in_array($current, ['cancelled', 'refunded', 'disputed'], true)) {
                flash('error', 'This booking can no longer be updated.');
                redirect('/dashboard/bookings');
            }
            if ($status === 'completed' && !in_array($current, ['confirmed', 'completed'], true) && ($booking['package_overall_status'] ?? '') !== 'confirmed') {
                flash('error', 'Confirm this booking first before marking it completed.');
                redirect('/dashboard/bookings');
            }
            Booking::updateStatus((int) $booking['id'], $status);
            \App\Services\PackageBookingService::onBookingUpdated((int) $booking['id'], $status, (int) Auth::id());
            if ($status === 'confirmed') {
                Booking::ensureVerifyToken((int) $booking['id']);
                NotificationService::bookingConfirmed(
                    (string) $booking['customer_email'],
                    (string) $booking['listing_title'],
                    (string) $booking['booking_date']
                );
                flash('success', 'Booking confirmed. The tourist has been notified.');
            } else {
                flash('success', 'Booking marked as completed.');
            }
        }

        redirect('/dashboard/bookings');
    }

    public function deleteBooking(string $id): void
    {
        $this->verifyCsrf();
        Auth::refreshUser();
        $user = Auth::user();
        $userId = (int) ($user['id'] ?? 0);
        $booking = Booking::find((int) $id);

        $isJson = $this->isJson() 
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if ($booking === null) {
            if ($isJson) {
                $this->json(['success' => false, 'error' => 'Booking not found.'], 404);
                return;
            }
            flash('error', 'Booking not found.');
            redirect('/dashboard/bookings');
            return;
        }

        $isGuideOwner = (int) ($booking['guide_id'] ?? 0) === $userId;
        $isAdmin = in_array($user['role'] ?? '', ['admin'], true);
        if (!$isGuideOwner && !$isAdmin) {
            $listing = \App\Core\Database::first('SELECT user_id FROM listings WHERE id = ?', [(int) $booking['listing_id']]);
            if (!$listing || (int) $listing['user_id'] !== $userId) {
                if ($isJson) {
                    $this->json(['success' => false, 'error' => 'You are not authorized to delete this booking.'], 403);
                    return;
                }
                flash('error', 'You are not authorized to delete this booking.');
                redirect('/dashboard/bookings');
                return;
            }
        }

        $status = (string) ($booking['status'] ?? '');
        $overallPkgStatus = (string) ($booking['package_overall_status'] ?? '');
        $hotelStatus = (string) ($booking['hotel_status'] ?? $status);
        $paymentStage = (string) ($booking['payment_stage'] ?? '');

        $isCompleted = ($status === 'completed' || $overallPkgStatus === 'completed' || $hotelStatus === 'completed' || $paymentStage === 'settled');

        if (!$isCompleted) {
            if ($isJson) {
                $this->json(['success' => false, 'error' => 'Only completed records can be deleted.'], 400);
                return;
            }
            flash('error', 'Only completed records can be deleted.');
            redirect('/dashboard/bookings');
            return;
        }

        Booking::delete((int) $booking['id']);

        if ($isJson) {
            $this->json(['success' => true, 'message' => 'Record deleted successfully.', 'id' => (int) $booking['id']]);
            return;
        }
        flash('success', 'Booking record deleted successfully.');
        redirect('/dashboard/bookings');
    }

    public function showVerifyBooking(): void
    {
        $this->rejectHotelPaidTools();
        Auth::refreshUser();
        $user = Auth::user();
        $this->view('guide/verify', [
            'title' => 'Verify booking',
            'errors' => errors(),
            'isWarned' => User::isGuideWarned((int) Auth::id()),
            'warningNote' => (string) ($user['guide_warning_note'] ?? ''),
        ]);
    }

    /**
     * Transactions list — bookings + payments for this provider's listings,
     * including transactions made from the mobile app.
     */
    public function transactions(): void
    {
        $this->rejectHotelPaidTools();
        $rows = Booking::transactionsForGuide((int) Auth::id());
        $paidTotal = 0.0;
        foreach ($rows as $r) {
            if (($r['payment_status'] ?? '') === 'paid') {
                $paidTotal += (float) ($r['paid_amount'] ?? 0);
            }
        }
        $this->view('guide/transactions', [
            'title' => 'Transactions',
            'transactions' => $rows,
            'paidTotal' => $paidTotal,
        ]);
    }

    public function verifyBooking(): void
    {
        $this->rejectHotelPaidTools();
        $this->verifyCsrf();
        Auth::refreshUser();
        if (User::isGuideWarned((int) Auth::id())) {
            $msg = 'Your account has an active administrator warning. Verification and booking actions are restricted until an administrator clears this warning.';
            if ($this->isJson() || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
                $this->json(['error' => $msg, 'restricted' => true], 403);
                return;
            }
            flash('error', $msg);
            redirect('/dashboard/bookings');
            return;
        }
        $token = trim((string) $this->input('verify_token', ''));
        if ($token === '') {
            flash_keep_old(['verify_token' => 'Enter or scan a booking code.'], [], '/dashboard/bookings/verify');
        }
        if (!Booking::verifyByToken($token, (int) Auth::id())) {
            flash('error', 'Invalid or expired booking code.');
            redirect('/dashboard/bookings/verify');
        }
        flash('success', 'Tourist verified for this booking.');
        redirect('/dashboard/bookings');
    }

    public function availability(string $id): void
    {
        $this->rejectHotelPaidTools();
        $listing = $this->ownedListing((int) $id);
        $this->view('guide/availability', [
            'title' => 'Availability',
            'listing' => $listing,
            'blocked' => GuideAvailability::forListing((int) $listing['id']),
        ]);
    }

    public function blockDate(string $id): void
    {
        $this->rejectHotelPaidTools();
        $this->verifyCsrf();
        $listing = $this->ownedListing((int) $id);
        $date = (string) $this->input('blocked_date', '');
        if ($date === '' || strtotime($date) === false) {
            flash('error', 'Choose a valid date.');
            redirect('/dashboard/listings/' . $listing['id'] . '/availability');
        }
        GuideAvailability::block((int) $listing['id'], $date, (string) $this->input('note', ''));
        flash('success', 'Date blocked.');
        redirect('/dashboard/listings/' . $listing['id'] . '/availability');
    }

    public function unblockDate(string $id): void
    {
        $this->rejectHotelPaidTools();
        $this->verifyCsrf();
        $listing = $this->ownedListing((int) $id);
        GuideAvailability::unblock((int) $listing['id'], (string) $this->input('blocked_date', ''));
        flash('info', 'Date unblocked.');
        redirect('/dashboard/listings/' . $listing['id'] . '/availability');
    }

    /** Schedule list — plot the sessions this listing actually runs. */
    public function schedule(string $id): void
    {
        $this->rejectHotelPaidTools();
        $listing = $this->ownedListing((int) $id);
        $schedules = ListingSchedule::forListing((int) $listing['id']);
        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'listing' => [
                    'id' => (int) $listing['id'],
                    'title' => (string) $listing['title'],
                ],
                'schedule' => $schedules,
            ]);
            return;
        }
        $this->view('guide/schedule', [
            'title' => 'Schedule',
            'listing' => $listing,
            'schedule' => $schedules,
        ]);
    }

    public function addSchedule(string $id): void
    {
        $this->rejectHotelPaidTools();
        $this->verifyCsrf();
        $listing = $this->ownedListing((int) $id);
        $date = (string) $this->input('schedule_date', '');
        if ($date === '' || strtotime($date) === false) {
            $err = 'Choose a valid date for your schedule.';
            if ($this->isJson()) {
                $this->json(['error' => $err], 422);
                return;
            }
            flash('error', $err);
            redirect('/dashboard/listings/' . $listing['id'] . '/schedule');
        }
        $startTime = trim((string) $this->input('start_time', ''));
        $endTime = trim((string) $this->input('end_time', ''));

        if ($startTime !== '' && $endTime !== '') {
            if (strtotime($endTime) <= strtotime($startTime)) {
                $err = 'End time must be later than the start time.';
                if ($this->isJson()) {
                    $this->json(['error' => $err], 422);
                    return;
                }
                flash('error', $err);
                redirect('/dashboard/listings/' . $listing['id'] . '/schedule');
            }
        }

        $capacityRaw = trim((string) $this->input('capacity', ''));
        $capacity = $capacityRaw !== '' ? max(0, (int) $capacityRaw) : null;
        $note = (string) $this->input('note', '');

        $scheduleId = (int) $this->input('schedule_id', 0);
        if ($scheduleId > 0) {
            ListingSchedule::update(
                (int) $listing['id'],
                $scheduleId,
                $date,
                $startTime !== '' ? $startTime : null,
                $endTime !== '' ? $endTime : null,
                $capacity,
                $note
            );
            $msg = 'Schedule updated.';
        } else {
            ListingSchedule::add(
                (int) $listing['id'],
                $date,
                $startTime !== '' ? $startTime : null,
                $endTime !== '' ? $endTime : null,
                $capacity,
                $note
            );
            $msg = 'Schedule added.';
        }

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => $msg,
                'schedule' => ListingSchedule::forListing((int) $listing['id']),
            ]);
            return;
        }

        flash('success', $msg);
        redirect('/dashboard/listings/' . $listing['id'] . '/schedule');
    }

    public function updateSchedule(string $id, string $scheduleId): void
    {
        $this->rejectHotelPaidTools();
        $this->verifyCsrf();
        $listing = $this->ownedListing((int) $id);
        $date = (string) $this->input('schedule_date', '');
        if ($date === '' || strtotime($date) === false) {
            $err = 'Choose a valid date for your schedule.';
            if ($this->isJson()) {
                $this->json(['error' => $err], 422);
                return;
            }
            flash('error', $err);
            redirect('/dashboard/listings/' . $listing['id'] . '/schedule');
        }
        $startTime = trim((string) $this->input('start_time', ''));
        $endTime = trim((string) $this->input('end_time', ''));

        if ($startTime !== '' && $endTime !== '') {
            if (strtotime($endTime) <= strtotime($startTime)) {
                $err = 'End time must be later than the start time.';
                if ($this->isJson()) {
                    $this->json(['error' => $err], 422);
                    return;
                }
                flash('error', $err);
                redirect('/dashboard/listings/' . $listing['id'] . '/schedule');
            }
        }

        $capacityRaw = trim((string) $this->input('capacity', ''));
        $capacity = $capacityRaw !== '' ? max(0, (int) $capacityRaw) : null;
        $note = (string) $this->input('note', '');

        ListingSchedule::update(
            (int) $listing['id'],
            (int) $scheduleId,
            $date,
            $startTime !== '' ? $startTime : null,
            $endTime !== '' ? $endTime : null,
            $capacity,
            $note
        );

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => 'Schedule updated.',
                'schedule' => ListingSchedule::forListing((int) $listing['id']),
            ]);
            return;
        }

        flash('success', 'Schedule updated.');
        redirect('/dashboard/listings/' . $listing['id'] . '/schedule');
    }

    public function removeSchedule(string $id): void
    {
        $this->rejectHotelPaidTools();
        $this->verifyCsrf();
        $listing = $this->ownedListing((int) $id);
        $scheduleId = (int) $this->input('schedule_id', 0);
        ListingSchedule::remove((int) $listing['id'], $scheduleId);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => 'Schedule entry removed.',
                'schedule' => ListingSchedule::forListing((int) $listing['id']),
            ]);
            return;
        }

        flash('info', 'Schedule entry removed.');
        redirect('/dashboard/listings/' . $listing['id'] . '/schedule');
    }

    /**
     * Validate listing form input and return normalized data.
     *
     * @return array<string, mixed>
     */
    private function validateListing(): array
    {
        $errors = $this->requireFields(['title', 'description', 'area', 'price']);
        $categoryId = (int) $this->input('category_id', 0);
        $category = Category::find($categoryId);
        if ($category === null || Category::isHiddenSlug((string) ($category['slug'] ?? '')) || ($category['slug'] ?? '') === 'hotels') {
            $errors['category_id'] = 'Please choose a valid guide experience category.';
        }
        $price = (float) $this->input('price', 0);
        if ($price < 0) {
            $errors['price'] = 'Price cannot be negative.';
        }
        $capacityRaw = $this->input('capacity');
        $capacity = $capacityRaw !== null ? (int) $capacityRaw : 10;
        if ($capacityRaw !== null && $capacity <= 0) {
            $errors['capacity'] = 'Please enter a valid capacity / slots (at least 1).';
        }
        $cover = trim((string) $this->input('cover_image', ''));
        if ($cover !== '') {
            $isLocal = str_starts_with($cover, 'uploads/') || str_starts_with($cover, '/uploads/') || str_contains($cover, 'uploads/') || str_starts_with($cover, 'assets/') || str_starts_with($cover, 'img/');
            $isData = str_starts_with($cover, 'data:image/');
            if (!$isLocal && !$isData && !filter_var($cover, FILTER_VALIDATE_URL)) {
                $errors['cover_image'] = 'Cover image must be a valid URL or uploaded image.';
            }
        }

        if ($errors !== []) {
            if ($this->isJson()) {
                $this->json([
                    'success' => false,
                    'error' => implode(' ', $errors),
                    'errors' => $errors,
                ], 422);
                return [];
            }
            $back = '/dashboard/listings';
            flash_keep_old($errors, $_POST, $back);
        }

        $priceUnit = strtolower(trim((string) $this->input('price_unit', 'per person')));
        $allowedUnits = ['per person', 'per group'];
        if (!in_array($priceUnit, $allowedUnits, true)) {
            $priceUnit = 'per person';
        }

        return [
            'category_id' => $categoryId,
            'title' => (string) $this->input('title', ''),
            'summary' => (string) $this->input('summary', ''),
            'description' => (string) $this->input('description', ''),
            'area' => (string) $this->input('area', ''),
            'address' => (string) $this->input('address', ''),
            'price' => $price,
            'price_unit' => $priceUnit,
            'duration' => (string) $this->input('duration', ''),
            'capacity' => $capacity > 0 ? $capacity : 10,
            'included' => trim((string) $this->input('included', '')),
            'not_included' => trim((string) $this->input('not_included', '')),
            'cover_image' => $cover === '' ? null : $cover,
        ];
    }

    /**
     * Resolve up to 3 photos for an experience listing from uploads, URLs, and existing data.
     *
     * @return array<int, string>
     */
    private function resolveGuidePhotos(?int $listingId = null): array
    {
        $existing = $listingId !== null ? Listing::getPhotos($listingId) : [];
        $photos = [];

        for ($i = 0; $i < 3; $i++) {
            $isRemoved = !empty($_POST['photo_removed_' . $i]) || !empty($_POST['photos_removed'][$i]);
            if ($isRemoved) {
                continue;
            }

            $file = null;
            if (isset($_FILES['photo_file_' . $i]) && Upload::present($_FILES['photo_file_' . $i])) {
                $file = $_FILES['photo_file_' . $i];
            } elseif ($i === 0 && isset($_FILES['cover_file']) && Upload::present($_FILES['cover_file'])) {
                $file = $_FILES['cover_file'];
            }

            if ($file !== null) {
                try {
                    $uploadedPath = Upload::store($file, 'listings/' . Auth::id(), ['jpg', 'jpeg', 'png', 'webp']);
                    $photos[] = $uploadedPath;
                    continue;
                } catch (\RuntimeException $e) {
                    // skip invalid image
                }
            }

            $url = null;
            if (!empty($_POST['photo_url_' . $i])) {
                $url = trim((string) $_POST['photo_url_' . $i]);
            } elseif ($i === 0 && !empty($_POST['cover_image'])) {
                $url = trim((string) $_POST['cover_image']);
            }

            if ($url !== null && $url !== '') {
                if (!str_starts_with($url, 'data:image/')) {
                    $photos[] = $this->normalizeStoredImagePath($url);
                    continue;
                }
            }

            $existingPath = null;
            if (!empty($_POST['existing_photo_' . $i])) {
                $existingPath = trim((string) $_POST['existing_photo_' . $i]);
            } elseif (isset($existing[$i]) && $existing[$i] !== '') {
                $existingPath = $existing[$i];
            }

            if ($existingPath !== null && $existingPath !== '') {
                $photos[] = $this->normalizeStoredImagePath($existingPath);
            }
        }

        $clean = [];
        foreach ($photos as $p) {
            $p = trim((string) $p);
            if ($p !== '' && !in_array($p, $clean, true)) {
                $clean[] = $p;
            }
        }

        return array_values(array_slice($clean, 0, 3));
    }

    private function normalizeStoredImagePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#(?:https?://[^/]+)?(?:/.*?)*?/(uploads/.+)#i', $path, $m)) {
            return $m[1];
        }
        if (str_starts_with($path, 'uploads/')) {
            return $path;
        }
        if (str_starts_with($path, '/uploads/')) {
            return substr($path, 1);
        }
        return $path;
    }

    /**
     * Store an uploaded cover photo (if any) and return its path, or null when
     * no file was submitted. Invalid files redirect back with an error.
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
            $back = '/dashboard/listings';
            flash_keep_old(['cover_file' => $e->getMessage()], $_POST, $back);
        }
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function ownedListing(int $id): array
    {
        $listing = Listing::find($id);
        if ($listing === null || ((int) $listing['user_id'] !== (int) Auth::id() && !Auth::hasRole('admin'))) {
            abort(404, 'Listing not found.');
        }
        return $listing;
    }

    private function uniqueSlug(string $title): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)) ?? '', '-');
        if ($base === '') {
            $base = 'listing';
        }
        $slug = $base;
        $i = 2;
        while (Listing::slugExists($slug)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Automatically determine the package highlight badge based on package status and listing metrics.
     * Easily extensible for additional statuses in the future.
     *
     * @param array $package
     * @param bool $isNew
     * @return string
     */
    public static function determinePackageBadge(array $package = [], bool $isNew = false): string
    {
        // 1. Newly created package or pending review always carries "New Expedition"
        if ($isNew || empty($package['id']) || ($package['status'] ?? '') === 'pending') {
            return 'New Expedition';
        }

        // 2. Freshly published packages created within the last 14 days
        $createdAt = !empty($package['created_at']) ? strtotime((string) $package['created_at']) : 0;
        if ($createdAt > 0 && (time() - $createdAt) < (14 * 86400)) {
            return 'New Expedition';
        }

        $rating = (float) ($package['avg_rating'] ?? 0);
        $reviewCount = (int) ($package['reviews_count'] ?? 0);
        $bookingsCount = (int) ($package['bookings_count'] ?? 0);
        $isFeatured = !empty($package['is_featured']);

        // 3. High booking volume / popular packages
        if ($bookingsCount >= 10 || ($isFeatured && $rating >= 4.8)) {
            return 'Best Seller';
        }

        // 4. Stellar traveler review ratings
        if ($rating >= 4.8 || $reviewCount >= 10) {
            return 'Top Rated';
        }

        // 5. Explicitly featured packages
        if ($isFeatured) {
            return 'Featured';
        }

        // 6. Default extensible fallback
        return 'New Expedition';
    }

    /**
     * Display the 5-Step Travel Package Builder Wizard.
     */
    public function createPackage(): void
    {
        if (Auth::hasRole('hotel_provider') || Auth::hasRole('hotel_admin')) {
            redirect('/dashboard/hotels');
            return;
        }

        if (!$this->ensureApproved()) {
            return;
        }

        Category::ensurePackagesCategory();

        $userId = (int) Auth::id();

        // 1. Guide's own approved activities & attractions only (No listings from other guides, no dummy data)
        $myActivities = [];
        try {
            $myActivities = \App\Core\Database::all("
                SELECT l.id, l.title, l.area, l.price, l.price_unit, l.duration, l.cover_image, l.summary,
                       c.name as category_name, c.slug as category_slug,
                       1 as is_mine
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                WHERE l.user_id = ?
                  AND l.status = 'approved'
                  AND c.slug IN ('things-to-do', 'attractions', 'attraction-and-tours', 'tour-guides')
                ORDER BY l.id DESC
            ", [$userId]);
        } catch (\Throwable $e) {
            $myActivities = [];
        }

        // 2. Approved hotels and available hotel rooms from database (no dummy/hardcoded data)
        $hotels = [];
        try {
            $hotels = \App\Core\Database::all("
                SELECT l.id, l.user_id, l.title, l.area, l.address, l.cover_image, l.summary, l.description,
                       u.name as partner_name,
                       (SELECT COUNT(*) FROM hotel_rooms hr WHERE hr.listing_id = l.id AND hr.status = 'available' AND (hr.available_units > 0 OR hr.available_units IS NULL)) as available_room_count,
                       (SELECT MIN(hr.price_per_night) FROM hotel_rooms hr WHERE hr.listing_id = l.id AND hr.status = 'available' AND (hr.available_units > 0 OR hr.available_units IS NULL) AND hr.price_per_night > 0) as min_price
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                JOIN users u ON u.id = l.user_id
                WHERE l.status = 'approved' AND c.slug = 'hotels'
                HAVING available_room_count > 0
                ORDER BY l.title ASC
            ");
        } catch (\Throwable $e) {
            $hotels = [];
        }

        $hotelRooms = [];
        try {
            $hotelRooms = \App\Core\Database::all("
                SELECT hr.id, hr.listing_id, hr.name as room_name, hr.price_per_night, hr.max_guests, hr.bed_type, hr.room_size, hr.amenities, hr.image as room_image, hr.description as room_description,
                       l.title as hotel_name, l.area as hotel_area, l.cover_image as hotel_cover,
                       u.name as partner_name
                FROM hotel_rooms hr
                JOIN listings l ON l.id = hr.listing_id
                JOIN categories c ON c.id = l.category_id
                JOIN users u ON u.id = l.user_id
                WHERE l.status = 'approved' AND c.slug = 'hotels'
                  AND hr.status = 'available'
                  AND (hr.available_units > 0 OR hr.available_units IS NULL)
                ORDER BY hr.price_per_night ASC
            ");
        } catch (\Throwable $e) {
            $hotelRooms = [];
        }

        // 3. Approved and available vehicles from rental partners (no dummy/hardcoded data, exclude already booked)
        $vehicles = [];
        try {
            $vehicles = \App\Core\Database::all("
                SELECT v.id, v.user_id, v.make, v.model, v.year, v.type as vehicle_type, v.name as vehicle_name,
                       v.price_per_day as daily_rate, v.image as vehicle_image, v.location, v.description, v.specs,
                       u.name as partner_name
                FROM vehicles v
                JOIN users u ON u.id = v.user_id
                WHERE v.status = 'approved'
                  AND v.is_available = 1
                  AND v.id NOT IN (
                      SELECT CAST(rr.vehicle_id AS UNSIGNED)
                      FROM rental_requests rr
                      WHERE rr.status IN ('approved', 'contacted')
                        AND CURDATE() <= DATE_ADD(rr.pickup_date, INTERVAL rr.rental_days DAY)
                  )
                ORDER BY v.price_per_day ASC
            ");
        } catch (\Throwable $e) {
            $vehicles = [];
        }

        $autoBadge = self::determinePackageBadge([], true);
        $guideStats = \App\Models\Booking::guideRatingStats((int) (Auth::id() ?? 0));

        $this->view('guide/package-builder', [
            'title' => 'New Travel Package',
            'user' => Auth::user(),
            'guideStats' => $guideStats,
            'myActivities' => $myActivities,
            'hotels' => $hotels,
            'hotelRooms' => $hotelRooms,
            'vehicles' => $vehicles,
            'package' => null,
            'isEdit' => false,
            'defaultCategory' => '',
            'defaultBadge' => $autoBadge,
            'autoBadge' => $autoBadge,
            'defaultAssurance' => '',
            'existingPhotos' => [],
            'selectedActivities' => [],
            'selectedActivityIds' => [],
            'selectedHotelId' => 0,
            'selectedHotelRoomId' => 0,
            'selectedHotelName' => 'None',
            'selectedHotelRoom' => '',
            'selectedHotelRate' => 0,
            'selectedHotelAmenities' => [],
            'selectedHotelCheckin' => '',
            'selectedHotelCheckout' => '',
            'selectedVehicleId' => 0,
            'selectedVehicleModel' => 'None',
            'selectedVehicleDailyRate' => 0,
            'selectedVehicleType' => '',
            'selectedVehicleLocation' => '',
            'selectedVehicleSpecs' => [],
            'parsedItineraryDays' => [],
            'errors' => errors(),
        ]);
    }

    /**
     * Display the 5-Step Travel Package Builder in Edit Mode for an existing package.
     */
    public function editPackage(string $id): void
    {
        if (Auth::hasRole('hotel_provider') || Auth::hasRole('hotel_admin')) {
            redirect('/dashboard/hotels');
            return;
        }

        if (!$this->ensureApproved()) {
            return;
        }

        Category::ensurePackagesCategory();

        $userId = (int) Auth::id();
        $package = $this->ownedListing((int) $id);

        if (($package['category_slug'] ?? '') !== 'packages') {
            redirect('/dashboard/listings/' . (int) $id . '/edit');
            return;
        }

        // 1. Guide's own approved activities & attractions only (No other guides, no dummy data)
        $myActivities = [];
        try {
            $myActivities = \App\Core\Database::all("
                SELECT l.id, l.title, l.area, l.price, l.price_unit, l.duration, l.cover_image, l.summary,
                       c.name as category_name, c.slug as category_slug,
                       1 as is_mine
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                WHERE l.user_id = ?
                  AND l.status = 'approved'
                  AND c.slug IN ('things-to-do', 'attractions', 'attraction-and-tours', 'tour-guides')
                ORDER BY l.id DESC
            ", [$userId]);
        } catch (\Throwable $e) {
            $myActivities = [];
        }

        // 2. Approved hotels and available hotel rooms from database (no dummy/hardcoded data)
        $hotels = [];
        try {
            $hotels = \App\Core\Database::all("
                SELECT l.id, l.user_id, l.title, l.area, l.address, l.cover_image, l.summary, l.description,
                       u.name as partner_name,
                       (SELECT COUNT(*) FROM hotel_rooms hr WHERE hr.listing_id = l.id AND hr.status = 'available' AND (hr.available_units > 0 OR hr.available_units IS NULL)) as available_room_count,
                       (SELECT MIN(hr.price_per_night) FROM hotel_rooms hr WHERE hr.listing_id = l.id AND hr.status = 'available' AND (hr.available_units > 0 OR hr.available_units IS NULL) AND hr.price_per_night > 0) as min_price
                FROM listings l
                JOIN categories c ON c.id = l.category_id
                JOIN users u ON u.id = l.user_id
                WHERE l.status = 'approved' AND c.slug = 'hotels'
                HAVING available_room_count > 0
                ORDER BY l.title ASC
            ");
        } catch (\Throwable $e) {
            $hotels = [];
        }

        $hotelRooms = [];
        try {
            $hotelRooms = \App\Core\Database::all("
                SELECT hr.id, hr.listing_id, hr.name as room_name, hr.price_per_night, hr.max_guests, hr.bed_type, hr.room_size, hr.amenities, hr.image as room_image, hr.description as room_description,
                       l.title as hotel_name, l.area as hotel_area, l.cover_image as hotel_cover,
                       u.name as partner_name
                FROM hotel_rooms hr
                JOIN listings l ON l.id = hr.listing_id
                JOIN categories c ON c.id = l.category_id
                JOIN users u ON u.id = l.user_id
                WHERE l.status = 'approved' AND c.slug = 'hotels'
                  AND hr.status = 'available'
                  AND (hr.available_units > 0 OR hr.available_units IS NULL)
                ORDER BY hr.price_per_night ASC
            ");
        } catch (\Throwable $e) {
            $hotelRooms = [];
        }

        // 3. Approved and available vehicles from rental partners (no dummy/hardcoded data, exclude booked)
        $vehicles = [];
        try {
            $vehicles = \App\Core\Database::all("
                SELECT v.id, v.user_id, v.make, v.model, v.year, v.type as vehicle_type, v.name as vehicle_name,
                       v.price_per_day as daily_rate, v.image as vehicle_image, v.location, v.description, v.specs,
                       u.name as partner_name
                FROM vehicles v
                JOIN users u ON u.id = v.user_id
                WHERE v.status = 'approved'
                  AND v.is_available = 1
                  AND v.id NOT IN (
                      SELECT CAST(rr.vehicle_id AS UNSIGNED)
                      FROM rental_requests rr
                      WHERE rr.status IN ('approved', 'contacted')
                        AND CURDATE() <= DATE_ADD(rr.pickup_date, INTERVAL rr.rental_days DAY)
                  )
                ORDER BY v.price_per_day ASC
            ");
        } catch (\Throwable $e) {
            $vehicles = [];
        }

        // 4. Retrieve existing photos
        $photos = Listing::getPhotos((int) $id);
        if ($photos === [] && !empty($package['cover_image'])) {
            $photos = [$package['cover_image']];
        }

        // 5. Parse structured description parts & metadata
        $rawDesc = (string) ($package['description'] ?? '');
        $itinerary = '';
        $policies = '';
        $tags = '';
        $cleanDesc = $rawDesc;

        $defaultCategory = 'Adventure & Nature';
        $defaultBadge = !empty($package['is_featured']) ? 'Best Seller' : 'Featured';
        $defaultAssurance = 'Guaranteed';

        if (preg_match('/<!-- PACKAGE_OVERVIEW:\s*(.*?)\s*-->/s', $cleanDesc, $m)) {
            $overviewMeta = json_decode($m[1], true) ?? [];
            $defaultCategory = $overviewMeta['category_label'] ?? $defaultCategory;
            $defaultBadge = $overviewMeta['badge'] ?? $defaultBadge;
            $defaultAssurance = $overviewMeta['assurance'] ?? $defaultAssurance;
            $cleanDesc = trim(str_replace($m[0], '', $cleanDesc));
        }

        if (preg_match('/--- Policies & Requirements ---\s*(.*)$/s', $cleanDesc, $m)) {
            $policies = trim($m[1]);
            $cleanDesc = trim(str_replace($m[0], '', $cleanDesc));
        }

        $parsedItineraryDays = [];
        if (preg_match('/--- Day-by-Day Itinerary ---\s*(.*?)(?=\n\n--- Policies|\n\nExperience Tags:|$)/s', $cleanDesc, $m)) {
            $itinerary = trim($m[1]);
            $cleanDesc = trim(str_replace($m[0], '', $cleanDesc));
            $decodedItin = json_decode($itinerary, true);
            if (is_array($decodedItin) && !empty($decodedItin)) {
                $parsedItineraryDays = array_map(function ($d, $idx) {
                    $dayNum = (int) ($d['day'] ?? ($idx + 1));
                    $dayTitle = (string) ($d['title'] ?? ('Day ' . $dayNum));
                    $dayDesc = (string) ($d['desc'] ?? ($d['description'] ?? ''));
                    $sched = isset($d['schedule']) && is_array($d['schedule']) ? $d['schedule'] : [];
                    $hl = isset($d['highlights']) && is_array($d['highlights']) ? $d['highlights'] : [];
                    return [
                        'day' => $dayNum,
                        'title' => $dayTitle,
                        'desc' => $dayDesc,
                        'description' => $dayDesc,
                        'schedule' => $sched,
                        'highlights' => $hl,
                    ];
                }, $decodedItin, array_keys($decodedItin));
            } else {
                $dayBlocks = preg_split('/(?=Day\s+\d+:)/i', $itinerary, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($dayBlocks as $idx => $block) {
                    $lines = array_filter(array_map('trim', explode("\n", trim($block))));
                    $dayTitle = array_shift($lines) ?? ('Day ' . ($idx + 1));
                    $dayDesc = '';
                    $daySchedule = [];
                    $dayHighlights = [];
                    foreach ($lines as $line) {
                        if (preg_match('/^(\d{1,2}:\d{2}\s*(?:AM|PM))\s*[-:–]?\s*(.*)$/i', $line, $sm)) {
                            $daySchedule[] = ['time' => trim($sm[1]), 'activity' => trim($sm[2])];
                        } elseif (str_starts_with($line, '✓') || str_starts_with($line, '•') || str_starts_with($line, '-')) {
                            $dayHighlights[] = trim(ltrim($line, '✓•- '));
                        } else {
                            $dayDesc .= ($dayDesc !== '' ? ' ' : '') . $line;
                        }
                    }
                    $parsedItineraryDays[] = [
                        'day' => $idx + 1,
                        'title' => $dayTitle,
                        'desc' => $dayDesc,
                        'schedule' => $daySchedule,
                        'highlights' => $dayHighlights,
                    ];
                }
            }
        }

        if (preg_match('/Experience Tags:\s*(.*)$/m', $cleanDesc, $m)) {
            $tags = trim(str_replace(', ', "\n", $m[1]));
            $cleanDesc = trim(str_replace($m[0], '', $cleanDesc));
        }

        // 6. Parse duration days and nights
        $durationDays = 3;
        $durationNights = 2;
        if (!empty($package['duration']) && preg_match('/(\d+)\s*Days?\s*\/\s*(\d+)\s*Nights?/i', (string) $package['duration'], $dm)) {
            $durationDays = (int) $dm[1];
            $durationNights = (int) $dm[2];
        }

        // 7. Parse included services metadata (activities, hotel room, vehicle)
        $includedText = (string) ($package['included'] ?? '');
        $cleanInclusions = preg_replace('/<!--.*?-->/s', '', $includedText);
        $cleanIncLines = [];
        foreach (explode("\n", $cleanInclusions) as $l) {
            $t = trim($l);
            if ($t === '') continue;
            if (preg_match('/^(Activities|Accommodation|Transportation)\s*:\s*/i', $t)) {
                continue;
            }
            $cleanIncLines[] = $t;
        }
        $defaultInclusions = implode("\n", $cleanIncLines);

        $serviceIds = null;
        if (preg_match_all('/<!--\s*PACKAGE_SERVICES:\s*(\{.*?\})\s*-->/s', $includedText . "\n" . $rawDesc, $jm) && !empty($jm[1])) {
            $lastJson = end($jm[1]);
            $serviceIds = json_decode($lastJson, true);
        }

        $selectedActivityIds = is_array($serviceIds) && !empty($serviceIds['activity_ids'])
            ? array_map('intval', $serviceIds['activity_ids'])
            : [];
        $selectedHotelRoomId = is_array($serviceIds) && isset($serviceIds['hotel_room_id'])
            ? (int) $serviceIds['hotel_room_id']
            : 0;
        $selectedVehicleId = is_array($serviceIds) && isset($serviceIds['vehicle_id'])
            ? (int) $serviceIds['vehicle_id']
            : 0;

        // DB record fallback for vehicle if not present in comment metadata
        if (empty($selectedVehicleId) && !empty($package['id'])) {
            try {
                $psVeh = \App\Core\Database::first("SELECT vehicle_id FROM package_services WHERE package_id = ? AND service_type = 'vehicle' LIMIT 1", [(int) $package['id']]);
                if ($psVeh && !empty($psVeh['vehicle_id'])) {
                    $selectedVehicleId = (int) $psVeh['vehicle_id'];
                }
            } catch (\Throwable $e) {}
        }

        // DB record fallback for hotel room
        if (empty($selectedHotelRoomId) && !empty($package['id'])) {
            try {
                $psHot = \App\Core\Database::first("SELECT hotel_room_id FROM package_services WHERE package_id = ? AND service_type = 'hotel' LIMIT 1", [(int) $package['id']]);
                if ($psHot && !empty($psHot['hotel_room_id'])) {
                    $selectedHotelRoomId = (int) $psHot['hotel_room_id'];
                }
            } catch (\Throwable $e) {}
        }

        // DB record fallback for activities
        if (empty($selectedActivityIds) && !empty($package['id'])) {
            try {
                $psActs = \App\Core\Database::all("SELECT listing_id FROM package_services WHERE package_id = ? AND service_type IN ('activity', 'attraction')", [(int) $package['id']]);
                if (!empty($psActs)) {
                    $selectedActivityIds = array_map('intval', array_column($psActs, 'listing_id'));
                }
            } catch (\Throwable $e) {}
        }

        // Fallback for activities by title if IDs not in metadata
        if (empty($selectedActivityIds) && preg_match('/Activities:\s*(.*)$/m', $includedText, $am)) {
            $actTitles = array_map('trim', explode(',', $am[1]));
            foreach ($actTitles as $aTitle) {
                foreach ($myActivities as $mAct) {
                    if (strcasecmp($mAct['title'], $aTitle) === 0) {
                        $selectedActivityIds[] = (int) $mAct['id'];
                        break;
                    }
                }
            }
        }

        // Ensure previously selected activities are in $myActivities so cards appear in Step 2
        if (!empty($selectedActivityIds)) {
            $existingMyIds = array_column($myActivities, 'id');
            $missingIds = array_diff($selectedActivityIds, $existingMyIds);
            if (!empty($missingIds)) {
                $inPlaceholders = implode(',', array_fill(0, count($missingIds), '?'));
                try {
                    $missingActs = \App\Core\Database::all("
                        SELECT l.id, l.title, l.area, l.price, l.price_unit, l.duration, l.cover_image, l.summary,
                               c.name as category_name, c.slug as category_slug, 1 as is_mine
                        FROM listings l
                        JOIN categories c ON c.id = l.category_id
                        WHERE l.id IN ($inPlaceholders)
                    ", array_values($missingIds));
                    foreach ($missingActs as $ma) {
                        $myActivities[] = $ma;
                    }
                } catch (\Throwable $e) {}
            }
        }

        $selectedActivities = [];
        foreach ($myActivities as $idx => $mAct) {
            if (in_array((int) $mAct['id'], $selectedActivityIds, true)) {
                $selectedActivities[] = [
                    'id' => (int) $mAct['id'],
                    'title' => $mAct['title'],
                    'rate' => (float) $mAct['price'],
                    'area' => $mAct['area'],
                    'cover' => $mAct['cover_image'],
                    'category' => $mAct['category_name'] ?? 'Things to Do',
                    'day' => 'Day ' . min(3, $idx + 1),
                    'time' => '09:00 AM',
                ];
            }
        }

        // Retrieve previously selected Hotel Room details
        $selectedHotelId = 0;
        $selectedHotelRate = 0;
        $selectedHotelName = 'None';
        $selectedHotelRoom = '';
        $selectedHotelAmenities = [];
        $selectedHotelCheckin = '';
        $selectedHotelCheckout = '';
        if ($selectedHotelRoomId > 0) {
            try {
                $hRoomRow = \App\Core\Database::first("
                    SELECT hr.id, hr.listing_id, hr.name as room_name, hr.price_per_night, hr.max_guests, hr.bed_type, hr.room_size, hr.amenities, hr.image as room_image, hr.description as room_description,
                           l.title as hotel_name, l.area as hotel_area, l.cover_image as hotel_cover,
                           u.name as partner_name
                    FROM hotel_rooms hr
                    JOIN listings l ON l.id = hr.listing_id
                    JOIN users u ON u.id = l.user_id
                    WHERE hr.id = ?
                ", [$selectedHotelRoomId]);
                if ($hRoomRow) {
                    $selectedHotelId = (int) ($hRoomRow['listing_id'] ?? 0);
                    $selectedHotelRate = (float) $hRoomRow['price_per_night'];
                    $selectedHotelName = (string) $hRoomRow['hotel_name'];
                    $selectedHotelRoom = (string) $hRoomRow['room_name'];
                    $selectedHotelAmenities = json_decode((string) ($hRoomRow['amenities'] ?? '[]'), true) ?: ['Free High-Speed Wi-Fi', 'Swimming Pool Access', 'Complimentary Daily Breakfast'];
                    $selectedHotelCheckin = '14:00:00';
                    $selectedHotelCheckout = '12:00:00';
                    if (!in_array($selectedHotelRoomId, array_column($hotelRooms, 'id'), true)) {
                        $hotelRooms[] = $hRoomRow;
                    }
                    if (!in_array((int)$hRoomRow['listing_id'], array_column($hotels, 'id'), true)) {
                        $hotels[] = [
                            'id' => (int)$hRoomRow['listing_id'],
                            'user_id' => 0,
                            'title' => $hRoomRow['hotel_name'],
                            'area' => $hRoomRow['hotel_area'],
                            'cover_image' => $hRoomRow['hotel_cover'],
                            'summary' => '',
                            'check_in_time' => '14:00:00',
                            'check_out_time' => '12:00:00',
                            'partner_name' => $hRoomRow['partner_name'],
                        ];
                    }
                }
            } catch (\Throwable $e) {}
        } elseif (preg_match('/Accommodation:\s*([^\n\r(]+)/i', $includedText, $hm)) {
            $parsedHName = trim($hm[1]);
            if ($parsedHName !== '' && stripos($parsedHName, 'none') === false && stripos($parsedHName, 'no hotel') === false) {
                foreach ($hotelRooms as $hr) {
                    if (strcasecmp($hr['hotel_name'], $parsedHName) === 0 || strcasecmp($hr['room_name'], $parsedHName) === 0) {
                        $selectedHotelRoomId = (int) $hr['id'];
                        $selectedHotelName = $hr['hotel_name'];
                        $selectedHotelRoom = $hr['room_name'];
                        $selectedHotelRate = (float) $hr['price_per_night'];
                        $selectedHotelAmenities = json_decode((string) ($hr['amenities'] ?? '[]'), true) ?: ['Free High-Speed Wi-Fi', 'Swimming Pool Access'];
                        $selectedHotelCheckin = (string) ($hr['check_in_time'] ?? '14:00:00');
                        $selectedHotelCheckout = (string) ($hr['check_out_time'] ?? '12:00:00');
                        break;
                    }
                }
            }
        }

        // Retrieve previously selected Vehicle details
        $selectedVehicleDailyRate = 0;
        $selectedVehicleModel = 'None';
        $selectedVehicleType = '';
        $selectedVehicleLocation = '';
        $selectedVehicleSpecs = [];
        $selectedVehicleHandover = '13:00:00';
        $selectedVehicleReturn = '12:00:00';
        if ($selectedVehicleId > 0) {
            try {
                $vehRow = \App\Core\Database::first("
                    SELECT v.id, v.user_id, v.make, v.model, v.year, v.type as vehicle_type, v.name as vehicle_name,
                           v.price_per_day as daily_rate, v.image as vehicle_image, v.location, v.description, v.specs,
                           u.name as partner_name
                    FROM vehicles v
                    JOIN users u ON u.id = v.user_id
                    WHERE v.id = ?
                ", [$selectedVehicleId]);
                if ($vehRow) {
                    $selectedVehicleDailyRate = (float) $vehRow['daily_rate'];
                    $selectedVehicleModel = (string) ($vehRow['vehicle_name'] ?? ($vehRow['make'] . ' ' . $vehRow['model']));
                    $selectedVehicleType = (string) ($vehRow['vehicle_type'] ?? 'Scooter');
                    $selectedVehicleLocation = (string) ($vehRow['location'] ?? 'Cebu');
                    $selectedVehicleSpecs = json_decode((string) ($vehRow['specs'] ?? '[]'), true) ?: ['Automatic Transmission', '2 Helmets Included'];
                    if (!in_array($selectedVehicleId, array_column($vehicles, 'id'), true)) {
                        $vehicles[] = $vehRow;
                    }
                }
            } catch (\Throwable $e) {}
        } elseif (preg_match('/Transportation:\s*([^\n\r(]+)/i', $includedText, $vm)) {
            $parsedVName = trim($vm[1]);
            if ($parsedVName !== '' && stripos($parsedVName, 'none') === false && stripos($parsedVName, 'no vehicle') === false) {
                foreach ($vehicles as $v) {
                    $vFullName = (string) ($v['vehicle_name'] ?? ($v['make'] . ' ' . $v['model']));
                    if (strcasecmp($vFullName, $parsedVName) === 0 || stripos($vFullName, $parsedVName) !== false) {
                        $selectedVehicleId = (int) $v['id'];
                        $selectedVehicleModel = $vFullName;
                        $selectedVehicleType = (string) ($v['vehicle_type'] ?? 'Scooter');
                        $selectedVehicleDailyRate = (float) $v['daily_rate'];
                        $selectedVehicleLocation = (string) ($v['location'] ?? 'Cebu');
                        $selectedVehicleSpecs = json_decode((string) ($v['specs'] ?? '[]'), true) ?: ['Automatic Transmission', '2 Helmets Included'];
                        break;
                    }
                }
            }
        }

        $autoBadge = self::determinePackageBadge($package, false);
        $guideStats = \App\Models\Booking::guideRatingStats((int) (Auth::id() ?? 0));

        $guideVehicleIdDoc = (string) ($package['guide_vehicle_id_doc'] ?? ($servicesMeta['guide_vehicle_id_doc'] ?? ''));
        if (empty($guideVehicleIdDoc) && $selectedVehicleId > 0) {
            $docRow = \App\Core\Database::first("
                SELECT guide_id_doc FROM package_services WHERE package_id = ? AND service_type = 'vehicle' LIMIT 1
            ", [(int) $package['id']]);
            if ($docRow && !empty($docRow['guide_id_doc'])) {
                $guideVehicleIdDoc = (string) $docRow['guide_id_doc'];
            }
        }

        $this->view('guide/package-builder', [
            'title' => 'Edit Travel Package: ' . $package['title'],
            'user' => Auth::user(),
            'guideStats' => $guideStats,
            'myActivities' => $myActivities,
            'hotels' => $hotels,
            'hotelRooms' => $hotelRooms,
            'vehicles' => $vehicles,
            'package' => $package,
            'isEdit' => true,
            'defaultCategory' => $defaultCategory,
            'defaultBadge' => $autoBadge,
            'autoBadge' => $autoBadge,
            'defaultAssurance' => $defaultAssurance,
            'parsedDesc' => $cleanDesc,
            'parsedItinerary' => $itinerary,
            'parsedItineraryDays' => $parsedItineraryDays,
            'parsedPolicies' => $policies,
            'parsedTags' => $tags,
            'durationDays' => $durationDays,
            'durationNights' => $durationNights,
            'existingPhotos' => $photos,
            'defaultInclusions' => $defaultInclusions,
            'selectedActivities' => $selectedActivities,
            'selectedActivityIds' => $selectedActivityIds,
            'selectedHotelId' => $selectedHotelId,
            'selectedHotelRoomId' => $selectedHotelRoomId,
            'selectedHotelName' => $selectedHotelName,
            'selectedHotelRoom' => $selectedHotelRoom,
            'selectedHotelRate' => $selectedHotelRate,
            'selectedHotelAmenities' => $selectedHotelAmenities,
            'selectedHotelCheckin' => $selectedHotelCheckin,
            'selectedHotelCheckout' => $selectedHotelCheckout,
            'selectedVehicleId' => $selectedVehicleId,
            'selectedVehicleModel' => $selectedVehicleModel,
            'selectedVehicleDailyRate' => $selectedVehicleDailyRate,
            'selectedVehicleType' => $selectedVehicleType,
            'selectedVehicleLocation' => $selectedVehicleLocation,
            'selectedVehicleSpecs' => $selectedVehicleSpecs,
            'existingGuideVehicleIdDoc' => $guideVehicleIdDoc,
            'errors' => errors(),
        ]);
    }

    /**
     * Store a new multi-service travel package created via the wizard.
     */
    public function storePackage(): void
    {
        if (Auth::hasRole('hotel_provider') || Auth::hasRole('hotel_admin')) {
            redirect('/dashboard/hotels');
            return;
        }

        if (!$this->ensureApproved()) {
            return;
        }

        $this->verifyCsrf();

        $title = trim((string) $this->input('title', ''));
        if ($title === '') {
            flash('error', 'Package title is required.');
            redirect('/dashboard/packages/create');
            return;
        }

        $area = trim((string) $this->input('area', 'Cebu, Philippines'));
        $days = max(1, (int) $this->input('duration_days', 3));
        $nights = max(0, (int) $this->input('duration_nights', 2));
        $durationText = $days . ' Days / ' . $nights . ' Nights';
        
        $minPax = max(1, (int) $this->input('min_pax', 2));
        $maxPax = max($minPax, (int) $this->input('max_pax', 8));
        $baseDeparture = trim((string) $this->input('base_departure', 'Moalboal, Cebu'));
        $summary = trim((string) $this->input('summary', ''));

        // Experience tags parsed from textarea (one per line)
        $tagsRaw = trim((string) $this->input('tags', ''));
        $tagsList = array_values(array_filter(array_map('trim', explode("\n", $tagsRaw))));
        $tagsString = implode(', ', $tagsList);

        $description = trim((string) $this->input('description', ''));
        $itinerary = trim((string) $this->input('itinerary', ''));
        $inclusionsCustom = trim((string) $this->input('inclusions', ''));
        // Strip comment tags and auto-generated service lines from custom inclusions
        $inclusionsCustom = preg_replace('/<!--.*?-->/s', '', $inclusionsCustom);
        $cleanIncLines = [];
        foreach (explode("\n", $inclusionsCustom) as $l) {
            $t = trim($l);
            if ($t === '') continue;
            if (preg_match('/^(Activities|Accommodation|Transportation)\s*:\s*/i', $t)) {
                continue;
            }
            $cleanIncLines[] = $t;
        }
        $inclusionsCustom = implode("\n", $cleanIncLines);

        $exclusionsCustom = trim((string) $this->input('exclusions', ''));
        $policiesCustom = trim((string) $this->input('policies', ''));

        $finalPrice = (float) $this->input('final_price_per_pax', 0);
        if ($finalPrice <= 0) {
            $finalPrice = (float) $this->input('total_package_price', 8499.00);
        }

        // Collect Inclusions
        $activities = (array) ($this->input('activities') ?? []);
        $hotelName = trim((string) $this->input('selected_hotel_name', ''));
        $vehicleName = trim((string) $this->input('selected_vehicle_name', ''));
        $activityIds = array_map('intval', (array) ($this->input('selected_activity_ids') ?? []));
        $hotelRoomId = (int) $this->input('selected_hotel_room', 0);
        $vehicleId = (int) $this->input('selected_vehicle_id', 0);

        $guideVehicleIdDoc = null;
        if ($vehicleId > 0 && isset($_FILES['guide_vehicle_id_doc']) && is_array($_FILES['guide_vehicle_id_doc']) && $_FILES['guide_vehicle_id_doc']['error'] === UPLOAD_ERR_OK) {
            try {
                $guideVehicleIdDoc = Upload::store($_FILES['guide_vehicle_id_doc'], 'guide-docs/' . Auth::id(), ['jpg', 'jpeg', 'png', 'pdf']);
            } catch (\Throwable $e) {}
        }
        if ($guideVehicleIdDoc === null && $vehicleId > 0) {
            $existingDoc = trim((string) $this->input('existing_guide_vehicle_id_doc', ''));
            if ($existingDoc !== '') {
                $guideVehicleIdDoc = $existingDoc;
            } else {
                $prevDoc = \App\Core\Database::first("SELECT guide_id_doc FROM package_services WHERE package_id = ? AND service_type = 'vehicle' LIMIT 1", [(int) $package['id']]);
                if ($prevDoc && !empty($prevDoc['guide_id_doc'])) {
                    $guideVehicleIdDoc = (string) $prevDoc['guide_id_doc'];
                }
            }
        }

        $includedLines = [];
        if (!empty($activities)) {
            $includedLines[] = 'Activities: ' . implode(', ', array_map('strval', $activities));
        }
        if ($hotelName !== '' && $hotelName !== 'none' && !str_contains(strtolower($hotelName), 'no hotel')) {
            $includedLines[] = 'Accommodation: ' . $hotelName . ' (' . $nights . ' Nights)';
        }
        if ($vehicleName !== '' && $vehicleName !== 'none' && !str_contains(strtolower($vehicleName), 'no vehicle')) {
            $includedLines[] = 'Transportation: ' . $vehicleName . ' (' . $days . ' Days)';
        }
        if ($inclusionsCustom !== '') {
            $includedLines[] = $inclusionsCustom;
        } else {
            $includedLines[] = "Licensed Local Tour Guide Host & Trip Coordination\nAll Environmental & Local Tourism Fees\nFull safety equipment, life vests & rescue gear";
        }

        $servicesMeta = [
            'activity_ids' => $activityIds,
            'hotel_room_id' => $hotelRoomId,
            'vehicle_id' => $vehicleId,
            'guide_vehicle_id_doc' => $guideVehicleIdDoc,
        ];
        $includedLines[] = '<!-- PACKAGE_SERVICES: ' . json_encode($servicesMeta) . ' -->';
        $includedText = implode("\n", $includedLines);

        $notIncludedText = $exclusionsCustom !== ''
            ? $exclusionsCustom
            : "Airfare to and from Cebu (Mactan)\nPersonal souvenir shopping & alcoholic drinks\nOptional upgrades & meals not specified";

        $categoryLabel = trim((string) $this->input('category_label', ''));
        // Badge is automatically determined based on package status/conditions (e.g. "New Expedition" for newly created packages)
        $badge = self::determinePackageBadge([], true);
        $assurance = trim((string) $this->input('assurance', ''));

        $overviewMeta = [
            'category_label' => $categoryLabel,
            'badge' => $badge,
            'assurance' => $assurance,
        ];

        // Structured package overview
        $fullDescription = '<!-- PACKAGE_OVERVIEW: ' . json_encode($overviewMeta) . " -->\n";
        $fullDescription .= ($description !== '' ? $description : $title);
        if ($tagsString !== '') {
            $fullDescription .= "\n\nExperience Tags: " . $tagsString;
        }
        if ($itinerary !== '') {
            $fullDescription .= "\n\n--- Day-by-Day Itinerary ---\n" . $itinerary;
        }
        if ($policiesCustom !== '') {
            $fullDescription .= "\n\n--- Policies & Requirements ---\n" . $policiesCustom;
        }

        // Find or ensure category for packages
        Category::ensurePackagesCategory();
        $pkgCat = Category::findBySlug('packages');
        $catId = $pkgCat ? (int) $pkgCat['id'] : 1;

        // Process photos using the structured 3-photo slot system
        $photos = [];
        for ($i = 0; $i < 3; $i++) {
            $fileInputKey = 'photo_file_' . $i;
            $urlInputKey = 'photo_url_' . $i;

            if (isset($_FILES[$fileInputKey]) && is_array($_FILES[$fileInputKey]) && $_FILES[$fileInputKey]['error'] === UPLOAD_ERR_OK) {
                try {
                    $uploadedPath = Upload::store($_FILES[$fileInputKey], 'listings/' . Auth::id(), ['jpg', 'jpeg', 'png', 'webp']);
                    $photos[] = $uploadedPath;
                    continue;
                } catch (\Throwable $e) {}
            }

            $urlVal = trim((string) $this->input($urlInputKey, ''));
            if ($urlVal !== '') {
                $photos[] = $this->normalizeStoredImagePath($urlVal);
            }
        }

        $cover = $photos[0] ?? 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80';
        if ($photos === []) {
            $photos = [$cover];
        }

        $slug = $this->uniqueSlug($title);
        $listingId = Listing::create([
            'user_id' => (int) Auth::id(),
            'category_id' => $catId,
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary !== '' ? $summary : substr($description, 0, 160),
            'description' => $fullDescription,
            'area' => $area,
            'address' => $baseDeparture,
            'price' => $finalPrice,
            'price_unit' => 'package',
            'duration' => $durationText,
            'capacity' => $maxPax,
            'included' => $includedText,
            'not_included' => $notIncludedText,
            'cover_image' => $cover,
            'guide_vehicle_id_doc' => $guideVehicleIdDoc,
            'is_featured' => ($badge === 'Best Seller' || $badge === 'Featured') ? 1 : 0,
            'status' => 'pending',
        ]);

        Listing::syncImages($listingId, $photos);

        // Decode itinerary for provider notifications
        $decodedItin = [];
        if (!empty($itinerary)) {
            $decodedItin = json_decode($itinerary, true) ?: [];
        }

        // Synchronize required services (hotel & vehicle) and notify respective providers
        \App\Models\PackageService::syncPackageServices(
            (int) $listingId,
            $hotelRoomId,
            $vehicleId,
            $activityIds,
            (int) Auth::id(),
            $title,
            $decodedItin,
            $durationText,
            $guideVehicleIdDoc
        );

        flash('success', 'Travel Package submitted successfully! It is now pending admin review and will be live once approved.');
        redirect('/dashboard/listings');
    }

    /**
     * Update an existing multi-service travel package.
     */
    public function updatePackage(string $id): void
    {
        if (Auth::hasRole('hotel_provider') || Auth::hasRole('hotel_admin')) {
            redirect('/dashboard/hotels');
            return;
        }

        if (!$this->ensureApproved()) {
            return;
        }

        $this->verifyCsrf();

        $package = $this->ownedListing((int) $id);
        $title = trim((string) $this->input('title', ''));
        if ($title === '') {
            flash('error', 'Package title is required.');
            redirect('/dashboard/packages/' . (int) $id . '/edit');
            return;
        }

        $area = trim((string) $this->input('area', 'Cebu, Philippines'));
        $days = max(1, (int) $this->input('duration_days', 3));
        $nights = max(0, (int) $this->input('duration_nights', 2));
        $durationText = $days . ' Days / ' . $nights . ' Nights';

        $minPax = max(1, (int) $this->input('min_pax', 2));
        $maxPax = max($minPax, (int) $this->input('max_pax', 8));
        $baseDeparture = trim((string) $this->input('base_departure', 'Moalboal, Cebu'));
        $summary = trim((string) $this->input('summary', ''));

        // Experience tags parsed from textarea (one per line)
        $tagsRaw = trim((string) $this->input('tags', ''));
        $tagsList = array_values(array_filter(array_map('trim', explode("\n", $tagsRaw))));
        $tagsString = implode(', ', $tagsList);

        $description = trim((string) $this->input('description', ''));
        $itinerary = trim((string) $this->input('itinerary', ''));
        $inclusionsCustom = trim((string) $this->input('inclusions', ''));
        // Strip comment tags and auto-generated service lines from custom inclusions
        $inclusionsCustom = preg_replace('/<!--.*?-->/s', '', $inclusionsCustom);
        $cleanIncLines = [];
        foreach (explode("\n", $inclusionsCustom) as $l) {
            $t = trim($l);
            if ($t === '') continue;
            if (preg_match('/^(Activities|Accommodation|Transportation)\s*:\s*/i', $t)) {
                continue;
            }
            $cleanIncLines[] = $t;
        }
        $inclusionsCustom = implode("\n", $cleanIncLines);

        $exclusionsCustom = trim((string) $this->input('exclusions', ''));
        $policiesCustom = trim((string) $this->input('policies', ''));

        $finalPrice = (float) $this->input('total_package_price', 0);
        if ($finalPrice <= 0) {
            $finalPrice = (float) $this->input('final_price_per_pax', 8499.00);
        }

        // Collect Inclusions & Service IDs
        $activities = (array) ($this->input('activities') ?? []);
        $hotelName = trim((string) $this->input('selected_hotel_name', ''));
        $vehicleName = trim((string) $this->input('selected_vehicle_name', ''));
        $activityIds = array_map('intval', (array) ($this->input('selected_activity_ids') ?? []));
        $hotelRoomId = (int) $this->input('selected_hotel_room', 0);
        $vehicleId = (int) $this->input('selected_vehicle_id', 0);

        $guideVehicleIdDoc = null;
        if ($vehicleId > 0 && isset($_FILES['guide_vehicle_id_doc']) && is_array($_FILES['guide_vehicle_id_doc']) && $_FILES['guide_vehicle_id_doc']['error'] === UPLOAD_ERR_OK) {
            try {
                $guideVehicleIdDoc = Upload::store($_FILES['guide_vehicle_id_doc'], 'guide-docs/' . Auth::id(), ['jpg', 'jpeg', 'png', 'pdf']);
            } catch (\Throwable $e) {}
        }
        if ($guideVehicleIdDoc === null && $vehicleId > 0) {
            $existingDoc = trim((string) $this->input('existing_guide_vehicle_id_doc', ''));
            if ($existingDoc !== '') {
                $guideVehicleIdDoc = $existingDoc;
            } else {
                $prevDoc = \App\Core\Database::first("SELECT guide_id_doc FROM package_services WHERE package_id = ? AND service_type = 'vehicle' LIMIT 1", [(int) $package['id']]);
                if ($prevDoc && !empty($prevDoc['guide_id_doc'])) {
                    $guideVehicleIdDoc = (string) $prevDoc['guide_id_doc'];
                }
            }
        }

        $includedLines = [];
        if (!empty($activities)) {
            $includedLines[] = 'Activities: ' . implode(', ', array_map('strval', $activities));
        }
        if ($hotelName !== '' && $hotelName !== 'none' && !str_contains(strtolower($hotelName), 'no hotel')) {
            $includedLines[] = 'Accommodation: ' . $hotelName . ' (' . $nights . ' Nights)';
        }
        if ($vehicleName !== '' && $vehicleName !== 'none' && !str_contains(strtolower($vehicleName), 'no vehicle')) {
            $includedLines[] = 'Transportation: ' . $vehicleName . ' (' . $days . ' Days)';
        }
        if ($inclusionsCustom !== '') {
            $includedLines[] = $inclusionsCustom;
        } else {
            $includedLines[] = "Licensed Local Tour Guide Host & Trip Coordination\nAll Environmental & Local Tourism Fees\nFull safety equipment, life vests & rescue gear";
        }

        $servicesMeta = [
            'activity_ids' => $activityIds,
            'hotel_room_id' => $hotelRoomId,
            'vehicle_id' => $vehicleId,
            'guide_vehicle_id_doc' => $guideVehicleIdDoc,
        ];
        $includedLines[] = '<!-- PACKAGE_SERVICES: ' . json_encode($servicesMeta) . ' -->';
        $includedText = implode("\n", $includedLines);

        $notIncludedText = $exclusionsCustom !== ''
            ? $exclusionsCustom
            : "Airfare to and from Cebu (Mactan)\nPersonal souvenir shopping & alcoholic drinks\nOptional upgrades & meals not specified";

        $categoryLabel = trim((string) $this->input('category_label', ''));
        // Badge is automatically determined based on package status/metrics
        $badge = self::determinePackageBadge($package, false);
        $assurance = trim((string) $this->input('assurance', ''));

        $overviewMeta = [
            'category_label' => $categoryLabel,
            'badge' => $badge,
            'assurance' => $assurance,
        ];

        // Structured package overview
        $fullDescription = '<!-- PACKAGE_OVERVIEW: ' . json_encode($overviewMeta) . " -->\n";
        $fullDescription .= ($description !== '' ? $description : $title);
        if ($tagsString !== '') {
            $fullDescription .= "\n\nExperience Tags: " . $tagsString;
        }
        if ($itinerary !== '') {
            $fullDescription .= "\n\n--- Day-by-Day Itinerary ---\n" . $itinerary;
        }
        if ($policiesCustom !== '') {
            $fullDescription .= "\n\n--- Policies & Requirements ---\n" . $policiesCustom;
        }

        Category::ensurePackagesCategory();
        $pkgCat = Category::findBySlug('packages');
        $catId = $pkgCat ? (int) $pkgCat['id'] : (int) $package['category_id'];

        // Process photos (existing, uploaded, or URL)
        $photos = [];
        for ($i = 0; $i < 3; $i++) {
            $existing = trim((string) $this->input('photo_existing_' . $i, ''));
            $removed = (string) $this->input('photo_removed_' . $i, '0') === '1';
            $fileInputKey = 'photo_file_' . $i;
            $urlInputKey = 'photo_url_' . $i;

            if (isset($_FILES[$fileInputKey]) && is_array($_FILES[$fileInputKey]) && $_FILES[$fileInputKey]['error'] === UPLOAD_ERR_OK) {
                try {
                    $uploadedPath = Upload::store($_FILES[$fileInputKey], 'listings/' . Auth::id(), ['jpg', 'jpeg', 'png', 'webp']);
                    $photos[] = $uploadedPath;
                    continue;
                } catch (\Throwable $e) {}
            }

            $urlVal = trim((string) $this->input($urlInputKey, ''));
            if ($urlVal !== '') {
                $photos[] = $this->normalizeStoredImagePath($urlVal);
            } elseif (!$removed && $existing !== '') {
                $photos[] = $existing;
            }
        }

        $cover = $photos[0] ?? $package['cover_image'] ?? 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800&q=80';
        if ($photos === []) {
            $photos = [$cover];
        }

        Listing::update((int) $id, [
            'category_id' => $catId,
            'title' => $title,
            'summary' => $summary !== '' ? $summary : substr($description, 0, 160),
            'description' => $fullDescription,
            'area' => $area,
            'address' => $baseDeparture,
            'price' => $finalPrice,
            'price_unit' => 'package',
            'duration' => $durationText,
            'capacity' => $maxPax,
            'included' => $includedText,
            'not_included' => $notIncludedText,
            'cover_image' => $cover,
            'guide_vehicle_id_doc' => $guideVehicleIdDoc,
            'is_featured' => ($badge === 'Best Seller' || $badge === 'Featured') ? 1 : 0,
        ]);

        Listing::syncImages((int) $id, $photos);

        // Decode itinerary for provider notifications
        $decodedItin = [];
        if (!empty($itinerary)) {
            $decodedItin = json_decode($itinerary, true) ?: [];
        }

        // Synchronize required services (hotel & vehicle) and notify respective providers
        \App\Models\PackageService::syncPackageServices(
            (int) $id,
            $hotelRoomId,
            $vehicleId,
            $activityIds,
            (int) Auth::id(),
            $title,
            $decodedItin,
            $durationText,
            $guideVehicleIdDoc
        );

        // Status transition handling:
        // If the package was rejected, resubmit it for review (status = 'pending') and clear rejection reason
        if ($package['status'] === 'rejected') {
            \App\Core\Database::run(
                "UPDATE listings SET status = 'pending', rejection_reason = NULL, submitted_at = NOW() WHERE id = ?",
                [(int) $id]
            );
        } elseif ($package['status'] === 'pending') {
            \App\Core\Database::run(
                "UPDATE listings SET submitted_at = NOW() WHERE id = ?",
                [(int) $id]
            );
        }

        flash('success', 'Travel Package updated successfully!');
        redirect('/dashboard/listings');
    }
}
