<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Upload;
use App\Models\RentalRequest;
use App\Models\RentalReview;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\NotificationService;
use RuntimeException;

/**
 * Rental Partner dashboard & public rental vehicle catalog.
 * Manages vehicles, incoming rental requests, and tourist listings.
 */
final class RentalController extends Controller
{
    // =========================================================================
    // Public Tourist Views
    // =========================================================================

    /**
     * Public catalog of approved and available vehicles for rent.
     */
    public function index(): void
    {
        $filters = [
            'q' => (string) $this->input('q', ''),
            'type' => (string) $this->input('type', ''),
            'location' => (string) $this->input('location', ''),
            'sort' => (string) $this->input('sort', ''),
        ];

        $vehicles = Vehicle::approvedAndAvailable($filters);

        $this->view('rental/browse', [
            'title' => 'Vehicle Rentals in Cebu',
            'heading' => 'Vehicle Rentals in Cebu',
            'sub' => 'Rent scooters, motorbikes, sedans, and SUVs directly from verified local rental partners.',
            'vehicles' => $vehicles,
            'filters' => $filters,
            'types' => Vehicle::TYPES,
            'activeCategory' => 'rentals',
        ]);
    }

    /**
     * Public vehicle detail page.
     */
    public function show(string $slug): void
    {
        $vehicle = Vehicle::findBySlug($slug);
        if ($vehicle === null && ctype_digit($slug)) {
            $vehicle = Vehicle::find((int) $slug);
        }
        if ($vehicle === null) {
            abort(404, 'Vehicle listing not found.');
        }

        $userId = Auth::id();
        $isOwner = $userId !== null && (int) $userId === (int) $vehicle['user_id'];
        $user = Auth::user();
        $isAdmin = $user !== null && ($user['role'] ?? '') === 'admin';

        $isPubliclyAvailable = ($vehicle['status'] ?? '') === 'approved' && (int) ($vehicle['is_available'] ?? 0) === 1;

        if (!$isPubliclyAvailable && !$isOwner && !$isAdmin) {
            abort(404, 'Vehicle listing is currently unavailable.');
        }

        $gallery = Vehicle::gallery((int) $vehicle['id']);
        $reviews = RentalReview::forVehicle((int) $vehicle['id']);
        $reviewStats = RentalReview::vehicleStats((string) $vehicle['id']);

        $this->view('rental/show', [
            'title' => (string) $vehicle['name'],
            'vehicle' => $vehicle,
            'gallery' => $gallery,
            'specs' => Vehicle::parseSpecs($vehicle['specs'] ?? null),
            'reviews' => $reviews,
            'reviewStats' => $reviewStats,
            'isPreview' => !$isPubliclyAvailable,
            'isOwner' => $isOwner,
            'activeCategory' => 'rentals',
        ]);
    }

    // =========================================================================
    // Rental Provider Vehicle Listings Management
    // =========================================================================

    /**
     * List all vehicles owned by the logged-in rental partner.
     */
    public function vehicles(): void
    {
        $user = Auth::user();
        $userId = (int) Auth::id();

        $this->view('rental/vehicles', [
            'title' => 'My Vehicles',
            'vehicles' => Vehicle::forOwner($userId),
            'stats' => Vehicle::countForOwner($userId),
            'isVerified' => (string) ($user['guide_status'] ?? 'none') === 'approved',
            'user' => $user,
            'types' => Vehicle::TYPES,
            'errors' => errors(),
        ]);
    }

    /**
     * Display the vehicle creation form.
     */
    public function createVehicle(): void
    {
        if (!$this->ensureApproved()) {
            return;
        }

        redirect('/dashboard/vehicles?create=1');
    }

    /**
     * Store a newly created vehicle listing.
     */
    public function storeVehicle(): void
    {
        if (!$this->ensureApproved()) {
            return;
        }

        $userId = (int) Auth::id();
        $this->verifyCsrf();

        // Complete server-side validation for all mandatory fields and primary photo
        $data = $this->validateVehicleData();

        // Store uploaded cover photo
        $coverPath = null;
        if (Upload::present($_FILES['image'] ?? null)) {
            try {
                $coverPath = Upload::store($_FILES['image'], 'vehicles/' . $userId, ['jpg', 'jpeg', 'png', 'webp']);
            } catch (RuntimeException $e) {
                $errors = errors();
                $errors['image'] = $e->getMessage();
                if ($this->isJson()) {
                    $this->json(['error' => $e->getMessage(), 'errors' => $errors], 422);
                    exit;
                }
                flash_keep_old($errors, $_POST, '/dashboard/vehicles?create=1');
            }
        }

        $status = 'pending';

        $vehicleId = Vehicle::create([
            'user_id' => $userId,
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'],
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'],
            'specs' => $data['specs'],
            'location' => $data['location'],
            'address' => $data['address'],
            'price_per_day' => $data['price_per_day'],
            'image' => $coverPath,
            'status' => $status,
        ]);

        // Upload additional gallery photos if provided
        $this->processGalleryUploads($vehicleId, $userId);

        flash('success', 'Vehicle listing submitted! It will appear publicly once an administrator approves it.');
        redirect('/dashboard/vehicles');
    }

