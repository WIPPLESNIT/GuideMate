<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;

try {
    $existing = Database::one("SELECT id FROM categories WHERE slug = 'packages'");
    if (!$existing) {
        Database::run(
            'INSERT INTO categories (name, slug, icon, description, sort_order) VALUES (?, ?, ?, ?, ?)',
            ['Travel Packages', 'packages', 'cube', 'All-inclusive multi-service trips curated and hosted by local guides', 5]
        );
        echo "✓ 'Travel Packages' category added to database successfully.\n";
    } else {
        echo "✓ 'Travel Packages' category already exists in database.\n";
    }
} catch (Throwable $e) {
    echo "Database patch notice: " . $e->getMessage() . "\n";
}
