<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;

try {
    echo "Running patch_checked_in_payment_status...\n";

    // Update enum for payment_stage to include 'paid'
    Database::run("ALTER TABLE bookings MODIFY COLUMN payment_stage ENUM('full','deposit_paid','balance_due','paid','settled') NOT NULL DEFAULT 'full'");
    echo " [OK] payment_stage enum updated to include 'paid'\n";

    // Update checked_in bookings that are not settled to 'paid'
    $affected = Database::run("UPDATE bookings SET payment_stage = 'paid', balance_amount = 0.00 WHERE hotel_status = 'checked_in' AND payment_stage != 'settled'");
    echo " [OK] Checked-in reservations updated to 'paid' with balance = 0.00\n";

    // Ensure completed reservations stay settled
    Database::run("UPDATE bookings SET payment_stage = 'settled', balance_amount = 0.00 WHERE hotel_status = 'completed' AND payment_stage != 'settled'");
    echo " [OK] Completed reservations ensured 'settled'\n";

    echo "Migration completed successfully.\n";
} catch (\Throwable $e) {
    echo "Error running migration: " . $e->getMessage() . "\n";
    exit(1);
}
