<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

try {
    // Check existing columns
    $cols = array_column(Database::all('DESCRIBE password_resets'), 'Field');

    if (!in_array('created_at', $cols, true)) {
        Database::run('ALTER TABLE password_resets ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER expires_at');
        echo "Added created_at column\n";
    }

    if (!in_array('used_at', $cols, true)) {
        Database::run('ALTER TABLE password_resets ADD COLUMN used_at TIMESTAMP NULL DEFAULT NULL AFTER created_at');
        echo "Added used_at column\n";
    }

    // Check indexes
    $indexes = array_column(Database::all('SHOW INDEX FROM password_resets'), 'Key_name');
    if (!in_array('idx_password_resets_token', $indexes, true)) {
        Database::run('ALTER TABLE password_resets ADD INDEX idx_password_resets_token (token)');
        echo "Added idx_password_resets_token index\n";
    }

    echo "Migration completed successfully.\n";
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
