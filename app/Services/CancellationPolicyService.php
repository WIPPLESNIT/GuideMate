<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\RentalRequest;
use App\Models\User;
use App\Models\Vehicle;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class CancellationPolicyService
{
    /** Booking location default timezone (Cebu, Philippines) */
    public const TIMEZONE = 'Asia/Manila';

    /**
     * Compute cancellation policy metadata, deadlines, eligibility, and refund rules.
     *
     * @param array<string, mixed> $item
     * @param string $kind 'hotel' | 'attraction' | 'rental'
     * @return array<string, mixed>
     */
    public static function compute(array $item, string $kind): array
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        $now = new DateTimeImmutable('now', $tz);
        $nowTs = $now->getTimestamp();

        // 1. Determine policy window (24h or 72h)
        $configured = (string) ($item['cancellation_policy'] ?? '');
        $policyHours = match ($configured) {
            '72h' => 72,
            '24h' => 24,
            default => ($kind === 'hotel' ? 72 : 24),
        };
        $policyCode = $policyHours === 72 ? '72h' : '24h';

        // 2. Determine scheduled start or check-in time in local timezone
        if ($kind === 'hotel') {
            $startDateStr = (string) ($item['check_in_date'] ?? $item['booking_date'] ?? '');
            if ($startDateStr === '') {
                $startDateStr = $now->format('Y-m-d');
            }
            if (!empty($item['booking_time'])) {
                $timeStr = (string) $item['booking_time'];
            } elseif (!empty($item['listing_duration']) || !empty($item['duration'])) {
                $parsedTimes = \App\Models\Listing::parseCheckInOutTimes($item['listing_duration'] ?? $item['duration'] ?? null);
                $timeStr = $parsedTimes['check_in_time'];
            } else {
                $timeStr = '14:00:00';
            }
            $policyLabel = "Free cancellation up to {$policyHours} hours before check-in";
        } elseif ($kind === 'rental') {
            $startDateStr = (string) ($item['pickup_date'] ?? $item['start_date'] ?? '');
            if ($startDateStr === '') {
                $startDateStr = $now->format('Y-m-d');
            }
            $timeStr = !empty($item['pickup_time']) ? (string) $item['pickup_time'] : (!empty($item['booking_time']) ? (string) $item['booking_time'] : '09:00:00');
            $policyLabel = "Free cancellation up to {$policyHours} hours before pickup";
        } else {
            $startDateStr = (string) ($item['booking_date'] ?? '');
            if ($startDateStr === '') {
                $startDateStr = $now->format('Y-m-d');
            }
            $timeStr = !empty($item['booking_time']) ? (string) $item['booking_time'] : '09:00:00';
            $policyLabel = "Free cancellation up to {$policyHours} hours before tour start";
        }

        try {
            $startDateTime = new DateTimeImmutable("{$startDateStr} {$timeStr}", $tz);
        } catch (\Throwable) {
            $startDateTime = new DateTimeImmutable("{$startDateStr} 09:00:00", $tz);
        }
        $startTs = $startDateTime->getTimestamp();

        // 3. Calculate cancellation deadline
        $standardDeadlineTs = $startTs - ($policyHours * 3600);
        $standardDeadlineDateTime = (new DateTimeImmutable('@' . $standardDeadlineTs))->setTimezone($tz);

        // 3.1 Check 24-hour Booking Grace Period (Cooling-off window for recently created bookings)
        // If a customer books within the policy window (e.g. 71h before check-in under 72h policy),
        // the standard deadline would already be passed at creation time ("born dead").
        // A 24-hour grace period from creation is provided (bounded by scheduled start/check-in) so newly placed bookings can be cancelled.
        $createdAtTs = !empty($item['created_at']) ? strtotime((string) $item['created_at']) : null;
        $isWithinGracePeriod = false;
        $graceDeadlineTs = null;

        if ($createdAtTs !== null && $nowTs > $standardDeadlineTs && $nowTs < $startTs) {
            $graceDeadlineTs = min($startTs, $createdAtTs + 86400);
            if ($nowTs <= $graceDeadlineTs) {
                $isWithinGracePeriod = true;
            }
        }

        if ($isWithinGracePeriod && $graceDeadlineTs !== null) {
            $deadlineTs = $graceDeadlineTs;
            $deadlineDateTime = (new DateTimeImmutable('@' . $deadlineTs))->setTimezone($tz);
            $remainingSeconds = max(0, $deadlineTs - $nowTs);
            $isPastDeadline = false;
            $policyLabel .= " (24h grace period active)";
        } else {
            $deadlineTs = ($graceDeadlineTs !== null) ? $graceDeadlineTs : $standardDeadlineTs;
            $deadlineDateTime = (new DateTimeImmutable('@' . $deadlineTs))->setTimezone($tz);
            $remainingSeconds = max(0, $deadlineTs - $nowTs);
            $isPastDeadline = ($nowTs > $deadlineTs);
        }

        if ($isPastDeadline) {
            $remainingText = 'Deadline passed';
        } else {
            $hoursLeft = (int) floor($remainingSeconds / 3600);
            $minutesLeft = (int) floor(($remainingSeconds % 3600) / 60);
            if ($hoursLeft >= 48) {
                $days = (int) floor($hoursLeft / 24);
                $remainingText = "{$days} days remaining";
            } elseif ($hoursLeft > 0) {
                $remainingText = "{$hoursLeft}h {$minutesLeft}m remaining";
            } else {
                $remainingText = "{$minutesLeft}m remaining";
            }
            if ($isWithinGracePeriod) {
                $remainingText .= ' (Grace period)';
            }
        }

        // 4. Booking status evaluation
        $status = (string) ($item['status'] ?? '');
        $hotelStatus = (string) ($item['hotel_status'] ?? '');
        $isCompleted = ($status === 'completed' || $hotelStatus === 'completed');
        $isCancelled = in_array($status, ['cancelled', 'refunded'], true) || in_array($hotelStatus, ['cancelled', 'refunded', 'no_show'], true);
        $isCheckedIn = ($hotelStatus === 'checked_in');
        $hasStarted = ($nowTs >= $startTs);
        $isPastDeadline = ($nowTs > $deadlineTs);

        // 5. Calculate paid amount and deposit status
        $paidAmount = 0.0;
        $isDepositOnly = false;
        $depositAmount = !empty($item['deposit_amount']) ? (float) $item['deposit_amount'] : 0.0;
        $totalAmount = !empty($item['total_amount']) ? (float) $item['total_amount'] : 0.0;

        if ($kind === 'rental') {
            $isPaid = ($item['payment_status'] ?? '') === 'paid';
            $paidAmount = !empty($item['paid_amount']) ? (float) $item['paid_amount'] : ($isPaid ? (float) ($item['total_amount'] ?? 0) : 0.0);
        } else {
            $bookingId = (int) ($item['id'] ?? 0);
            if ($bookingId > 0) {
                $paidRow = Database::first(
                    "SELECT SUM(amount) AS total_paid FROM payments WHERE booking_id = ? AND status = 'paid'",
                    [$bookingId]
                );
                if ($paidRow !== null && $paidRow['total_paid'] !== null) {
                    $paidAmount = (float) $paidRow['total_paid'];
                }
            }

            if ($paidAmount <= 0.0 && !empty($item['paid_amount'])) {
                $paidAmount = (float) $item['paid_amount'];
            }
            if ($paidAmount <= 0.0 && !empty($item['deposit_amount'])) {
                $paidAmount = (float) $item['deposit_amount'];
            }
            if ($paidAmount <= 0.0 && (in_array($item['payment_stage'] ?? '', ['paid', 'confirmed', 'settled'], true) || ($item['payment_status'] ?? '') === 'paid')) {
                $paidAmount = $totalAmount;
            }

            if ($depositAmount > 0.0 && abs($paidAmount - $depositAmount) < 0.01) {
                $isDepositOnly = true;
            }
        }

        // 6. Eligibility Invariant Checks
        $canCancel = true;
        $ineligibleReason = null;

        if ($isCompleted) {
            $canCancel = false;
            $ineligibleReason = 'This booking has already been completed and cannot be cancelled.';
        } elseif ($isCancelled) {
            $canCancel = false;
            $ineligibleReason = ($status === 'refunded' || $hotelStatus === 'refunded')
                ? 'This reservation was already refunded.'
                : 'This reservation has already been cancelled.';
        } elseif ($isCheckedIn) {
            $canCancel = false;
            $ineligibleReason = 'You are already checked in. Cancellations must be arranged directly with the front desk.';
        } elseif ($hasStarted) {
            $canCancel = false;
            $ineligibleReason = 'The scheduled start time for this booking has already arrived.';
        } elseif ($isPastDeadline) {
            $canCancel = false;
            $ineligibleReason = "The cancellation deadline for this {$policyCode} policy passed on " . $deadlineDateTime->format('M j, Y, g:i A') . '.';
        }

        // 7. Refund description and deposit-specific rules
        if ($canCancel) {
            if ($paidAmount > 0.0) {
                $refundAmount = $paidAmount;
                $refundType = $isDepositOnly ? 'deposit_full' : 'full';
                $refundDescription = $isDepositOnly
                    ? "100% full refund of the deposit paid (₱" . number_format($refundAmount, 2) . ")"
                    : "100% full refund of the amount paid (₱" . number_format($refundAmount, 2) . ")";
                $depositRule = $isDepositOnly
                    ? "Deposit Refund Rule: Because your cancellation is made before the {$policyCode} deadline, your deposit of ₱" . number_format($refundAmount, 2) . " will be fully refunded to your original payment method. The remaining balance of ₱" . number_format(max(0, $totalAmount - $depositAmount), 2) . " is cancelled and will not be charged."
                    : ($depositAmount > 0 ? "Full Payment Refund Rule: 100% of the total amount paid (₱" . number_format($refundAmount, 2) . ") will be refunded to your original payment method." : null);
                $refundStatus = 'eligible';
            } else {
                $refundAmount = 0.0;
                $refundType = 'none';
                $refundDescription = 'No payment was made — free cancellation with no charges incurred.';
                $depositRule = null;
                $refundStatus = 'none';
            }
        } else {
            if ($status === 'refunded' || $hotelStatus === 'refunded') {
                $refundAmount = !empty($item['refund_amount']) && (float) $item['refund_amount'] > 0
                    ? (float) $item['refund_amount']
                    : $paidAmount;
                $refundType = 'refunded';
                $refundDescription = "A full refund of ₱" . number_format($refundAmount, 2) . " was processed.";
                $depositRule = null;
                $refundStatus = 'initiated';
            } else {
                $refundAmount = 0.0;
                $refundType = 'none';
                $refundDescription = 'Non-refundable: Cancellation window has expired.';
                $depositRule = null;
                $refundStatus = 'ineligible';
            }
        }

        return [
            'kind' => $kind,
            'policy' => $policyCode,
            'policy_hours' => $policyHours,
            'policy_label' => $policyLabel,
            'scheduled_start' => $startDateTime->format('Y-m-d H:i:s'),
            'scheduled_start_formatted' => $startDateTime->format('M j, Y, g:i A'),
            'deadline' => $deadlineDateTime->format('Y-m-d H:i:s'),
            'deadline_formatted' => $deadlineDateTime->format('M j, Y, g:i A'),
            'deadline_timestamp' => $deadlineTs,
            'remaining_seconds' => $remainingSeconds,
            'remaining_text' => $remainingText,
            'can_cancel' => $canCancel,
            'ineligible_reason' => $ineligibleReason,
            'paid_amount' => $paidAmount,
            'refund_amount' => $refundAmount,
            'refund_type' => $refundType,
            'refund_description' => $refundDescription,
            'deposit_rule' => $depositRule,
            'refund_status' => $refundStatus,
            'processing_time' => '1–3 business days via original payment method',
            'cancelled_at' => (string) ($item['cancelled_at'] ?? ''),
            'cancellation_reason' => (string) ($item['cancellation_reason'] ?? ''),
        ];
    }

    /**
     * Process a tourist-initiated cancellation for a Hotel stay or Attraction tour.
     *
     * @return array<string, mixed>
     */
    public static function cancelBooking(int $bookingId, int $userId, string $reason = 'Cancelled by tourist'): array
    {
        if (User::isGuideWarned($userId)) {
            throw new RuntimeException('Your account has an active administrator warning. Booking actions are restricted until an administrator clears this warning.');
        }

        $booking = Booking::find($bookingId);
        if ($booking === null || (int) $booking['user_id'] !== $userId) {
            throw new InvalidArgumentException('Reservation not found.');
        }

        $isPackage = !empty($booking['package_booking_ref']) || str_contains((string) ($booking['notes'] ?? ''), 'package_booking');
        if ($isPackage) {
            PackageBookingService::cancelByTourist($bookingId, $userId, $reason);
            $updated = Booking::find($bookingId);
            $refundAmount = (float) ($updated['total_amount'] ?? 0);
            return [
                'success' => true,
                'booking_id' => $bookingId,
                'status' => 'cancelled',
                'refund_amount' => $refundAmount,
                'refund_status' => 'initiated',
                'refund_description' => '100% full package refund to original payment method',
                'deposit_rule' => 'Full package refund',
                'processing_time' => '1–3 business days via original payment method',
                'message' => 'Travel package booking cancelled successfully. A full refund has been initiated.',
                'booking' => $updated,
            ];
        }

        $isHotel = !empty($booking['room_id']) || !empty($booking['hotel_status']) || (!empty($booking['deposit_amount']) && (float) $booking['deposit_amount'] > 0);
        $kind = $isHotel ? 'hotel' : 'attraction';

        $policy = self::compute($booking, $kind);
        if (!$policy['can_cancel']) {
            throw new RuntimeException($policy['ineligible_reason'] ?? 'This booking cannot be cancelled.');
        }

        $refundAmount = (float) $policy['refund_amount'];
        $refundStatus = $refundAmount > 0 ? 'initiated' : 'none';
        $newStatus = $refundAmount > 0 ? 'refunded' : 'cancelled';

        // 1. Process payment refund if paid
        if ($refundAmount > 0) {
            Payment::refund($bookingId);
        }

        // 2. Update booking record
        $hotelStatusSql = $isHotel ? "'cancelled'" : "hotel_status";
        Database::run(
            "UPDATE bookings
             SET status = ?,
                 hotel_status = {$hotelStatusSql},
                 payment_stage = CASE WHEN ? > 0 THEN 'refunded' ELSE payment_stage END,
                 cancelled_at = NOW(),
                 cancellation_reason = ?,
                 refund_amount = ?,
                 refund_status = ?
             WHERE id = ?",
            [$newStatus, $refundAmount, $reason, $refundAmount, $refundStatus, $bookingId]
        );

        // 3. Notify customer
        $user = User::find($userId);
        $customerEmail = (string) ($user['email'] ?? '');
        $listingTitle = (string) ($booking['listing_title'] ?? 'Booking #' . $bookingId);
        if ($customerEmail !== '') {
            $refundNote = $refundAmount > 0
                ? "A full refund of ₱" . number_format($refundAmount, 2) . " has been automatically initiated to your original payment method. Expected processing time is 1–3 business days."
                : "No charges were incurred.";
            NotificationService::bookingCancelled($customerEmail, $listingTitle, $refundNote);
        }

        return [
            'success' => true,
            'booking_id' => $bookingId,
            'status' => $newStatus,
            'refund_amount' => $refundAmount,
            'refund_status' => $refundStatus,
            'refund_description' => $policy['refund_description'],
            'deposit_rule' => $policy['deposit_rule'],
            'processing_time' => '1–3 business days via original payment method',
            'message' => $refundAmount > 0
                ? "Booking cancelled successfully. " . $policy['refund_description'] . " has been initiated."
                : "Booking cancelled successfully. No charges were incurred.",
            'booking' => Booking::find($bookingId),
        ];
    }

    /**
     * Process a tourist-initiated cancellation for a Vehicle Rental.
     *
     * @return array<string, mixed>
     */
    public static function cancelRental(int $rentalId, int $userId, string $reason = 'Cancelled by tourist'): array
    {
        if (User::isGuideWarned($userId)) {
            throw new RuntimeException('Your account has an active administrator warning. Rental actions are restricted until an administrator clears this warning.');
        }

        $rental = RentalRequest::find($rentalId);
        if ($rental === null || (int) $rental['user_id'] !== $userId) {
            throw new InvalidArgumentException('Vehicle reservation not found.');
        }

        $policy = self::compute($rental, 'rental');
        if (!$policy['can_cancel']) {
            throw new RuntimeException($policy['ineligible_reason'] ?? 'This reservation cannot be cancelled.');
        }

        $refundAmount = (float) $policy['refund_amount'];
        $refundStatus = $refundAmount > 0 ? 'initiated' : 'none';
        $newStatus = $refundAmount > 0 ? 'refunded' : 'cancelled';
        $newPaymentStatus = $refundAmount > 0 ? 'refunded' : (string) ($rental['payment_status'] ?? 'unpaid');

        // 1. Deduct provider revenue if paid
        if ($refundAmount > 0 && ($rental['payment_status'] ?? '') === 'paid') {
            $vehicleId = $rental['vehicle_id'] ?? null;
            if ($vehicleId !== null && is_numeric($vehicleId)) {
                $vehicle = Vehicle::find((int) $vehicleId);
                if ($vehicle !== null && !empty($vehicle['user_id'])) {
                    User::deductRevenue((int) $vehicle['user_id'], $refundAmount);
                }
            }
        }

        // 2. Update rental request record
        Database::run(
            "UPDATE rental_requests
             SET status = ?,
                 payment_status = ?,
                 cancelled_at = NOW(),
                 cancellation_reason = ?,
                 refund_amount = ?,
                 refund_status = ?
             WHERE id = ?",
            [$newStatus, $newPaymentStatus, $reason, $refundAmount, $refundStatus, $rentalId]
        );

        // 3. Notify customer
        $customerEmail = (string) ($rental['customer_email'] ?? '');
        $vehicleName = (string) ($rental['vehicle_name'] ?? 'Vehicle Rental');
        if ($customerEmail !== '') {
            $refundNote = $refundAmount > 0
                ? "A full refund of ₱" . number_format($refundAmount, 2) . " has been automatically initiated to your original payment method. Expected processing time is 1–3 business days."
                : "No charges were incurred.";
            NotificationService::rentalCancelled($customerEmail, $vehicleName, $refundNote);
        }

        // 4. If this rental request is part of a Travel Package, synchronize package cancellation
        if (!empty($rental['package_booking_ref']) || !empty($rental['package_booking_id'])) {
            PackageBookingService::onRentalUpdated($rentalId, 'cancelled', $userId);
        }

        return [
            'success' => true,
            'rental_id' => $rentalId,
            'status' => $newStatus,
            'refund_amount' => $refundAmount,
            'refund_status' => $refundStatus,
            'refund_description' => $policy['refund_description'],
            'processing_time' => '1–3 business days via original payment method',
            'message' => $refundAmount > 0
                ? "Vehicle reservation cancelled successfully. " . $policy['refund_description'] . " has been initiated."
                : "Vehicle reservation cancelled successfully. No charges were incurred.",
            'rental' => RentalRequest::find($rentalId),
        ];
    }
}
