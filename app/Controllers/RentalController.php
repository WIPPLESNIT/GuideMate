<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\RentalRequest;
use App\Services\NotificationService;

/**
 * Rental Partner dashboard — manages incoming vehicle rental requests.
 * This functionality used to live in the admin portal (/admin/rentals) and has
 * been relocated here so rental partners run it from their own dashboard.
 */
final class RentalController extends Controller
{
    public function requests(): void
    {
        $this->view('rental/requests', [
            'title' => 'Rental Requests',
            'requests' => RentalRequest::all(),
        ]);
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        $status = (string) $this->input('status', '');
        if (!in_array($status, ['pending', 'approved', 'contacted', 'cancelled', 'completed'], true)) {
            flash('error', 'Invalid rental status.');
            redirect('/dashboard/rentals');
        }
        $rental = RentalRequest::find((int) $id);
        if ($rental === null) {
            flash('error', 'Rental request not found.');
            redirect('/dashboard/rentals');
        }

        $note = trim((string) $this->input('admin_note', ''));
        $paid = ($rental['payment_status'] ?? 'unpaid') === 'paid';
        $customerEmail = (string) ($rental['customer_email'] ?? '');
        $vehicleName = (string) ($rental['vehicle_name'] ?? 'your rental');
        $pickupDate = (string) ($rental['pickup_date'] ?? '');

        if ($status === 'cancelled' && $paid) {
            RentalRequest::refund((int) $id, $note !== '' ? $note : null);
            if ($customerEmail !== '') {
                NotificationService::rentalDeclined(
                    $customerEmail,
                    $vehicleName,
                    'Your payment has been refunded.'
                );
            }
            flash('success', 'Reservation declined and the tourist has been refunded.');
            redirect('/dashboard/rentals');
        }

        RentalRequest::updateStatus((int) $id, $status, $note !== '' ? $note : null);
        if ($status === 'approved' && $customerEmail !== '') {
            NotificationService::rentalConfirmed($customerEmail, $vehicleName, $pickupDate);
            flash('success', 'Rental confirmed. The tourist has been notified.');
        } elseif ($status === 'cancelled' && $customerEmail !== '') {
            NotificationService::rentalDeclined($customerEmail, $vehicleName);
            flash('success', 'Reservation declined.');
        } else {
            flash('success', 'Rental request updated.');
        }
        redirect('/dashboard/rentals');
    }

    /**
     * Refund a paid reservation (resolves any open problem report).
     */
    public function refund(string $id): void
    {
        $this->verifyCsrf();
        $rental = RentalRequest::find((int) $id);
        if ($rental === null) {
            flash('error', 'Reservation not found.');
            redirect('/dashboard/rentals');
        }
        if (($rental['payment_status'] ?? 'unpaid') !== 'paid') {
            flash('error', 'Only a paid reservation can be refunded.');
            redirect('/dashboard/rentals');
        }
        $note = trim((string) $this->input('owner_note', ''));
        RentalRequest::refund((int) $id, $note !== '' ? $note : null);
        flash('success', 'Reservation refunded and the customer has been notified.');
        redirect('/dashboard/rentals');
    }

    /**
     * Dismiss a tourist's problem report without a refund.
     */
    public function rejectReport(string $id): void
    {
        $this->verifyCsrf();
        $note = trim((string) $this->input('owner_note', ''));
        RentalRequest::rejectReport((int) $id, $note !== '' ? $note : null);
        flash('success', 'Report marked as reviewed.');
        redirect('/dashboard/rentals');
    }
}
