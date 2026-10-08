<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\DisputeFile;
use App\Models\Payment;
use App\Core\Upload;

final class DisputeController extends Controller
{
    public function create(string $id): void
    {
        $booking = $this->ownedPaidBooking((int) $id);
        $existing = Dispute::forBooking((int) $booking['id']);

        $this->view('bookings/report', [
            'title' => 'Report a problem',
            'booking' => $booking,
            'dispute' => $existing,
            'types' => Dispute::TYPES,
            'errors' => errors(),
        ]);
    }

    public function store(string $id): void
    {
        $this->verifyCsrf();
        $booking = $this->ownedPaidBooking((int) $id);

        if (Dispute::forBooking((int) $booking['id']) !== null) {
            flash('info', 'You already reported a problem for this booking.');
            redirect('/bookings/' . $booking['id'] . '/report');
        }

        $type = (string) $this->input('problem_type', '');
        $description = trim((string) $this->input('description', ''));
        $amountRaw = trim((string) $this->input('amount_requested', ''));

        $errors = [];
        if (!isset(Dispute::TYPES[$type])) {
            $errors['problem_type'] = 'Please choose a problem type.';
        }
        if ($description === '' || strlen($description) < 20) {
            $errors['description'] = 'Please describe the problem in at least 20 characters.';
        }
        $amount = null;
        if ($type === 'extra_payment') {
            if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0) {
                $errors['amount_requested'] = 'Enter the extra amount the guide requested.';
            } else {
                $amount = (float) $amountRaw;
            }
        }

        if ($errors !== []) {
            flash_keep_old($errors, $_POST, '/bookings/' . $booking['id'] . '/report');
        }

        $disputeId = Dispute::create([
            'booking_id' => (int) $booking['id'],
            'user_id' => (int) Auth::id(),
            'guide_id' => (int) $booking['guide_id'],
            'problem_type' => $type,
            'amount_requested' => $amount,
            'description' => $description,
        ]);

        if (!empty($_FILES['evidence']['name'])) {
            $names = $_FILES['evidence']['name'];
            if (!is_array($names)) {
                $path = Upload::image($_FILES['evidence'], 'disputes');
                if ($path !== null) {
                    DisputeFile::add($disputeId, $path, $_FILES['evidence']['name']);
                }
            } else {
                $count = count($names);
                for ($i = 0; $i < $count; $i++) {
                    if (empty($names[$i])) {
                        continue;
                    }
                    $file = [
                        'name' => $_FILES['evidence']['name'][$i],
                        'type' => $_FILES['evidence']['type'][$i],
                        'tmp_name' => $_FILES['evidence']['tmp_name'][$i],
                        'error' => $_FILES['evidence']['error'][$i],
                        'size' => $_FILES['evidence']['size'][$i],
                    ];
                    $path = Upload::image($file, 'disputes');
                    if ($path !== null) {
                        DisputeFile::add($disputeId, $path, $file['name']);
                    }
                }
            }
        }

        Booking::updateStatus((int) $booking['id'], 'disputed');
        flash('success', 'Your report was submitted. Our team will review it within 24–72 hours.');
        redirect('/bookings');
    }

    /**
     * Booking must belong to the tourist and be paid before they can report.
     *
     * @return array<string, mixed>
     */
    private function ownedPaidBooking(int $id): array
    {
        $booking = Booking::find($id);
        if ($booking === null || (int) $booking['user_id'] !== (int) Auth::id()) {
            abort(404, 'Booking not found.');
        }

        $payment = Payment::forBooking($id);
        if ($payment === null || $payment['status'] !== 'paid') {
            flash('error', 'You can only report a problem on paid bookings.');
            redirect('/bookings');
        }

        if (!in_array($booking['status'], ['confirmed', 'completed', 'disputed'], true)) {
            flash('error', 'This booking cannot be reported yet.');
            redirect('/bookings');
        }

        return $booking;
    }
}
