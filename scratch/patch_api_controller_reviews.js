const fs = require('fs');

let api = fs.readFileSync('app/Controllers/ApiController.php', 'utf8');
const hasCRLF = api.includes('\r\n');
if (hasCRLF) api = api.replace(/\r\n/g, '\n');

// 1. Add use App\Models\RentalReview;
if (!api.includes('use App\Models\RentalReview;')) {
  api = api.replace('use App\Models\RentalRequest;', "use App\Models\RentalRequest;\nuse App\Models\RentalReview;");
}

// 2. Update rentalDetail to use dynamic provider & vehicle stats
const oldRentalDetailBlock = `        $specs = Vehicle::parseSpecs($vehicle['specs'] ?? null);

        $this->json([
            'vehicle' => [
                'id' => (string) $vehicle['id'],
                'slug' => (string) $vehicle['slug'],
                'name' => (string) $vehicle['name'],
                'make' => (string) $vehicle['make'],
                'model' => (string) $vehicle['model'],
                'year' => (int) $vehicle['year'],
                'type' => (string) $vehicle['type'],
                'price_per_day' => (float) $vehicle['price_per_day'],
                'specs' => $specs,
                'shop' => (string) $providerName,
                'location' => (string) $vehicle['location'],
                'address' => (string) ($vehicle['address'] ?? ''),
                'description' => (string) ($vehicle['description'] ?? ''),
                'status' => (string) ($vehicle['status'] ?? 'approved'),
                'is_available' => (bool) ($vehicle['is_available'] ?? true),
                'rating' => 4.8,
                'image' => $mainImg,
                'images' => $images,
                'provider' => [
                    'id' => (int) $vehicle['user_id'],
                    'name' => (string) $providerName,
                    'email' => (string) ($providerUser['email'] ?? ''),
                    'avatar' => $providerAvatar,
                    'online' => $isOnline,
                    'role' => (string) ($providerUser['role'] ?? 'rental_provider'),
                    'rating' => 4.8,
                    'reviews_count' => 18,
                    'response_time' => 'Typically replies in 15 mins',
                    'completed_rentals' => 24,
                ],`;

const newRentalDetailBlock = `        $specs = Vehicle::parseSpecs($vehicle['specs'] ?? null);
        $providerStats = RentalReview::providerStats((int) $vehicle['user_id']);
        $vehicleStats = RentalReview::vehicleStats((string) $vehicle['id']);
        $displayRating = $vehicleStats['rating'] > 0.0 ? $vehicleStats['rating'] : $providerStats['rating'];

        $this->json([
            'vehicle' => [
                'id' => (string) $vehicle['id'],
                'slug' => (string) $vehicle['slug'],
                'name' => (string) $vehicle['name'],
                'make' => (string) $vehicle['make'],
                'model' => (string) $vehicle['model'],
                'year' => (int) $vehicle['year'],
                'type' => (string) $vehicle['type'],
                'price_per_day' => (float) $vehicle['price_per_day'],
                'specs' => $specs,
                'shop' => (string) $providerName,
                'location' => (string) $vehicle['location'],
                'address' => (string) ($vehicle['address'] ?? ''),
                'description' => (string) ($vehicle['description'] ?? ''),
                'status' => (string) ($vehicle['status'] ?? 'approved'),
                'is_available' => (bool) ($vehicle['is_available'] ?? true),
                'rating' => (float) $displayRating,
                'reviews_count' => (int) $vehicleStats['reviews_count'],
                'image' => $mainImg,
                'images' => $images,
                'provider' => [
                    'id' => (int) $vehicle['user_id'],
                    'name' => (string) $providerName,
                    'email' => (string) ($providerUser['email'] ?? ''),
                    'avatar' => $providerAvatar,
                    'online' => $isOnline,
                    'role' => (string) ($providerUser['role'] ?? 'rental_provider'),
                    'rating' => (float) $providerStats['rating'],
                    'reviews_count' => (int) $providerStats['reviews_count'],
                    'response_time' => 'Typically replies in 15 mins',
                    'completed_rentals' => (int) $providerStats['completed_rentals'],
                ],`;

