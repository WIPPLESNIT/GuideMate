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
        if (Auth::hasRole('hotel_admin')) {
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
        if (Auth::hasRole('hotel_admin')) {
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

        // An uploaded photo always wins over a pasted URL.
        $cover = $this->uploadedCover() ?? $data['cover_image'];

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
            'included' => $data['included'],
            'not_included' => $data['not_included'],
            'cover_image' => $cover,
            'status' => 'pending',
        ]);

        if ($cover !== null) {
            Listing::addImage($listingId, $cover, 1);
        }

        flash('success', 'Listing submitted! It will appear publicly once an admin approves it.');
        redirect('/dashboard/listings');
    }

    public function edit(string $id): void
    {
        if (Auth::hasRole('hotel_admin')) {
            redirect('/dashboard/hotels');
            return;
        }

        if (!$this->ensureApproved()) {
            return;
        }
        $listing = $this->ownedListing((int) $id);
        if (($listing['category_slug'] ?? '') === 'hotels') {
            flash('error', 'Hotel listings are managed from My Hotels.');
            redirect('/dashboard/hotels');
            return;
        }
        if ($this->isJson()) {
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
            if ($this->isJson()) {
                $this->json(['error' => 'Please fill in all required fields.', 'errors' => errors()], 422);
                return;
            }
            return;
        }

        // Keep the existing photo unless the guide uploaded a new one.
        $cover = $this->uploadedCover() ?? (string) ($listing['cover_image'] ?? '') ?: null;

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
            'included' => $data['included'],
            'not_included' => $data['not_included'],
            'cover_image' => $cover,
        ]);

        if ($this->isJson()) {
            $this->json([
                'success' => true,
                'message' => 'Listing updated.',
                'listing' => Listing::find((int) $listing['id']),
            ]);
            return;
        }

        flash('success', 'Listing updated.');
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
        $isHotel = ($user['role'] ?? '') === 'hotel_admin';
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
            flash('error', 'Your account has an active administrator warning. Booking actions (including cancel and refund) are restricted until an admin clears it.');
            redirect('/dashboard/bookings');
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
            if ($status === 'completed' && $current !== 'confirmed') {
                flash('error', 'Confirm this booking first before marking it completed.');
                redirect('/dashboard/bookings');
            }
            Booking::updateStatus((int) $booking['id'], $status);
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

    public function showVerifyBooking(): void
    {
        $this->rejectHotelPaidTools();
        $this->view('guide/verify', ['title' => 'Verify booking', 'errors' => errors()]);
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
        $cover = trim((string) $this->input('cover_image', ''));
        if ($cover !== '' && !filter_var($cover, FILTER_VALIDATE_URL)) {
            $errors['cover_image'] = 'Cover image must be a valid URL (or left blank).';
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
            // New listings use the pop-up modal on the My Listings page, so send
            // errors there; edits still go back to the full edit page.
            $back = $this->input('listing_id') ? '/dashboard/listings/' . $this->input('listing_id') . '/edit' : '/dashboard/listings';
            flash_keep_old($errors, $_POST, $back);
        }

        return [
            'category_id' => $categoryId,
            'title' => (string) $this->input('title', ''),
            'summary' => (string) $this->input('summary', ''),
            'description' => (string) $this->input('description', ''),
            'area' => (string) $this->input('area', ''),
            'address' => (string) $this->input('address', ''),
            'price' => $price,
            'price_unit' => (string) $this->input('price_unit', 'per person'),
            'duration' => (string) $this->input('duration', ''),
            'included' => trim((string) $this->input('included', '')),
            'not_included' => trim((string) $this->input('not_included', '')),
            'cover_image' => $cover === '' ? null : $cover,
        ];
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
            $back = $this->input('listing_id')
                ? '/dashboard/listings/' . $this->input('listing_id') . '/edit'
                : '/dashboard/listings';
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
}
