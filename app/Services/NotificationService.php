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

    public static function passwordReset(string $email, string $token, ?string $link = null): void
    {
        $link = $link ?? self::appUrl('/reset-password/' . $token);
        self::send(
            $email,
            'Reset your GuideMate password',
            "Hi,\n\nWe received a request to reset your GuideMate password.\n\n"
            . "Use this link to set a new password (valid for 1 hour):\n\n{$link}\n\n"
            . "If you didn't request this, you can safely ignore this email.\n\n— GuideMate"
        );
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

    private static function send(string $to, string $subject, string $body): void
    {
        Mailer::send($to, $subject, $body);
        SmsService::notifyIfConfigured($to, $subject);
    }

    private static function appUrl(string $path = '/'): string
    {
        $base = trim((string) (App::config('app')['url'] ?? ''));
        if ($base !== '') {
            return rtrim($base, '/') . $path;
        }
        return \url($path);
    }
}
