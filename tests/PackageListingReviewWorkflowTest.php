<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\Booking;
use App\Models\Review;
use App\Controllers\ApiController;

final class PackageListingReviewWorkflowTest
{
    private int $passCount = 0;
    private int $failCount = 0;
    private array $cleanUpUserIds = [];
    private array $cleanUpListingIds = [];
    private array $cleanUpBookingIds = [];
    private array $cleanUpReviewIds = [];

    public function run(): void
    {
        echo "\n====================================================================\n";
        echo " GuideMate Package Listing Review Functionality Test Suite\n";
        echo "====================================================================\n\n";

        try {
            $this->testPackageReviewWorkflow();
            $this->testEdgeCasesAndValidation();
            $this->testRegularListingPreservation();
        } finally {
            $this->tearDown();
        }

        echo "\n--------------------------------------------------------------------\n";
        echo "Results: {$this->passCount} passed, {$this->failCount} failed\n";
        echo "--------------------------------------------------------------------\n\n";

        if ($this->failCount > 0) {
            exit(1);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo " [PASS] {$message}\n";
            $this->passCount++;
        } else {
            echo " [FAIL] {$message}\n";
            $this->failCount++;
        }
    }

    private function tearDown(): void
    {
        if ($this->cleanUpReviewIds !== []) {
            $in = implode(',', array_map('intval', $this->cleanUpReviewIds));
            Database::run("DELETE FROM reviews WHERE id IN ($in)");
        }
        if ($this->cleanUpBookingIds !== []) {
            $in = implode(',', array_map('intval', $this->cleanUpBookingIds));
            Database::run("DELETE FROM bookings WHERE id IN ($in)");
        }
        if ($this->cleanUpListingIds !== []) {
            $in = implode(',', array_map('intval', $this->cleanUpListingIds));
            Database::run("DELETE FROM listings WHERE id IN ($in)");
        }
        if ($this->cleanUpUserIds !== []) {
            $in = implode(',', array_map('intval', $this->cleanUpUserIds));
            Database::run("DELETE FROM api_tokens WHERE user_id IN ($in)");
            Database::run("DELETE FROM users WHERE id IN ($in)");
        }
    }

    private function createTourist(): array
    {
        $suffix = bin2hex(random_bytes(4));
        $email = "tourist_$suffix@test.com";
        $id = Database::insert(
            "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, 'hash', 'tourist', 1, NOW())",
            ["Tourist $suffix", $email]
        );
        $this->cleanUpUserIds[] = $id;
        $user = User::find($id);

        $token = \App\Core\ApiAuth::issue($id);
        $user['api_token'] = $token;
        return $user;
    }

    private function createGuide(): array
    {
        $suffix = bin2hex(random_bytes(4));
        $email = "guide_$suffix@test.com";
        $id = Database::insert(
            "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES (?, ?, 'hash', 'guide', 1, NOW())",
            ["Guide $suffix", $email]
        );
        $this->cleanUpUserIds[] = $id;
        return User::find($id);
    }

