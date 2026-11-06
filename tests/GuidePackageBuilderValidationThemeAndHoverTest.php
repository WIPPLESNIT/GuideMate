<?php
/**
 * GuideMate - Travel Package Builder Validation, Green Theme & Button Hover Stability Tests
 * 
 * Verifies:
 * 1. Step-by-Step Validation (trim checks, required fields, inline error messages, blocking forward progress)
 * 2. System Green Color Theme (complete removal of purple accents, adoption of emerald/green system tokens)
 * 3. Universal Button Hover Stability (removal of translateY/upward shift across all buttons and roles)
 */

$builderPath = __DIR__ . '/../app/Views/guide/package-builder.php';
$cssPath = __DIR__ . '/../public/assets/css/style.css';

if (!file_exists($builderPath)) {
    echo "FAIL: package-builder.php not found at {$builderPath}\n";
    exit(1);
}
if (!file_exists($cssPath)) {
    echo "FAIL: style.css not found at {$cssPath}\n";
    exit(1);
}

$builderContent = file_get_contents($builderPath);
$cssContent = file_get_contents($cssPath);

$passes = 0;
$failures = 0;

function it($description, $condition, &$passes, &$failures) {
    if ($condition) {
        echo " [PASS] {$description}\n";
        $passes++;
    } else {
        echo " [FAIL] {$description}\n";
        $failures++;
    }
}

echo "========================================================\n";
echo " GuideMate - Validation, Green Theme & Hover Stability\n";
echo "========================================================\n\n";

echo "--- 1. Step-by-Step Validation & Whitespace Trimming ---\n";

it("Validation function validateStep(step) is defined",
    str_contains($builderContent, 'function validateStep(step) {'),
    $passes, $failures);

it("Validation clears prior step errors via clearStepErrors",
    str_contains($builderContent, 'function clearStepErrors(step) {') &&
    str_contains($builderContent, 'clearStepErrors(step);'),
    $passes, $failures);

it("Step 1 validates pkg_title with trim() against empty or whitespace",
    str_contains($builderContent, "const titleVal = (titleEl?.value || '').trim();") &&
    str_contains($builderContent, "if (titleVal === '') {"),
    $passes, $failures);

it("Step 1 validates pkg_area with trim() against empty or whitespace",
    str_contains($builderContent, "const areaVal = (areaEl?.value || '').trim();") &&
    str_contains($builderContent, "if (areaVal === '') {"),
    $passes, $failures);

it("Step 1 validates pkg_days duration is valid positive integer",
    str_contains($builderContent, "const daysVal = (daysEl?.value || '').trim();") &&
    str_contains($builderContent, "daysNum < 1"),
    $passes, $failures);

it("Step 1 validates pkg_nights duration",
    str_contains($builderContent, "const nightsVal = (nightsEl?.value || '').trim();") &&
    str_contains($builderContent, "nightsNum < 0"),
    $passes, $failures);

it("Step 1 validates max capacity is required and not less than min capacity",
    str_contains($builderContent, "const maxPaxVal = (maxPaxEl?.value || '').trim();") &&
    str_contains($builderContent, "minPaxNum > maxPaxNum"),
    $passes, $failures);

it("Step 1 validates pkg_description with trim()",
    str_contains($builderContent, "const descVal = (descEl?.value || '').trim();") &&
    str_contains($builderContent, "if (descVal === '') {"),
    $passes, $failures);

it("Step 1 validates cover photo requirement",
    str_contains($builderContent, "const photoFile = document.getElementById('pkg_file_0')") &&
    str_contains($builderContent, "Cover photo (Photo 1) is required"),
    $passes, $failures);

it("Step 2 validates that at least one activity/attraction is bundled",
    str_contains($builderContent, "!selectedActivities || selectedActivities.length === 0") &&
    str_contains($builderContent, "Please select at least one activity or attraction"),
    $passes, $failures);

it("Step 2 validates that an accommodation option is selected",
    str_contains($builderContent, "input[name=\"selected_hotel_room\"]:checked") &&
    str_contains($builderContent, "Please select an accommodation option"),
    $passes, $failures);

it("Step 2 validates that a vehicle rental option is selected",
    str_contains($builderContent, "input[name=\"selected_vehicle_id\"]:checked") &&
    str_contains($builderContent, "Please select a vehicle rental option"),
    $passes, $failures);

it("Step 3 validates inclusions textarea with trim()",
    str_contains($builderContent, "const incVal = (incEl?.value || '').trim();") &&
    str_contains($builderContent, "Please specify included perks & permits"),
    $passes, $failures);