if (!api.includes(oldRentalDetailBlock)) {
  console.error('Could not find oldRentalDetailBlock in ApiController.php');
  process.exit(1);
}
api = api.replace(oldRentalDetailBlock, newRentalDetailBlock);

// 3. Update rentalPayload to include review info
const oldRentalPayload = `        return [
            'id' => (int) $r['id'],
            'vehicle_name' => (string) $r['vehicle_name'],
            'vehicle_type' => (string) $r['vehicle_type'],
            'shop_name' => (string) $r['shop_name'],
            'location' => (string) ($r['location'] ?? ''),
            'pickup_date' => (string) $r['pickup_date'],
            'rental_days' => (int) $r['rental_days'],
            'total_amount' => (float) $r['total_amount'],
            'status' => $status,
            'status_label' => RentalRequest::statusLabel($status),
            'payment_status' => $paymentStatus,
            'payment_method' => (string) ($r['payment_method'] ?? ''),
            'notes' => (string) ($r['notes'] ?? ''),
            'report_status' => $reportStatus,
            'report_type' => (string) ($r['report_type'] ?? ''),
            'report_label' => RentalRequest::reportTypeLabel($r['report_type'] ?? null),
            'report_message' => (string) ($r['report_message'] ?? ''),
            'owner_report_note' => (string) ($r['owner_report_note'] ?? ''),
            'can_report' => $paymentStatus === 'paid' && $reportStatus === 'none' && $status !== 'refunded',
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];`;

const newRentalPayload = `        $review = RentalReview::findForRental((int) $r['id']);

        return [
            'id' => (int) $r['id'],
            'vehicle_id' => (string) ($r['vehicle_id'] ?? ''),
            'vehicle_name' => (string) $r['vehicle_name'],
            'vehicle_type' => (string) $r['vehicle_type'],
            'shop_name' => (string) $r['shop_name'],
            'location' => (string) ($r['location'] ?? ''),
            'pickup_date' => (string) $r['pickup_date'],
            'rental_days' => (int) $r['rental_days'],
            'total_amount' => (float) $r['total_amount'],
            'status' => $status,
            'status_label' => RentalRequest::statusLabel($status),
            'payment_status' => $paymentStatus,
            'payment_method' => (string) ($r['payment_method'] ?? ''),
            'notes' => (string) ($r['notes'] ?? ''),
            'report_status' => $reportStatus,
            'report_type' => (string) ($r['report_type'] ?? ''),
            'report_label' => RentalRequest::reportTypeLabel($r['report_type'] ?? null),
            'report_message' => (string) ($r['report_message'] ?? ''),
            'owner_report_note' => (string) ($r['owner_report_note'] ?? ''),
            'can_report' => $paymentStatus === 'paid' && $reportStatus === 'none' && $status !== 'refunded',
            'can_review' => $status === 'completed',
            'has_reviewed' => $review !== null,
            'review' => $review !== null ? [
                'id' => (int) $review['id'],
                'rating' => (int) $review['rating'],
                'feedback' => (string) $review['feedback'],
                'created_at' => (string) $review['created_at'],
                'updated_at' => (string) $review['updated_at'],
            ] : null,
            'created_at' => (string) ($r['created_at'] ?? ''),
        ];`;

if (!api.includes(oldRentalPayload)) {
  console.error('Could not find oldRentalPayload in ApiController.php');
  process.exit(1);
}
api = api.replace(oldRentalPayload, newRentalPayload);

