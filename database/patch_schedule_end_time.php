<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

echo "=== Adding end_time column to listing_schedules ===\n";

try {
    $col = Database::all("SHOW COLUMNS FROM `listing_schedules` LIKE 'end_time'");
    if (empty($col)) {
        Database::run("ALTER TABLE `listing_schedules` ADD COLUMN `end_time` VARCHAR(5) NULL AFTER `start_time`");
        echo "✓ Added end_time VARCHAR(5) NULL AFTER start_time to listing_schedules.\n";
    } else {
        echo "✓ Column end_time already exists.\n";
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== Migration complete ===\n";