it("Step 3 validates policies textarea with trim()",
    str_contains($builderContent, "const polVal = (polEl?.value || '').trim();") &&
    str_contains($builderContent, "Package policies and cancellation requirements are required"),
    $passes, $failures);

it("Step 4 validates each itinerary day title with trim()",
    str_contains($builderContent, "const title = (d.title || '').trim();") &&
    str_contains($builderContent, "Title is required and cannot be empty"),
    $passes, $failures);

it("Step 4 validates each itinerary day narrative description with trim()",
    str_contains($builderContent, "const desc = (d.desc || '').trim();") &&
    str_contains($builderContent, "Summary Narrative is required and cannot be empty"),
    $passes, $failures);

it("Step 4 validates each itinerary day has schedule time slots",
    str_contains($builderContent, "!d.schedule || d.schedule.length === 0") &&
    str_contains($builderContent, "requires at least one scheduled time slot"),
    $passes, $failures);

it("Step 4 validates all schedule slots have non-empty time and activity descriptions",
    str_contains($builderContent, "(slot.time || '').trim() === '' || (slot.activity || '').trim() === ''"),
    $passes, $failures);

it("Step 5 validates guide margin is a valid non-negative number",
    str_contains($builderContent, "const marginVal = (marginEl?.value || '').trim();") &&
    str_contains($builderContent, "Please enter a valid guide fee / margin"),
    $passes, $failures);

it("Step 5 validates calculated total price is greater than 0",
    str_contains($builderContent, "priceNum <= 0") &&
    str_contains($builderContent, "Calculated total package price must be greater than"),
    $passes, $failures);

it("goToStep blocks forward advancement when current step validation fails",
    str_contains($builderContent, "if (step > currentStep) {") &&
    str_contains($builderContent, "!validateStep(s)") &&
    str_contains($builderContent, "return false;"),
    $passes, $failures);

it("handleFormSubmit performs comprehensive validation across all 5 steps before submitting",
    str_contains($builderContent, "function handleFormSubmit(e) {") &&
    str_contains($builderContent, "for (let s = 1; s <= 5; s++) {") &&
    str_contains($builderContent, "!validateStep(s)"),
    $passes, $failures);

it("Inline error messages use user-friendly styling (.pkg-field-error)",
    str_contains($builderContent, "err.className = 'pkg-field-error';") &&
    str_contains($builderContent, "showFieldError(fieldEl, message)") &&
    str_contains($builderContent, "showSectionError(containerEl, message)"),
    $passes, $failures);

it("Field errors auto-clear on user input and change events",
    str_contains($builderContent, "fieldEl.addEventListener('input', clearHandler);") &&
    str_contains($builderContent, "fieldEl.addEventListener('change', clearHandler);"),
    $passes, $failures);


echo "\n--- 2. System Green Color Theme ---\n";

it("Legacy purple accent #7c3aed is removed from package-builder.php",
    !str_contains($builderContent, '#7c3aed'),
    $passes, $failures);

it("Legacy purple accent #8b5cf6 is removed from package-builder.php",
    !str_contains($builderContent, '#8b5cf6'),
    $passes, $failures);

it("Legacy purple accent #6d28d9 is removed from package-builder.php",
    !str_contains($builderContent, '#6d28d9'),
    $passes, $failures);

it("Legacy purple accent #ede9fe is removed from package-builder.php",
    !str_contains($builderContent, '#ede9fe'),
    $passes, $failures);

it("Legacy purple accent #faf5ff is removed from package-builder.php",
    !str_contains($builderContent, '#faf5ff'),
    $passes, $failures);

it("Active stepper pill uses green theme background and border",
    str_contains($builderContent, '.pkg-step-pill.active {') &&
    str_contains($builderContent, 'background: #ecfdf5;') &&
    str_contains($builderContent, 'border-color: #a7f3d0;'),
    $passes, $failures);

it("Active stepper pill number uses brand emerald #10b981",
    str_contains($builderContent, '.pkg-step-pill.active .pkg-step-num {') &&
    str_contains($builderContent, 'background: #10b981;'),
    $passes, $failures);

it("Active category filter buttons use green theme (#10b981 / #059669)",
    str_contains($builderContent, '.pkg-category-filter-btn.active {') &&
    str_contains($builderContent, 'background: #10b981;') &&
    str_contains($builderContent, 'border-color: #059669;'),
    $passes, $failures);