// 4. Add submitRentalReview and getRentalReview
const newReviewMethods = `
    /**
     * POST /api/rentals/reviews — submit or update rating and feedback for a completed rental.
     */
    public function submitRentalReview(): void
    {
        $userId = ApiAuth::id();
        if ($userId === null) {
            $this->json(['error' => 'Unauthorized.'], 401);
            return;
        }

        $body = $this->jsonBody();
        $rentalId = (int) ($body['rental_id'] ?? 0);
        $rating = (int) ($body['rating'] ?? 0);
        $feedback = trim((string) ($body['feedback'] ?? ''));

        if ($rentalId <= 0) {
            $this->json(['error' => 'Rental ID is required.'], 422);
            return;
        }

        $rental = RentalRequest::find($rentalId);
        if ($rental === null || (int) $rental['user_id'] !== $userId) {
            $this->json(['error' => 'Rental reservation not found or access denied.'], 404);
            return;
        }

        // Must be completed!
        if (($rental['status'] ?? '') !== 'completed') {
            $this->json([
                'error' => 'You can only rate and review a rental after it has been marked as Completed by the provider.'
            ], 403);
            return;
        }

        if ($rating < 1 || $rating > 5) {
            $this->json(['error' => 'Please select a valid rating between 1 and 5 stars.'], 422);
            return;
        }

        if (mb_strlen($feedback) < 3) {
            $this->json(['error' => 'Please provide written feedback with at least 3 characters.'], 422);
            return;
        }

        // Resolve provider ID
        $providerId = 0;
        $vehicleId = $rental['vehicle_id'] ?? null;
        if ($vehicleId !== null && is_numeric($vehicleId)) {
            $vehicle = Vehicle::find((int) $vehicleId);
            if ($vehicle !== null && !empty($vehicle['user_id'])) {
                $providerId = (int) $vehicle['user_id'];
            }
        }
        if ($providerId <= 0) {
            $shopName = trim((string) ($rental['shop_name'] ?? ''));
            $partnerUser = Database::first("SELECT id FROM users WHERE name = ? AND role = 'rental_provider'", [$shopName]);
            if ($partnerUser !== null) {
                $providerId = (int) $partnerUser['id'];
            }
        }
        if ($providerId <= 0) {
            $providerId = 1;
        }

        try {
            $review = RentalReview::createOrUpdate(
                $rentalId,
                $userId,
                $providerId,
                $vehicleId ? (string) $vehicleId : null,
                $rating,
                $feedback
            );

            $stats = RentalReview::providerStats($providerId);

            $this->json([
                'success' => true,
                'message' => 'Review submitted successfully.',
                'review' => [
                    'id' => (int) $review['id'],
                    'rental_id' => (int) $review['rental_id'],
                    'rating' => (int) $review['rating'],
                    'feedback' => (string) $review['feedback'],
                    'created_at' => (string) $review['created_at'],
                    'updated_at' => (string) $review['updated_at'],
                ],
                'provider_stats' => $stats,
            ]);
        } catch (\\Throwable $e) {
            $this->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /api/rentals/reviews?rental_id=... — fetch a review by rental ID.
     */
    public function getRentalReview(): void
    {
        $rentalId = (int) $this->input('rental_id', 0);
        if ($rentalId <= 0) {
            $this->json(['error' => 'Rental ID is required.'], 422);
            return;
        }

        $review = RentalReview::findForRental($rentalId);
        $this->json([
            'review' => $review !== null ? [
                'id' => (int) $review['id'],
                'rental_id' => (int) $review['rental_id'],
                'rating' => (int) $review['rating'],
                'feedback' => (string) $review['feedback'],
                'created_at' => (string) $review['created_at'],
                'updated_at' => (string) $review['updated_at'],
            ] : null,
        ]);
    }
`;

const insertMarker = '    public function reportRental(): void';
if (!api.includes(insertMarker)) {
  console.error('Could not find insertMarker in ApiController.php');
  process.exit(1);
}

api = api.replace(insertMarker, newReviewMethods + '\n' + insertMarker);

if (hasCRLF) api = api.replace(/\n/g, '\r\n');
fs.writeFileSync('app/Controllers/ApiController.php', api, 'utf8');
console.log('ApiController.php updated with dynamic stats & review methods successfully!');
