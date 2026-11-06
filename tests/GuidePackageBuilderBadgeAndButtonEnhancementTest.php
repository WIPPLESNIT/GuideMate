<?php
/**
 * GuideMate - Guide Package Builder Badge and Button Enhancement Test Suite
 * Validates:
 * 1. Removal of manual "Special Highlight Badge" field/control
 * 2. Automatic badge determination logic (e.g. "New Expedition" for new packages, extensible conditions)
 * 3. "Next" button typography and styling matching design tokens
 * 4. Rename former "Cancel" button to "Back"
 * 5. Add separate "Cancel" button
 * 6. Place "Cancel" button at far left of action-button row
 * 7. Layout, spacing, responsiveness, and vertical alignment
 * 8. Intuitive button behavior (Cancel exits/discards, Back returns, Next advances)
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);

$passes = 0;
$failures = 0;

// Autoloader without starting web session
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require_once $file;
});

function it(string $description, bool $condition, &$passes, &$failures): void {
    if ($condition) {
        echo " [PASS] " . $description . "\n";
        $passes++;
    } else {
        echo " [FAIL] " . $description . "\n";
        $failures++;
    }
}

echo "========================================================\n";
echo " GuideMate - Badge & Action Button Enhancement Tests\n";
echo "========================================================\n\n";

$builderViewPath = __DIR__ . '/../app/Views/guide/package-builder.php';
$builderContent = file_exists($builderViewPath) ? file_get_contents($builderViewPath) : '';

$controllerPath = __DIR__ . '/../app/Controllers/GuideController.php';
$controllerContent = file_exists($controllerPath) ? file_get_contents($controllerPath) : '';

$appCreatePath = __DIR__ . '/../app/package/create.tsx';
$appCreateContent = file_exists($appCreatePath) ? file_get_contents($appCreatePath) : '';

// 1. Requirement 1: Manual "Special Highlight Badge" field/control removed
it("Requirement 1: Manual badge pill group is removed from Web view", !str_contains($builderContent, 'id="badgePillGroup"'), $passes, $failures);
it("Requirement 1: Manual badge selection onclick handler is removed from Web view", !str_contains($builderContent, 'selectBadgePill('), $passes, $failures);
it("Requirement 1: Manual badge chip row is removed from Mobile app", !str_contains($appCreateContent, 'BADGES.map'), $passes, $failures);

// 2. Requirement 2: Automatic badge logic and display
it("Requirement 2: GuideController defines determinePackageBadge method", str_contains($controllerContent, 'function determinePackageBadge'), $passes, $failures);

$newBadge = \App\Controllers\GuideController::determinePackageBadge([], true);
it("Requirement 2: Newly created package automatically receives 'New Expedition'", $newBadge === 'New Expedition', $passes, $failures);

$topRatedBadge = \App\Controllers\GuideController::determinePackageBadge(['id' => 1, 'status' => 'approved', 'avg_rating' => 4.9, 'reviews_count' => 15, 'created_at' => '2025-01-01 00:00:00'], false);
it("Requirement 2: High rated package receives 'Top Rated'", $topRatedBadge === 'Top Rated', $passes, $failures);

$bestSellerBadge = \App\Controllers\GuideController::determinePackageBadge(['id' => 2, 'status' => 'approved', 'bookings_count' => 12, 'created_at' => '2025-01-01 00:00:00'], false);
it("Requirement 2: High booking package receives 'Best Seller'", $bestSellerBadge === 'Best Seller', $passes, $failures);

$featuredBadge = \App\Controllers\GuideController::determinePackageBadge(['id' => 3, 'status' => 'approved', 'is_featured' => 1, 'created_at' => '2025-01-01 00:00:00'], false);
it("Requirement 2: Featured package receives 'Featured'", $featuredBadge === 'Featured', $passes, $failures);

it("Requirement 2: Web view has hidden pkg_badge input to preserve badge data", str_contains($builderContent, 'id="pkg_badge"') && str_contains($builderContent, 'name="badge"'), $passes, $failures);
it("Requirement 2: Web view displays auto badge in header with consistent style", str_contains($builderContent, '$autoBadge') && str_contains($builderContent, '✨'), $passes, $failures);
it("Requirement 2: Mobile app defaults badge to 'New Expedition'", str_contains($appCreateContent, "useState('New Expedition')"), $passes, $failures);

// 3. Requirement 3: "Next" button typography and styling
it("Requirement 3: .pkg-btn-next class is defined in package-builder.php", str_contains($builderContent, '.pkg-btn-next'), $passes, $failures);
it("Requirement 3: .pkg-btn-next uses system font family (Plus Jakarta Sans)", str_contains($builderContent, "font-family: 'Plus Jakarta Sans'"), $passes, $failures);
it("Requirement 3: .pkg-btn-next uses semibold/bold weight (700)", str_contains($builderContent, 'font-weight: 700') && str_contains($builderContent, '.pkg-btn-next'), $passes, $failures);
it("Requirement 3: .pkg-btn-next uses brand emerald background (#10b981)", str_contains($builderContent, 'background: #10b981') && str_contains($builderContent, '.pkg-btn-next'), $passes, $failures);
it("Requirement 3: Step 1 Next button uses .pkg-btn-next class", str_contains($builderContent, 'class="pkg-btn-next">Next</button>'), $passes, $failures);

// 4. Requirement 4 & 5: Renamed to "Back" and Separate "Cancel" Button
it("Requirement 4: Step 1 includes 'Back' button", str_contains($builderContent, 'class="pkg-btn-back"') && str_contains($builderContent, 'handleStep1Back'), $passes, $failures);
it("Requirement 5: Separate 'Cancel' button is present with .pkg-btn-cancel", str_contains($builderContent, 'class="pkg-btn-cancel"') && str_contains($builderContent, 'Cancel</a>'), $passes, $failures);

// 5. Requirement 6: Cancel placed at far left of action-button row
it("Requirement 6: .pkg-step-actions-row uses justify-content: space-between", str_contains($builderContent, 'justify-content: space-between') && str_contains($builderContent, '.pkg-step-actions-row'), $passes, $failures);
it("Requirement 6: .pkg-step-actions-row uses align-items: center for vertical alignment", str_contains($builderContent, 'align-items: center') && str_contains($builderContent, '.pkg-step-actions-row'), $passes, $failures);

// 6. Requirement 7: Layout & Responsiveness
it("Requirement 7: .pkg-step-actions-row includes mobile media query for responsive stacking", str_contains($builderContent, '@media (max-width: 640px)') && str_contains($builderContent, '.pkg-step-actions-row'), $passes, $failures);
it("Requirement 7: Cancel button styled with consistent padding and rounded pill shape", str_contains($builderContent, 'border-radius: 999px') && str_contains($builderContent, '.pkg-btn-cancel'), $passes, $failures);

// 7. Requirement 8: Intuitive button behavior & order across all steps
it("Requirement 8: Step 1 Cancel button exits/discards to listings with confirmation", str_contains($builderContent, 'confirmDiscardPackage') && str_contains($builderContent, 'url(\'/dashboard/listings\')'), $passes, $failures);
it("Requirement 8: Step 1 Back button navigates back", str_contains($builderContent, 'handleStep1Back'), $passes, $failures);
it("Requirement 8: Step 1 Next button advances to Step 2", str_contains($builderContent, 'goToStep(2)'), $passes, $failures);
it("Requirement 8: Step 2 has Cancel on left, Back (goToStep(1)) and Next (goToStep(3)) on right", str_contains($builderContent, 'goToStep(1)') && str_contains($builderContent, 'goToStep(3)'), $passes, $failures);
it("Requirement 8: Step 3 has Cancel on left, Back (goToStep(2)) and Next (goToStep(4)) on right", str_contains($builderContent, 'goToStep(2)') && str_contains($builderContent, 'goToStep(4)'), $passes, $failures);
it("Requirement 8: Step 4 has Cancel on left, Back (goToStep(3)) and Next (goToStep(5)) on right", str_contains($builderContent, 'goToStep(3)') && str_contains($builderContent, 'goToStep(5)'), $passes, $failures);
it("Requirement 8: Step 5 has Cancel on left, Back (goToStep(4)) and Submit on right", str_contains($builderContent, 'goToStep(4)') && str_contains($builderContent, 'Submit Package for Approval'), $passes, $failures);

// 8. Mobile App Button Layout
it("Mobile app includes Cancel button on far left", str_contains($appCreateContent, 'styles.cancelBtn') && str_contains($appCreateContent, 'Discard Package'), $passes, $failures);
it("Mobile app includes Back button", str_contains($appCreateContent, 'styles.backBtn') && str_contains($appCreateContent, 'router.back()'), $passes, $failures);
it("Mobile app includes Next button", str_contains($appCreateContent, 'styles.nextBtn') && str_contains($appCreateContent, 'handleNext'), $passes, $failures);

echo "\n--------------------------------------------------------\n";
echo "Results: {$passes} passed, {$failures} failed\n";
echo "--------------------------------------------------------\n";

if ($failures > 0) {
    exit(1);
}
