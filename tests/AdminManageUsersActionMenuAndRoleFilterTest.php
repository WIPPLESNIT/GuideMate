<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;

final class AdminManageUsersActionMenuAndRoleFilterTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "====================================================================\n";
        echo " GuideMate Manage Users Table Action Menu & Role Filter Tests\n";
        echo "====================================================================\n\n";

        $this->testRestrictedRemovedFromRoleFilter();
        $this->testRestrictedRemainsInStatusFilter();
        $this->testThreeDotActionMenuOverflowAndFixedPositioning();
        $this->testPanelAndWrapOverflowVisible();
        $this->testRestrictedRowThreeDotMenuContentAndPositioning();
        $this->testRoleFilterBehaviorAndDynamicStatusOptions();
        $this->testRoleFilterResetRestoresAllStatuses();

        echo "\n====================================================================\n";
        echo " Test Summary: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "====================================================================\n\n";

        return $this->failed === 0;
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo " [PASS] $message\n";
        } else {
            $this->failed++;
            echo " [FAIL] $message\n";
        }
    }

    public function testRestrictedRemovedFromRoleFilter(): void
    {
        echo "--- Test 1: Role Filter Excludes 'Restricted' ---\n";
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        // Extract role filter popover section
        preg_match('/id="roleFilterPopover".*?<\/div>\s*<\/div>/s', $viewContent, $matches);
        $rolePopoverHtml = $matches[0] ?? '';

        $this->assert(
            strpos($rolePopoverHtml, 'data-value="warned"') === false,
            'Role filter popover does not contain data-value="warned" (Restricted)'
        );
        $this->assert(
            strpos($rolePopoverHtml, 'Restricted') === false,
            'Role filter popover text does not contain "Restricted"'
        );

        // Extract backing select userRoleFilter
        preg_match('/<select id="userRoleFilter".*?<\/select>/s', $viewContent, $selectMatches);
        $roleSelectHtml = $selectMatches[0] ?? '';

        $this->assert(
            strpos($roleSelectHtml, 'value="warned"') === false,
            'Backing select userRoleFilter does not contain option value="warned"'
        );
        $this->assert(
            strpos($roleSelectHtml, 'Restricted') === false,
            'Backing select userRoleFilter does not contain "Restricted"'
        );
    }

    public function testRestrictedRemainsInStatusFilter(): void
    {
        echo "\n--- Test 2: Status Filter Preserves 'Restricted' Option ---\n";
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        preg_match('/id="statusFilterPopover".*?<\/div>\s*<\/div>/s', $viewContent, $matches);
        $statusPopoverHtml = $matches[0] ?? '';

        $this->assert(
            strpos($statusPopoverHtml, 'data-value="warned"') !== false,
            'Status filter popover contains data-value="warned"'
        );
        $this->assert(
            strpos($statusPopoverHtml, 'Restricted') !== false,
            'Status filter popover displays "Restricted" status'
        );

        preg_match('/<select id="userStatusFilter".*?<\/select>/s', $viewContent, $selectMatches);
        $statusSelectHtml = $selectMatches[0] ?? '';

        $this->assert(
            strpos($statusSelectHtml, 'value="warned"') !== false,
            'Backing select userStatusFilter contains option value="warned"'
        );
    }

    public function testThreeDotActionMenuOverflowAndFixedPositioning(): void
    {
        echo "\n--- Test 3: Three-Dot Menu Overflow & Layering Styles ---\n";
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');
        $view = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        // Check CSS uses position: fixed and high z-index
        $this->assert(
            strpos($css, 'position: fixed') !== false,
            '.user-action-dropdown styles include position: fixed'
        );
        $this->assert(
            strpos($css, 'z-index: 99999') !== false,
            '.user-action-dropdown uses top-tier elevation z-index: 99999'
        );
        $this->assert(
            strpos($css, 'max-width: calc(100vw - 20px)') !== false,
            '.user-action-dropdown sets responsive max-width to prevent viewport overflow'
        );

        // Check JavaScript implements positioning logic
        $this->assert(
            strpos($view, 'positionUserMenu') !== false,
            'admin/users.php implements positionUserMenu function'
        );
        $this->assert(
            strpos($view, 'btn.getBoundingClientRect()') !== false,
            'positionUserMenu retrieves trigger bounding client rect'
        );
        $this->assert(
            strpos($view, 'openUpward') !== false,
            'positionUserMenu handles collision detection and upward/downward direction'
        );
        $this->assert(
            strpos($view, "window.addEventListener('scroll', closeUserMenu") !== false,
            'closeUserMenu is registered on window scroll to preserve positioning'
        );
        $this->assert(
            strpos($view, "window.addEventListener('resize', closeUserMenu") !== false,
            'closeUserMenu is registered on window resize'
        );
    }

    public function testPanelAndWrapOverflowVisible(): void
    {
        echo "\n--- Test 4: Container Overflow Rules for Menu Escape ---\n";
        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');
        $view = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($css, '.users-table-panel') !== false && strpos($css, 'overflow: visible !important;') !== false,
            '.users-table-panel configured with overflow: visible !important'
        );
        $this->assert(
            strpos($css, '.users-table-wrap') !== false,
            '.users-table-wrap defined in style.css'
        );
        $this->assert(
            strpos($view, 'class="panel users-table-panel"') !== false,
            'Manage users view assigns users-table-panel class to panel'
        );
        $this->assert(
            strpos($view, 'style="overflow:visible;"') !== false,
            'Manage users view applies inline overflow:visible to table wrapper'
        );
    }

    public function testRestrictedRowThreeDotMenuContentAndPositioning(): void
    {
        echo "\n--- Test 5: Restricted Row Action Menu Content ---\n";
        $view = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($view, 'user-action-unrestrict') !== false,
            'Action dropdown includes Remove Restriction item for restricted users'
        );
        $this->assert(
            strpos($view, 'js-unrestrict') !== false,
            'Remove Restriction uses form with js-unrestrict class for confirmation dialog'
        );
        $this->assert(
            strpos($view, 'user-action-docs') !== false,
            'Action dropdown includes View Documents item for providers'
        );
        $this->assert(
            strpos($view, 'user-action-suspend') !== false,
            'Action dropdown includes Suspend Account'
        );
        $this->assert(
            strpos($view, 'user-action-delete') !== false,
            'Action dropdown includes Delete Account'
        );
    }

    public function testRoleFilterBehaviorAndDynamicStatusOptions(): void
    {
        echo "\n--- Test 6: Role Filter Behavior & Dynamic Status Synchronization ---\n";
        $view = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($view, 'function rowMatchesRole(row, selRole)') !== false,
            'Implements rowMatchesRole helper for role filtering'
        );
        $this->assert(
            strpos($view, 'function updateStatusFilterForRole(selRole)') !== false,
            'Implements updateStatusFilterForRole function'
        );
        $this->assert(
            strpos($view, "item.style.display = 'none'") !== false,
            'Hides statuses that have 0 matching users for the selected role'
        );
        $this->assert(
            strpos($view, "cntEl.textContent = cnt") !== false,
            'Updates status option count badges to match the selected role totals'
        );
        $this->assert(
            strpos($view, "statusSel.value = 'all'") !== false,
            'Automatically resets status selection to all if current status is invalid for new role'
        );
    }

    public function testRoleFilterResetRestoresAllStatuses(): void
    {
        echo "\n--- Test 7: Filter Reset & Empty State Handlers ---\n";
        $view = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($view, 'updateStatusFilterForRole(selectedRole)') !== false,
            'applyFilter invokes updateStatusFilterForRole on every filter change'
        );
        $this->assert(
            strpos($view, "updateStatusFilterForRole(roleFilter ? roleFilter.value : 'all')") !== false,
            'Initializes status filter options with current role selection on script load'
        );
    }
}

$test = new AdminManageUsersActionMenuAndRoleFilterTest();
$success = $test->run();
exit($success ? 0 : 1);
