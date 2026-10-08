<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Models\Booking;
use App\Services\NotificationService;

foreach (Booking::dueReminders() as $row) {
    $title = (string) $row['listing_title'];
    $date = (string) $row['booking_date'];
    NotificationService::bookingReminder((string) $row['customer_email'], $title, $date);
    NotificationService::bookingReminder((string) $row['guide_email'], $title, $date);
    Booking::markReminderSent((int) $row['id']);
}

echo 'Cron complete: ' . date('c') . "\n";
