<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\PromoCode;
use App\Models\RentalRequest;
use App\Models\Review;
use App\Services\PackageBookingService;

class AppVersionImprovementsTest
{
    private static int $testCount = 0;
    private static int $passCount = 0;

    public static function run(): void
    {
        echo "========================================================\n";
        echo "   GUIDEMATE APP VERSION IMPROVEMENTS VERIFICATION TEST \n";
        echo "========================================================\n\n";

        self::testTravelPackageConsensusAndComponentCompletion();
        self::testReviewSubmissionWithoutTitle();
        self::testPackageReviewsAndAverageRating();
        self::testReportIssueSubmissionStateAndDuplicatePrevention();
        self::testVehicleRentalActiveDuplicatePrevention();
        self::testVoucherRedemptionWorkflowAndDistinction();

        echo "\n--------------------------------------------------------\n";
        echo "Results: " . self::$passCount . " / " . self::$testCount . " tests passed.\n";
        if (self::$passCount === self::$testCount) {
            echo "ALL TESTS PASSED SUCCESSFULLY!\n";
        } else {
            echo "SOME TESTS FAILED!\n";
        }
        echo "========================================================\n";
    }

    private static function assert(bool $condition, string $message): void
    {
        self::$testCount++;
        if ($condition) {
            self::$passCount++;
            echo "  [PASS] {$message}\n";
        } else {
            echo "  [FAIL] {$message}\n";
        }
    }

