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

    private static function send(string $to, string $subject, string $body, ?string $htmlBody = null): bool
    {
        $mailOk = Mailer::send($to, $subject, $body, $htmlBody);
        SmsService::notifyIfConfigured($to, $subject);
        return $mailOk;
    }

    private static function appUrl(string $path = '/'): string
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
