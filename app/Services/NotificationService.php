<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Mailer;
use App\Core\Upload;

final class NotificationService
{
    public static function welcome(string $email, string $name): void
    {
        self::send($email, 'Welcome to GuideMate', "Hi {$name},\n\nWelcome to GuideMate! Start exploring the best of Cebu.\n\n" . self::appUrl());
    }

    public static function passwordReset(string $email, string $token, ?string $link = null): bool
    {
        $link = $link ?? self::appUrl('/reset-password/' . $token);
        $subject = 'Reset your GuideMate password';

        $textBody = "Hello,\n\n"
            . "We received a request to reset the password for your GuideMate account.\n\n"
            . "Click the link below to set a new password:\n"
            . "{$link}\n\n"
            . "IMPORTANT:\n"
            . "- This link is valid for 30 minutes.\n"
            . "- This link is single-use and cannot be reused once your password is changed.\n\n"
            . "If you did not request a password reset, you can safely ignore this email. Your password will remain unchanged.\n\n"
            . "— The GuideMate Team\n"
            . self::appUrl('/');

        $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
.card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
.brand { font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 24px; }
.brand span { color: #059669; }
h1 { font-size: 20px; font-weight: 700; margin: 0 0 16px 0; color: #0f172a; }
p { font-size: 15px; line-height: 1.6; margin: 0 0 16px 0; color: #475569; }
.btn-wrap { margin: 28px 0; text-align: center; }
.btn { display: inline-block; background-color: #059669; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; }
.notice { background: #f1f5f9; border-left: 4px solid #059669; padding: 12px 16px; border-radius: 4px; font-size: 13px; color: #475569; margin: 24px 0; }
.footer { font-size: 12px; color: #94a3b8; margin-top: 32px; border-top: 1px solid #e2e8f0; padding-top: 16px; word-break: break-all; }
</style>
</head>
<body>
<div class="card">
    <div class="brand">Guide<span>Mate</span></div>
    <h1>Password Reset Request</h1>
    <p>Hello,</p>
    <p>We received a request to reset your GuideMate account password. Click the button below to set a new password:</p>
    <div class="btn-wrap">
        <a href="{$link}" class="btn" target="_blank">Reset Password</a>
    </div>
    <div class="notice">
        <strong>Security Notice:</strong>
        <ul style="margin:6px 0 0 18px;padding:0;">
            <li>This link expires in <strong>30 minutes</strong>.</li>
            <li>This link is single-use and cannot be reused after your password is changed.</li>
        </ul>
    </div>
    <p>If you did not request a password reset, you can safely ignore this email. Your account remains secure.</p>
    <div class="footer">
        If the button above does not work, copy and paste this link into your browser:<br>
        <a href="{$link}" style="color:#059669;">{$link}</a>
    </div>
</div>
</body>
</html>
HTML;

        return self::send($email, $subject, $textBody, $htmlBody);
    }

    public static function bookingAwaitingConfirmation(string $email, string $listingTitle, string $date): void
    {
        self::send(
            $email,
            'Payment received — waiting for confirmation',
            "Your payment for {$listingTitle} on {$date} was received.\n\n"
            . "The partner still needs to confirm this booking. You will get another message once they accept it.\n\n"
            . 'Track it here: ' . self::appUrl('/bookings')
        );
    }

    public static function bookingNeedsConfirmation(
        string $email,
        string $ownerName,
        string $listingTitle,
        string $customerName,
        string $date
    ): void {
        self::send(
            $email,
            'Confirm this booking — ' . $listingTitle,
            "Hi {$ownerName},\n\n"
            . "{$customerName} booked \"{$listingTitle}\" on {$date} and has already paid.\n\n"
            . "Please confirm or decline it in your dashboard:\n"
            . self::appUrl('/dashboard/bookings')
        );
    }

    public static function bookingConfirmed(string $email, string $listingTitle, string $date): void
    {
        self::send($email, 'Booking confirmed — ' . $listingTitle, "Your booking for {$listingTitle} on {$date} is confirmed.\n\nView bookings: " . self::appUrl('/bookings'));
    }

    public static function rentalAwaitingConfirmation(string $email, string $vehicleName, string $pickupDate): void
    {
        self::send(
            $email,
            'Payment received — waiting for rental confirmation',
            "Your payment for {$vehicleName} (pickup {$pickupDate}) was received.\n\n"
            . "The rental partner still needs to confirm this reservation. You will get another message once they accept it."
        );
    }

    public static function rentalConfirmed(string $email, string $vehicleName, string $pickupDate): void
    {
        self::send(
            $email,
            'Rental confirmed — ' . $vehicleName,
            "Your reservation for {$vehicleName} (pickup {$pickupDate}) has been confirmed by the rental partner.\n\nThey will contact you to arrange delivery."
        );
    }

    public static function rentalDeclined(string $email, string $vehicleName, string $refundNote = ''): void
    {
        $body = "Your reservation for {$vehicleName} was declined by the rental partner.";
        if ($refundNote !== '') {
            $body .= "\n\n{$refundNote}";
        }
        self::send($email, 'Rental declined — ' . $vehicleName, $body);
    }

    public static function rentalCancelled(string $email, string $vehicleName, string $refundNote = ''): void
    {
        $body = "Your vehicle reservation for {$vehicleName} has been cancelled.";
        if ($refundNote !== '') {
            $body .= "\n\n{$refundNote}";
        }
        self::send($email, 'Rental reservation cancelled — ' . $vehicleName, $body);
    }

    public static function bookingCancelled(string $email, string $listingTitle, string $refundNote = ''): void
    {
        $body = "Your booking for {$listingTitle} was cancelled.";
        if ($refundNote !== '') {
            $body .= "\n\n{$refundNote}";
        }
        self::send($email, 'Booking cancelled — ' . $listingTitle, $body);
    }

    public static function bookingReminder(string $email, string $listingTitle, string $date): void
    {
        self::send($email, 'Reminder: tour tomorrow — ' . $listingTitle, "This is a reminder that your booking for {$listingTitle} is on {$date}.\n");
    }

    public static function adminBookingPlaced(string $email, string $subject, string $body): void
    {
        self::send($email, $subject, $body);
    }

    public static function guideApproved(string $email, string $name, string $roleLabel = 'partner'): void
    {
        self::send(
            $email,
            'Application Approved',
            "Hi {$name},\n\nYour {$roleLabel} application has been approved. You can now use your partner account on GuideMate.\n"
        );
    }

    public static function guideRejected(string $email, string $name, string $note = '', string $roleLabel = 'partner'): void
    {
        $body = "Hi {$name},\n\nYour {$roleLabel} application was not approved.";
        if ($note !== '') {
            $body .= "\n\nAdmin note: {$note}";
        }
        self::send($email, 'Application update', $body);
    }

    public static function vehicleApproved(string $email, string $vehicleName): void
    {
        self::send(
            $email,
            'Vehicle listing approved — ' . $vehicleName,
            "Great news! Your vehicle listing for {$vehicleName} has been approved by the administrator and is now published and available for tourists to rent."
        );
    }

    public static function vehicleRejected(string $email, string $vehicleName, string $reason = ''): void
    {
        $body = "Your vehicle listing for {$vehicleName} was not approved by the administrator.";
        if ($reason !== '') {
            $body .= "\n\nReason: {$reason}";
        }
        $body .= "\n\nYou can review the feedback, update your listing details or photos, and resubmit it for approval from your dashboard.";
        self::send($email, 'Vehicle listing update — ' . $vehicleName, $body);
    }

    public static function hotelApproved(string $email, string $hotelTitle): void
    {
        self::send(
            $email,
            'Hotel listing approved — ' . $hotelTitle,
            "Great news! Your hotel property listing for {$hotelTitle} has been approved by the administrator and is now published and available for travelers. You can now configure room types and inventory from your dashboard."
        );
    }

    public static function guideListingApproved(string $email, string $title): void
    {
        self::send(
            $email,
            'Listing approved — ' . $title,
            "Great news! Your guide listing for {$title} has been approved by the administrator and is now published and visible to travelers."
        );
    }

    public static function guideListingRejected(string $email, string $title, string $reason = ''): void
    {
        $body = "Your guide listing for {$title} was not approved by the administrator.";
        if ($reason !== '') {
            $body .= "\n\nReason: {$reason}";
        }
        $body .= "\n\nYou can review the feedback, update your listing details, photos, or schedule, and resubmit it for approval from your dashboard.";
        self::send($email, 'Listing update — ' . $title, $body);
    }

    public static function hotelRejected(string $email, string $hotelTitle, string $reason = ''): void
    {
        $body = "Your hotel property listing for {$hotelTitle} was not approved by the administrator.";
        if ($reason !== '') {
            $body .= "\n\nReason: {$reason}";
        }
        $body .= "\n\nYou can review the feedback, update your property details or amenities, and resubmit it for approval from your dashboard.";
        self::send($email, 'Hotel listing update — ' . $hotelTitle, $body);
    }

    public static function packageBookingConfirmedToGuide(
        string $guideEmail,
        string $guideName,
        string $packageTitle,
        string $bookingRef,
        string $touristName,
        string $touristPhone,
        string $touristEmail,
        string $travelDate,
        int $guests,
        float $totalAmount,
        string $hotelSummary = '',
        string $vehicleSummary = '',
        array $activities = []
    ): void {
        $subject = 'New Package Booking Confirmed — ' . $packageTitle . ' (Ref: ' . $bookingRef . ')';
        $body = "Hi {$guideName},\n\n"
            . "Great news! A new traveler has booked and confirmed your travel package \"{$packageTitle}\".\n\n"
            . "Booking Reference: {$bookingRef}\n"
            . "Travel Start Date: {$travelDate}\n"
            . "Number of Guests: {$guests} persons\n"
            . "Total Package Price: PHP " . number_format($totalAmount, 2) . " (Paid & Confirmed)\n\n"
            . "Traveler Contact Details:\n"
            . "- Lead Guest: {$touristName}\n"
            . "- Mobile: {$touristPhone}\n"
            . ($touristEmail !== '' ? "- Email: {$touristEmail}\n\n" : "\n")
            . ($hotelSummary !== '' ? "Bundled Hotel Accommodation:\n{$hotelSummary}\n\n" : "")
            . ($vehicleSummary !== '' ? "Bundled Transportation / Vehicle Rental:\n{$vehicleSummary}\n\n" : "")
            . (!empty($activities) ? "Bundled Activities & Attractions:\n- " . implode("\n- ", $activities) . "\n\n" : "")
            . "Please reach out to the tourist to coordinate the meeting point, scooter handover, and day-by-day itinerary.\n"
            . self::appUrl('/dashboard/listings');

        self::send($guideEmail, $subject, $body);
    }

    public static function packageHotelReservationToProvider(
        string $providerEmail,
        string $providerName,
        string $hotelName,
        string $roomName,
        string $bookingRef,
        string $packageTitle,
        string $guideName,
        string $touristName,
        string $touristPhone,
        string $checkInDate,
        string $checkOutDate,
        int $nights,
        int $guests
    ): void {
        $subject = 'New Hotel Reservation from Package — ' . $hotelName . ' (Ref: ' . $bookingRef . ')';
        $body = "Hi {$providerName},\n\n"
            . "You have a new hotel room reservation included as part of the confirmed travel package \"{$packageTitle}\".\n\n"
            . "Hotel Property: {$hotelName}\n"
            . "Room Type: {$roomName}\n"
            . "Reservation Reference: {$bookingRef}\n"
            . "Check-in Date: {$checkInDate} (from 02:00 PM)\n"
            . "Check-out Date: {$checkOutDate} (until 12:00 PM)\n"
            . "Duration: {$nights} " . ($nights === 1 ? 'Night' : 'Nights') . "\n"
            . "Guests: {$guests} persons\n\n"
            . "Guest Details:\n"
            . "- Lead Guest: {$touristName}\n"
            . "- Mobile: {$touristPhone}\n"
            . "- Coordinating Guide Host: {$guideName}\n\n"
            . "Payment Status: Paid & Confirmed via Travel Package Bundle.\n\n"
            . "View and manage your hotel reservations:\n"
            . self::appUrl('/dashboard/hotel');

        self::send($providerEmail, $subject, $body);
    }

    public static function packageVehicleReservationToProvider(
        string $providerEmail,
        string $providerName,
        string $vehicleName,
        string $rentalRef,
        string $packageTitle,
        string $guideName,
        string $touristName,
        string $touristPhone,
        string $pickupDate,
        int $rentalDays,
        string $location
    ): void {
        $subject = 'New Vehicle Reservation from Package — ' . $vehicleName . ' (Ref: #' . $rentalRef . ')';
        $body = "Hi {$providerName},\n\n"
            . "You have a new vehicle rental reservation included as part of the confirmed travel package \"{$packageTitle}\".\n\n"
            . "Vehicle: {$vehicleName}\n"
            . "Rental Reference: #{$rentalRef}\n"
            . "Pickup Date: {$pickupDate}\n"
            . "Rental Duration: {$rentalDays} " . ($rentalDays === 1 ? 'Day' : 'Days') . "\n"
            . "Handover Location: {$location}\n\n"
            . "Customer Details:\n"
            . "- Customer Name: {$touristName}\n"
            . "- Mobile: {$touristPhone}\n"
            . "- Coordinating Guide Host: {$guideName}\n\n"
            . "Payment Status: Paid & Confirmed via Travel Package Bundle.\n\n"
            . "View and manage your vehicle rentals:\n"
            . self::appUrl('/dashboard/rentals');

        self::send($providerEmail, $subject, $body);
    }

    public static function packageActivityReservationToHost(
        string $hostEmail,
        string $hostName,
        string $activityTitle,
        string $packageTitle,
        string $guideName,
        string $touristName,
        string $activityDate,
        int $guests
    ): void {
        $subject = 'New Activity Booking from Package — ' . $activityTitle;
        $body = "Hi {$hostName},\n\n"
            . "A traveler has booked your activity/attraction \"{$activityTitle}\" as part of the bundled travel package \"{$packageTitle}\".\n\n"
            . "Scheduled Date: {$activityDate}\n"
            . "Participants: {$guests} persons\n"
            . "Traveler: {$touristName}\n"
            . "Lead Guide Host: {$guideName}\n\n"
            . "Payment is verified and settled via the travel package reservation.\n"
            . self::appUrl('/dashboard/bookings');

        self::send($hostEmail, $subject, $body);
    }

    public static function packageCancellationNotification(
        string $email,
        string $recipientName,
        string $role,
        string $packageTitle,
        string $bookingRef,
        string $touristName,
        string $serviceSpecificNote = ''
    ): void {
        $subject = 'Package Booking Cancelled — ' . $packageTitle . ' (Ref: ' . $bookingRef . ')';
        $body = "Hello {$recipientName},\n\n"
            . "The travel package reservation \"{$packageTitle}\" (Ref: {$bookingRef}) booked by traveler {$touristName} has been cancelled.\n\n"
            . ($serviceSpecificNote !== '' ? "{$serviceSpecificNote}\n\n" : "")
            . "The system has updated all associated reservation records and returned availability to your inventory.\n\n"
            . "— The GuideMate Team\n"
            . self::appUrl('/dashboard');

        self::send($email, $subject, $body);
    }

    public static function packageInclusionRequest(
        string $email,
        string $providerName,
        string $packageTitle,
        string $guideName,
        string $guideEmail,
        string $guidePhone,
        string $serviceType,
        string $serviceName,
        string $rateFormatted,
        string $durationText,
        array $itineraryDays,
        int $packageId,
        int $serviceId
    ): void {
        $subject = "Travel Package Inclusion Request — {$packageTitle}";
        $reviewUrl = self::appUrl('/dashboard/package-requests');

        $itinSummary = '';
        if (!empty($itineraryDays)) {
            $itinSummary = "\nPlanned Schedule Overview:\n";
            foreach (array_slice($itineraryDays, 0, 4) as $d) {
                $dayNum = $d['day'] ?? '?';
                $dayTitle = $d['title'] ?? ('Day ' . $dayNum);
                $itinSummary .= "  • Day {$dayNum}: {$dayTitle}\n";
            }
        }

        $textBody = "Hello {$providerName},\n\n"
            . "A licensed tour guide has requested to bundle your service into a new GuideMate Travel Package.\n\n"
            . "PACKAGE DETAILS:\n"
            . "• Travel Package: {$packageTitle}\n"
            . "• Guide Host: {$guideName}" . ($guideEmail ? " ({$guideEmail})" : "") . ($guidePhone ? " | Phone: {$guidePhone}" : "") . "\n"
            . "• Duration: {$durationText}\n\n"
            . "YOUR SERVICE REQUESTED:\n"
            . "• Service Type: {$serviceType}\n"
            . "• Listing / Unit: {$serviceName}\n"
            . "• Agreed Rate: {$rateFormatted}\n"
            . $itinSummary . "\n"
            . "ACTION REQUIRED:\n"
            . "Please review how your service will be utilized in this package and respond with your decision (Agree or Reject):\n"
            . "{$reviewUrl}\n\n"
            . "— The GuideMate Partner Team\n"
            . self::appUrl('/');

        self::send($email, $subject, $textBody);
    }

    public static function packageProviderDecision(
        string $email,
        string $guideName,
        string $providerName,
        string $serviceType,
        string $packageTitle,
        string $decision,
        ?string $rejectionReason,
        string $overallStatus
    ): void {
        $isAgreed = ($decision === 'agreed' || $decision === 'approved');
        $subject = $isAgreed
            ? "Service Partner Agreed to Package — {$packageTitle}"
            : "Service Partner Declined Package Inclusion — {$packageTitle}";

        $statusLabel = match($overallStatus) {
            'agreed' => 'Agreed (All providers have approved)',
            'rejected' => 'Rejected (At least one provider declined)',
            default => 'Pending (Waiting for other providers)',
        };

        $body = "Hello {$guideName},\n\n"
            . "Service partner {$providerName} has responded to your inclusion request for \"{$packageTitle}\".\n\n"
            . "• Service: {$serviceType}\n"
            . "• Decision: " . ($isAgreed ? "AGREED" : "REJECTED") . "\n"
            . (!$isAgreed && $rejectionReason ? "• Reason: {$rejectionReason}\n" : "")
            . "• Overall Package Approval Status: {$statusLabel}\n\n"
            . "You can track and manage your travel packages here:\n"
            . self::appUrl('/dashboard/listings') . "\n\n"
            . "— The GuideMate Partner Team";

        self::send($email, $subject, $body);
    }

    public static function send(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        $mailOk = Mailer::send($to, $subject, $body, $htmlBody);
        SmsService::notifyIfConfigured($to, $subject);
        return $mailOk;
    }

    public static function appUrl(string $path = '/'): string
    {
        $base = trim((string) (App::config('app')['url'] ?? ''));
        if ($base !== '') {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $relative = \url($path);

        return $scheme . '://' . $host . $relative;
    }
}
