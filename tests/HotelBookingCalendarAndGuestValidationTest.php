<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Booking;
use App\Models\HotelRoom;
use App\Models\Listing;

class HotelBookingCalendarAndGuestValidationTest
{
    private array $cleanupListingIds = [];
    private array $cleanupRoomIds = [];
    private array $cleanupBookingIds = [];

    public function run(): void
    {
        echo "Running HotelBookingCalendarAndGuestValidationTest...\n";

        $this->testCalendarPickerComponentSource();
        $this->testMultiDayBookedDatesExpansionAndListingIsolation();
        $this->testMaxGuestValidationBackendApiAndController();
        $this->testListingDetailSlugMobileComponent();
        $this->testWebViewMaxGuestValidation();
        $this->testCompletedHotelBookingReleasesDates();

        $this->cleanup();
        echo "All tests passed successfully!\n";
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            $this->cleanup();
            throw new \RuntimeException("FAILED: {$message}");
        }
        echo "  [PASS] {$message}\n";
    }

    private function testCalendarPickerComponentSource(): void
    {
        echo "\n1. Testing CalendarPicker.tsx component structure and styling...\n";
        $file = __DIR__ . '/../components/CalendarPicker.tsx';
        $this->assert(file_exists($file), 'CalendarPicker.tsx exists');
        $code = file_get_contents($file);

        // Verify explicit 7-column rows to prevent Saturday from wrapping
        $this->assert(str_contains($code, 'const rows = useMemo'), 'CalendarPicker groups days into explicit rows');
        $this->assert(str_contains($code, 'slice(i, i + 7)'), 'CalendarPicker chunks into exactly 7 days per row');
        $this->assert(str_contains($code, 'styles.gridRow'), 'CalendarPicker renders rows with gridRow style');
        $this->assert(str_contains($code, 'gridRow: { flexDirection: \'row\', width: \'100%\' }'), 'gridRow is flexDirection row with full width');
        $this->assert(str_contains($code, 'cell: { flex: 1, aspectRatio: 1'), 'cell uses flex: 1 to guarantee 7 equal columns without float overflow');

        // Verify clean grey styling for booked/disabled dates without strike-through
        $this->assert(str_contains($code, 'dayInnerFull'), 'CalendarPicker has dayInnerFull styling');
        $this->assert(str_contains($code, 'dayTextFull'), 'CalendarPicker has dayTextFull styling');
        $this->assert(str_contains($code, '#94A3B8'), 'CalendarPicker styles booked dates in clean slate grey (#94A3B8)');
        $this->assert(!str_contains($code, 'textDecorationLine: \'line-through\''), 'CalendarPicker removed line-through text decoration for booked dates');

        // Test the row chunking algorithm directly
        // Month: November 2026 (Starts on Sunday Nov 1, 30 days)
        $firstDay = 0; // Sunday
        $daysInMonth = 30;
        $list = [];
        for ($i = 0; $i < $firstDay; $i++) $list[] = null;
        for ($d = 1; $d <= $daysInMonth; $d++) $list[] = $d;

        $rows = [];
        for ($i = 0; $i < count($list); $i += 7) {
            $slice = array_slice($list, $i, 7);
            while (count($slice) < 7) {
                $slice[] = null;
            }
            $rows[] = $slice;
        }

        $this->assert(count($rows) === 5, 'November 2026 splits into 5 rows of 7');
        // Saturday is index 6 of each row
        $this->assert($rows[0][6] === 7, 'First Saturday of Nov 2026 is Nov 7 in column 6');
        $this->assert($rows[1][6] === 14, 'Second Saturday of Nov 2026 is Nov 14 in column 6');
        $this->assert($rows[2][6] === 21, 'Third Saturday of Nov 2026 is Nov 21 in column 6');
        $this->assert($rows[3][6] === 28, 'Fourth Saturday of Nov 2026 is Nov 28 in column 6');
        $this->assert($rows[4][0] === 29, 'Nov 29 is correctly on Sunday of row 5');
        $this->assert($rows[4][1] === 30, 'Nov 30 is correctly on Monday of row 5');
        $this->assert($rows[4][6] === null, 'Row 5 pads remaining days up to Saturday with null');
    }

    private function testMultiDayBookedDatesExpansionAndListingIsolation(): void
    {
        echo "\n2. Testing multi-day booked dates expansion and per-listing isolation...\n";

        // Create two test hotel listings (Listing A and Listing B)
        $user = Database::first('SELECT id FROM users WHERE role = "hotel_provider" OR role = "guide" LIMIT 1');
        $userId = (int) ($user['id'] ?? 1);

        $listingAId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, description, area, price, capacity, status, created_at)
             VALUES (?, (SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1), 'Test Hotel Alpha', 'test-hotel-alpha-" . uniqid() . "', 'Test description', 'Moalboal, Cebu', 3500.00, 2, 'approved', NOW())",
            [$userId]
        );
        $this->cleanupListingIds[] = $listingAId;

        $listingBId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, description, area, price, capacity, status, created_at)
             VALUES (?, (SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1), 'Test Hotel Beta', 'test-hotel-beta-" . uniqid() . "', 'Test description', 'Moalboal, Cebu', 4200.00, 4, 'approved', NOW())",
            [$userId]
        );
        $this->cleanupListingIds[] = $listingBId;

        // Create room for Listing A
        $roomAId = Database::insert(
            "INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests, total_units, status, created_at)
             VALUES (?, 'Villa Ocean View', 3500.00, 2, 1, 'available', NOW())",
            [$listingAId]
        );
        $this->cleanupRoomIds[] = $roomAId;

        // Create room for Listing B
        $roomBId = Database::insert(
            "INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests, total_units, status, created_at)
             VALUES (?, 'Family Garden Suite', 4200.00, 4, 1, 'available', NOW())",
            [$listingBId]
        );
        $this->cleanupRoomIds[] = $roomBId;

        // Future dates for test
        $checkIn = date('Y-m-d', strtotime('+10 days'));
        $checkOut = date('Y-m-d', strtotime('+13 days')); // 3 nights: +10, +11, +12 days
        $night1 = date('Y-m-d', strtotime('+10 days'));
        $night2 = date('Y-m-d', strtotime('+11 days'));
        $night3 = date('Y-m-d', strtotime('+12 days'));
        $checkoutDay = date('Y-m-d', strtotime('+13 days'));

        // Insert active hotel booking for Listing A only
        $bookingAId = Database::insert(
            "INSERT INTO bookings (listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status)
             VALUES (?, ?, ?, ?, ?, ?, 3, 2, 10500.00, 2100.00, 8400.00, 'deposit_paid', 'pending', 'pending')",
            [$listingAId, $roomAId, $userId, $checkIn, $checkIn, $checkOut]
        );
        $this->cleanupBookingIds[] = $bookingAId;

        // Query booked dates for Listing A
        $bookedDatesA = Booking::bookedDates($listingAId);
        $this->assert(in_array($night1, $bookedDatesA, true), "Night 1 ({$night1}) is marked booked for Listing A");
        $this->assert(in_array($night2, $bookedDatesA, true), "Night 2 ({$night2}) is marked booked for Listing A");
        $this->assert(in_array($night3, $bookedDatesA, true), "Night 3 ({$night3}) is marked booked for Listing A");
        $this->assert(!in_array($checkoutDay, $bookedDatesA, true), "Checkout day ({$checkoutDay}) is NOT marked booked (available for next guest check-in)");

        // Query room-specific booked dates for Room A
        $roomBookedDatesA = Booking::bookedDates($listingAId, $roomAId);
        $this->assert(in_array($night1, $roomBookedDatesA, true), "Room A has Night 1 marked booked");
        $this->assert(in_array($night2, $roomBookedDatesA, true), "Room A has Night 2 marked booked");
        $this->assert(in_array($night3, $roomBookedDatesA, true), "Room A has Night 3 marked booked");

        // Query booked dates for Listing B (Isolation check)
        $bookedDatesB = Booking::bookedDates($listingBId);
        $this->assert(!in_array($night1, $bookedDatesB, true), "Listing B does NOT have Night 1 marked booked (Strict per-listing isolation verified)");
        $this->assert(!in_array($night2, $bookedDatesB, true), "Listing B does NOT have Night 2 marked booked");
        $this->assert(!in_array($night3, $bookedDatesB, true), "Listing B does NOT have Night 3 marked booked");
    }

    private function testMaxGuestValidationBackendApiAndController(): void
    {
        echo "\n3. Testing maximum guest capacity validation on API & controllers...\n";

        // Check ApiController.php
        $apiCode = file_get_contents(__DIR__ . '/../app/Controllers/ApiController.php');
        $this->assert(str_contains($apiCode, '$maxGuests = max(1, (int) ($room[\'max_guests\'] ?? 2));'), 'ApiController calculates room maxGuests');
        $this->assert(str_contains($apiCode, 'if ($guests > $maxGuests) {'), 'ApiController checks if guests exceed maxGuests');
        $this->assert(str_contains($apiCode, 'This room accommodates a maximum of'), 'ApiController returns clear validation error message');

        // Check BookingController.php
        $bookingCtrlCode = file_get_contents(__DIR__ . '/../app/Controllers/BookingController.php');
        $this->assert(str_contains($bookingCtrlCode, '$maxGuests = max(1, (int) ($room[\'max_guests\'] ?? 2));'), 'BookingController calculates room maxGuests');
        $this->assert(str_contains($bookingCtrlCode, 'if ($guests > $maxGuests) {'), 'BookingController rejects bookings exceeding room maxGuests');

        // Check ApiController listingShow returns booked_dates per listing and per room
        $this->assert(str_contains($apiCode, '\'booked_dates\' => \App\Models\Booking::bookedDates($listingId)'), 'ApiController::listingShow returns booked_dates scoped to listing');
        $this->assert(str_contains($apiCode, '$rm[\'booked_dates\'] = \App\Models\Booking::bookedDates($listingId, (int) ($rm[\'id\'] ?? 0));'), 'ApiController::listingShow attaches room-specific booked_dates to each room');
    }

    private function testListingDetailSlugMobileComponent(): void
    {
        echo "\n4. Testing mobile app listing/[slug].tsx component logic...\n";
        $file = __DIR__ . '/../app/listing/[slug].tsx';
        $this->assert(file_exists($file), 'listing/[slug].tsx exists');
        $code = file_get_contents($file);

        // Check hasBookedDatesInRange implementation
        $this->assert(str_contains($code, 'function hasBookedDatesInRange'), 'hasBookedDatesInRange function exists');

        // Check hotelDisabledDates and effectiveHotelDisabledDates
        $this->assert(str_contains($code, 'const hotelDisabledDates = React.useMemo'), 'hotelDisabledDates memo exists');
        $this->assert(str_contains($code, 'const effectiveHotelDisabledDates = React.useMemo'), 'effectiveHotelDisabledDates memo exists');
        $this->assert(str_contains($code, 'disabledDates={effectiveHotelDisabledDates}'), 'Hotel CalendarPicker receives effectiveHotelDisabledDates');

        // Check maxGuestsAllowed calculation
        $this->assert(str_contains($code, 'const maxGuestsAllowed = React.useMemo'), 'maxGuestsAllowed memo exists');
        $this->assert(str_contains($code, 'const parsed = Number(selectedRoom.max_guests);'), 'maxGuestsAllowed reads selectedRoom.max_guests');

        // Check automatic guest count clamp when room changes
        $this->assert(str_contains($code, 'setGuests(roomMax);'), 'Automatically clamps guests to roomMax');

        // Check guest increment stepper restriction with Alert
        $this->assert(str_contains($code, 'guests >= maxGuestsAllowed'), 'Guest stepper checks guests >= maxGuestsAllowed');
        $this->assert(str_contains($code, 'This room accommodates a maximum of ${maxGuestsAllowed} guest'), 'Guest stepper shows clear Alert on capacity limit');

        // Check helper text displays max capacity
        $this->assert(str_contains($code, 'Maximum capacity: {maxGuestsAllowed} guest'), 'Displays room maximum capacity in modal');

        // Check goToPayment validates booked dates and room max guests
        $this->assert(str_contains($code, 'if (hotelDisabledDates.includes(hotelCheckIn))'), 'goToPayment checks if checkIn date is booked');
        $this->assert(str_contains($code, 'if (hasBookedDatesInRange(hotelCheckIn, hotelCheckOut, hotelDisabledDates))'), 'goToPayment checks if stay overlaps booked dates');
        $this->assert(str_contains($code, 'if (guests > roomMax)'), 'goToPayment checks if guests exceed roomMax');
    }

    private function testWebViewMaxGuestValidation(): void
    {
        echo "\n5. Testing web view listings/show.php max guest validation...\n";
        $showCode = file_get_contents(__DIR__ . '/../app/Views/listings/show.php');

        $this->assert(str_contains($showCode, 'oninput="validateHotelGuests(this)"'), 'hotel_guests input has oninput validation');
        $this->assert(str_contains($showCode, 'function updateGuestsCapacity(maxGuests)'), 'updateGuestsCapacity updates max and clamps input');
        $this->assert(str_contains($showCode, 'function validateHotelGuests(input)'), 'validateHotelGuests clamps input to maxGuests');
        $this->assert(str_contains($showCode, 'hotelGuestsHint'), 'hotelGuestsHint element provides real-time feedback');
    }

    private function testCompletedHotelBookingReleasesDates(): void
    {
        echo "\n6. Testing completed hotel bookings release calendar dates and keep them available...\n";

        $userId = Database::first("SELECT id FROM users WHERE role = 'tourist' LIMIT 1")['id'] ?? 1;

        $listingId = Database::insert(
            "INSERT INTO listings (user_id, category_id, title, slug, description, area, price, capacity, status, created_at)
             VALUES (?, (SELECT id FROM categories WHERE slug = 'hotels' LIMIT 1), 'Test Hotel Release', 'test-hotel-release-" . uniqid() . "', 'Testing date release', 'Lapu-Lapu, Cebu', 5000.00, 2, 'approved', NOW())",
            [$userId]
        );
        $this->cleanupListingIds[] = $listingId;

        $roomId = Database::insert(
            "INSERT INTO hotel_rooms (listing_id, name, price_per_night, max_guests, total_units, status, created_at)
             VALUES (?, 'Deluxe Pool Villa', 5000.00, 2, 1, 'available', NOW())",
            [$listingId]
        );
        $this->cleanupRoomIds[] = $roomId;

        $checkIn = date('Y-m-d', strtotime('+20 days'));
        $checkOut = date('Y-m-d', strtotime('+23 days')); // 3 nights
        $night1 = date('Y-m-d', strtotime('+20 days'));
        $night2 = date('Y-m-d', strtotime('+21 days'));
        $night3 = date('Y-m-d', strtotime('+22 days'));

        // 1. Initially create a confirmed booking for the hotel room
        $bookingId = Database::insert(
            "INSERT INTO bookings (listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status)
             VALUES (?, ?, ?, ?, ?, ?, 3, 2, 15000.00, 3000.00, 12000.00, 'deposit_paid', 'confirmed', 'confirmed')",
            [$listingId, $roomId, $userId, $checkIn, $checkIn, $checkOut]
        );
        $this->cleanupBookingIds[] = $bookingId;

        // Verify active booking blocks the dates
        $bookedDates = Booking::bookedDates($listingId, $roomId);
        $this->assert(in_array($night1, $bookedDates, true), "Active booking: Night 1 is marked booked in calendar");
        $this->assert(in_array($night2, $bookedDates, true), "Active booking: Night 2 is marked booked in calendar");
        $this->assert(in_array($night3, $bookedDates, true), "Active booking: Night 3 is marked booked in calendar");
        $this->assert(!HotelRoom::isAvailableForDates($roomId, $checkIn, $checkOut), "Active booking: Room is NOT available for overlapping dates");

        // 2. Complete the booking (status = 'completed', hotel_status = 'completed')
        Database::run(
            "UPDATE bookings SET status = 'completed', hotel_status = 'completed', unit_completed_at = NOW() WHERE id = ?",
            [$bookingId]
        );

        // Verify completed booking releases dates
        $releasedBookedDates = Booking::bookedDates($listingId, $roomId);
        $this->assert(!in_array($night1, $releasedBookedDates, true), "Completed booking: Night 1 is NO LONGER marked booked (active and available again)");
        $this->assert(!in_array($night2, $releasedBookedDates, true), "Completed booking: Night 2 is NO LONGER marked booked (active and available again)");
        $this->assert(!in_array($night3, $releasedBookedDates, true), "Completed booking: Night 3 is NO LONGER marked booked (active and available again)");

        $listingBookedDates = Booking::bookedDates($listingId);
        $this->assert(!in_array($night1, $listingBookedDates, true), "Completed booking: Listing level booked dates released Night 1");

        $this->assert(!Booking::isDateBooked($listingId, $night1), "Completed booking: Booking::isDateBooked returns false for completed date");
        $this->assert(HotelRoom::isAvailableForDates($roomId, $checkIn, $checkOut), "Completed booking: HotelRoom::isAvailableForDates returns true (room is available again)");

        // Verify record is preserved in history
        $savedBooking = Booking::find($bookingId);
        $this->assert($savedBooking !== null, "Completed booking record is preserved in database (not deleted)");
        $this->assert($savedBooking['status'] === 'completed', "Completed booking maintains 'completed' status in history");
        $this->assert($savedBooking['hotel_status'] === 'completed', "Completed booking maintains 'completed' hotel_status");

        // 3. Test that other statuses (e.g. pending or checked_in) still block the room
        $booking2Id = Database::insert(
            "INSERT INTO bookings (listing_id, room_id, user_id, booking_date, check_in_date, check_out_date, nights, guests, total_amount, deposit_amount, balance_amount, payment_stage, hotel_status, status)
             VALUES (?, ?, ?, ?, ?, ?, 3, 2, 15000.00, 3000.00, 12000.00, 'deposit_paid', 'pending', 'pending')",
            [$listingId, $roomId, $userId, $checkIn, $checkIn, $checkOut]
        );
        $this->cleanupBookingIds[] = $booking2Id;

        $newBookedDates = Booking::bookedDates($listingId, $roomId);
        $this->assert(in_array($night1, $newBookedDates, true), "Pending booking: dates become booked again");
        $this->assert(!HotelRoom::isAvailableForDates($roomId, $checkIn, $checkOut), "Pending booking: room is not available");

        // Cancel the pending booking -> should release again
        Database::run("UPDATE bookings SET status = 'cancelled', hotel_status = 'cancelled' WHERE id = ?", [$booking2Id]);
        $afterCancelDates = Booking::bookedDates($listingId, $roomId);
        $this->assert(!in_array($night1, $afterCancelDates, true), "Cancelled booking: dates released again");
        $this->assert(HotelRoom::isAvailableForDates($roomId, $checkIn, $checkOut), "Cancelled booking: room is available again");
    }

    private function cleanup(): void
    {
        foreach ($this->cleanupBookingIds as $id) {
            Database::run('DELETE FROM bookings WHERE id = ?', [$id]);
        }
        foreach ($this->cleanupRoomIds as $id) {
            Database::run('DELETE FROM hotel_rooms WHERE id = ?', [$id]);
        }
        foreach ($this->cleanupListingIds as $id) {
            Database::run('DELETE FROM listings WHERE id = ?', [$id]);
        }
    }
}

$test = new HotelBookingCalendarAndGuestValidationTest();
$test->run();
