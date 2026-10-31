<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

final class HotelInspectSidebarTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Admin Hotel Inspection Navigation Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testTableDoesNotDisplayReasonByDefault', [$this, 'testTableDoesNotDisplayReasonByDefault']);
        $this->runTest('testInspectButtonNavigatesDirectlyToFullPageView', [$this, 'testInspectButtonNavigatesDirectlyToFullPageView']);
        $this->runTest('testHotelTitleLinksDirectlyToFullPageView', [$this, 'testHotelTitleLinksDirectlyToFullPageView']);
        $this->runTest('testSidebarDrawerRemovedFromHotelsView', [$this, 'testSidebarDrawerRemovedFromHotelsView']);
        $this->runTest('testRejectModalPreservedOnTableView', [$this, 'testRejectModalPreservedOnTableView']);
        $this->runTest('testDestinationInspectionViewRendersFullContext', [$this, 'testDestinationInspectionViewRendersFullContext']);
        $this->runTest('testActiveWhenRouteDeterminationForHotels', [$this, 'testActiveWhenRouteDeterminationForHotels']);
        $this->runTest('testActiveStatePreservedOnPageRefresh', [$this, 'testActiveStatePreservedOnPageRefresh']);
        $this->runTest('testControllerShowHotelActionRoute', [$this, 'testControllerShowHotelActionRoute']);

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testTableDoesNotDisplayReasonByDefault(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');

        $tableBody = '';
        if (preg_match('/<tbody>(.*?)<\/tbody>/s', $view, $matches)) {
            $tableBody = $matches[1];
        }

        $this->assert(
            !str_contains($tableBody, 'admin-note-card'),
            'Review Reason card must be removed from the main hotel listings table rows'
        );
        $this->assert(
            !str_contains($tableBody, 'Reason:'),
            'Table row must not display Reason: label by default'
        );
    }

    public function testInspectButtonNavigatesDirectlyToFullPageView(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');

        // Check that the Inspect button is a direct anchor link to /admin/hotels/{id}
        $this->assert(
            str_contains($view, "url('/admin/hotels/' . (int) \$h['id'])") ||
            str_contains($view, 'url(\'/admin/hotels/\' . (int) $h[\'id\'])'),
            'Inspect button must link to /admin/hotels/{id}'
        );
        $this->assert(
            str_contains($view, 'btn btn-ghost btn-sm btn-action-vertical'),
            'Inspect button must maintain consistent vertical action button styling'
        );

        // Ensure sidebar drawer triggers are completely removed
        $this->assert(
            !str_contains($view, 'inspectHotel('),
            'Inspect button must not call inspectHotel() JavaScript function'
        );
        $this->assert(
            !str_contains($view, 'aria-controls="hotelInspectSidebar"'),
            'Inspect button must not reference hotelInspectSidebar drawer'
        );
        $this->assert(
            !str_contains($view, 'data-hotel='),
            'Inspect button must not serialize entire model into data-hotel attribute'
        );
    }

    public function testHotelTitleLinksDirectlyToFullPageView(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');

        $this->assert(
            preg_match('/<a[^>]+href="<\?=\s*e\(url\(\'\/admin\/hotels\/\'\s*\.\s*\(int\)\s*\$h\[\'id\'\]\)\)\s*\?>"[^>]*><strong class="vehicle-title">/', $view) === 1,
            'Hotel title in table must link directly to /admin/hotels/{id}'
        );
    }

    public function testSidebarDrawerRemovedFromHotelsView(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');

        $this->assert(
            !str_contains($view, 'id="hotelInspectSidebar"'),
            'Hotels view must not contain #hotelInspectSidebar drawer markup'
        );
        $this->assert(
            !str_contains($view, 'populateAndOpenSidebar'),
            'Hotels view must not contain populateAndOpenSidebar function'
        );
        $this->assert(
            !str_contains($view, 'closeHotelSidebar'),
            'Hotels view must not contain closeHotelSidebar function'
        );
    }

    public function testRejectModalPreservedOnTableView(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');

        $this->assert(
            str_contains($view, 'id="adminRejectModal"'),
            'Hotels view must preserve #adminRejectModal for inline quick rejections'
        );
        $this->assert(
            str_contains($view, 'openRejectModal'),
            'Hotels view must preserve openRejectModal function'
        );
        $this->assert(
            str_contains($view, 'closeRejectModal'),
            'Hotels view must preserve closeRejectModal function'
        );
    }

    public function testDestinationInspectionViewRendersFullContext(): void
    {
        $viewPath = BASE_DIR . '/app/Views/admin/hotel-show.php';
        $this->assert(file_exists($viewPath), 'admin/hotel-show.php inspection view must exist');

        $showView = file_get_contents($viewPath);

        // Header and back navigation
        $this->assert(str_contains($showView, 'url(\'/admin/hotels\')'), 'Inspection view must provide back button to /admin/hotels');
        $this->assert(str_contains($showView, '$hotel[\'title\']'), 'Inspection view must display hotel title');
        $this->assert(str_contains($showView, 'hotelStatusPillClass'), 'Inspection view must display status pill');

        // Rejection reason banner
        $this->assert(str_contains($showView, '$hotel[\'rejection_reason\']'), 'Inspection view must render rejection reason when rejected');
        $this->assert(str_contains($showView, 'Rejection Reason Recorded:'), 'Inspection view must have rejection banner header');

        // Content sections
        $this->assert(str_contains($showView, 'Property Photos &amp; Gallery') || str_contains($showView, 'Property Photos'), 'Inspection view must display gallery');
        $this->assert(str_contains($showView, 'Property Description'), 'Inspection view must display property description');
        $this->assert(str_contains($showView, 'Included Amenities'), 'Inspection view must display amenities');
        $this->assert(str_contains($showView, 'Configured Room Types'), 'Inspection view must display configured room types');

        // Review Decision & Partner Info
        $this->assert(str_contains($showView, 'Review Decision'), 'Inspection view must provide Review Decision panel');
        $this->assert(str_contains($showView, '/approve'), 'Inspection view must provide Approve action');
        $this->assert(str_contains($showView, '/reject'), 'Inspection view must provide Reject form with reason textarea');
        $this->assert(str_contains($showView, 'Accommodation Partner'), 'Inspection view must display partner details');
        $this->assert(str_contains($showView, 'Audit Trail'), 'Inspection view must display audit trail');
    }

    public function testActiveWhenRouteDeterminationForHotels(): void
    {
        $backupUri = $_SERVER['REQUEST_URI'] ?? null;

        // Route: inspecting hotel with ID 15
        $_SERVER['REQUEST_URI'] = '/admin/hotels/15';
        $activeHotel = active_when('/admin/hotels');
        $activeVehicle = active_when('/admin/vehicles');
        $this->assert($activeHotel === 'active', "active_when('/admin/hotels') must return 'active' on /admin/hotels/15");
        $this->assert($activeVehicle === '', "active_when('/admin/vehicles') must return empty string on /admin/hotels/15");

        // Route: inspecting with query string
        $_SERVER['REQUEST_URI'] = '/admin/hotels/15?tab=audit';
        $this->assert(active_when('/admin/hotels') === 'active', "active_when('/admin/hotels') must return 'active' with query parameters");

        // Route: hotel review list with status filter
        $_SERVER['REQUEST_URI'] = '/admin/hotels?status=pending';
        $this->assert(active_when('/admin/hotels') === 'active', "active_when('/admin/hotels') must return 'active' on /admin/hotels?status=pending");

        if ($backupUri !== null) {
            $_SERVER['REQUEST_URI'] = $backupUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }
    }

    public function testActiveStatePreservedOnPageRefresh(): void
    {
        $backupUri = $_SERVER['REQUEST_URI'] ?? null;

        // Simulate page refresh on an inspect route /admin/hotels/42
        $_SERVER['REQUEST_URI'] = '/admin/hotels/42';
        
        // Render admin navigation partial
        ob_start();
        include BASE_DIR . '/app/Views/partials/admin-nav.php';
        $renderedNav = ob_get_clean();

        $this->assert($renderedNav !== false, 'admin-nav.php must render successfully');
        $this->assert(
            preg_match('/<a[^>]+href="[^"]*\/admin\/hotels"[^>]+class="[^"]*active[^"]*"[^>]*>/', $renderedNav) === 1,
            'Hotels navigation link must have class "active" when page is refreshed on /admin/hotels/42'
        );

        if ($backupUri !== null) {
            $_SERVER['REQUEST_URI'] = $backupUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }
    }

    public function testControllerShowHotelActionRoute(): void
    {
        $routes = file_get_contents(BASE_DIR . '/routes/web.php');
        $this->assert(
            str_contains($routes, "'/admin/hotels/{id}', [AdminController::class, 'showHotel']"),
            'web.php must register route GET /admin/hotels/{id} pointing to AdminController@showHotel'
        );

        $controller = file_get_contents(BASE_DIR . '/app/Controllers/AdminController.php');
        $this->assert(
            str_contains($controller, 'public function showHotel(string $id)'),
            'AdminController must implement showHotel(string $id) action'
        );
    }

    private function runTest(string $name, callable $callable): void
    {
        try {
            $callable();
            $this->passed++;
            echo sprintf(" [\033[32mPASS\033[0m] %s\n", $name);
        } catch (\Throwable $e) {
            $this->failed++;
            echo sprintf(" [\033[31mFAIL\033[0m] %s: %s (in %s:%d)\n", $name, $e->getMessage(), $e->getFile(), $e->getLine());
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}

$suite = new HotelInspectSidebarTest();
exit($suite->run() ? 0 : 1);
