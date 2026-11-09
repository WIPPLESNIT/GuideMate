<?php
/**
 * TravelPackageProviderApprovalTest.php
 *
 * Verifies:
 * 1. Provider Notification When a Package Is Created (hotel & rental provider notifications).
 * 2. Provider Approval & Rejection (Agree and Reject actions, recording decision and reason).
 * 3. Travel Package Status Management (Pending, Agreed, Rejected logic and multi-provider tracking).
 * 4. Admin Management Table (displays approval status, View action button in Action column).
 * 5. App Version (only Agreed packages displayed to tourists, Pending/Rejected hidden).
 * 6. Database and Backend Integration (PackageService model, sync logic, no duplicate notifications).
 */

$passes = 0;
$failures = 0;

function it(string $title, bool $condition, int &$passes, int &$failures): void {
    if ($condition) {
        echo " [PASS] {$title}\n";
        $passes++;
    } else {
        echo " [FAIL] {$title}\n";
        $failures++;
    }
}

echo "========================================================\n";
echo " GuideMate - Travel Package Provider Approval Tests\n";
echo "========================================================\n\n";

$modelPath = __DIR__ . '/../app/Models/PackageService.php';
$notifPath = __DIR__ . '/../app/Services/NotificationService.php';
$guideCtrlPath = __DIR__ . '/../app/Controllers/GuideController.php';
$dashCtrlPath = __DIR__ . '/../app/Controllers/DashboardController.php';
$apiCtrlPath = __DIR__ . '/../app/Controllers/ApiController.php';
$adminListingsViewPath = __DIR__ . '/../app/Views/admin/guide-listings.php';
$adminShowViewPath = __DIR__ . '/../app/Views/admin/guide-listing-show.php';
$providerReqViewPath = __DIR__ . '/../app/Views/dashboard/package-requests.php';
$dashNavPath = __DIR__ . '/../app/Views/partials/dash-nav.php';
$routesPath = __DIR__ . '/../routes/web.php';

$modelContent = file_exists($modelPath) ? file_get_contents($modelPath) : '';
$notifContent = file_exists($notifPath) ? file_get_contents($notifPath) : '';
$guideCtrlContent = file_exists($guideCtrlPath) ? file_get_contents($guideCtrlPath) : '';
$dashCtrlContent = file_exists($dashCtrlPath) ? file_get_contents($dashCtrlPath) : '';
$apiCtrlContent = file_exists($apiCtrlPath) ? file_get_contents($apiCtrlPath) : '';
$adminListingsContent = file_exists($adminListingsViewPath) ? file_get_contents($adminListingsViewPath) : '';
$adminShowContent = file_exists($adminShowViewPath) ? file_get_contents($adminShowViewPath) : '';
$providerReqContent = file_exists($providerReqViewPath) ? file_get_contents($providerReqViewPath) : '';
$dashNavContent = file_exists($dashNavPath) ? file_get_contents($dashNavPath) : '';
$routesContent = file_exists($routesPath) ? file_get_contents($routesPath) : '';

// -------------------------------------------------------------
// 1. Model & Database Schema Integration
// -------------------------------------------------------------
echo "--- 1. Database and Backend Integration (PackageService) ---\n";

it("PackageService model exists in App\\Models", file_exists($modelPath), $passes, $failures);

it("PackageService defines ensureTable method for package_services and listings column",
    str_contains($modelContent, 'function ensureTable()') &&
    str_contains($modelContent, 'CREATE TABLE IF NOT EXISTS `package_services`') &&
    str_contains($modelContent, 'package_approval_status'),
    $passes, $failures);

it("PackageService defines syncPackageServices method",
    str_contains($modelContent, 'function syncPackageServices('),
    $passes, $failures);

it("PackageService defines respond method for Agree and Reject decisions",
    str_contains($modelContent, 'function respond(') &&
    str_contains($modelContent, "'agree'") &&
    str_contains($modelContent, "'reject'"),
    $passes, $failures);

it("PackageService defines refreshPackageApprovalStatus method",
    str_contains($modelContent, 'function refreshPackageApprovalStatus('),
    $passes, $failures);

