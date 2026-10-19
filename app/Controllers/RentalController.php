<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Upload;
use App\Models\RentalRequest;
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

        $this->view('rental/show', [
            'title' => (string) $vehicle['name'],
            'vehicle' => $vehicle,
            'gallery' => $gallery,
            'specs' => Vehicle::parseSpecs($vehicle['specs'] ?? null),
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
        ]);
    }

    /**
     * Display the vehicle creation form.
     */
    public function createVehicle(): void
    {
        $user = Auth::user();
        if ((string) ($user['guide_status'] ?? 'none') !== 'approved') {
            flash('error', 'You must be an approved rental partner before listing vehicles for rent.');
            redirect('/dashboard/vehicles');
        }

        $this->view('rental/vehicle-form', [
            'title' => 'List a Vehicle',
            'vehicle' => null,
            'types' => Vehicle::TYPES,
            'errors' => errors(),
        ]);
    }

    /**
     * Store a newly created vehicle listing.
     */
    public function storeVehicle(): void
    {
        $user = Auth::user();
        $userId = (int) Auth::id();

        if ((string) ($user['guide_status'] ?? 'none') !== 'approved') {
            flash('error', 'You must be an approved rental partner before listing vehicles for rent.');
            redirect('/dashboard/vehicles');
        }

        $this->verifyCsrf();

        $data = $this->validateVehicleData();
        $action = (string) $this->input('action', 'submit');

        // Cover photo is required for new listings
        $coverPath = null;
        if (Upload::present($_FILES['image'] ?? null)) {
            try {
                $coverPath = Upload::store($_FILES['image'], 'vehicles/' . $userId, ['jpg', 'jpeg', 'png', 'webp']);
            } catch (RuntimeException $e) {
                $errors = errors();
                $errors['image'] = $e->getMessage();
                flash_keep_old($errors, $_POST, '/dashboard/vehicles/create');
            }
        } else {
            $errors = errors();
            $errors['image'] = 'A primary photo of the vehicle is required.';
            flash_keep_old($errors, $_POST, '/dashboard/vehicles/create');
        }

        $status = $action === 'draft' ? 'draft' : 'pending';

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

        if ($status === 'draft') {
            flash('success', 'Vehicle saved as draft.');
        } else {
            flash('success', 'Vehicle listing submitted! It will appear publicly once an administrator approves it.');
        }

        redirect('/dashboard/vehicles');
    }

    /**
     * Display the vehicle edit form.
     */
    public function editVehicle(string $id): void
    {
        $vehicle = $this->ownedVehicle((int) $id);

        $this->view('rental/vehicle-form', [
            'title' => 'Edit Vehicle: ' . $vehicle['name'],
            'vehicle' => $vehicle,
            'gallery' => Vehicle::gallery((int) $vehicle['id']),
            'types' => Vehicle::TYPES,
            'errors' => errors(),
        ]);
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
                flash_keep_old($errors, $_POST, '/dashboard/vehicles/' . (int) $id . '/edit');
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
        $user = Auth::user();
        $isGlobalAdmin = ($user['role'] ?? '') === 'admin';
        $requests = $isGlobalAdmin
            ? RentalRequest::all()
            : RentalRequest::forProvider((int) ($user['id'] ?? 0));

        $this->view('rental/requests', [
            'title' => 'Rental Requests',
            'requests' => $requests,
        ]);
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
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
        $note = trim((string) $this->input('owner_note', ''));
        RentalRequest::rejectReport((int) $id, $note !== '' ? $note : null);
        flash('success', 'Report marked as reviewed.');
        redirect('/dashboard/rentals');
    }

    // =========================================================================
    // Helpers & Validation
    // =========================================================================

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
    private function validateVehicleData(?int $vehicleId = null): array
    {
        $make = trim((string) $this->input('make', ''));
        $model = trim((string) $this->input('model', ''));
        $year = (int) $this->input('year', 0);
        $type = trim((string) $this->input('type', ''));
        $name = trim((string) $this->input('name', ''));
        $price = (float) $this->input('price_per_day', 0);
        $location = trim((string) $this->input('location', ''));
        $address = trim((string) $this->input('address', ''));
        $description = trim((string) $this->input('description', ''));
        $specsRaw = $this->input('specs', []);

        $errors = [];

        if ($make === '') {
            $errors['make'] = 'Vehicle make is required (e.g. Honda, Toyota).';
        }
        if ($model === '') {
            $errors['model'] = 'Vehicle model is required (e.g. Click 125i, Vios).';
        }
        $currentYear = (int) date('Y') + 1;
        if ($year < 1980 || $year > $currentYear) {
            $errors['year'] = "Please enter a valid model year (between 1980 and {$currentYear}).";
        }
        if ($type === '' || !in_array($type, Vehicle::TYPES, true)) {
            $errors['type'] = 'Please select a valid vehicle type.';
        }
        if ($price <= 0) {
            $errors['price_per_day'] = 'Please enter a valid daily rental rate greater than 0.';
        }
        if ($location === '') {
            $errors['location'] = 'Vehicle location / pickup area is required.';
        }
        if ($description === '') {
            $errors['description'] = 'Vehicle description is required.';
        } elseif (strlen($description) < 20) {
            $errors['description'] = 'Description should be at least 20 characters.';
        }

        $backUrl = $vehicleId === null ? '/dashboard/vehicles/create' : '/dashboard/vehicles/' . $vehicleId . '/edit';

        if ($errors !== []) {
            flash_keep_old($errors, $_POST, $backUrl);
        }

        if ($name === '') {
            $name = "{$year} {$make} {$model}";
        }

        $specs = [];
        if (is_array($specsRaw)) {
            $specs = array_values(array_filter(array_map('trim', $specsRaw)));
        } elseif (is_string($specsRaw) && $specsRaw !== '') {
            $specs = array_values(array_filter(array_map('trim', explode(',', $specsRaw))));
        }

        // Additional custom spec tag if entered
        $customSpec = trim((string) $this->input('custom_spec', ''));
        if ($customSpec !== '' && !in_array($customSpec, $specs, true)) {
            $specs[] = $customSpec;
        }

        return [
            'make' => $make,
            'model' => $model,
            'year' => $year,
            'type' => $type,
            'name' => $name,
            'price_per_day' => $price,
            'location' => $location,
            'address' => $address !== '' ? $address : null,
            'description' => $description,
            'specs' => $specs,
        ];
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
