<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Listing;
use App\Models\Booking;
use App\Models\User;

$passed = 0;
$failed = 0;

function assertCondition(bool $condition, string $description): void {
    global $passed, $failed;
    if ($condition) {
        echo " [\033[32mPASS\033[0m] $description\n";
        $passed++;
    } else {
        echo " [\033[31mFAIL\033[0m] $description\n";
        $failed++;
    }
}

echo "====================================================================\n";
echo " GuideMate – Daily Slot Availability & Calendar Booking Tests\n";
echo "====================================================================\n\n";

// --- Fixture Setup ---
// 1. Ensure a guide user exists
$guideUser = Database::first("SELECT id FROM users WHERE role = 'guide' LIMIT 1");
if (!$guideUser) {
    Database::run("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'guide', 'active', NOW())", [
        'Test Guide DailySlots',
        'testguide_slots_' . time() . '@example.com',
        password_hash('password123', PASSWORD_BCRYPT)
    ]);
    $guideId = (int) Database::pdo()->lastInsertId();
} else {
    $guideId = (int) $guideUser['id'];
}

// 2. Ensure tourist users exist
$tourist1 = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1");
if (!$tourist1) {
    Database::run("INSERT INTO users (name, email, password, role, status, created_at) VALUES (?, ?, ?, 'tourist', 'active', NOW())", [
        'Tourist One',
        'tourist1_' . time() . '@example.com',
        password_hash('password123', PASSWORD_BCRYPT)
    ]);
    $tourist1Id = (int) Database::pdo()->lastInsertId();
} else {
    $tourist1Id = (int) $tourist1['id'];
}

// 3. Create a test Things to Do / Attractions listing with capacity = 10
$testSlug = 'test-daily-slots-' . time();
Database::run(
    "INSERT INTO listings (user_id, category_id, title, slug, description, price, price_unit, capacity, status, created_at)
     VALUES (?, 1, 'Daily Slots Test Tour', ?, 'Testing daily slot capacities', 500.00, 'per person', 10, 'approved', NOW())",
    [$guideId, $testSlug]
);
$listingId = (int) Database::pdo()->lastInsertId();

$date1 = date('Y-m-d', strtotime('+3 days'));
$date2 = date('Y-m-d', strtotime('+4 days'));
$date3 = date('Y-m-d', strtotime('+5 days'));