    /**
     * Display the vehicle edit form.
     */
    public function editVehicle(string $id): void
    {
        $vehicle = $this->ownedVehicle((int) $id);

        redirect('/dashboard/vehicles?edit=' . (int) $vehicle['id']);
    }

    /**
     * Update an existing vehicle listing.
     * If an approved listing is edited in a way that affects important rental details,
     * it returns to Pending Approval until an administrator reviews it again.
     */
    public function updateVehicle(string $id): void
    {
        $vehicle = $this->ownedVehicle((int) $id);
        $userId = (int) Auth::id();

        $this->verifyCsrf();

        $data = $this->validateVehicleData((int) $id);
        $action = (string) $this->input('action', 'submit');

        $coverPath = (string) ($vehicle['image'] ?? '');
        $newPhotoUploaded = false;
        if (Upload::present($_FILES['image'] ?? null)) {
            try {
                $coverPath = Upload::store($_FILES['image'], 'vehicles/' . $userId, ['jpg', 'jpeg', 'png', 'webp']);
                $newPhotoUploaded = true;
            } catch (RuntimeException $e) {
                $errors = errors();
                $errors['image'] = $e->getMessage();
                flash_keep_old($errors, $_POST, '/dashboard/vehicles?edit=' . (int) $id);
            }
        }

        // Upload additional gallery photos if provided
        $this->processGalleryUploads((int) $id, $userId);

        // Check if important rental details changed
        $importantChanged = $newPhotoUploaded
            || (string) $data['make'] !== (string) $vehicle['make']
            || (string) $data['model'] !== (string) $vehicle['model']
            || (int) $data['year'] !== (int) $vehicle['year']
            || (string) $data['type'] !== (string) $vehicle['type']
            || (float) $data['price_per_day'] !== (float) $vehicle['price_per_day']
            || (string) $data['location'] !== (string) $vehicle['location']
            || (string) $data['description'] !== (string) $vehicle['description']
            || json_encode($data['specs']) !== json_encode(Vehicle::parseSpecs($vehicle['specs'] ?? null));

        $currentStatus = (string) ($vehicle['status'] ?? 'draft');
        $newStatus = $currentStatus;
        $isAvailable = (int) ($vehicle['is_available'] ?? 0);
        $message = 'Vehicle listing updated.';

        if ($action === 'draft') {
            $newStatus = 'draft';
            $isAvailable = 0;
            $message = 'Vehicle listing saved as draft.';
        } elseif ($currentStatus === 'rejected') {
            // Re-submitting a rejected listing resets it to pending approval
            $newStatus = 'pending';
            $isAvailable = 0;
            $message = 'Vehicle listing resubmitted for administrator review.';
        } elseif (in_array($currentStatus, ['approved', 'unavailable'], true)) {
            if ($importantChanged) {
                // Return to Pending Approval if important rental details changed
                $newStatus = 'pending';
                $isAvailable = 0;
                $message = 'Vehicle listing updated and returned to Pending Approval for administrator review.';
            }
        } elseif ($currentStatus === 'draft' && $action === 'submit') {
            $newStatus = 'pending';
            $isAvailable = 0;
            $message = 'Vehicle listing submitted for administrator review.';
        }

        $updatePayload = [
            'make' => $data['make'],
            'model' => $data['model'],
            'year' => $data['year'],
            'type' => $data['type'],
            'name' => $data['name'],
            'description' => $data['description'],
            'specs' => $data['specs'],
            'location' => $data['location'],
            'address' => $data['address'],
            'price_per_day' => $data['price_per_day'],
            'image' => $coverPath,
            'status' => $newStatus,
            'is_available' => $isAvailable,
        ];

        if ($newStatus === 'pending') {
            $updatePayload['submitted_at'] = date('Y-m-d H:i:s');
            $updatePayload['rejection_reason'] = null;
        }

        Vehicle::update((int) $id, $updatePayload);

        flash('success', $message);
        redirect('/dashboard/vehicles');
    }