    private function createPackageListing(int $guideId): array
    {
        $suffix = bin2hex(random_bytes(4));
        $catPackages = (int) (Database::first("SELECT id FROM categories WHERE slug = 'packages' LIMIT 1")['id'] ?? 4);

        $listingId = Database::insert("
            INSERT INTO listings (
                user_id, category_id, title, slug, summary, description, area, price, price_unit,
                duration, capacity, status, package_approval_status, created_at
            ) VALUES (
                ?, ?, ?, ?, 'Multi-day island adventure', 'Full package description', 'Cebu & Bohol', 9500.00, 'package',
                '3 Days 2 Nights', 8, 'approved', 'agreed', NOW()
            )
        ", [
            $guideId,
            $catPackages,
            "South Cebu & Bohol Adventure $suffix",
            "south-cebu-bohol-$suffix"
        ]);
        $this->cleanUpListingIds[] = $listingId;
        return Listing::find($listingId);
    }

    private function createRegularListing(int $guideId): array
    {
        $suffix = bin2hex(random_bytes(4));
        $catThings = (int) (Database::first("SELECT id FROM categories WHERE slug = 'things-to-do' LIMIT 1")['id'] ?? 1);

        $listingId = Database::insert("
            INSERT INTO listings (
                user_id, category_id, title, slug, summary, description, area, price, price_unit,
                duration, capacity, status, created_at
            ) VALUES (
                ?, ?, ?, ?, 'Exciting canyon jump experience', 'Full canyon tour details', 'Badian, Cebu', 1800.00, 'per person',
                '4 hours', 12, 'approved', NOW()
            )
        ", [
            $guideId,
            $catThings,
            "Kawasan Canyoneering $suffix",
            "kawasan-canyoneering-$suffix"
        ]);
        $this->cleanUpListingIds[] = $listingId;
        return Listing::find($listingId);
    }

    private function mockApiAuth(array $user): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . ($user['api_token'] ?? '');
    }

    private function callSubmitReview(array $payload): array
    {
        $_POST = [];
        $raw = json_encode($payload);
        // ApiController reads from php://input, so we use a custom test wrapper or reflection to inject jsonBody
        $controller = new ApiController();
        $ref = new \ReflectionClass($controller);

        ob_start();
        try {
            // Set input body via property or mock jsonBody
            // In ApiController, jsonBody reads file_get_contents('php://input') or $_POST
            $_POST = $payload;
            $controller->submitReview();
            $output = ob_get_clean();
            $data = json_decode($output, true) ?? [];
            return ['code' => http_response_code(), 'data' => $data, 'raw' => $output];
        } catch (\Throwable $e) {
            ob_end_clean();
            return ['code' => 500, 'error' => $e->getMessage()];
        }
    }

    private function getBookingPayload(int $bookingId, int $userId): array
    {
        $booking = Booking::find($bookingId);
        $controller = new ApiController();
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('bookingPayload');
        $method->setAccessible(true);
        return $method->invoke($controller, $booking, $userId);
    }

    private function testPackageReviewWorkflow(): void
    {
        echo "1. Testing Package Tour Review Workflow & Duplicate Prevention...\n";

        $tourist = $this->createTourist();
        $guide = $this->createGuide();
        $pkg = $this->createPackageListing((int) $guide['id']);
        $this->mockApiAuth($tourist);

        // A. Incomplete booking (confirmed, but not completed)
        $pkgRef1 = 'GM-PKG-TEST-' . bin2hex(random_bytes(3));
        $booking1Id = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
                status, package_booking_ref, package_overall_status, created_at
            ) VALUES (?, ?, '2026-12-01', '2026-12-01', '2026-12-03', 2, 2, 9500.00, 'confirmed', ?, 'confirmed', NOW())
        ", [(int) $pkg['id'], (int) $tourist['id'], $pkgRef1]);
        $this->cleanUpBookingIds[] = $booking1Id;

        $payloadIncomplete = $this->getBookingPayload($booking1Id, (int) $tourist['id']);
        $this->assert($payloadIncomplete['can_review'] === false, 'Confirmed but incomplete package booking has can_review = false');
        $this->assert($payloadIncomplete['reviewed'] === false, 'Incomplete package booking has reviewed = false');

        // Attempting review while incomplete must fail with 403
        http_response_code(200);
        $resIncomplete = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'booking_id' => $booking1Id,
            'rating' => 5,
            'comment' => 'Should fail because not completed',
        ]);
        $this->assert($resIncomplete['code'] === 403, 'Submitting review for incomplete package booking returns 403 Forbidden');
        $this->assert(
            str_contains($resIncomplete['data']['error'] ?? '', 'completed'),
            'Error message indicates booking must be completed'
        );

        // B. Cancelled booking cannot be reviewed
        Database::run("UPDATE bookings SET status = 'cancelled', package_overall_status = 'cancelled' WHERE id = ?", [$booking1Id]);
        $payloadCancelled = $this->getBookingPayload($booking1Id, (int) $tourist['id']);
        $this->assert($payloadCancelled['can_review'] === false, 'Cancelled package booking has can_review = false');

        http_response_code(200);
        $resCancelled = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'booking_id' => $booking1Id,
            'rating' => 5,
            'comment' => 'Should fail because cancelled',
        ]);
        $this->assert($resCancelled['code'] === 403, 'Submitting review for cancelled package booking returns 403 Forbidden');

        // C. Completed booking becomes eligible for review
        Database::run("UPDATE bookings SET status = 'completed', package_overall_status = 'completed' WHERE id = ?", [$booking1Id]);
        $payloadCompleted = $this->getBookingPayload($booking1Id, (int) $tourist['id']);
        $this->assert($payloadCompleted['can_review'] === true, 'Completed package booking has can_review = true');
        $this->assert($payloadCompleted['reviewed'] === false, 'Completed package booking before review has reviewed = false');

        // D. Submit first review for this completed booking
        http_response_code(200);
        $resSubmit1 = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'booking_id' => $booking1Id,
            'rating' => 5,
            'comment' => 'The multi-day package was breathtaking and completely seamless!',
            'title' => 'Unforgettable Experience',
        ]);
        $this->assert($resSubmit1['code'] === 201, 'Submitting first review for completed package booking returns 201 Created');
        $this->assert($resSubmit1['data']['success'] === true, 'Response indicates success = true');

        // Verify review in DB has booking_id populated
        $savedRev1 = Database::first("SELECT * FROM reviews WHERE booking_id = ?", [$booking1Id]);
        $this->assert($savedRev1 !== null, 'Review record was created with booking_id');
        $this->assert((int) $savedRev1['rating'] === 5, 'Review record stores correct rating');
        if ($savedRev1 !== null) {
            $this->cleanUpReviewIds[] = (int) $savedRev1['id'];
        }

        // E. After review submitted, booking status reflects reviewed
        $payloadAfterReview = $this->getBookingPayload($booking1Id, (int) $tourist['id']);
        $this->assert($payloadAfterReview['can_review'] === false, 'Reviewed completed booking now has can_review = false');
        $this->assert($payloadAfterReview['reviewed'] === true, 'Reviewed completed booking now has reviewed = true');

        // F. Prevent duplicate review for the same completed package booking
        http_response_code(200);
        $resDuplicate = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'booking_id' => $booking1Id,
            'rating' => 4,
            'comment' => 'Trying to submit a duplicate review for the same booking!',
        ]);
        $this->assert($resDuplicate['code'] === 409, 'Attempting second review for the same booking returns 409 Conflict');
        $this->assert(
            str_contains($resDuplicate['data']['error'] ?? '', 'already reviewed'),
            'Error message indicates booking was already reviewed'
        );

        // G. Database level UNIQUE constraint prevents duplicate review for same booking_id
        $dbDuplicateBlocked = false;
        try {
            Review::create((int) $pkg['id'], (int) $tourist['id'], 3, 'dup', 'dup comment', date('Y-m-d'), $booking1Id);
        } catch (\PDOException $e) {
            $dbDuplicateBlocked = true;
        }
        $this->assert($dbDuplicateBlocked, 'Database UNIQUE KEY unique_booking_review rejects duplicate booking_id insertion');

        // =====================================================================
        // H. RE-BOOKING FLOW: Traveler books and completes the package AGAIN
        // =====================================================================
        echo "\n2. Testing Re-booking Flow: Traveler books and completes the package a second time...\n";

        $pkgRef2 = 'GM-PKG-TEST2-' . bin2hex(random_bytes(3));
        $booking2Id = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount,
                status, package_booking_ref, package_overall_status, created_at
            ) VALUES (?, ?, '2026-12-20', '2026-12-20', '2026-12-22', 2, 2, 9500.00, 'confirmed', ?, 'confirmed', NOW())
        ", [(int) $pkg['id'], (int) $tourist['id'], $pkgRef2]);
        $this->cleanUpBookingIds[] = $booking2Id;

        // While booking 2 is confirmed:
        $p1 = $this->getBookingPayload($booking1Id, (int) $tourist['id']);
        $p2 = $this->getBookingPayload($booking2Id, (int) $tourist['id']);
        $this->assert($p1['can_review'] === false, 'Booking 1 remains can_review = false');
        $this->assert($p2['can_review'] === false, 'Booking 2 (confirmed) has can_review = false');

        // Mark booking 2 as completed
        Database::run("UPDATE bookings SET status = 'completed', package_overall_status = 'completed' WHERE id = ?", [$booking2Id]);
        $p2Completed = $this->getBookingPayload($booking2Id, (int) $tourist['id']);
        $this->assert($p2Completed['can_review'] === true, 'Booking 2 (completed) is now eligible: can_review = true');
        $this->assert($p2Completed['reviewed'] === false, 'Booking 2 has reviewed = false');

        // Review booking 2
        http_response_code(200);
        $resSubmit2 = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'booking_id' => $booking2Id,
            'rating' => 4,
            'comment' => 'Second trip on this package was just as wonderful!',
            'title' => 'Great Second Adventure',
        ]);
        $this->assert($resSubmit2['code'] === 201, 'Submitting review for newly completed second booking returns 201 Created');

        $savedRev2 = Database::first("SELECT * FROM reviews WHERE booking_id = ?", [$booking2Id]);
        $this->assert($savedRev2 !== null, 'Second review record was created with booking_id 2');
        if ($savedRev2 !== null) {
            $this->cleanUpReviewIds[] = (int) $savedRev2['id'];
        }

        // Now both bookings are reviewed
        $p1Final = $this->getBookingPayload($booking1Id, (int) $tourist['id']);
        $p2Final = $this->getBookingPayload($booking2Id, (int) $tourist['id']);
        $this->assert($p1Final['can_review'] === false, 'Booking 1 final can_review = false');
        $this->assert($p2Final['can_review'] === false, 'Booking 2 final can_review = false');

        // Attempting to review booking 2 again must fail with 409
        http_response_code(200);
        $resDup2 = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'booking_id' => $booking2Id,
            'rating' => 5,
            'comment' => 'Duplicate attempt for booking 2',
        ]);
        $this->assert($resDup2['code'] === 409, 'Submitting duplicate for second booking returns 409 Conflict');
    }

    private function testEdgeCasesAndValidation(): void
    {
        echo "\n3. Testing Edge Cases and Validation...\n";

        $tourist1 = $this->createTourist();
        $tourist2 = $this->createTourist();
        $guide = $this->createGuide();
        $pkg = $this->createPackageListing((int) $guide['id']);

        // A. Unauthorized (no token)
        unset($_SERVER['HTTP_AUTHORIZATION']);
        http_response_code(200);
        $resNoAuth = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'rating' => 5,
            'comment' => 'Should fail auth',
        ]);
        $this->assert($resNoAuth['code'] === 401, 'Unauthenticated request returns 401 Unauthorized');

        $this->mockApiAuth($tourist1);

        // B. Non-existent listing
        http_response_code(200);
        $resNotFound = $this->callSubmitReview([
            'listing_id' => 99999999,
            'rating' => 5,
            'comment' => 'Listing does not exist',
        ]);
        $this->assert($resNotFound['code'] === 404, 'Non-existent listing returns 404 Not Found');

        // C. Rating validation (0 or 6)
        http_response_code(200);
        $resInvalidRating = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'rating' => 6,
            'comment' => 'Invalid star rating',
        ]);
        $this->assert($resInvalidRating['code'] === 422, 'Rating of 6 returns 422 Unprocessable');

        // D. Empty comment validation
        http_response_code(200);
        $resEmptyComment = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'rating' => 5,
            'comment' => '   ',
        ]);
        $this->assert($resEmptyComment['code'] === 422, 'Empty review comment returns 422 Unprocessable');

        // E. Booking belonging to another user
        $bookingTourist2 = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, status, package_overall_status, created_at
            ) VALUES (?, ?, '2026-11-20', 'completed', 'completed', NOW())
        ", [(int) $pkg['id'], (int) $tourist2['id']]);
        $this->cleanUpBookingIds[] = $bookingTourist2;

        http_response_code(200);
        $resOtherUser = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'booking_id' => $bookingTourist2,
            'rating' => 5,
            'comment' => 'Trying to review another user booking',
        ]);
        $this->assert($resOtherUser['code'] === 404, 'Reviewing another traveler booking returns 404 Not Found / access denied');

        // F. User with no completed bookings attempting to review without booking_id
        http_response_code(200);
        $resNoBookings = $this->callSubmitReview([
            'listing_id' => (int) $pkg['id'],
            'rating' => 5,
            'comment' => 'Never booked this package',
        ]);
        $this->assert($resNoBookings['code'] === 403, 'User with no completed bookings returns 403 Forbidden');
    }

    private function testRegularListingPreservation(): void
    {
        echo "\n4. Testing Regular Listing Reviews Preservation...\n";

        $tourist = $this->createTourist();
        $guide = $this->createGuide();
        $regListing = $this->createRegularListing((int) $guide['id']);
        $this->mockApiAuth($tourist);

        // A. Regular listing completed booking allows review
        $regBooking1 = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, status, guests, total_amount, created_at
            ) VALUES (?, ?, '2026-11-15', 'completed', 2, 3600.00, NOW())
        ", [(int) $regListing['id'], (int) $tourist['id']]);
        $this->cleanUpBookingIds[] = $regBooking1;

        $payload = $this->getBookingPayload($regBooking1, (int) $tourist['id']);
        $this->assert($payload['can_review'] === true, 'Regular listing completed booking has can_review = true');

        // Submit review for regular listing
        http_response_code(200);
        $resReg1 = $this->callSubmitReview([
            'listing_id' => (int) $regListing['id'],
            'booking_id' => $regBooking1,
            'rating' => 5,
            'comment' => 'Spectacular canyoneering experience with safety marshals!',
        ]);
        $this->assert($resReg1['code'] === 201, 'Regular listing review returns 201 Created');

        $savedRev = Database::first("SELECT * FROM reviews WHERE booking_id = ?", [$regBooking1]);
        $this->assert($savedRev !== null, 'Regular listing review saved with booking_id');
        if ($savedRev !== null) {
            $this->cleanUpReviewIds[] = (int) $savedRev['id'];
        }

        // Duplicate review on regular listing blocked
        http_response_code(200);
        $resRegDup = $this->callSubmitReview([
            'listing_id' => (int) $regListing['id'],
            'booking_id' => $regBooking1,
            'rating' => 5,
            'comment' => 'Duplicate attempt on regular listing',
        ]);
        $this->assert($resRegDup['code'] === 409, 'Duplicate review on regular listing returns 409 Conflict');

        // B. Re-booking regular listing allows second review
        $regBooking2 = Database::insert("
            INSERT INTO bookings (
                listing_id, user_id, booking_date, status, guests, total_amount, created_at
            ) VALUES (?, ?, '2026-11-22', 'completed', 2, 3600.00, NOW())
        ", [(int) $regListing['id'], (int) $tourist['id']]);
        $this->cleanUpBookingIds[] = $regBooking2;

        $payload2 = $this->getBookingPayload($regBooking2, (int) $tourist['id']);
        $this->assert($payload2['can_review'] === true, 'Second completed regular booking has can_review = true');

        http_response_code(200);
        $resReg2 = $this->callSubmitReview([
            'listing_id' => (int) $regListing['id'],
            'booking_id' => $regBooking2,
            'rating' => 5,
            'comment' => 'Second canyoneering trip was just as good!',
        ]);
        $this->assert($resReg2['code'] === 201, 'Review on second regular booking returns 201 Created');

        $savedRev2 = Database::first("SELECT * FROM reviews WHERE booking_id = ?", [$regBooking2]);
        if ($savedRev2 !== null) {
            $this->cleanUpReviewIds[] = (int) $savedRev2['id'];
        }

        $payload2After = $this->getBookingPayload($regBooking2, (int) $tourist['id']);
        $this->assert($payload2After['can_review'] === false, 'After review, second regular booking can_review = false');
    }
}

$test = new PackageListingReviewWorkflowTest();
$test->run();
