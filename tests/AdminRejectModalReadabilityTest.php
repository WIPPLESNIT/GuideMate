<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

final class AdminRejectModalReadabilityTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Admin Reject Modal Readability Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testHotelViewRejectModalStructure', [$this, 'testHotelViewRejectModalStructure']);
        $this->runTest('testVehicleViewRejectModalStructure', [$this, 'testVehicleViewRejectModalStructure']);
        $this->runTest('testModalCssThemeOverridesWhiteBackground', [$this, 'testModalCssThemeOverridesWhiteBackground']);
        $this->runTest('testModalCssTypographyAndHighContrastHints', [$this, 'testModalCssTypographyAndHighContrastHints']);
        $this->runTest('testModalCssTextareaStylingAndFocusStates', [$this, 'testModalCssTextareaStylingAndFocusStates']);
        $this->runTest('testModalCssButtonsVisibilityAndDestructiveColor', [$this, 'testModalCssButtonsVisibilityAndDestructiveColor']);
        $this->runTest('testEscapeKeyDismissalHandledInBothViews', [$this, 'testEscapeKeyDismissalHandledInBothViews']);

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testHotelViewRejectModalStructure(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');

        $this->assert(str_contains($view, 'id="adminRejectModal"'), 'Hotel view must include id="adminRejectModal"');
        $this->assert(str_contains($view, 'admin-reject-modal'), 'Hotel view modal must have admin-reject-modal class');
        $this->assert(str_contains($view, 'admin-reject-head'), 'Hotel view modal must use admin-reject-head');
        $this->assert(str_contains($view, 'admin-reject-hint'), 'Hotel view modal must use admin-reject-hint for guidance text');
        $this->assert(str_contains($view, 'admin-reject-label'), 'Hotel view modal must use admin-reject-label');
        $this->assert(str_contains($view, 'admin-reject-textarea'), 'Hotel view modal must use admin-reject-textarea');
        $this->assert(str_contains($view, 'btn-cancel-reject'), 'Hotel view modal must use btn-cancel-reject');
        $this->assert(str_contains($view, 'btn-confirm-reject'), 'Hotel view modal must use btn-confirm-reject');
        $this->assert(str_contains($view, 'btn-danger'), 'Hotel view confirm rejection must be btn-danger');

        $modalHtml = '';
        if (preg_match('/<div id="adminRejectModal".*?<\/form>\s*<\/div>\s*<\/div>/s', $view, $m)) {
            $modalHtml = $m[0];
        }
        $this->assert(!str_contains($modalHtml, 'var(--coral-600)'), 'Hotel reject modal must not use var(--coral-600) on submit button');
    }

    public function testVehicleViewRejectModalStructure(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/vehicles.php');

        $this->assert(str_contains($view, 'id="adminRejectModal"'), 'Vehicle view must include id="adminRejectModal"');
        $this->assert(str_contains($view, 'admin-reject-modal'), 'Vehicle view modal must have admin-reject-modal class');
        $this->assert(str_contains($view, 'admin-reject-head'), 'Vehicle view modal must use admin-reject-head');
        $this->assert(str_contains($view, 'admin-reject-hint'), 'Vehicle view modal must use admin-reject-hint for guidance text');
        $this->assert(str_contains($view, 'admin-reject-label'), 'Vehicle view modal must use admin-reject-label');
        $this->assert(str_contains($view, 'admin-reject-textarea'), 'Vehicle view modal must use admin-reject-textarea');
        $this->assert(str_contains($view, 'btn-cancel-reject'), 'Vehicle view modal must use btn-cancel-reject');
        $this->assert(str_contains($view, 'btn-confirm-reject'), 'Vehicle view modal must use btn-confirm-reject');
        $this->assert(str_contains($view, 'btn-danger'), 'Vehicle view confirm rejection must be btn-danger');

        $modalHtml = '';
        if (preg_match('/<div id="adminRejectModal".*?<\/form>\s*<\/div>\s*<\/div>/s', $view, $m)) {
            $modalHtml = $m[0];
        }
        $this->assert(!str_contains($modalHtml, 'var(--coral-600)'), 'Vehicle reject modal must not use var(--coral-600) on submit button');
    }

    public function testModalCssThemeOverridesWhiteBackground(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        // Verify the modal dialog in admin body is styled with dark theme
        $this->assert(str_contains($css, '.admin-body .gm-modal-dialog'), 'style.css must style .admin-body .gm-modal-dialog');
        $this->assert(str_contains($css, 'background: #11252c'), 'style.css must use #11252c surface background for admin modal dialog');
        $this->assert(!preg_match('/\.admin-body\s+\.gm-modal-dialog\s*\{\s*background:\s*#ffffff/i', $css), 'style.css must not assign pure white background to .admin-body .gm-modal-dialog');
        $this->assert(str_contains($css, 'border: 1px solid rgba(255, 255, 255, 0.14)'), 'style.css must configure crisp elevated border on modal dialog');
        $this->assert(str_contains($css, 'backdrop-filter: blur(4px)'), 'style.css must configure blurred backdrop for high background distinction');
    }

    public function testModalCssTypographyAndHighContrastHints(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        $this->assert(str_contains($css, 'color: #cbd5e1 !important'), 'style.css must give guidance hint text #cbd5e1 for readable contrast');
        $this->assert(str_contains($css, 'color: #f1f5f9 !important'), 'style.css must give labels bold readable text color');
        $this->assert(str_contains($css, 'color: #94a3b8 !important'), 'style.css must give optional label hints distinct secondary styling');
    }

    public function testModalCssTextareaStylingAndFocusStates(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        $this->assert(str_contains($css, 'background: #08161b !important'), 'style.css must style textarea with dark recessed background');
        $this->assert(str_contains($css, 'color: #ffffff !important'), 'style.css must set textarea text to #ffffff');
        $this->assert(str_contains($css, 'border-color: #ef4444 !important'), 'style.css must highlight textarea border with red on focus');
        $this->assert(str_contains($css, 'box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important'), 'style.css must configure red focus ring on textarea');
    }

    public function testModalCssButtonsVisibilityAndDestructiveColor(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        // Cancel Button
        $this->assert(str_contains($css, '.admin-body .gm-modal-dialog .btn-cancel-reject'), 'style.css must define .btn-cancel-reject');
        $this->assert(str_contains($css, 'border: 1px solid rgba(255, 255, 255, 0.22) !important'), 'style.css must configure visible cancel button border');
        $this->assert(str_contains($css, 'background: rgba(255, 255, 255, 0.12) !important'), 'style.css must configure hover background for cancel button');

        // Confirm Rejection Button
        $this->assert(str_contains($css, '.admin-body .gm-modal-dialog .btn-confirm-reject'), 'style.css must define .btn-confirm-reject');
        $this->assert(str_contains($css, 'background: #dc2626 !important'), 'style.css must configure semantic red #dc2626 for rejection action');
        $this->assert(str_contains($css, 'background: #b91c1c !important'), 'style.css must configure dark red #b91c1c on hover');
        $this->assert(str_contains($css, 'background: #991b1b !important'), 'style.css must configure active pressed state');
        $this->assert(str_contains($css, 'box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.4) !important'), 'style.css must configure focus-visible ring for keyboard accessibility');
        $this->assert(str_contains($css, 'cursor: not-allowed !important'), 'style.css must configure disabled state cursor');
    }

    public function testEscapeKeyDismissalHandledInBothViews(): void
    {
        $hotelView = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');
        $vehicleView = file_get_contents(BASE_DIR . '/app/Views/admin/vehicles.php');

        $this->assert(str_contains($hotelView, "e.key === 'Escape'"), 'Hotel view must dismiss reject modal on Escape key');
        $this->assert(str_contains($vehicleView, "e.key === 'Escape'"), 'Vehicle view must dismiss reject modal on Escape key');
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

$suite = new AdminRejectModalReadabilityTest();
exit($suite->run() ? 0 : 1);