    /**
     * Toggle vehicle availability (Available <-> Unavailable).
     * Providers can only toggle availability if the listing has been approved.
     */
    public function toggleAvailability(string $id): void
    {
        $this->verifyCsrf();
        $vehicle = $this->ownedVehicle((int) $id);

        $status = (string) ($vehicle['status'] ?? 'draft');
        if (!in_array($status, ['approved', 'unavailable'], true)) {
            flash('error', 'Only approved vehicles can be presented as available for rent.');
            redirect('/dashboard/vehicles');
        }

        $isAvailable = (int) ($vehicle['is_available'] ?? 0) === 1;
        $newAvailable = !$isAvailable;

        Vehicle::toggleAvailability((int) $id, $newAvailable);

        if ($newAvailable) {
            flash('success', "'{$vehicle['name']}' is now available for rent.");
        } else {
            flash('info', "'{$vehicle['name']}' is now marked as unavailable.");
        }

        redirect('/dashboard/vehicles');
    }

    /**
     * Quick resubmit of a rejected or draft listing for admin approval.
     */
    public function resubmitVehicle(string $id): void
    {
        $this->verifyCsrf();
        $vehicle = $this->ownedVehicle((int) $id);

        $status = (string) ($vehicle['status'] ?? 'draft');
        if (!in_array($status, ['rejected', 'draft'], true)) {
            flash('info', 'This listing is not eligible for resubmission.');
            redirect('/dashboard/vehicles');
        }

        Vehicle::resubmit((int) $id);
        flash('success', 'Vehicle listing submitted for administrator review.');
        redirect('/dashboard/vehicles');
    }

    /**
     * Delete a vehicle listing.
     */
    public function destroyVehicle(string $id): void
    {
        $this->verifyCsrf();
        $vehicle = $this->ownedVehicle((int) $id);

        Vehicle::delete((int) $id);
        flash('info', "Vehicle listing '{$vehicle['name']}' has been deleted.");
        redirect('/dashboard/vehicles');
    }

    // =========================================================================
    // Existing Rental Requests Management
    // =========================================================================

