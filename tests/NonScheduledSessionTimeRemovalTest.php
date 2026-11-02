<?php
/**
 * Non-Scheduled Tour Session Time Removal Tests
 *
 * Verifies that:
 * 1. If a listing has NO schedule entries, the "Tour Session Time" card directly below the calendar is removed.
 * 2. If a listing DOES include a schedule, the "Tour Session Time" card below the calendar is kept intact.
 * 3. Operating / Recommended Hours in Duration & Details remains preserved.
 * 4. Layout, spacing, typography, and colors are preserved.
 */

echo "\n========================================================\n";
echo " Non-Scheduled Listing Tour Session Time Removal Tests\n";
echo "========================================================\n\n";

$slugPath = __DIR__ . '/../app/listing/[slug].tsx';
if (!file_exists($slugPath)) {
    echo " [FAIL] app/listing/[slug].tsx not found\n";
    exit(1);
}
echo " [PASS] app/listing/[slug].tsx exists\n";

$slugCode = file_get_contents($slugPath);

// --- Test 1: Guard on Tour Session Time card ---
echo "--- Test 1: Tour Session Time Below Calendar Conditional Rendering ---\n";

$hasScheduleGuard = preg_match('/\{!isHotel\s*&&\s*hasSchedule\s*\?\s*\(\s*<View\s+style=\{\[styles\.guideTimeCard/', $slugCode);
if ($hasScheduleGuard) {
    echo " [PASS] Tour session time card is strictly guarded by '!isHotel && hasSchedule'\n";
} else {
    echo " [FAIL] Tour session time card should only render when '!isHotel && hasSchedule'\n";
    exit(1);
}

// Ensure it is NOT rendered for non-scheduled listings (where hasSchedule is false)
if (strpos($slugCode, '{!isHotel ? (') === false) {
    echo " [PASS] Unconditional non-hotel render '{!isHotel ? (' has been removed\n";
} else {
    echo " [FAIL] Found lingering '{!isHotel ? (' without hasSchedule check\n";
    exit(1);
}

// --- Test 2: Scheduled Listings Retain Tour Session Time ---
echo "\n--- Test 2: Scheduled Listings Retain Tour Session Time & Guide Defined Pill ---\n";

if (strpos($slugCode, 'styles.guideTimeCard') !== false &&
    strpos($slugCode, 'Tour Session Time') !== false &&
    strpos($slugCode, 'Guide Defined') !== false &&
    strpos($slugCode, 'The start and end times for this tour are predefined by the guide') !== false) {
    echo " [PASS] Scheduled listings preserve Tour Session Time header, time value, Guide Defined pill, and subtext\n";
} else {
    echo " [FAIL] Guide session time elements missing in scheduled flow\n";
    exit(1);
}

// --- Test 3: Duration & Details Modal Retains Operating Hours ---
echo "\n--- Test 3: Operating / Recommended Hours Preserved in Duration & Details ---\n";

if (strpos($slugCode, 'Operating / Recommended Hours') !== false &&
    strpos($slugCode, 'Available for booking on any date. The guide will coordinate meeting times with you.') !== false) {
    echo " [PASS] Operating / Recommended Hours section is intact in Duration & Details\n";
} else {
    echo " [FAIL] Operating / Recommended Hours section missing from Duration & Details\n";
    exit(1);
}

// --- Test 4: Spacing and Modal Layout Consistency ---
echo "\n--- Test 4: Layout and Spacing Preservation ---\n";

if (strpos($slugCode, 'modalLabel: { fontSize: 13, fontWeight: \'600\', marginBottom: 6, marginTop: 12 }') !== false) {
    echo " [PASS] Modal labels maintain consistent 12px margin top preserving clean vertical rhythm below calendar\n";
} else {
    echo " [FAIL] modalLabel style definition was altered\n";
    exit(1);
}

// --- Test 5: Booking Times Saved Automatically Without Prompting ---
echo "\n--- Test 5: Automated Start and End Time Persistence ---\n";

if (strpos($slugCode, 'currentStartTime = currentStartTime || \'09:00\';') !== false &&
    strpos($slugCode, 'currentEndTime = currentEndTime || \'17:00\';') !== false) {
    echo " [PASS] Non-scheduled bookings automatically retain default operating times for database records\n";
} else {
    echo " [FAIL] Default operating times not handled in booking submission\n";
    exit(1);
}

echo "\n--------------------------------------------------------\n";
echo "Results: All 5 tests passed!\n";
echo "--------------------------------------------------------\n";
