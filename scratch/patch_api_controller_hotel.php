<?php

require __DIR__ . '/patch_helper.php';

$file = 'app/Controllers/ApiController.php';

// 1. In listing() method:
$listingSearch = "\$this->json([
            'listing' => \$listingPayload,
            'already_booked' => \$alreadyBooked,";

$listingReplace = "\$rooms = ((string) (\$listing['category_slug'] ?? '') === 'hotels')
            ? \\App\\Models\\HotelRoom::forListing(\$listingId)
            : [];

        \$this->json([
            'listing' => \$listingPayload,
            'rooms' => \$rooms,
            'already_booked' => \$alreadyBooked,";

patchFile($file, $listingSearch, $listingReplace);

// 2. In bookingPayload() method:
$payloadSearch = "'total_amount' => (float) \$booking['total_amount'],";
$payloadReplace = "'total_amount' => (float) \$booking['total_amount'],
            'room_id' => !empty(\$booking['room_id']) ? (int) \$booking['room_id'] : null,
            'room_name' => (string) (\$booking['room_name'] ?? ''),
            'check_in_date' => (string) (\$booking['check_in_date'] ?? \$booking['booking_date']),
            'check_out_date' => (string) (\$booking['check_out_date'] ?? \$booking['booking_date']),
            'nights' => (int) (\$booking['nights'] ?? 1),
            'deposit_amount' => (float) (\$booking['deposit_amount'] ?? 0),
            'balance_amount' => (float) (\$booking['balance_amount'] ?? 0),
            'payment_stage' => (string) (\$booking['payment_stage'] ?? 'full'),
            'hotel_status' => (string) (\$booking['hotel_status'] ?? ''),";

patchFile($file, $payloadSearch, $payloadReplace);

// 3. In createBooking() method: support hotel room booking branch
$createBookingSearch = "\$listing = Listing::find(\$listingId);
        if (\$listing === null || \$listing['status'] !== 'approved') {
            \$this->json(['error' => 'Listing not available.'], 404);
            return;
        }";

$createBookingReplace = "\$listing = Listing::find(\$listingId);
        if (\$listing === null || \$listing['status'] !== 'approved') {
            \$this->json(['error' => 'Listing not available.'], 404);
            return;
        }

        \$isHotelBooking = (string) (\$listing['category_slug'] ?? '') === 'hotels' || !empty(\$body['room_id']);
        if (\$isHotelBooking) {
            \$roomId = (int) (\$body['room_id'] ?? 0);
            \$room = \$roomId > 0 ? \\App\\Models\\HotelRoom::find(\$roomId) : null;
            if (\$room === null || (int) \$room['listing_id'] !== \$listingId) {
                // If no specific room passed, find first available room for this hotel
                \$availRooms = \\App\\Models\\HotelRoom::forListing(\$listingId, true);
                if (\$availRooms !== []) {
                    \$room = \$availRooms[0];
                    \$roomId = (int) \$room['id'];
                } else {
                    \$this->json(['error' => 'No rooms currently available for this hotel.'], 422);
                    return;
                }
            }

            if ((\$room['status'] ?? '') !== \\App\\Models\\HotelRoom::STATUS_AVAILABLE) {
                \$this->json(['error' => 'This room is currently ' . str_replace('_', ' ', (string) \$room['status']) . ' and cannot be booked.'], 422);
                return;
            }

            \$checkIn = trim((string) (\$body['check_in_date'] ?? \$primaryDate));
            \$checkOut = trim((string) (\$body['check_out_date'] ?? (isset(\$dates[1]) ? \$dates[count(\$dates) - 1] : date('Y-m-d', strtotime(\$checkIn . ' +1 day')))));

            if (strtotime(\$checkIn) < strtotime('today')) {
                \$this->json(['error' => 'Check-in date cannot be in the past.'], 422);
                return;
            }
            if (strtotime(\$checkOut) <= strtotime(\$checkIn)) {
                \$this->json(['error' => 'Check-out date must be after check-in date.'], 422);
                return;
            }

            if (!\\App\\Models\\HotelRoom::isAvailableForDates(\$roomId, \$checkIn, \$checkOut)) {
                \$this->json(['error' => 'Sorry, this room is already booked or blocked for your selected dates.'], 409);
                return;
            }

            \$nights = max(1, (int) round((strtotime(\$checkOut) - strtotime(\$checkIn)) / 86400));
            \$nightlyRate = (float) \$room['price_per_night'];
            \$totalStay = round(\$nightlyRate * \$nights, 2);
            \$depositPct = max(5, min(100, (int) (\$room['deposit_percentage'] ?? 20)));
            \$depositAmount = round((\$totalStay * \$depositPct) / 100, 2);
            \$balanceAmount = round(\$totalStay - \$depositAmount, 2);

            \$methodLabel = match (\$method) {
                'gcash' => 'GCash / e-Wallet QR',
                'instapay' => 'InstaPay QR',
                default => 'Debit/Credit Card',
            };
            \$hotelNotes = \"Hotel Room: {\$room['name']} ({$nights} nights: {\$checkIn} to {\$checkOut})\\n\"
                . \"Deposit paid via {\$methodLabel}: ₱\" . number_format(\$depositAmount, 2) . \"\\n\"
                . \"Remaining balance due at front desk: ₱\" . number_format(\$balanceAmount, 2);
            if (\$paymentReference !== '') {
                \$hotelNotes .= ' · Ref: ' . \$paymentReference;
            }
            if (\$notes !== '') {
                \$hotelNotes .= \"\\nGuest notes: \" . \$notes;
            }

            \$bookingId = \\App\\Models\\Booking::createHotelReservation(
                \$listingId,
                \$roomId,
                \$userId,
                \$checkIn,
                \$checkOut,
                \$nights,
                \$guests,
                \$totalStay,
                \$depositAmount,
                \$balanceAmount,
                \$hotelNotes
            );

            \\App\\Models\\Payment::create(\$bookingId, \$depositAmount, \$method, 'paid');
            \\App\\Core\\Database::run(\"UPDATE bookings SET hotel_status = 'deposit_paid', payment_stage = 'deposit_paid' WHERE id = ?\", [\$bookingId]);

            \$customer = User::find(\$userId);
            if (\$customer !== null && !empty(\$customer['email'])) {
                try {
                    NotificationService::bookingAwaitingConfirmation(
                        (string) \$customer['email'],
                        (string) (\$listing['title'] ?? ''),
                        \"{\$nights} nights ({\$checkIn} to {\$checkOut})\"
                    );
                } catch (\\Throwable \$e) {}
            }

            \$this->notifyAdminsOfBooking(\$listing, \$userId, \$checkIn, \$guests, \$depositAmount, \$methodLabel . ' (Room Deposit)');

            \$booking = \\App\\Models\\Booking::find(\$bookingId);
            \$this->json([
                'booking' => \$booking !== null ? \$this->bookingPayload(\$booking) : null,
                'deposit_amount' => \$depositAmount,
                'balance_amount' => \$balanceAmount,
                'payment_instructions' => \"Deposit of ₱\" . number_format(\$depositAmount, 2) . \" received. Remaining ₱\" . number_format(\$balanceAmount, 2) . \" is payable at the hotel front desk upon check-in.\",
            ], 201);
            return;
        }";

patchFile($file, $createBookingSearch, $createBookingReplace);

echo "ApiController patched.\n";