    public function requests(): void
    {
        Auth::refreshUser();
        $user = Auth::user();
        $isGlobalAdmin = ($user['role'] ?? '') === 'admin';
        $requests = $isGlobalAdmin
            ? RentalRequest::all()
            : RentalRequest::forProvider((int) ($user['id'] ?? 0));

        $this->view('rental/requests', [
            'title' => 'Rental Requests',
            'requests' => $requests,
            'isWarned' => User::isGuideWarned((int) ($user['id'] ?? 0)),
            'warningNote' => (string) ($user['guide_warning_note'] ?? ''),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        Auth::refreshUser();
        if (User::isGuideWarned((int) Auth::id())) {
            $msg = 'Your account has an active administrator warning. Rental actions (Confirm, Complete, and Cancel) are restricted until an administrator clears this warning.';
            if ($this->isJson() || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
                $this->json(['error' => $msg, 'restricted' => true], 403);
                return;
            }
            flash('error', $msg);
            redirect('/dashboard/rentals');
            return;
        }

        $status = (string) $this->input('status', '');
        if (!in_array($status, ['pending', 'approved', 'contacted', 'cancelled', 'completed'], true)) {
            flash('error', 'Invalid rental status.');
            redirect('/dashboard/rentals');
        }
        $rental = RentalRequest::find((int) $id);
        if ($rental === null) {
            flash('error', 'Rental request not found.');
            redirect('/dashboard/rentals');
        }

        $note = trim((string) $this->input('admin_note', ''));
        $paid = ($rental['payment_status'] ?? 'unpaid') === 'paid';
        $customerEmail = (string) ($rental['customer_email'] ?? '');
        $vehicleName = (string) ($rental['vehicle_name'] ?? 'your rental');
        $pickupDate = (string) ($rental['pickup_date'] ?? '');

        if ($status === 'cancelled' && $paid) {
            RentalRequest::refund((int) $id, $note !== '' ? $note : null);
            if ($customerEmail !== '') {
                NotificationService::rentalDeclined(
                    $customerEmail,
                    $vehicleName,
                    'Your payment has been refunded.'
                );
            }
            flash('success', 'Reservation declined and the tourist has been refunded.');
            redirect('/dashboard/rentals');
        }

        RentalRequest::updateStatus((int) $id, $status, $note !== '' ? $note : null);
        if ($status === 'approved' && $customerEmail !== '') {
            NotificationService::rentalConfirmed($customerEmail, $vehicleName, $pickupDate);
            flash('success', 'Rental confirmed. The tourist has been notified.');
        } elseif ($status === 'cancelled' && $customerEmail !== '') {
            NotificationService::rentalDeclined($customerEmail, $vehicleName);
            flash('success', 'Reservation declined.');
        } else {
            flash('success', 'Rental request updated.');
        }
        redirect('/dashboard/rentals');
    }

    /**
     * Refund a paid reservation (resolves any open problem report).
     */
    public function refund(string $id): void
    {
        $this->verifyCsrf();
        Auth::refreshUser();
        if (User::isGuideWarned((int) Auth::id())) {
            $msg = 'Your account has an active administrator warning. Refund actions are restricted until an administrator clears this warning.';
            if ($this->isJson() || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
                $this->json(['error' => $msg, 'restricted' => true], 403);
                return;
            }
            flash('error', $msg);
            redirect('/dashboard/rentals');
            return;
        }

        $rental = RentalRequest::find((int) $id);
        if ($rental === null) {
            flash('error', 'Reservation not found.');
            redirect('/dashboard/rentals');
        }
        if (($rental['payment_status'] ?? 'unpaid') !== 'paid') {
            flash('error', 'Only a paid reservation can be refunded.');
            redirect('/dashboard/rentals');
        }
        $note = trim((string) $this->input('owner_note', ''));
        RentalRequest::refund((int) $id, $note !== '' ? $note : null);
        flash('success', 'Reservation refunded and the customer has been notified.');
        redirect('/dashboard/rentals');
    }

    /**
     * Dismiss a tourist's problem report without a refund.
     */
    public function rejectReport(string $id): void
    {
        $this->verifyCsrf();
        Auth::refreshUser();
        if (User::isGuideWarned((int) Auth::id())) {
            $msg = 'Your account has an active administrator warning. Report actions are restricted until an administrator clears this warning.';
            if ($this->isJson() || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))) {
                $this->json(['error' => $msg, 'restricted' => true], 403);
                return;
            }
            flash('error', $msg);
            redirect('/dashboard/rentals');
            return;
        }

        $note = trim((string) $this->input('owner_note', ''));
        RentalRequest::rejectReport((int) $id, $note !== '' ? $note : null);
        flash('success', 'Report marked as reviewed.');
        redirect('/dashboard/rentals');
    }

    // =========================================================================
    // Helpers & Validation
    // =========================================================================

    /**
     * Rental providers can only create listings once an administrator has approved them.
     */
    private function ensureApproved(): bool
    {
        $user = Auth::user();
        if ((string) ($user['guide_status'] ?? 'none') === 'approved') {
            return true;
        }

        if ($this->isJson()) {
            $this->json([
                'error' => 'You must be an approved rental partner before listing vehicles for rent.',
                'guide_status' => (string) ($user['guide_status'] ?? 'none'),
                'is_verified' => false,
                'redirect' => url('/documents'),
            ], 403);
            return false;
        }

        flash('error', 'You must be an approved rental partner before listing vehicles for rent.');
        redirect('/documents');
        return false;
    }

    /**
     * Ensure the vehicle exists and belongs to the currently authenticated provider.
     *
     * @return array<string, mixed>
     */
    private function ownedVehicle(int $id): array
    {
        $vehicle = Vehicle::find($id);
        if ($vehicle === null) {
            abort(404, 'Vehicle listing not found.');
        }

        $userId = (int) Auth::id();
        if ((int) $vehicle['user_id'] !== $userId) {
            abort(403, 'You do not have permission to manage this vehicle listing.');
        }

        return $vehicle;
    }