it("Input focus state uses emerald green (#10b981)",
    str_contains($builderContent, '.pkg-input:focus') &&
    str_contains($builderContent, 'border-color: #10b981 !important;'),
    $passes, $failures);

it("Selected card border and background use green theme (#10b981 / #f0fdf4)",
    str_contains($builderContent, '.pkg-destination-card.is-selected, .pkg-card-radio.selected {') &&
    str_contains($builderContent, 'border: 2px solid #10b981 !important;') &&
    str_contains($builderContent, 'background: #f0fdf4 !important;'),
    $passes, $failures);

it("Selected radio card uses green theme (#10b981 / #f0fdf4)",
    str_contains($builderContent, '.pkg-destination-card.is-selected, .pkg-card-radio.selected {') &&
    str_contains($builderContent, 'border-color: #10b981 !important;') ||
    str_contains($builderContent, 'border: 2px solid #10b981 !important;'),
    $passes, $failures);

it("Card selection pill badge uses green theme (#10b981)",
    str_contains($builderContent, '.pkg-destination-card.is-selected .pkg-card-select-pill {') &&
    str_contains($builderContent, 'background: #10b981;') &&
    str_contains($builderContent, 'border-color: #059669;'),
    $passes, $failures);

it("Active itinerary day tab button uses green theme (#10b981)",
    str_contains($builderContent, '.pkg-day-tab-btn.active {') &&
    str_contains($builderContent, 'background: #10b981;') &&
    str_contains($builderContent, 'border-color: #059669;'),
    $passes, $failures);

it("Guide margin financial card in Step 5 uses light green background (#f0fdf4) and border (#a7f3d0)",
    str_contains($builderContent, 'background:#f0fdf4;') &&
    str_contains($builderContent, 'border:1px solid #a7f3d0;'),
    $passes, $failures);


echo "\n--- 3. Universal Button Hover Stability ---\n";

it(".pkg-btn-next has transform: none on hover",
    str_contains($builderContent, '.pkg-btn-next:hover {') &&
    str_contains($builderContent, 'transform: none !important;'),
    $passes, $failures);

it(".pkg-btn-back and .pkg-btn-cancel have transform: none on hover",
    str_contains($builderContent, '.pkg-btn-cancel:hover {') &&
    str_contains($builderContent, '.pkg-btn-back:hover {') &&
    str_contains($builderContent, 'transform: none !important;'),
    $passes, $failures);

it("style.css base .btn:hover has transform: none",
    str_contains($cssContent, '.btn:hover { box-shadow: var(--shadow-md); color: #fff; transform: none; }'),
    $passes, $failures);

it("style.css universal button hover rule enforces transform: none !important on all buttons",
    str_contains($cssContent, 'button:hover,') &&
    str_contains($cssContent, '[role="button"]:hover,') &&
    str_contains($cssContent, '.btn:hover,') &&
    str_contains($cssContent, '[type="button"]:hover,') &&
    str_contains($cssContent, '[type="submit"]:hover,') &&
    str_contains($cssContent, 'transform: none !important;'),
    $passes, $failures);

it("style.css vehicle-actions-vertical button hover has transform: none",
    str_contains($cssContent, '.vehicle-actions-vertical .btn-action-vertical:hover {') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

it("style.css dashboard header buttons have transform: none",
    str_contains($cssContent, '.dash-header-actions .btn-primary:hover {') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

it("style.css hotel property actions button hover has transform: none",
    str_contains($cssContent, '.hotel-prop-actions .btn:hover') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

it("style.css hotel room button hover has transform: none",
    str_contains($cssContent, '.btn-hotel-room:hover {') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

it("style.css booking QR code button hover has transform: none",
    str_contains($cssContent, '.booking-qr-btn:hover {') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

it("style.css user menu trigger hover has transform: none",
    str_contains($cssContent, '.btn-user-menu-trigger:hover') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

it("style.css admin navigation icon hover has transform: none",
    str_contains($cssContent, '.admin-nav-icon:hover {') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

it("style.css profile corner control hover has transform: none",
    str_contains($cssContent, '.profile-corner-control:hover') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

it("style.css rental note button hover has transform: none",
    str_contains($cssContent, '.rental-note-btn:hover') &&
    str_contains($cssContent, "transform: none;"),
    $passes, $failures);

echo "\n--------------------------------------------------------\n";
echo "Results: {$passes} passed, {$failures} failed\n";
echo "========================================================\n";

if ($failures > 0) {
    exit(1);
}
exit(0);