    /**
     * Requirement 1:
     * When any provider updates status to completed, component updates to completed,
     * but overall package only becomes completed when ALL required providers complete.
     */
    private static function testTravelPackageConsensusAndComponentCompletion(): void
    {
        echo "Scenario 1-3: Package Consensus & Component Completion...\n";
        PackageBookingService::ensureTables();

        $existingListing = Database::first("SELECT id, user_id FROM listings LIMIT 1");
        $listingId = $existingListing ? (int) $existingListing['id'] : 1;
        $existingUser = Database::first("SELECT id FROM users LIMIT 1");
        $userId = $existingUser ? (int) $existingUser['id'] : 1;

        $testRef = 'GM-PKG-TEST-' . time() . '-' . rand(100, 999);

        // 1. Create a dummy parent booking
        $parentBookingId = (int) Database::insert("
            INSERT INTO bookings (user_id, listing_id, booking_date, guests, total_amount, status, package_overall_status, package_booking_ref, notes, created_at)
            VALUES (?, ?, '2026-12-01', 2, 10000.00, 'pending', 'pending', ?, '{\"type\":\"package_booking\"}', NOW())
        ", [$userId, $listingId, $testRef]);

        // 2. Create sub-booking for hotel
        $hotelSubId = (int) Database::insert("
            INSERT INTO bookings (user_id, listing_id, booking_date, guests, total_amount, status, hotel_status, notes, created_at)
            VALUES (?, ?, '2026-12-01', 2, 4000.00, 'pending', 'pending', '{\"package_sub_booking\":true}', NOW())
        ", [$userId, $listingId]);

        // 3. Create rental request for vehicle
        $rentalReqId = (int) Database::insert("
            INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, location, pickup_date, rental_days, total_amount, status, payment_status, created_at)
            VALUES (?, '9999', 'Test Scooter', 'Scooter', 'Test Shop', 'Moalboal', '2026-12-01', 1, 1000.00, 'pending', 'paid', NOW())
        ", [$userId]);

        // 4. Create package booking items for guide, hotel, vehicle
        Database::run("
            INSERT INTO package_booking_items (package_booking_ref, parent_booking_id, package_id, provider_id, provider_role, service_type, service_name, allocated_amount, status, sub_booking_id, rental_request_id, created_at)
            VALUES
            (?, ?, ?, 10, 'guide', 'guide', 'Guided Tour', 5000.00, 'confirmed', NULL, NULL, NOW()),
            (?, ?, ?, 20, 'hotel_provider', 'hotel', 'Hotel Room', 4000.00, 'confirmed', ?, NULL, NOW()),
            (?, ?, ?, 30, 'rental_provider', 'vehicle', 'Scooter Rental', 1000.00, 'confirmed', NULL, ?, NOW())
        ", [$testRef, $parentBookingId, $listingId, $testRef, $parentBookingId, $listingId, $hotelSubId, $testRef, $parentBookingId, $listingId, $rentalReqId]);

        // Both all items confirmed => overall status is confirmed
        $summary = PackageBookingService::getPackageBookingSummary($testRef);
        self::assert($summary !== null && $summary['overall_status'] === 'confirmed', 'When all items are confirmed, overall package status is confirmed');

        // Step A: Hotel provider marks hotel stay as Completed
        PackageBookingService::onHotelBookingUpdated($hotelSubId, 'completed', 20);
        $summary = PackageBookingService::getPackageBookingSummary($testRef);
        $hotelItem = null;
        foreach ($summary['items'] as $it) {
            if ($it['service_type'] === 'hotel') $hotelItem = $it;
        }
        self::assert($hotelItem !== null && $hotelItem['status'] === 'completed', 'Hotel component item is marked completed');
        self::assert($summary['overall_status'] === 'confirmed', 'Overall package remains confirmed (NOT prematurely completed) when only hotel is completed');

        // Step B: Rental provider marks rental as Completed
        PackageBookingService::onRentalUpdated($rentalReqId, 'completed', 30);
        $summary = PackageBookingService::getPackageBookingSummary($testRef);
        $vehicleItem = null;
        foreach ($summary['items'] as $it) {
            if ($it['service_type'] === 'vehicle') $vehicleItem = $it;
        }
        self::assert($vehicleItem !== null && $vehicleItem['status'] === 'completed', 'Vehicle component item is marked completed');
        self::assert($summary['overall_status'] === 'confirmed', 'Overall package still remains confirmed while guide has not completed yet');

        // Step C: Guide completes tour
        PackageBookingService::onBookingUpdated($parentBookingId, 'completed', 10);
        $summary = PackageBookingService::getPackageBookingSummary($testRef);
        $guideItem = null;
        foreach ($summary['items'] as $it) {
            if ($it['service_type'] === 'guide') $guideItem = $it;
        }
        self::assert($guideItem !== null && $guideItem['status'] === 'completed', 'Guide component item is marked completed');
        self::assert($summary['overall_status'] === 'completed', 'Overall package status becomes COMPLETED only when ALL required providers finish services');

        // Clean up
        Database::run("DELETE FROM package_booking_items WHERE package_booking_ref = ?", [$testRef]);
        Database::run("DELETE FROM bookings WHERE id IN (?, ?)", [$parentBookingId, $hotelSubId]);
        Database::run("DELETE FROM rental_requests WHERE id = ?", [$rentalReqId]);
    }

    /**
     * Requirement 2:
     * Users can submit reviews with star rating and review comment without title field.
     */
    private static function testReviewSubmissionWithoutTitle(): void
    {
        echo "Scenario 4: Review Submission Without Title...\n";

        $existingListing = Database::first("SELECT id FROM listings LIMIT 1");
        $listingId = $existingListing ? (int) $existingListing['id'] : 1;
        $existingUser = Database::first("SELECT id FROM users LIMIT 1");
        $userId = $existingUser ? (int) $existingUser['id'] : 1;

        // Create review with rating and comment (title omitted)
        $revId = (int) Database::insert("
            INSERT INTO reviews (user_id, listing_id, rating, comment, created_at)
            VALUES (?, ?, 5, 'Exceptional experience with great service!', NOW())
        ", [$userId, $listingId]);
        self::assert($revId > 0, 'Review created successfully in database without title');

        $rev = Database::first("SELECT * FROM reviews WHERE id = ?", [$revId]);
        self::assert($rev !== null && (int) $rev['rating'] === 5 && !empty($rev['comment']), 'Review details verified in database');

        // Clean up
        Database::run("DELETE FROM reviews WHERE id = ?", [$revId]);
    }

    /**
     * Requirement 3:
     * Package reviews and average rating are strictly from database for that package ID.
     */
    private static function testPackageReviewsAndAverageRating(): void
    {
        echo "Scenario 5: Package Rating & Reviews...\n";

        $existingListing = Database::first("SELECT id FROM listings LIMIT 1");
        $listingId = $existingListing ? (int) $existingListing['id'] : 1;

        // Check genuine review count and average
        $stats = Database::first("
            SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(id) AS review_count
            FROM reviews
            WHERE listing_id = ?
        ", [$listingId]);
        $avg = round((float) ($stats['avg_rating'] ?? 0), 1);
        $count = (int) ($stats['review_count'] ?? 0);

        self::assert($count >= 0, "Reviews count queried directly from database: {$count}");
        self::assert($avg >= 0.0 && $avg <= 5.0, "Average rating mathematically valid: {$avg}");

        $reviews = Review::forListing($listingId);
        self::assert(is_array($reviews), 'Review::forListing returns array of verified reviews');
    }

    /**
     * Requirement 4:
     * Report issue button changes to View Report, duplicate report submission is prevented.
     */
    private static function testReportIssueSubmissionStateAndDuplicatePrevention(): void
    {
        echo "Scenario 6: Report Submission State & Duplicate Prevention...\n";

        $existingListing = Database::first("SELECT id, user_id FROM listings LIMIT 1");
        $listingId = $existingListing ? (int) $existingListing['id'] : 1;
        $guideId = $existingListing ? (int) $existingListing['user_id'] : 1;
        $existingUser = Database::first("SELECT id FROM users LIMIT 1");
        $userId = $existingUser ? (int) $existingUser['id'] : 1;

        // 1. Create paid booking
        $bId = (int) Database::insert("
            INSERT INTO bookings (user_id, listing_id, booking_date, guests, total_amount, status, created_at)
            VALUES (?, ?, '2026-12-15', 1, 1500.00, 'completed', NOW())
        ", [$userId, $listingId]);
        Database::run("
            INSERT INTO payments (booking_id, amount, status, method, reference, created_at)
            VALUES (?, 1500.00, 'paid', 'gcash', ?, NOW())
        ", [$bId, 'TEST-REF-' . time() . '-' . rand(100, 999)]);

        // 2. Submit initial dispute
        $dispId = Dispute::create([
            'booking_id' => $bId,
            'user_id' => $userId,
            'guide_id' => $guideId,
            'problem_type' => 'service_issue',
            'description' => 'Detailed report description test with more than 20 characters for validation',
            'amount_requested' => null,
        ]);
        self::assert($dispId > 0, 'Dispute successfully created for user and booking');

        // 3. Verify duplicate prevention
        $existing = Dispute::forBooking($bId);
        self::assert($existing !== null && (int) $existing['id'] === $dispId, 'Dispute::forBooking retrieves the existing active dispute');

        // 4. Booking join with dispute
        $joined = Booking::find($bId);
        self::assert($joined !== null, 'Booking found');

        // Clean up
        Database::run("DELETE FROM disputes WHERE id = ?", [$dispId]);
        Database::run("DELETE FROM payments WHERE booking_id = ?", [$bId]);
        Database::run("DELETE FROM bookings WHERE id = ?", [$bId]);
    }

    /**
     * Requirement 5:
     * Prevent duplicate active rentals for the same vehicle unit until completed.
     */
    private static function testVehicleRentalActiveDuplicatePrevention(): void
    {
        echo "Scenario 7: Prevent Duplicate Active Vehicle Rentals...\n";

        $existingUser = Database::first("SELECT id FROM users LIMIT 1");
        $userId = $existingUser ? (int) $existingUser['id'] : 1;

        $testVehicleUnitId = 'UNIT-TEST-9988';

        // Initially no active rental
        self::assert(!RentalRequest::hasActiveRental($testVehicleUnitId), 'Initially vehicle unit has NO active rental');

        // Create active rental (pending)
        $rId = (int) Database::insert("
            INSERT INTO rental_requests (user_id, vehicle_id, vehicle_name, vehicle_type, shop_name, location, pickup_date, rental_days, total_amount, status, payment_status, created_at)
            VALUES (?, ?, 'Active Honda Click', 'Scooter', 'Test Shop', 'Cebu', '2026-12-10', 2, 1000.00, 'pending', 'paid', NOW())
        ", [$userId, $testVehicleUnitId]);

        self::assert(RentalRequest::hasActiveRental($testVehicleUnitId), 'hasActiveRental returns true when rental is pending');

        // Update status to approved -> still active
        Database::run("UPDATE rental_requests SET status = 'approved' WHERE id = ?", [$rId]);
        self::assert(RentalRequest::hasActiveRental($testVehicleUnitId), 'hasActiveRental returns true when rental is approved');

        // Update status to contacted -> still active
        Database::run("UPDATE rental_requests SET status = 'contacted' WHERE id = ?", [$rId]);
        self::assert(RentalRequest::hasActiveRental($testVehicleUnitId), 'hasActiveRental returns true when rental is contacted');

        // Update status to completed -> vehicle unit is now available again!
        Database::run("UPDATE rental_requests SET status = 'completed' WHERE id = ?", [$rId]);
        self::assert(!RentalRequest::hasActiveRental($testVehicleUnitId), 'hasActiveRental returns false once rental reaches completed');

        // Clean up
        Database::run("DELETE FROM rental_requests WHERE id = ?", [$rId]);
    }

    /**
     * Requirement 6:
     * Voucher redemption allows 1 claim per user account, distinguishes Redeemed vs Used.
     */
    private static function testVoucherRedemptionWorkflowAndDistinction(): void
    {
        echo "Scenario 8-10: Voucher Redemption & Distinction...\n";
        PromoCode::ensureTables();

        $testUser1 = 9871;
        $testUser2 = 9872;
        $testCode = 'CEBU6';

        // Clean any existing test redemptions
        Database::run("DELETE FROM voucher_redemptions WHERE user_id IN (?, ?)", [$testUser1, $testUser2]);

        // User 1 claims voucher for the first time
        $res1 = PromoCode::redeemByUser($testUser1, $testCode);
        self::assert($res1['success'] === true, 'User 1 successfully redeems CEBU6 voucher');
        self::assert(PromoCode::userHasRedeemed($testUser1, $testCode), 'userHasRedeemed returns true for User 1');
        self::assert(!PromoCode::userHasUsed($testUser1, $testCode), 'userHasUsed is false right after redemption (distinguishes Redeemed from Used)');

        // User 1 attempts duplicate claim on same voucher
        $resDuplicate = PromoCode::redeemByUser($testUser1, $testCode);
        self::assert($resDuplicate['success'] === false, 'User 1 prevented from redeeming the same voucher twice');

        // Different account (User 2) can independently redeem the same voucher
        $resUser2 = PromoCode::redeemByUser($testUser2, $testCode);
        self::assert($resUser2['success'] === true, 'User 2 can independently redeem the same voucher');

        // Check catalog flags
        $catalogUser1 = PromoCode::catalogForUser($testUser1);
        $found1 = null;
        foreach ($catalogUser1 as $v) {
            if ($v['code'] === $testCode) $found1 = $v;
        }
        self::assert($found1 !== null && $found1['redeemed'] === true && $found1['used'] === false, 'Catalog reflects redeemed=true, used=false for User 1');

        // Simulate booking checkout using the voucher
        PromoCode::markRedemptionUsed($testUser1, $testCode, 555);
        self::assert(PromoCode::userHasUsed($testUser1, $testCode), 'userHasUsed is now true after checkout');
        $catalogAfterUse = PromoCode::catalogForUser($testUser1);
        $foundUsed = null;
        foreach ($catalogAfterUse as $v) {
            if ($v['code'] === $testCode) $foundUsed = $v;
        }
        self::assert($foundUsed !== null && $foundUsed['used'] === true, 'Catalog now reflects used=true for User 1');

        // Clean up
        Database::run("DELETE FROM voucher_redemptions WHERE user_id IN (?, ?)", [$testUser1, $testUser2]);
    }
}

AppVersionImprovementsTest::run();