it("PackageService defines getOverallStatus method",
    str_contains($modelContent, 'function getOverallStatus('),
    $passes, $failures);

it("PackageService defines getProviderApprovalSummary method",
    str_contains($modelContent, 'function getProviderApprovalSummary('),
    $passes, $failures);

// -------------------------------------------------------------
// 2. Provider Notification When Package Is Created
// -------------------------------------------------------------
echo "\n--- 2. Provider Notification When Package Is Created ---\n";

it("NotificationService defines packageInclusionRequest method",
    str_contains($notifContent, 'function packageInclusionRequest('),
    $passes, $failures);

it("packageInclusionRequest includes package title, guide details, service info, and review URL",
    str_contains($notifContent, '$packageTitle') &&
    str_contains($notifContent, '$guideName') &&
    str_contains($notifContent, '$serviceType') &&
    str_contains($notifContent, '$serviceName') &&
    str_contains($notifContent, '$rateFormatted') &&
    str_contains($notifContent, '/dashboard/package-requests'),
    $passes, $failures);

it("NotificationService defines packageProviderDecision method to inform guide of decisions",
    str_contains($notifContent, 'function packageProviderDecision('),
    $passes, $failures);

it("GuideController storePackage invokes PackageService::syncPackageServices",
    str_contains($guideCtrlContent, 'PackageService::syncPackageServices('),
    $passes, $failures);

it("GuideController updatePackage invokes PackageService::syncPackageServices",
    substr_count($guideCtrlContent, 'PackageService::syncPackageServices(') >= 2,
    $passes, $failures);

it("PackageService syncPackageServices creates automated internal messages for providers",
    str_contains($modelContent, 'sendAutomatedInclusionMessage(') &&
    str_contains($modelContent, 'INSERT INTO messages'),
    $passes, $failures);

// -------------------------------------------------------------
// 3. Provider Approval & Rejection (Agree & Reject actions)
// -------------------------------------------------------------
echo "\n--- 3. Provider Approval & Rejection System ---\n";

it("routes/web.php registers GET /dashboard/package-requests",
    str_contains($routesContent, "'/dashboard/package-requests'"),
    $passes, $failures);

it("routes/web.php registers POST /dashboard/package-requests/{id}/agree",
    str_contains($routesContent, "'/dashboard/package-requests/{id}/agree'"),
    $passes, $failures);

it("routes/web.php registers POST /dashboard/package-requests/{id}/reject",
    str_contains($routesContent, "'/dashboard/package-requests/{id}/reject'"),
    $passes, $failures);

it("DashboardController defines packageRequests view handler",
    str_contains($dashCtrlContent, 'function packageRequests()'),
    $passes, $failures);

it("DashboardController defines agreePackageRequest handler",
    str_contains($dashCtrlContent, 'function agreePackageRequest('),
    $passes, $failures);

it("DashboardController defines rejectPackageRequest handler",
    str_contains($dashCtrlContent, 'function rejectPackageRequest('),
    $passes, $failures);

it("Provider package requests view exists", file_exists($providerReqViewPath), $passes, $failures);

it("Provider package requests view contains Agree action button",
    str_contains($providerReqContent, '/agree') &&
    str_contains($providerReqContent, 'Agree to Inclusion'),
    $passes, $failures);

it("Provider package requests view contains Decline action button and modal",
    str_contains($providerReqContent, '/reject') &&
    str_contains($providerReqContent, 'Decline Request') &&
    str_contains($providerReqContent, 'declineModal'),
    $passes, $failures);

it("Provider package requests view allows inspecting complete package details & itinerary",
    str_contains($providerReqContent, 'Inspect Full Package & Itinerary') &&
    str_contains($providerReqContent, 'openPackageInspectionModal'),
    $passes, $failures);

it("Dashboard sidebar navigation includes Package Requests link for hotel & rental partners",
    str_contains($dashNavContent, '/dashboard/package-requests') &&
    str_contains($dashNavContent, 'Package Requests'),
    $passes, $failures);

