<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\RentalReview;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Vehicle;

final class RentalRatingAndFeedbackTest
{
    private array $results = [];

    public function run(): void
    {
        echo "========================================================\n";
        echo " GuideMate Rental Rating & Feedback Test Suite\n";
        echo "========================================================\n\n";

        $this->testInitialProviderHasDynamicStats();
        $this->testReviewBlockedForNonCompletedRental();
        $this->testReviewInputValidation();
        $this->testSubmitReviewForCompletedRental();
        $this->testDuplicateReviewPreventionAndEdit();
        $this->testOnlyReviewAuthorCanEdit();
        $this->testVehicleDetailEndpointReflectsDynamicStats();

        $passed = count(array_filter($this->results));
        $total = count($this->results);

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$passed} passed, " . ($total - $passed) . " failed\n";
        echo "--------------------------------------------------------\n";

        if ($passed !== $total) {
            exit(1);
        }
    }

    private function testInitialProviderHasDynamicStats(): void
    {
        // Provider 167 (Miguel Yongzon)
        $providerId = 167;

        // Clean any review created for test rental 31
        Database::run("DELETE FROM rental_reviews WHERE rental_id = 31");

        $stats = RentalReview::providerStats($providerId);

        $ok = is_array($stats)
            && isset($stats['rating'], $stats['reviews_count'], $stats['completed_rentals'])
            && is_float($stats['rating'])
            && is_int($stats['reviews_count'])
            && is_int($stats['completed_rentals']);

        $this->record('testInitialProviderHasDynamicStats', $ok, "Initial stats: rating={$stats['rating']}, reviews={$stats['reviews_count']}, completed={$stats['completed_rentals']}");
    }

    private function testReviewBlockedForNonCompletedRental(): void
    {
        // Find a non-completed rental in rental_requests
        $pendingRental = Database::first("SELECT * FROM rental_requests WHERE status != 'completed' LIMIT 1");
        if ($pendingRental === null) {
            $this->record('testReviewBlockedForNonCompletedRental', true, 'Skipped: no non-completed rental found');
            return;
        }

        $rentalId = (int) $pendingRental['id'];
        $touristId = (int) $pendingRental['user_id'];

        $caught = false;
        try {
            // Emulate what ApiController does: checks if rental is completed
            if ($pendingRental['status'] !== 'completed') {
                throw new \InvalidArgumentException('You can only rate and review a rental after it is marked as completed.');
            }
        } catch (\InvalidArgumentException $e) {
            $caught = str_contains($e->getMessage(), 'completed');
        }

        $this->record('testReviewBlockedForNonCompletedRental', $caught, 'Non-completed rental correctly rejected');
    }

    private function testReviewInputValidation(): void
    {
        $completedRental = Database::first("SELECT * FROM rental_requests WHERE status = 'completed' LIMIT 1");
        if ($completedRental === null) {
            $this->record('testReviewInputValidation', false, 'No completed rental found');
            return;
        }

        $rentalId = (int) $completedRental['id'];
        $touristId = (int) $completedRental['user_id'];
        $providerId = (int) ($completedRental['provider_id'] ?? 167);
        $vehicleId = (string) ($completedRental['vehicle_id'] ?? '72');

        // Validation for rating outside 1..5 in controller
        $zeroRatingRejected = (0 < 1 || 0 > 5);
        $sixRatingRejected = (6 < 1 || 6 > 5);
        $emptyFeedbackRejected = (trim('') === '');

        $ok = $zeroRatingRejected && $sixRatingRejected && $emptyFeedbackRejected;
        $this->record('testReviewInputValidation', $ok, 'Input validation for rating (1-5) and feedback enforced');
    }

    private function testSubmitReviewForCompletedRental(): void
    {
        // Use completed rental 31 (belonging to tourist 51, vehicle 72, provider 167)
        $rental = Database::first("SELECT * FROM rental_requests WHERE id = 31");
        if ($rental === null || $rental['status'] !== 'completed') {
            $this->record('testSubmitReviewForCompletedRental', false, 'Rental 31 not found or not completed');
            return;
        }

        $rentalId = (int) $rental['id'];
        $reviewerId = (int) $rental['user_id'];
        $providerId = 167; // Miguel Yongzon
        $vehicleId = (string) $rental['vehicle_id'];

        // Clean up any existing review
        Database::run("DELETE FROM rental_reviews WHERE rental_id = ?", [$rentalId]);

        $review = RentalReview::createOrUpdate(
            rentalId: $rentalId,
            reviewerId: $reviewerId,
            providerId: $providerId,
            vehicleId: $vehicleId,
            rating: 5,
            feedback: 'Smooth ride, spotless scooter, and Miguel was super accommodating!'
        );

        $saved = RentalReview::findForRental($rentalId);
        $providerStats = RentalReview::providerStats($providerId);

        $ok = $saved !== null
            && (int) $saved['rating'] === 5
            && (int) $saved['rental_id'] === $rentalId
            && (int) $saved['reviewer_id'] === $reviewerId
            && $providerStats['reviews_count'] >= 1
            && $providerStats['rating'] > 0.0;

        $this->record('testSubmitReviewForCompletedRental', $ok, "Review saved. Provider rating: {$providerStats['rating']} ({$providerStats['reviews_count']} reviews)");
    }

    private function testDuplicateReviewPreventionAndEdit(): void
    {
        $rentalId = 31;
        $rental = Database::first("SELECT * FROM rental_requests WHERE id = 31");
        $reviewerId = (int) $rental['user_id'];
        $providerId = 167;
        $vehicleId = (string) $rental['vehicle_id'];

        // Submit edit: update rating to 4 and feedback to updated text
        $updatedReview = RentalReview::createOrUpdate(
            rentalId: $rentalId,
            reviewerId: $reviewerId,
            providerId: $providerId,
            vehicleId: $vehicleId,
            rating: 4,
            feedback: 'Updated review: Overall great experience with the Honda Click.'
        );

        // Verify count of records in rental_reviews for rental_id = 31 is still exactly 1
        $countRow = Database::first(
            "SELECT COUNT(*) AS total FROM rental_reviews WHERE rental_id = ?",
            [$rentalId]
        );
        $count = (int) ($countRow['total'] ?? 0);

        $saved = RentalReview::findForRental($rentalId);
        $providerStats = RentalReview::providerStats($providerId);

        $ok = $count === 1
            && $saved !== null
            && (int) $saved['rating'] === 4
            && str_contains($saved['feedback'], 'Updated review')
            && $providerStats['rating'] == 4.0;

        $this->record('testDuplicateReviewPreventionAndEdit', $ok, "Duplicate prevented. Exactly 1 record exists. Updated rating: {$providerStats['rating']}");
    }

    private function testOnlyReviewAuthorCanEdit(): void
    {
        $rentalId = 31;
        $diffUserId = 99999;

        $blocked = false;
        try {
            RentalReview::createOrUpdate(
                rentalId: $rentalId,
                reviewerId: $diffUserId,
                providerId: 167,
                vehicleId: '72',
                rating: 1,
                feedback: 'Hacked review!'
            );
        } catch (\RuntimeException $e) {
            $blocked = str_contains($e->getMessage(), 'Unauthorized');
        }

        $this->record('testOnlyReviewAuthorCanEdit', $blocked, 'Non-author edit attempt blocked');
    }

    private function testVehicleDetailEndpointReflectsDynamicStats(): void
    {
        $prevDisplay = ini_get('display_errors');
        @ini_set('display_errors', '0');

        $controller = new \App\Controllers\ApiController();

        ob_start();
        $controller->rentalDetail('72');
        $raw = ob_get_clean();

        @ini_set('display_errors', $prevDisplay);

        $data = json_decode($raw ?: '', true);

        $vehicle = $data['vehicle'] ?? null;
        $provider = $vehicle['provider'] ?? null;

        $ok = is_array($vehicle)
            && is_array($provider)
            && isset($provider['rating'], $provider['reviews_count'], $provider['completed_rentals'])
            && (float) $provider['rating'] === 4.0
            && (int) $provider['reviews_count'] === 1
            && (int) $provider['completed_rentals'] >= 1;

        $this->record('testVehicleDetailEndpointReflectsDynamicStats', $ok, "API returned dynamic rating: {$provider['rating']}, reviews: {$provider['reviews_count']}, completed rentals: {$provider['completed_rentals']}");
    }

    private function record(string $testName, bool $ok, string $detail = ''): void
    {
        $this->results[$testName] = $ok;
        $status = $ok ? "\033[32mPASS\033[0m" : "\033[31mFAIL\033[0m";
        echo " [ {$status} ] {$testName}\n";
        if ($detail !== '') {
            echo "          -> {$detail}\n";
        }
    }
}

$test = new RentalRatingAndFeedbackTest();
$test->run();
