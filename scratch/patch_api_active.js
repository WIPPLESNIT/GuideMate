const fs = require('fs');
let c = fs.readFileSync('app/Controllers/ApiController.php', 'utf8');

// 1. In show(), update alreadyBooked and add active_booking payload
const oldShowAlreadyBooked = `        $alreadyBooked = $userId !== null && Booking::hasActivePaidBooking($listingId, $userId);`;
const newShowAlreadyBooked = `        $activeBooking = $userId !== null ? Booking::getActiveBookingForListing($listingId, $userId) : null;
        $alreadyBooked = $activeBooking !== null;`;

c = c.replace(oldShowAlreadyBooked, newShowAlreadyBooked);

const oldShowAlreadyBookedPayload = `            'already_booked' => $alreadyBooked,`;
const newShowAlreadyBookedPayload = `            'already_booked' => $alreadyBooked,
            'active_booking' => $activeBooking !== null ? [
                'id' => (int) $activeBooking['id'],
                'status' => (string) $activeBooking['status'],
                'status_label' => ucfirst((string) $activeBooking['status']),
                'booking_date' => (string) $activeBooking['booking_date'],
                'booking_time' => (string) ($activeBooking['booking_time'] ?? ''),
            ] : null,`;

c = c.replace(oldShowAlreadyBookedPayload, newShowAlreadyBookedPayload);

// 2. In submitBooking(), add advisory lock and server-side duplicate check
const oldSubmitDatesLoop = `        foreach ($dates as $d) {
            if (strtotime($d) < strtotime('today')) {
                $this->json(['error' => "Date {$d} cannot be in the past."], 422);
                return;
            }
            if (Booking::hasActivePaidBooking($listingId, $userId, $d)) {
                $this->json(['error' => "You already have an active booking on {$d} for this experience."], 409);
                return;
            }
            $excludeListingId = ($sched !== null) ? $listingId : null;
            if ($time !== '' && Booking::isGuideSlotBooked($guideId, $d, $time, null, $excludeListingId)) {
                $this->json(['error' => "This guide is already booked on {$d} at {$time}. Please pick another slot."], 409);
                return;
            }
            if ($time === '' && Booking::isGuideDateBooked($guideId, $d)) {
                $this->json(['error' => "This guide is already booked on {$d}. Please pick another day."], 409);
                return;
            }
        }`;

const newSubmitDatesLoop = `        // Server-side duplicate active booking check for the entire tour / package identifier (listing_id)
        $activeBooking = Booking::getActiveBookingForListing($listingId, $userId);
        if ($activeBooking !== null) {
            $statusLabel = ucfirst((string) $activeBooking['status']);
            $dateFormatted = date('M j, Y', strtotime((string) $activeBooking['booking_date']));
            $this->json([
                'error' => "You already have an active booking for this tour (Status: {$statusLabel}, Date: {$dateFormatted}). You can book this tour again once your current experience is marked as completed.",
                'active_booking' => [
                    'id' => (int) $activeBooking['id'],
                    'status' => (string) $activeBooking['status'],
                    'status_label' => $statusLabel,
                    'booking_date' => (string) $activeBooking['booking_date'],
                ]
            ], 409);
            return;
        }

        // Idempotency check for repeated clicks or refreshes with the same payment reference
        if ($paymentReference !== '') {
            $existingRefBooking = Database::first(
                "SELECT b.* FROM bookings b
                 WHERE b.user_id = ? AND b.listing_id = ? AND b.notes LIKE ?
                 ORDER BY b.id DESC LIMIT 1",
                [$userId, $listingId, "%Ref: {$paymentReference}%"]
            );
            if ($existingRefBooking !== null) {
                $this->json([
                    'booking' => $this->bookingPayload($existingRefBooking),
                    'idempotent' => true,
                ], 200);
                return;
            }
        }

        foreach ($dates as $d) {
            if (strtotime($d) < strtotime('today')) {
                $this->json(['error' => "Date {$d} cannot be in the past."], 422);
                return;
            }
            $excludeListingId = ($sched !== null) ? $listingId : null;
            if ($time !== '' && Booking::isGuideSlotBooked($guideId, $d, $time, null, $excludeListingId)) {
                $this->json(['error' => "This guide is already booked on {$d} at {$time}. Please pick another slot."], 409);
                return;
            }
            if ($time === '' && Booking::isGuideDateBooked($guideId, $d)) {
                $this->json(['error' => "This guide is already booked on {$d}. Please pick another day."], 409);
                return;
            }
        }`;

c = c.replace(oldSubmitDatesLoop, newSubmitDatesLoop);

fs.writeFileSync('app/Controllers/ApiController.php', c, 'utf8');
console.log('ApiController.php updated with duplicate active check and idempotency');