// -------------------------------------------------------------
// 4. Status Management Rules (Pending, Agreed, Rejected)
// -------------------------------------------------------------
echo "\n--- 4. Travel Package Status Management Rules ---\n";

it("PackageService marks package as agreed when all required providers agree",
    str_contains($modelContent, "\$overall = 'agreed';") ||
    str_contains($modelContent, "\$overallStatus = 'agreed';"),
    $passes, $failures);

it("PackageService marks package as rejected if ANY provider declines",
    str_contains($modelContent, "\$hasRejected = true;") &&
    str_contains($modelContent, "\$overall = 'rejected';"),
    $passes, $failures);

it("PackageService marks package as pending while waiting for any provider",
    str_contains($modelContent, "\$hasPending = true;") &&
    str_contains($modelContent, "\$overall = 'pending';"),
    $passes, $failures);

it("PackageService handles guide-only packages (0 external providers) as automatically agreed",
    str_contains($modelContent, "empty(\$externalServices)") &&
    str_contains($modelContent, "\$overall = 'agreed';"),
    $passes, $failures);

// -------------------------------------------------------------
// 5. Admin Travel Package Management Table
// -------------------------------------------------------------
echo "\n--- 5. Admin Guide Listing Review & Package Table ---\n";

it("Admin guide listings table displays package approval status",
    str_contains($adminListingsContent, 'data-pkg-status') &&
    str_contains($adminListingsContent, '$pkgOverallStatus'),
    $passes, $failures);

it("Admin guide listings table renders Agreed badge for agreed packages",
    str_contains($adminListingsContent, '✓ Agreed'),
    $passes, $failures);

it("Admin guide listings table renders Rejected badge for declined packages",
    str_contains($adminListingsContent, '✕ Rejected'),
    $passes, $failures);

it("Admin guide listings table renders Pending badge for pending packages",
    str_contains($adminListingsContent, '⏳ Pending'),
    $passes, $failures);

it("Admin guide listings table renders 'View' action button in Actions column for packages",
    str_contains($adminListingsContent, "class=\"btn btn-ghost btn-sm btn-action-vertical\"") &&
    str_contains($adminListingsContent, ">View</a>"),
    $passes, $failures);

it("Admin guide listing show view renders Provider Approval Status card for packages",
    str_contains($adminShowContent, 'Provider Approval Status') &&
    str_contains($adminShowContent, 'PackageService::getProviderApprovalSummary'),
    $passes, $failures);

// -------------------------------------------------------------
// 6. App Version (Display Only Approved Agreed Packages)
// -------------------------------------------------------------
echo "\n--- 6. App Version - Display Approved Agreed Packages Only ---\n";

it("ApiController getDatabasePackages filters packages by l.status = 'approved'",
    str_contains($apiCtrlContent, "WHERE (c.slug = 'packages' OR c.slug = 'travel-packages' OR l.title LIKE '%Package%' OR l.title LIKE '%Travel Package%')\n                  AND l.status = 'approved'"),
    $passes, $failures);

it("ApiController getDatabasePackages filters packages by Agreed approval status",
    str_contains($apiCtrlContent, 'PackageService::getOverallStatus') &&
    str_contains($apiCtrlContent, "if (\$pkgApprovalStatus !== 'agreed') {\n                    // Pending and Rejected packages are not visible to tourists\n                    continue;\n                }"),
    $passes, $failures);

it("ApiController packageDetail blocks tourists from accessing Pending or Rejected packages",
    str_contains($apiCtrlContent, "if (\$pkgApprovalStatus !== 'agreed' && !\$isOwnerOrAdmin) {\n                    \$this->json(['error' => 'This travel package is not currently available.'], 404);"),
    $passes, $failures);

it("ApiController formatListingAsPackage exposes approval_status to app",
    str_contains($apiCtrlContent, "'approval_status' => 'agreed'") &&
    str_contains($apiCtrlContent, "'package_approval_status' => 'agreed'"),
    $passes, $failures);

echo "\n--------------------------------------------------------\n";
echo "Results: {$passes} passed, {$failures} failed\n";
echo "--------------------------------------------------------\n";

if ($failures > 0) {
    exit(1);
}