    /**
     * Validate common vehicle form fields.
     *
     * @return array<string, mixed>
     */
    /**
     * Pure validation logic for vehicle listing inputs and files.
     * Can be invoked from controller actions, APIs, and unit tests.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $file
     * @param int|null $vehicleId
     * @return array{data: array<string, mixed>, errors: array<string, string>}
     */
    public static function validateVehicleInputs(array $input, ?array $file = null, ?int $vehicleId = null): array
    {
        $make = trim((string) ($input['make'] ?? ''));
        $model = trim((string) ($input['model'] ?? ''));
        $rawYear = $input['year'] ?? '';
        $type = trim((string) ($input['type'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        $rawPrice = $input['price_per_day'] ?? '';
        $location = trim((string) ($input['location'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $specsRaw = $input['specs'] ?? [];
        $customSpec = trim((string) ($input['custom_spec'] ?? ''));

        $errors = [];

        // 1. Make / Brand validation (required, 2-80 chars, non-whitespace)
        if ($make === '') {
            $errors['make'] = 'Vehicle make/brand is required (e.g. Honda, Toyota, Yamaha).';
        } elseif (mb_strlen($make) < 2) {
            $errors['make'] = 'Vehicle make/brand must be at least 2 characters.';
        } elseif (mb_strlen($make) > 80) {
            $errors['make'] = 'Vehicle make/brand must not exceed 80 characters.';
        }

        // 2. Model validation (required, 1-80 chars, non-whitespace)
        if ($model === '') {
            $errors['model'] = 'Vehicle model is required (e.g. Click 125i, Vios, Aerox 155).';
        } elseif (mb_strlen($model) > 80) {
            $errors['model'] = 'Vehicle model must not exceed 80 characters.';
        }

        // 3. Model Year validation (required, integer between 1980 and current year + 1)
        $maxYear = (int) date('Y') + 1;
        if ($rawYear === '' || $rawYear === null || !is_numeric($rawYear)) {
            $errors['year'] = 'Model year is required.';
        } else {
            $year = (int) $rawYear;
            if ($year < 1980 || $year > $maxYear) {
                $errors['year'] = "Please enter a valid model year between 1980 and {$maxYear}.";
            }
        }

        // 4. Vehicle Type validation (required, in Vehicle::TYPES)
        if ($type === '') {
            $errors['type'] = 'Vehicle type category is required.';
        } elseif (!in_array($type, Vehicle::TYPES, true)) {
            $errors['type'] = 'Please select a valid vehicle type category (' . implode(', ', Vehicle::TYPES) . ').';
        }

        // 5. Daily Rental Rate validation (required, numeric, >= 50, <= 500,000)
        if ($rawPrice === '' || $rawPrice === null || !is_numeric($rawPrice)) {
            $errors['price_per_day'] = 'Daily rental rate is required and must be a valid number.';
            $price = 0.0;
        } else {
            $price = (float) $rawPrice;
            if ($price <= 0) {
                $errors['price_per_day'] = 'Daily rental rate must be greater than 0.';
            } elseif ($price < 50) {
                $errors['price_per_day'] = 'Daily rental rate must be at least ₱50.';
            } elseif ($price > 500000) {
                $errors['price_per_day'] = 'Daily rental rate cannot exceed ₱500,000.';
            }
        }

        // 6. Pickup Location validation (required, 3-160 chars, non-whitespace)
        if ($location === '') {
            $errors['location'] = 'Pickup area or island hub is required (e.g. Cebu City, Mactan Island, Oslob).';
        } elseif (mb_strlen($location) < 3) {
            $errors['location'] = 'Pickup location must be at least 3 characters.';
        } elseif (mb_strlen($location) > 160) {
            $errors['location'] = 'Pickup location must not exceed 160 characters.';
        }

        // 7. Description validation (required, 20-5000 chars, non-whitespace)
        if ($description === '') {
            $errors['description'] = 'Vehicle description and rental terms are required.';
        } elseif (mb_strlen($description) < 20) {
            $errors['description'] = 'Description must be at least 20 characters (currently ' . mb_strlen($description) . ' characters).';
        } elseif (mb_strlen($description) > 5000) {
            $errors['description'] = 'Description cannot exceed 5,000 characters.';
        }

        // 8. Primary Cover Photo validation
        if ($vehicleId === null) {
            // Creation: Photo is strictly required
            if ($file === null || !Upload::present($file)) {
                $errors['image'] = 'A primary cover photo of the vehicle is required (JPG, PNG, or WebP up to 5 MB).';
            } else {
                $fileError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
                if ($fileError !== UPLOAD_ERR_OK) {
                    $errors['image'] = match ($fileError) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded photo is larger than the server allows (max 5 MB).',
                        UPLOAD_ERR_PARTIAL => 'The photo was only partially uploaded. Please try again.',
                        default => 'Photo upload failed. Please choose a valid image file.',
                    };
                } elseif ((int) ($file['size'] ?? 0) > Upload::MAX_BYTES) {
                    $errors['image'] = 'Cover photo must be 5 MB or smaller.';
                } else {
                    $orig = (string) ($file['name'] ?? '');
                    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        $errors['image'] = 'Invalid image type (.' . $ext . '). Allowed formats: JPG, PNG, WebP.';
                    }
                }
            }
        } elseif ($file !== null && Upload::present($file)) {
            // Update: If a new photo is provided, validate it
            $fileError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fileError !== UPLOAD_ERR_OK) {
                $errors['image'] = 'Photo upload failed. Please choose a valid image file.';
            } elseif ((int) ($file['size'] ?? 0) > Upload::MAX_BYTES) {
                $errors['image'] = 'Cover photo must be 5 MB or smaller.';
            } else {
                $orig = (string) ($file['name'] ?? '');
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    $errors['image'] = 'Invalid image type (.' . $ext . '). Allowed formats: JPG, PNG, WebP.';
                }
            }
        }

        // Optional field validations
        if ($name !== '' && mb_strlen($name) > 160) {
            $errors['name'] = 'Listing display title must not exceed 160 characters.';
        }

        if ($address !== '' && mb_strlen($address) > 255) {
            $errors['address'] = 'Pickup landmark/address must not exceed 255 characters.';
        }

        if ($customSpec !== '' && mb_strlen($customSpec) > 100) {
            $errors['custom_spec'] = 'Custom inclusion tag must not exceed 100 characters.';
        }

        // Fallback display name
        $finalYear = isset($year) ? $year : (int) $rawYear;
        if ($name === '') {
            $name = trim("{$finalYear} {$make} {$model}");
        }

        $specs = [];
        if (is_array($specsRaw)) {
            $specs = array_values(array_filter(array_map('trim', $specsRaw)));
        } elseif (is_string($specsRaw) && $specsRaw !== '') {
            $specs = array_values(array_filter(array_map('trim', explode(',', $specsRaw))));
        }

        if ($customSpec !== '' && !in_array($customSpec, $specs, true) && !isset($errors['custom_spec'])) {
            $specs[] = $customSpec;
        }

        return [
            'data' => [
                'make' => $make,
                'model' => $model,
                'year' => $finalYear,
                'type' => $type,
                'name' => $name,
                'price_per_day' => $price,
                'location' => $location,
                'address' => $address !== '' ? $address : null,
                'description' => $description,
                'specs' => $specs,
            ],
            'errors' => $errors,
        ];
    }

    /**
     * Common vehicle listing input validation helper for HTTP requests.
     *
     * @return array<string, mixed>
     */
    private function validateVehicleData(?int $vehicleId = null): array
    {
        $input = [
            'make' => $this->input('make', ''),
            'model' => $this->input('model', ''),
            'year' => $this->input('year', ''),
            'type' => $this->input('type', ''),
            'name' => $this->input('name', ''),
            'price_per_day' => $this->input('price_per_day', ''),
            'location' => $this->input('location', ''),
            'address' => $this->input('address', ''),
            'description' => $this->input('description', ''),
            'specs' => $this->input('specs', []),
            'custom_spec' => $this->input('custom_spec', ''),
        ];

        $file = $_FILES['image'] ?? null;
        $validation = self::validateVehicleInputs($input, $file, $vehicleId);
        $errors = $validation['errors'];

        $backUrl = $vehicleId === null ? '/dashboard/vehicles?create=1' : '/dashboard/vehicles?edit=' . $vehicleId;

        if ($errors !== []) {
            if ($this->isJson()) {
                $this->json(['error' => 'Please resolve all required field errors before submitting.', 'errors' => $errors], 422);
                exit;
            }
            flash_keep_old($errors, $_POST, $backUrl);
        }

        return $validation['data'];
    }

    /**
     * Process multiple gallery uploads.
     */
    private function processGalleryUploads(int $vehicleId, int $userId): void
    {
        if (empty($_FILES['gallery']['name']) || !is_array($_FILES['gallery']['name'])) {
            return;
        }

        $files = $_FILES['gallery'];
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $fileItem = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
                try {
                    $path = Upload::store($fileItem, 'vehicles/' . $userId, ['jpg', 'jpeg', 'png', 'webp']);
                    Vehicle::addImage($vehicleId, $path, $i + 1);
                } catch (RuntimeException $e) {
                    // Skip invalid extra images gracefully
                }
            }
        }
    }
}