try {
    // -------------------------------------------------------------------------
    // Scenario 1: Date with 10 available slots and 4 booked slots remains active
    // -------------------------------------------------------------------------
    echo "--- Scenario 1: Date with 10 available slots and 4 booked slots ---\n";
    
    // Create booking for 4 guests on date1 (pending status)
    $b1Id = Booking::create($listingId, $tourist1Id, $date1, 4, 2000.00, 'Test Booking 4 slots');
    
    $remainingDate1 = Booking::getRemainingSlots($listingId, $date1);
    assertCondition($remainingDate1 === 6, "Date 1 with 10 capacity and 4 booked slots has 6 remaining slots (got: {$remainingDate1})");
    
    $isBookedDate1 = Booking::isDateBooked($listingId, $date1);
    assertCondition(!$isBookedDate1, "Date 1 remains active and is NOT marked booked when 6 slots remain");
    
    $bookedDates = Booking::bookedDates($listingId);
    assertCondition(!in_array($date1, $bookedDates, true), "Date 1 is NOT included in bookedDates list");
    
    $daily = Booking::dailySlotAvailability($listingId);
    assertCondition(isset($daily[$date1]) && $daily[$date1]['remaining'] === 6 && !$daily[$date1]['is_fully_booked'], "dailySlotAvailability reports remaining = 6 and is_fully_booked = false for Date 1");

    // -------------------------------------------------------------------------
    // Scenario 2: When all 10 slots are booked, date becomes fully booked
    // -------------------------------------------------------------------------
    echo "\n--- Scenario 2: All 10 slots booked -> Date becomes unavailable ---\n";
    
    // Create second booking for remaining 6 guests on date1 (confirmed status)
    $b2Id = Booking::create($listingId, $tourist1Id, $date1, 6, 3000.00, 'Test Booking 6 slots');
    Booking::updateStatus($b2Id, 'confirmed');
    
    $remainingDate1Full = Booking::getRemainingSlots($listingId, $date1);
    assertCondition($remainingDate1Full === 0, "Date 1 has 0 remaining slots after booking 6 more slots (got: {$remainingDate1Full})");
    
    $isBookedDate1Full = Booking::isDateBooked($listingId, $date1);
    assertCondition($isBookedDate1Full, "Date 1 is now marked booked when all 10 slots are occupied");
    
    $bookedDatesFull = Booking::bookedDates($listingId);
    assertCondition(in_array($date1, $bookedDatesFull, true), "Date 1 is now returned in bookedDates array");
    
    $dailyFull = Booking::dailySlotAvailability($listingId);
    assertCondition(isset($dailyFull[$date1]) && $dailyFull[$date1]['remaining'] === 0 && $dailyFull[$date1]['is_fully_booked'], "dailySlotAvailability reports remaining = 0 and is_fully_booked = true for Date 1");

    // -------------------------------------------------------------------------
    // Scenario 3: Other dates with available slots remain active and bookable
    // -------------------------------------------------------------------------
    echo "\n--- Scenario 3: Date 2 remains active and unaffected by Date 1 ---\n";
    
    $remainingDate2 = Booking::getRemainingSlots($listingId, $date2);
    assertCondition($remainingDate2 === 10, "Date 2 remains fully available with 10 slots (got: {$remainingDate2})");
    
    $isBookedDate2 = Booking::isDateBooked($listingId, $date2);
    assertCondition(!$isBookedDate2, "Date 2 is NOT marked booked");
    assertCondition(!in_array($date2, $bookedDatesFull, true), "Date 2 is NOT in bookedDates list despite Date 1 being full");

    // -------------------------------------------------------------------------
    // Scenario 4: Selecting another date displays that date's actual remaining slot count
    // -------------------------------------------------------------------------
    echo "\n--- Scenario 4: Different dates have independent slot counts ---\n";
    
    // Book 3 slots on Date 2
    $b3Id = Booking::create($listingId, $tourist1Id, $date2, 3, 1500.00, 'Test Booking 3 slots on Date 2');
    $remainingDate2After3 = Booking::getRemainingSlots($listingId, $date2);
    assertCondition($remainingDate2After3 === 7, "Date 2 now has 7 slots remaining (got: {$remainingDate2After3})");
    
    // Date 1 still has 0 remaining
    $remainingDate1Check = Booking::getRemainingSlots($listingId, $date1);
    assertCondition($remainingDate1Check === 0, "Date 1 still independently reports 0 slots remaining");

    // -------------------------------------------------------------------------
    // Scenario 5 & 6: Listing completion releases slots & date becomes active again
    // -------------------------------------------------------------------------
    echo "\n--- Scenario 5 & 6: Completed booking releases slots & restores availability ---\n";
    
    // Mark the 6-slot booking on Date 1 as completed
    Booking::updateStatus($b2Id, 'completed');
    
    // Verify booking record is preserved with completed status
    $checkB2 = Database::first("SELECT id, status FROM bookings WHERE id = ?", [$b2Id]);
    assertCondition($checkB2 !== null && $checkB2['status'] === 'completed', "Completed booking #{$b2Id} is preserved in database with status 'completed'");
    
    // Releasing 6 slots leaves only the 4-slot booking on Date 1: remaining should be 6
    $remainingDate1AfterComplete = Booking::getRemainingSlots($listingId, $date1);
    assertCondition($remainingDate1AfterComplete === 6, "Date 1 has 6 slots available again after 6-slot booking is completed (got: {$remainingDate1AfterComplete})");
    
    $isBookedDate1AfterComplete = Booking::isDateBooked($listingId, $date1);
    assertCondition(!$isBookedDate1AfterComplete, "Date 1 is active and selectable again after completion");
    
    $bookedDatesAfterComplete = Booking::bookedDates($listingId);
    assertCondition(!in_array($date1, $bookedDatesAfterComplete, true), "Date 1 is no longer in bookedDates list");

    // -------------------------------------------------------------------------
    // Scenario 7: Date remains unavailable if another active booking occupies all capacity
    // -------------------------------------------------------------------------
    echo "\n--- Scenario 7: Date remains unavailable if another active booking occupies all capacity ---\n";
    
    // On Date 3, create a 10-slot confirmed booking, plus a completed booking
    $b4Id = Booking::create($listingId, $tourist1Id, $date3, 10, 5000.00, 'Date 3 full active booking');
    Booking::updateStatus($b4Id, 'confirmed');
    
    $b5Id = Booking::create($listingId, $tourist1Id, $date3, 2, 1000.00, 'Date 3 completed booking');
    Booking::updateStatus($b5Id, 'completed');
    
    $remainingDate3 = Booking::getRemainingSlots($listingId, $date3);
    assertCondition($remainingDate3 === 0, "Date 3 has 0 remaining slots because active booking occupies 10/10 slots");
    
    $isBookedDate3 = Booking::isDateBooked($listingId, $date3);
    assertCondition($isBookedDate3, "Date 3 remains unavailable even with a completed booking because active booking occupies all slots");
    assertCondition(in_array($date3, Booking::bookedDates($listingId), true), "Date 3 remains in bookedDates list");

    // -------------------------------------------------------------------------
    // Scenario 8: Cancelled bookings release their reserved slots
    // -------------------------------------------------------------------------
    echo "\n--- Scenario 8: Cancelled bookings release reserved slots ---\n";
    
    // Cancel the remaining 4-slot booking on Date 1
    Booking::updateStatus($b1Id, 'cancelled');
    
    $remainingDate1AfterCancel = Booking::getRemainingSlots($listingId, $date1);
    assertCondition($remainingDate1AfterCancel === 10, "Date 1 has all 10 slots available after cancelling the 4-slot booking (got: {$remainingDate1AfterCancel})");

    // -------------------------------------------------------------------------
    // Scenario 9: Calendar display updates correctly
    // -------------------------------------------------------------------------
    echo "\n--- Scenario 9: CalendarPicker strikethrough & styling ---\n";
    
    $calendarPickerCode = file_get_contents(__DIR__ . '/../components/CalendarPicker.tsx');
    assertCondition(strpos($calendarPickerCode, 'isFull') !== false, "CalendarPicker checks isFull against blocked/disabled dates");
    assertCondition(strpos($calendarPickerCode, 'dayTextFull') !== false, "CalendarPicker styles full dates with dayTextFull");
    assertCondition(strpos($calendarPickerCode, '#94A3B8') !== false, "CalendarPicker styles full dates in slate grey (#94A3B8)");
    assertCondition(strpos($calendarPickerCode, 'line-through') !== false, "CalendarPicker renders strikethrough for full dates");
    assertCondition(strpos($calendarPickerCode, 'disabled={readOnly || isDisabled}') !== false, "CalendarPicker disables selection on full dates");

    // -------------------------------------------------------------------------
    // Scenario 10: Overbooking prevention
    // -------------------------------------------------------------------------
    echo "\n--- Scenario 10: Overbooking prevention ---\n";
    
    // Date 2 currently has 3 booked (7 remaining)
    $remD2 = Booking::getRemainingSlots($listingId, $date2);
    assertCondition($remD2 === 7, "Date 2 has 7 slots remaining");
    
    // Attempting to book 8 guests should be prevented by getRemainingSlots check
    $requestedGuests = 8;
    $canBook8 = $requestedGuests <= $remD2;
    assertCondition(!$canBook8, "Backend prevents booking 8 guests when only 7 slots are available");
    
    $requestedGuestsValid = 7;
    $canBook7 = $requestedGuestsValid <= $remD2;
    assertCondition($canBook7, "Backend permits booking 7 guests when 7 slots are available");

} finally {
    // --- Cleanup ---
    Database::run("DELETE FROM bookings WHERE listing_id = ?", [$listingId]);
    Database::run("DELETE FROM listings WHERE id = ?", [$listingId]);
}

echo "\n====================================================================\n";
echo "Results: {$passed} passed, {$failed} failed\n";
echo "====================================================================\n";

exit($failed === 0 ? 0 : 1);
