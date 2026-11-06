<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Core/helpers.php';

final class AdminNavLayoutTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "========================================================\n";
        echo " GuideMate Admin Navigation & Messages Layout Test Suite\n";
        echo "========================================================\n\n";

        $this->testAdminLayoutContainsMessageIconAndProfileDropdown();
        $this->testAdminDropdownExcludesProfileAndViewSite();
        $this->testNonAdminDropdownRetainsProfile();
        $this->testAdminNavExcludesMessagesAndIncludesComplaint();
        $this->testWebRoutesIncludeComplaints();
        $this->testMainJsIncludesUserMenuEventListeners();
        $this->testStyleCssIncludesSmoothDropdownAndAdminNavStyles();
        $this->testMessagesLayoutAlignsWithDashboard();
        $this->testAdminMessagesOriginalLayoutRestored();
        $this->testAdminAuthAndPortalCssIntegrity();
        $this->testPartnerApplicationsRedesignAndLabel();

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo " [FAIL] {$message}\n";
        }
    }

    private function testAdminLayoutContainsMessageIconAndProfileDropdown(): void
    {
        $adminLayout = file_get_contents(__DIR__ . '/../app/Views/layouts/admin.php');

        $this->assert(
            strpos($adminLayout, "url('/admin/messages')") !== false && strpos($adminLayout, 'admin-nav-icon') !== false,
            'Admin layout top navigation bar includes Messages icon linking to /admin/messages'
        );

        $this->assert(
            strpos($adminLayout, 'id="userMenuBtn"') !== false && strpos($adminLayout, 'admin-nav-avatar') !== false,
            'Admin layout includes profile avatar button with id="userMenuBtn"'
        );

        $this->assert(
            strpos($adminLayout, 'id="userMenu"') !== false && strpos($adminLayout, 'class="dropdown"') !== false,
            'Admin layout includes profile dropdown with id="userMenu"'
        );

        $this->assert(
            strpos($adminLayout, "url('/admin/logout')") !== false && stripos($adminLayout, 'Logout') !== false,
            'Profile dropdown includes clearly labeled Logout option posting to /admin/logout'
        );

        $this->assert(
            strpos($adminLayout, 'csrf_field()') !== false,
            'Logout form includes CSRF field for security'
        );
    }

    private function testAdminDropdownExcludesProfileAndViewSite(): void
    {
        $adminLayout = file_get_contents(__DIR__ . '/../app/Views/layouts/admin.php');

        // Extract the userMenu dropdown section
        preg_match('/<div class="dropdown" id="userMenu"[^>]*>(.*?)<\/div>/s', $adminLayout, $matches);
        $dropdownContent = $matches[1] ?? '';

        $this->assert(
            strpos($dropdownContent, 'Profile') === false,
            'Admin profile dropdown menu does NOT contain "Profile" option'
        );

        $this->assert(
            stripos($dropdownContent, 'View site') === false,
            'Admin profile dropdown menu does NOT contain "View site" option'
        );

        $this->assert(
            strpos($dropdownContent, 'Dashboard') !== false,
            'Admin profile dropdown menu retains "Dashboard" option'
        );

        $this->assert(
            stripos($dropdownContent, 'Logout') !== false,
            'Admin profile dropdown menu retains "Logout" option'
        );
    }

    private function testNonAdminDropdownRetainsProfile(): void
    {
        $mainLayout = file_get_contents(__DIR__ . '/../app/Views/layouts/main.php');

        preg_match('/<div class="dropdown" id="userMenu"[^>]*>(.*?)<\/div>/s', $mainLayout, $matches);
        $dropdownContent = $matches[1] ?? '';

        $this->assert(
            strpos($dropdownContent, "url('/profile')") !== false && strpos($dropdownContent, 'Profile') !== false,
            'Non-admin user dropdown retains the "Profile" option'
        );
    }

    private function testAdminNavExcludesMessagesAndIncludesComplaint(): void
    {
        $adminNav = file_get_contents(__DIR__ . '/../app/Views/partials/admin-nav.php');

        $this->assert(
            strpos($adminNav, '<span class="nav-label">Messages</span>') === false,
            'Sidebar partial does NOT include Messages'
        );

        $this->assert(
            strpos($adminNav, '<span class="nav-label">Complaint</span>') !== false,
            'Sidebar partial includes Complaint (renamed from Disputes)'
        );

        $this->assert(
            strpos($adminNav, "url('/admin/complaints')") !== false,
            'Sidebar Complaint links to /admin/complaints'
        );
    }

    private function testWebRoutesIncludeComplaints(): void
    {
        $webRoutes = file_get_contents(__DIR__ . '/../routes/web.php');

        $this->assert(
            strpos($webRoutes, "'/admin/complaints'") !== false,
            'Web routes include /admin/complaints route mapping'
        );
    }

    private function testMainJsIncludesUserMenuEventListeners(): void
    {
        $mainJs = file_get_contents(__DIR__ . '/../public/assets/js/main.js');

        $this->assert(
            strpos($mainJs, "userMenu.classList.toggle('open')") !== false,
            'main.js toggles userMenu on userBtn click'
        );

        $this->assert(
            strpos($mainJs, "!userMenu.contains(e.target)") !== false,
            'main.js closes userMenu when clicking outside'
        );

        $this->assert(
            strpos($mainJs, "Escape") !== false,
            'main.js closes userMenu smoothly when pressing Escape'
        );
    }

    private function testStyleCssIncludesSmoothDropdownAndAdminNavStyles(): void
    {
        $styleCss = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(
            strpos($styleCss, '.admin-nav-icon') !== false,
            'style.css contains .admin-nav-icon styling'
        );

        $this->assert(
            strpos($styleCss, '.admin-nav-avatar') !== false,
            'style.css contains .admin-nav-avatar styling'
        );

        $this->assert(
            strpos($styleCss, '.admin-header .dropdown') !== false,
            'style.css contains .admin-header .dropdown styling'
        );

        $this->assert(
            strpos($styleCss, 'transition: opacity') !== false,
            'style.css contains smooth opening/closing transition for dropdown'
        );
    }

    private function testMessagesLayoutAlignsWithDashboard(): void
    {
        $styleCss = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(
            strpos($styleCss, 'body.page-messages') !== false && strpos($styleCss, '--dash-maxw: 1680px;') !== false,
            'Messages layout adopts the expanded 1680px dashboard max-width variable'
        );

        $this->assert(
            strpos($styleCss, 'body.page-messages .container') !== false,
            'Messages section container is styled with dashboard container responsive widths'
        );

        $this->assert(
            strpos($styleCss, 'grid-template-columns: 250px minmax(0, 1fr)') !== false,
            'Messages shell layout matches the dashboard 250px sidebar and fluid main content grid'
        );

        $this->assert(
            strpos($styleCss, 'gap: clamp(1.5rem, 2.2vw, 2.5rem)') !== false,
            'Messages shell layout adopts the identical fluid gap as the dashboard layout'
        );

        $this->assert(
            strpos($styleCss, 'body.page-messages .msg-shell .dash-nav') !== false,
            'Messages sidebar nav uses 250px fixed width and aligns flush with the top of Messages'
        );

        $this->assert(
            strpos($styleCss, '@media (max-width: 900px)') !== false && strpos($styleCss, 'body.page-messages .msg-shell') !== false,
            'Messages section shares the 900px mobile responsive breakpoint with the dashboard'
        );
    }

    private function testAdminAuthAndPortalCssIntegrity(): void
    {
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // Check brace balance
        $openCount = substr_count($css, '{');
        $closeCount = substr_count($css, '}');
        $this->assert(
            $openCount === $closeCount,
            "style.css has balanced curly braces (open: {$openCount}, close: {$closeCount})"
        );

        // Check admin portal & auth styling is present
        $this->assert(
            strpos($css, '.admin-auth {') !== false && strpos($css, '.admin-auth-card {') !== false,
            'style.css contains .admin-auth and .admin-auth-card rules for admin sign in portal'
        );

        $this->assert(
            strpos($css, 'radial-gradient(1200px 600px at 50% -10%, #13402a, #08160f 60%)') !== false,
            'style.css contains the dark emerald radial background gradient for admin sign in'
        );

        $this->assert(
            strpos($css, '.admin-body { background: #0c1c22;') !== false,
            'style.css contains the dark admin dashboard body styling'
        );
    }

    private function testAdminMessagesOriginalLayoutRestored(): void
    {
        $view = file_get_contents(__DIR__ . '/../app/Views/admin/messages.php');
        $controller = file_get_contents(__DIR__ . '/../app/Controllers/AdminController.php');
        $routes = file_get_contents(__DIR__ . '/../routes/web.php');
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // 1. Original stable two-pane structure
        $this->assert(
            strpos($view, 'class="panel admin-msg-panel"') !== false && strpos($view, 'class="admin-msg"') !== false,
            'Admin messages view uses the original panel structure (.admin-msg-panel and .admin-msg)'
        );

        $this->assert(
            strpos($view, 'class="admin-msg-list"') !== false && strpos($view, 'class="admin-msg-thread"') !== false,
            'Admin messages view retains the original two-pane list and thread elements'
        );

        // 2. Redesigned elements successfully reverted
        $this->assert(
            strpos($view, 'admin-msg-cards-wrap') === false && strpos($view, 'admin-msg-card') === false,
            'Admin messages view does not contain the redesigned two-card architecture'
        );

        // 3. Web routes cleanly restored
        $this->assert(
            strpos($routes, '/admin/messages/{partner}/pin') === false
            && strpos($routes, '/admin/messages/{partner}/archive') === false
            && strpos($routes, '/admin/messages/{partner}/unarchive') === false
            && strpos($routes, '/admin/messages/{partner}/delete') === false,
            'Routes do not contain the extra pin, archive, unarchive, or delete endpoints'
        );

        // 4. AdminController cleanly restored
        $this->assert(
            strpos($controller, 'function pinConversation') === false
            && strpos($controller, 'function archiveConversation') === false
            && strpos($controller, 'function unarchiveConversation') === false
            && strpos($controller, 'function deleteConversation') === false,
            'AdminController does not contain the extra pin/archive/delete methods'
        );

        // 5. CSS styles cleanly restored
        $this->assert(
            strpos($css, '.admin-msg-panel') !== false && strpos($css, '.admin-msg {') !== false,
            'style.css contains the original .admin-msg-panel and .admin-msg grid styles'
        );

        $this->assert(
            strpos($css, '.admin-msg-cards-wrap') === false && strpos($css, '.admin-msg-page') === false,
            'style.css no longer contains the redesigned two-card admin messaging classes'
        );
    }
    private function testPartnerApplicationsRedesignAndLabel(): void
    {
        $guidesView = file_get_contents(__DIR__ . '/../app/Views/admin/guides.php');
        $adminNav = file_get_contents(__DIR__ . '/../app/Views/partials/admin-nav.php');
        $routes = file_get_contents(__DIR__ . '/../routes/web.php');
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // 1. Correct section label to Partner Applications
        $this->assert(
            strpos($guidesView, '>Partner Applications</h1>') !== false,
            'Partner applications view header uses title-cased "Partner Applications"'
        );

        $this->assert(
            strpos($adminNav, '<span class="nav-label">Partner Applications</span>') !== false,
            'Sidebar navigation label is corrected to "Partner Applications"'
        );

        // 2. Web routes include partner application aliases
        $this->assert(
            strpos($routes, "'/admin/partner-applications'") !== false && strpos($routes, "'/admin/partners'") !== false,
            'Web routes include /admin/partner-applications and /admin/partners'
        );

        // 3. Card-based 2x2 grid in view
        $this->assert(
            strpos($guidesView, 'class="partner-docs-grid"') !== false,
            'Partner applications view contains .partner-docs-grid container'
        );

        $this->assert(
            strpos($guidesView, 'class="partner-doc-card"') !== false,
            'Partner applications view contains .partner-doc-card elements'
        );

        $this->assert(
            strpos($guidesView, 'class="partner-docs-empty"') !== false,
            'Partner applications view contains styled .partner-docs-empty state'
        );

        // 4. Document preview integration intact
        $this->assert(
            strpos($guidesView, 'data-doc-preview="1"') !== false,
            'Document card preview action preserves modal data-doc-preview="1"'
        );

        // 5. CSS definitions in style.css
        $this->assert(
            strpos($css, '.partner-docs-grid {') !== false && strpos($css, 'grid-template-columns: repeat(2, minmax(0, 1fr));') !== false,
            'style.css contains .partner-docs-grid with 2-by-2 column layout'
        );

        $this->assert(
            strpos($css, '.partner-doc-card {') !== false,
            'style.css contains .partner-doc-card styling'
        );
    }
}

(new AdminNavLayoutTest())->run();
