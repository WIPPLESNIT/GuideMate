<?php

declare(strict_types=1);

/**
 * Migration: Replace rental_admin with rental_provider and add initial account metrics.
 *
 * 1. Updates `users.role` enum to include 'rental_provider'.
 * 2. Migrates existing 'rental_admin' accounts to 'rental_provider'.
 * 3. Finalizes `users.role` enum to ('admin','guide','tourist','rental_provider','hotel_admin').
 * 4. Adds `revenue` (DECIMAL(12,2) DEFAULT 0.00) and `total_requests` (INT UNSIGNED DEFAULT 0)
 *    to `users` table so every newly registered user starts with revenue=0 and total_requests=0.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "Running migration: Rental Provider Role & Account Metrics...\n";

try {
    // 1. Temporarily allow both roles in enum so we can migrate data smoothly
    Database::run("ALTER TABLE users MODIFY COLUMN role ENUM('admin','guide','tourist','rental_provider','rental_admin','hotel_admin') NOT NULL DEFAULT 'tourist'");

    // 2. Migrate existing accounts
    $stmt = Database::run("UPDATE users SET role = 'rental_provider' WHERE role = 'rental_admin'");
    $migrated = $stmt ? $stmt->rowCount() : 0;
    echo "Migrated existing rental_admin users to rental_provider: {$migrated} row(s) updated.\n";

    // 3. Finalize the enum
    Database::run("ALTER TABLE users MODIFY COLUMN role ENUM('admin','guide','tourist','rental_provider','hotel_admin') NOT NULL DEFAULT 'tourist'");
    echo "Updated users.role enum to ('admin','guide','tourist','rental_provider','hotel_admin').\n";

    // 4. Add revenue column if not exists
    $hasRevenue = Database::first("SHOW COLUMNS FROM users LIKE 'revenue'");
    if (!$hasRevenue) {
        Database::run("ALTER TABLE users ADD COLUMN revenue DECIMAL(12, 2) NOT NULL DEFAULT 0.00 AFTER guide_status");
        echo "Added column users.revenue (DECIMAL(12,2) DEFAULT 0.00).\n";
    } else {
        echo "Column users.revenue already exists.\n";
    }

    // 5. Add total_requests column if not exists
    $hasTotalRequests = Database::first("SHOW COLUMNS FROM users LIKE 'total_requests'");
    if (!$hasTotalRequests) {
        Database::run("ALTER TABLE users ADD COLUMN total_requests INT UNSIGNED NOT NULL DEFAULT 0 AFTER revenue");
        echo "Added column users.total_requests (INT UNSIGNED DEFAULT 0).\n";
    } else {
        echo "Column users.total_requests already exists.\n";
    }

    // 6. Ensure existing users have zero or valid defaults
    Database::run("UPDATE users SET revenue = 0.00 WHERE revenue IS NULL");
    Database::run("UPDATE users SET total_requests = 0 WHERE total_requests IS NULL");

    echo "Migration completed successfully!\n";
} catch (Throwable $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
