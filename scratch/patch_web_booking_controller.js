const fs = require('fs');
let c = fs.readFileSync('app/Controllers/BookingController.php', 'utf8');

const target = `        // Tour guide booking flow
        $date = (string) $this->input('booking_date', '');`;

const replacement = `        // Tour guide booking flow
        $activeBooking = Booking::getActiveBookingForListing((int) $listing['id'], (int) Auth::id());
        if ($activeBooking !== null) {
            $statusLabel = ucfirst((string) $activeBooking['status']);
            $dateFormatted = date('M j, Y', strtotime((string) $activeBooking['booking_date']));
            flash('error', "You already have an active booking for this tour (Status: {$statusLabel}, Date: {$dateFormatted}). You can book this tour again once your current experience is marked as completed.");
            redirect('/listing/' . $listing['slug']);
            return;
        }

        $date = (string) $this->input('booking_date', '');`;

c = c.replace(target, replacement);

fs.writeFileSync('app/Controllers/BookingController.php', c, 'utf8');
console.log('BookingController.php updated');
