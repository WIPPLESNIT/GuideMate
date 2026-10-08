<?php

declare(strict_types=1);

/**
 * GuideMate installer / seeder (CLI).
 *
 * Usage:  php database/migrate.php
 *
 * 1. Creates the database (if missing)
 * 2. Applies database/schema.sql (drops & recreates tables)
 * 3. Seeds ONLY the real admin account + the category structure.
 *
 * NOTE: This is a clean, production-ready seed. There are no demo/sample
 * accounts or listings — guides register and add their own real listings.
 */

if (PHP_SAPI !== 'cli') {
    exit('This script must be run from the command line: php database/migrate.php');
}

require __DIR__ . '/../bootstrap.php';

use App\Core\App;

$db = App::config('db');

echo "GuideMate installer\n===================\n";

try {
    // Connect to the server (no database selected) to create it.
    $serverDsn = sprintf('mysql:host=%s;port=%d;charset=%s', $db['host'], $db['port'], $db['charset']);
    $pdo = new PDO($serverDsn, $db['username'], $db['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '{$db['database']}' ready\n";

    $pdo->exec("USE `{$db['database']}`");

    // Apply schema.
    $schema = file_get_contents(__DIR__ . '/schema.sql');
    if ($schema === false) {
        throw new RuntimeException('Could not read schema.sql');
    }
    $pdo->exec($schema);
    echo "✓ Schema applied\n";

    seed($pdo);
    echo "✓ Admin account + categories seeded\n";

    echo "\nDone! Administrator login:\n";
    echo "  Admin portal : /admin/login\n";
    echo "  Email        : fbaquerosa@gmail.com\n";
    echo "  Password     : (the one you set)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\n✗ Install failed: " . $e->getMessage() . "\n");
    exit(1);
}

/**
 * Seed the real administrator account and the category structure.
 * No demo users, listings, reviews, bookings or messages are created.
 */
function seed(PDO $pdo): void
{
    // -- Administrator -----------------------------------------------------
    $adminHash = password_hash('@Wem12345', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, password, role, location, bio)
         VALUES (?, ?, ?, "admin", ?, ?)'
    );
    $stmt->execute([
        'Administrator',
        'fbaquerosa@gmail.com',
        $adminHash,
        'Cebu City',
        'Platform administrator.',
    ]);

    // -- Categories (required so guides can create listings) ---------------
    $categories = [
        ['Things to Do', 'things-to-do', 'compass', 'Tours, attractions & activities across Cebu', 1],
        ['Tour Guides', 'tour-guides', 'user-check', 'Hire trusted local Cebuano guides', 2],
        ['Hotels & Stays', 'hotels', 'bed', 'Resorts, hotels and stays', 3],
        ['Restaurants', 'restaurants', 'utensils', 'Where to eat in Cebu', 4],
    ];
    $stmt = $pdo->prepare('INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (?, ?, ?, ?, ?)');
    foreach ($categories as $c) {
        $stmt->execute($c);
    }
}
