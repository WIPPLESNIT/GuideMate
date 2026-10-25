<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

final class AdminResponsiveTablesTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate Admin Responsive Tables Test Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testUsersTableMarkupAndNoScrollbarWrap', [$this, 'testUsersTableMarkupAndNoScrollbarWrap']);
        $this->runTest('testVehiclesTableMarkupAndNoScrollbarWrap', [$this, 'testVehiclesTableMarkupAndNoScrollbarWrap']);
        $this->runTest('testCssRulesEliminateHorizontalScrollbars', [$this, 'testCssRulesEliminateHorizontalScrollbars']);
        $this->runTest('testResponsiveMediaQueriesConfiguredForSmallScreens', [$this, 'testResponsiveMediaQueriesConfiguredForSmallScreens']);
        $this->runTest('testReadableWrappingRulesConfiguredWithoutTruncation', [$this, 'testReadableWrappingRulesConfiguredWithoutTruncation']);

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    public function testUsersTableMarkupAndNoScrollbarWrap(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/users.php');

        $this->assert(str_contains($view, 'users-table-wrap'), 'users.php panel-body must have users-table-wrap class');
        $this->assert(!str_contains($view, 'overflow-x:auto'), 'users.php panel-body must not have inline overflow-x:auto');
        $this->assert(str_contains($view, 'table-users'), 'users.php must use table-users class');

        // Check data-label attributes for mobile accessibility
        $this->assert(str_contains($view, 'data-label="Full Name"'), 'Name cell must have data-label="Full Name"');
        $this->assert(str_contains($view, 'data-label="Email"'), 'Email cell must have data-label="Email"');
        $this->assert(str_contains($view, 'data-label="Status"'), 'Status cell must have data-label="Status"');
        $this->assert(str_contains($view, 'data-label="Role"'), 'Role cell must have data-label="Role"');
        $this->assert(str_contains($view, 'data-label="Joined Date"'), 'Joined Date cell must have data-label="Joined Date"');
        $this->assert(str_contains($view, 'data-label="Actions"'), 'Actions cell must have data-label="Actions"');
    }

    public function testVehiclesTableMarkupAndNoScrollbarWrap(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/vehicles.php');

        $this->assert(str_contains($view, 'vehicles-table-wrap'), 'vehicles.php panel-body must have vehicles-table-wrap class');
        $this->assert(!str_contains($view, 'overflow-x:auto'), 'vehicles.php panel-body must not have inline overflow-x:auto');
        $this->assert(str_contains($view, 'table-vehicles'), 'vehicles.php must use table-vehicles class');

        // Check column classes and data-label attributes
        $this->assert(str_contains($view, 'class="col-vehicle"'), 'Vehicle column header must have col-vehicle class');
        $this->assert(str_contains($view, 'class="col-partner"'), 'Partner column header must have col-partner class');
        $this->assert(str_contains($view, 'class="col-type"'), 'Type column header must have col-type class');
        $this->assert(str_contains($view, 'class="col-status"'), 'Status column header must have col-status class');
        $this->assert(str_contains($view, 'class="col-audit"'), 'Audit column header must have col-audit class');
        $this->assert(str_contains($view, 'class="col-actions"'), 'Actions column header must have col-actions class');

        $this->assert(str_contains($view, 'data-label="Vehicle"'), 'Vehicle cell must have data-label="Vehicle"');
        $this->assert(str_contains($view, 'data-label="Partner"'), 'Partner cell must have data-label="Partner"');
        $this->assert(str_contains($view, 'data-label="Type / Rate"'), 'Type cell must have data-label="Type / Rate"');
        $this->assert(str_contains($view, 'data-label="Status"'), 'Status cell must have data-label="Status"');
        $this->assert(str_contains($view, 'data-label="Dates / Audit"'), 'Dates cell must have data-label="Dates / Audit"');
        $this->assert(str_contains($view, 'data-label="Actions"'), 'Actions cell must have data-label="Actions"');
    }

    public function testCssRulesEliminateHorizontalScrollbars(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        // Users table container no scrollbar
        $this->assert(str_contains($css, '.users-table-wrap'), '.users-table-wrap rule must exist in stylesheet');
        $this->assert(str_contains($css, 'overflow-x: hidden !important;'), 'Stylesheet must enforce overflow-x: hidden !important');

        // Vehicles table container no scrollbar
        $this->assert(str_contains($css, '.vehicles-table-wrap'), '.vehicles-table-wrap rule must exist in stylesheet');

        // No rigid min-width on table-users or table-vehicles forcing overflow
        $this->assert(!str_contains($css, '.table-users { width: 100%; min-width: 760px; }'), 'table-users must not have rigid min-width 760px');
        $this->assert(!str_contains($css, '.table-vehicles th.col-vehicle,' . "\n" . '.table-vehicles td.vehicle-cell {' . "\n" . '    min-width: 290px;'), 'table-vehicles must not have rigid min-width 290px');
    }

    public function testResponsiveMediaQueriesConfiguredForSmallScreens(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        // Media query for tablets and mobile
        $this->assert(str_contains($css, '@media (max-width: 820px)'), '@media (max-width: 820px) breakpoint must be configured');
        $this->assert(str_contains($css, '.table-users thead {'), 'table-users header must be hidden on mobile card layout');
        $this->assert(str_contains($css, '.table-vehicles thead {'), 'table-vehicles header must be hidden on mobile card layout');
        $this->assert(str_contains($css, 'grid-template-areas:'), 'Responsive grid areas must be configured for card presentation');
    }

    public function testReadableWrappingRulesConfiguredWithoutTruncation(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        $this->assert(str_contains($css, 'word-break: break-word;') || str_contains($css, 'overflow-wrap: break-word;'), 'Readable word breaking must be configured');
        $this->assert(str_contains($css, 'overflow-wrap: anywhere;') || str_contains($css, 'word-break: break-all;'), 'Email breaking must be configured');
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

$suite = new AdminResponsiveTablesTest();
exit($suite->run() ? 0 : 1);
