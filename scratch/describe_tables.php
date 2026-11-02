<?php
require_once __DIR__ . '/../bootstrap.php';
echo "--- listings columns ---\n";
$c = App\Core\Database::all('DESCRIBE listings');
foreach ($c as $col) {
    if (in_array($col['Field'], ['id', 'title', 'capacity', 'price', 'price_unit', 'duration', 'included', 'not_included'])) {
        echo $col['Field'] . ' (' . $col['Type'] . ")\n";
    }
}
echo "\n--- bookings columns ---\n";
$b = App\Core\Database::all('DESCRIBE bookings');
foreach ($b as $col) {
    echo $col['Field'] . ' (' . $col['Type'] . ")\n";
}
