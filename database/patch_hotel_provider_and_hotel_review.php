<?php

declare(strict_types=1);

/**
 * Migration: Rename hotel_admin to hotel_provider & Add Hotel Review Audit Columns
 *
 * 1. Updates `users.role` enum to include 'hotel_provider'.
 * 2. Migrates existing 'hotel_admin' accounts to 'hotel_provider'.
 * 3. Finalizes `users.role` enum to ('admin','guide','tourist','rental_provider','hotel_provider').
 * 4. Adds hotel review columns to `listings`:
 *    - `rejection_reason` VARCHAR(500) NULL
 *    - `submitted_at` TIMESTAMP NULL DEFAULT NULL
 *    - `reviewed_at` TIMESTAMP NULL DEFAULT NULL
 *    - `reviewed_by` INT UNSIGNED NULL DEFAULT NULL
 * 5. Backfills audit timestamps for existing approved hotel listings.
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "=== Running Migration: Hotel Provider Role & Hotel Review Workflow ===\n";

try {
    // 1. Temporarily allow both roles in enum so we can migrate data smoothly
    Database::run("ALTER TABLE users MODIFY COLUMN role ENUM('admin','guide','tourist','rental_provider','rental_admin','hotel_provider','hotel_admin') NOT NULL DEFAULT 'tourist'");
    echo "✓ Temporarily updated users.role enum.\n";

    // 2. Migrate existing accounts
    $stmt = Database::run("UPDATE users SET role = 'hotel_provider' WHERE role = 'hotel_admin'");
    $migrated = $stmt ? $stmt->rowCount() : 0;
    echo "✓ Migrated existing hotel_admin users to hotel_provider: {$migrated} row(s) updated.\n";

    // 3. Finalize the enum
    Database::run("ALTER TABLE users MODIFY COLUMN role ENUM('admin','guide','tourist','rental_provider','hotel_provider') NOT NULL DEFAULT 'tourist'");
    echo "✓ Finalized users.role enum to ('admin','guide','tourist','rental_provider','hotel_provider').\n";

    // 4. Add rejection_reason column to listings if not exists
    $hasRejectionReason = Database::first("SHOW COLUMNS FROM listings LIKE 'rejection_reason'");
    if (!$hasRejectionReason) {
        Database::run("ALTER TABLE listings ADD COLUMN rejection_reason VARCHAR(500) NULL AFTER status");
        echo "✓ Added column listings.rejection_reason (VARCHAR(500) NULL).\n";
    } else {
        echo "✓ Column listings.rejection_reason already exists.\n";
    }

    // 5. Add submitted_at column to listings if not exists
    $hasSubmittedAt = Database::first("SHOW COLUMNS FROM listings LIKE 'submitted_at'");
    if (!$hasSubmittedAt) {
        Database::run("ALTER TABLE listings ADD COLUMN submitted_at TIMESTAMP NULL DEFAULT NULL AFTER rejection_reason");
        echo "✓ Added column listings.submitted_at (TIMESTAMP NULL).\n";
    } else {
        echo "✓ Column listings.submitted_at already exists.\n";
    }

    // 6. Add reviewed_at column to listings if not exists
    $hasReviewedAt = Database::first("SHOW COLUMNS FROM listings LIKE 'reviewed_at'");
    if (!$hasReviewedAt) {
        Database::run("ALTER TABLE listings ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER submitted_at");
        echo "✓ Added column listings.reviewed_at (TIMESTAMP NULL).\n";
    } else {
        echo "✓ Column listings.reviewed_at already exists.\n";
    }

    // 7. Add reviewed_by column to listings if not exists
    $hasReviewedBy = Database::first("SHOW COLUMNS FROM listings LIKE 'reviewed_by'");
    if (!$hasReviewedBy) {
        Database::run("ALTER TABLE listings ADD COLUMN reviewed_by INT UNSIGNED NULL DEFAULT NULL AFTER reviewed_at");
        Database::run("ALTER TABLE listings ADD KEY idx_listings_reviewed_by (reviewed_by)");
        echo "✓ Added column listings.reviewed_by (INT UNSIGNED NULL).\n";
    } else {
        echo "✓ Column listings.reviewed_by already exists.\n";
    }

    // 8. Backfill audit timestamps for existing approved hotel listings
    $cat = Database::first("SELECT id FROM categories WHERE slug = 'hotels'");
    $hotelCatId = $cat ? (int) $cat['id'] : 3;

    $admin = Database::first("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $adminId = $admin ? (int) $admin['id'] : 1;

    Database::run(
        "UPDATE listings
         SET submitted_at = created_at,
             reviewed_at = created_at,
             reviewed_by = ?
         WHERE category_id = ? AND status = 'approved' AND submitted_at IS NULL",
        [$adminId, $hotelCatId]
    );
    echo "✓ Backfilled audit timestamps for existing approved hotel listings.\n";

    echo "=== Migration Completed Successfully! ===\n";
} catch (\Throwable $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    exit(1);
}
