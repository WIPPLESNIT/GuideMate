<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

class UserListSearchAndFilterTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate User List Real-time Search & Filter Tests\n";
        echo "========================================================\n\n";

        $this->testSearchBarMarkupAndPlaceholder();
        $this->testFilterDropdownsExist();
        $this->testExportButtonAndLastActiveColumnOmitted();
        $this->testTableColumnsMatchReferenceLayout();
        $this->testRowDataAttributesForSearchAndFilters();
        $this->testEmptyStateMarkup();
        $this->testStylesheetContainsSearchAndFilterClasses();
        $this->testAddUserButtonToolbarAlignment();
        $this->testTableActionButtonsCleanAndConsistentSizing();
        $this->testCustomFilterDropdownComponent();
        $this->testCaseInsensitiveSearchLogic();
        $this->testRoleFilterLogic();
        $this->testStatusFilterLogic();
        $this->testDateFilterAndSortingLogic();
        $this->testCombinedMultiFilterLogic();
        $this->testNonMatchingQueryYieldsZeroResults();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n\n";

        return $this->failed === 0;
    }

    private function testSearchBarMarkupAndPlaceholder(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($viewContent, 'id="userSearchInput"') !== false,
            'testSearchBarMarkupAndPlaceholder: userSearchInput input element exists'
        );

        $this->assert(
            strpos($viewContent, 'placeholder="Search"') !== false,
            'testSearchBarMarkupAndPlaceholder: placeholder text matches reference "Search"'
        );

        $this->assert(
            strpos($viewContent, 'class="filter-control users-search-wrap"') !== false,
            'testSearchBarMarkupAndPlaceholder: wrapped in filter-control users-search-wrap'
        );

        $this->assert(
            strpos($viewContent, 'class="filter-icon"') !== false,
            'testSearchBarMarkupAndPlaceholder: search icon exists'
        );
    }

    private function testFilterDropdownsExist(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($viewContent, 'id="userRoleFilter"') !== false,
            'testFilterDropdownsExist: userRoleFilter dropdown exists'
        );

        $this->assert(
            strpos($viewContent, 'id="userStatusFilter"') !== false,
            'testFilterDropdownsExist: userStatusFilter dropdown exists'
        );

        $this->assert(
            strpos($viewContent, 'id="userDateFilter"') !== false,
            'testFilterDropdownsExist: userDateFilter dropdown exists'
        );
    }

    private function testExportButtonAndLastActiveColumnOmitted(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        // User explicitly instructed: "However, do not include the “Export” button or the “Last Active” column in the table."
        $this->assert(
            stripos($viewContent, 'Export') === false,
            'testExportButtonAndLastActiveColumnOmitted: Export button is strictly omitted'
        );

        $this->assert(
            stripos($viewContent, 'Last Active') === false,
            'testExportButtonAndLastActiveColumnOmitted: Last Active column is strictly omitted'
        );
    }

    private function testTableColumnsMatchReferenceLayout(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($viewContent, '>Full Name<') !== false,
            'testTableColumnsMatchReferenceLayout: Full Name column present'
        );

        $this->assert(
            strpos($viewContent, '>Email<') !== false,
            'testTableColumnsMatchReferenceLayout: Email column present'
        );

        $this->assert(
            strpos($viewContent, '>Status<') !== false,
            'testTableColumnsMatchReferenceLayout: Status column present'
        );

        $this->assert(
            strpos($viewContent, '>Role<') !== false,
            'testTableColumnsMatchReferenceLayout: Role column present'
        );

        $this->assert(
            strpos($viewContent, '>Joined Date<') !== false,
            'testTableColumnsMatchReferenceLayout: Joined Date column present'
        );

        $this->assert(
            strpos($viewContent, '>Actions<') !== false,
            'testTableColumnsMatchReferenceLayout: Actions column present'
        );
    }

    private function testRowDataAttributesForSearchAndFilters(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($viewContent, 'data-name=') !== false,
            'testRowDataAttributesForSearchAndFilters: tr includes data-name attribute'
        );

        $this->assert(
            strpos($viewContent, 'data-email=') !== false,
            'testRowDataAttributesForSearchAndFilters: tr includes data-email attribute'
        );

        $this->assert(
            strpos($viewContent, 'data-role=') !== false,
            'testRowDataAttributesForSearchAndFilters: tr includes data-role attribute'
        );

        $this->assert(
            strpos($viewContent, 'data-status=') !== false,
            'testRowDataAttributesForSearchAndFilters: tr includes data-status attribute'
        );

        $this->assert(
            strpos($viewContent, 'data-created=') !== false,
            'testRowDataAttributesForSearchAndFilters: tr includes data-created attribute'
        );
    }

    private function testEmptyStateMarkup(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');

        $this->assert(
            strpos($viewContent, 'id="usersEmptyRow"') !== false,
            'testEmptyStateMarkup: usersEmptyRow tr exists'
        );

        $this->assert(
            strpos($viewContent, 'No accounts found.') !== false,
            'testEmptyStateMarkup: contains "No accounts found." empty state text'
        );
    }

    private function testStylesheetContainsSearchAndFilterClasses(): void
    {
        $cssContent = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(
            strpos($cssContent, '.users-toolbar') !== false,
            'testStylesheetContainsSearchAndFilterClasses: .users-toolbar selector defined'
        );

        $this->assert(
            strpos($cssContent, '.users-filters-group') !== false,
            'testStylesheetContainsSearchAndFilterClasses: .users-filters-group selector defined'
        );

        $this->assert(
            strpos($cssContent, '.filter-select') !== false,
            'testStylesheetContainsSearchAndFilterClasses: .filter-select selector defined'
        );

        $this->assert(
            strpos($cssContent, '.users-search-wrap') !== false,
            'testStylesheetContainsSearchAndFilterClasses: .users-search-wrap selector defined'
        );

        $this->assert(
            strpos($cssContent, '.table-users .col-role') !== false,
            'testStylesheetContainsSearchAndFilterClasses: .table-users .col-role defined'
        );
    }

    private function testAddUserButtonToolbarAlignment(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');
        $cssContent = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        $this->assert(
            strpos($viewContent, 'btn-add-user') !== false,
            'testAddUserButtonToolbarAlignment: + Add User link has btn-add-user class'
        );

        $this->assert(
            strpos($cssContent, '.users-toolbar-actions .btn-add-user') !== false,
            'testAddUserButtonToolbarAlignment: .users-toolbar-actions .btn-add-user rule defined'
        );

        // Verify height alignment (38px on input, select, and add user button)
        $this->assert(
            preg_match('/\.users-toolbar-actions\s+\.btn-add-user\s*\{[^}]*height:\s*38px;/s', $cssContent) === 1,
            'testAddUserButtonToolbarAlignment: btn-add-user has matching 38px height'
        );

        // Verify flex nowrap keeps items on one horizontal line on desktop
        $this->assert(
            preg_match('/\.users-toolbar\s*\{[^}]*flex-wrap:\s*nowrap;/s', $cssContent) === 1,
            'testAddUserButtonToolbarAlignment: .users-toolbar flex-wrap set to nowrap for desktop'
        );

        // Verify compact dropdown widths prevent unwanted horizontal wrapping
        $this->assert(
            strpos($cssContent, '#userRoleFilter { width: 100px; }') !== false,
            'testAddUserButtonToolbarAlignment: userRoleFilter constrained width defined'
        );
        $this->assert(
            strpos($cssContent, '#userStatusFilter { width: 110px; }') !== false,
            'testAddUserButtonToolbarAlignment: userStatusFilter constrained width defined'
        );
        $this->assert(
            strpos($cssContent, '#userDateFilter { width: 100px; }') !== false,
            'testAddUserButtonToolbarAlignment: userDateFilter constrained width defined'
        );
    }

    private function testCustomFilterDropdownComponent(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');
        $cssContent = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // Check custom filter dropdown markup
        $this->assert(
            strpos($viewContent, 'filter-custom-dropdown') !== false,
            'testCustomFilterDropdownComponent: filter-custom-dropdown wrapper exists'
        );
        $this->assert(
            strpos($viewContent, 'class="filter-dropdown-btn"') !== false,
            'testCustomFilterDropdownComponent: filter-dropdown-btn trigger button exists'
        );
        $this->assert(
            strpos($viewContent, 'aria-haspopup="listbox"') !== false,
            'testCustomFilterDropdownComponent: aria-haspopup="listbox" attribute defined on trigger'
        );
        $this->assert(
            strpos($viewContent, 'class="filter-dropdown-popover"') !== false,
            'testCustomFilterDropdownComponent: filter-dropdown-popover container exists'
        );
        $this->assert(
            strpos($viewContent, 'role="listbox"') !== false,
            'testCustomFilterDropdownComponent: popover has role="listbox"'
        );
        $this->assert(
            strpos($viewContent, 'class="filter-popover-head"') !== false,
            'testCustomFilterDropdownComponent: popover header exists'
        );
        $this->assert(
            strpos($viewContent, 'class="filter-popover-reset"') !== false,
            'testCustomFilterDropdownComponent: reset action in popover header exists'
        );
        $this->assert(
            strpos($viewContent, 'class="filter-popover-item') !== false,
            'testCustomFilterDropdownComponent: filter-popover-item options rendered'
        );
        $this->assert(
            strpos($viewContent, 'class="filter-item-indicator"') !== false,
            'testCustomFilterDropdownComponent: filter-item-indicator bullet rendered'
        );
        $this->assert(
            strpos($viewContent, 'class="filter-item-count"') !== false,
            'testCustomFilterDropdownComponent: live count badge rendered on options'
        );
        $this->assert(
            strpos($viewContent, 'id="clearAllFiltersBtn"') !== false,
            'testCustomFilterDropdownComponent: clearAllFiltersBtn present in empty state'
        );

        // Check CSS rules
        $this->assert(
            strpos($cssContent, '.filter-dropdown-btn') !== false,
            'testCustomFilterDropdownComponent: .filter-dropdown-btn defined in stylesheet'
        );
        $this->assert(
            strpos($cssContent, '.filter-dropdown-popover') !== false,
            'testCustomFilterDropdownComponent: .filter-dropdown-popover defined in stylesheet'
        );
        $this->assert(
            strpos($cssContent, '.filter-popover-item.is-selected') !== false,
            'testCustomFilterDropdownComponent: .filter-popover-item.is-selected highlighted style defined'
        );
        $this->assert(
            strpos($cssContent, '.filter-item-count') !== false,
            'testCustomFilterDropdownComponent: .filter-item-count badge styling defined'
        );

        // Check JavaScript controller
        $this->assert(
            strpos($viewContent, 'closeAllFilterPopovers') !== false,
            'testCustomFilterDropdownComponent: closeAllFilterPopovers controller function exists'
        );
        $this->assert(
            strpos($viewContent, 'filterConfigs') !== false,
            'testCustomFilterDropdownComponent: filterConfigs dictionary registered for bidirectional sync'
        );
    }

    private function testTableActionButtonsCleanAndConsistentSizing(): void
    {
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/admin/users.php');
        $cssContent = file_get_contents(__DIR__ . '/../public/assets/css/style.css');

        // Check vertical trigger button markup and accessibility
        $this->assert(
            strpos($viewContent, 'btn-user-menu-trigger') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: vertical trigger button has btn-user-menu-trigger class'
        );
        $this->assert(
            strpos($viewContent, 'user-action-menu-wrap') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: action column wrapped in user-action-menu-wrap'
        );
        $this->assert(
            strpos($viewContent, 'aria-haspopup="true"') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: trigger button includes aria-haspopup="true"'
        );
        $this->assert(
            strpos($viewContent, 'aria-expanded="false"') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: trigger button includes aria-expanded="false"'
        );
        $this->assert(
            strpos($viewContent, 'user-action-dropdown') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: dropdown container user-action-dropdown exists'
        );
        $this->assert(
            strpos($viewContent, 'role="menu"') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: dropdown has role="menu"'
        );
        $this->assert(
            strpos($viewContent, 'user-action-item user-action-edit') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: Edit User item present with user-action-edit class'
        );
        $this->assert(
            strpos($viewContent, 'user-action-item user-action-suspend') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: Suspend Account item present with user-action-suspend class'
        );
        $this->assert(
            strpos($viewContent, 'user-action-item user-action-delete') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: Delete Account item present with user-action-delete class'
        );
        $this->assert(
            strpos($viewContent, 'action-dropdown-divider') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: action dropdown contains divider for clean hierarchy'
        );

        // Check CSS definitions for vertical action button and dropdown
        $this->assert(
            strpos($cssContent, '.btn-user-menu-trigger') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: .btn-user-menu-trigger style defined'
        );
        $this->assert(
            preg_match('/\.btn-user-menu-trigger\s*\{[^}]*width:\s*32px;/s', $cssContent) === 1,
            'testTableActionButtonsCleanAndConsistentSizing: .btn-user-menu-trigger width set to 32px circular pill'
        );
        $this->assert(
            strpos($cssContent, '.user-action-dropdown') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: .user-action-dropdown defined'
        );
        $this->assert(
            strpos($cssContent, '.user-action-dropdown.open') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: .user-action-dropdown.open rule defined'
        );
        $this->assert(
            strpos($cssContent, '.user-action-dropdown.drop-up') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: .user-action-dropdown.drop-up rule defined for bottom bounds'
        );
        $this->assert(
            strpos($cssContent, '.user-action-item.user-action-suspend') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: amber color defined for suspend action'
        );
        $this->assert(
            strpos($cssContent, '.user-action-item.user-action-delete') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: danger coral color defined for delete action'
        );
        $this->assert(
            strpos($cssContent, 'tr.is-menu-open') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: tr.is-menu-open elevation z-index configured'
        );

        // Check JavaScript interactive handling
        $this->assert(
            strpos($viewContent, 'closeUserMenu') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: closeUserMenu function exists in script'
        );
        $this->assert(
            strpos($viewContent, 'Escape') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: Escape key handler wired to dismiss dropdown'
        );

        // Check table column width
        $this->assert(
            strpos($cssContent, '.table-users .col-actions { width: 8%; text-align: right; }') !== false,
            'testTableActionButtonsCleanAndConsistentSizing: col-actions width slimmed to 8% for clean compact layout'
        );
    }

    private function testCaseInsensitiveSearchLogic(): void
    {
        $sampleUsers = $this->getMockUsers();

        $filteredLower = $this->simulateFilter($sampleUsers, 'yongzon', 'all', 'all', 'all');
        $this->assert(
            count($filteredLower) === 2,
            'testCaseInsensitiveSearchLogic: searching lowercase "yongzon" finds 2 accounts'
        );

        $filteredUpper = $this->simulateFilter($sampleUsers, 'YONGZON', 'all', 'all', 'all');
        $this->assert(
            count($filteredUpper) === 2,
            'testCaseInsensitiveSearchLogic: searching uppercase "YONGZON" finds 2 accounts'
        );

        $filteredEmail = $this->simulateFilter($sampleUsers, 'johnivan5558', 'all', 'all', 'all');
        $this->assert(
            count($filteredEmail) === 1 && $filteredEmail[0]['name'] === 'johnivangwapo',
            'testCaseInsensitiveSearchLogic: searching by email finds corresponding account'
        );
    }

    private function testRoleFilterLogic(): void
    {
        $sampleUsers = $this->getMockUsers();

        $guides = $this->simulateFilter($sampleUsers, '', 'guide', 'all', 'all');
        $this->assert(
            count($guides) === 1 && $guides[0]['name'] === 'Maria Santos',
            'testRoleFilterLogic: filtering by role guide finds only Maria Santos'
        );

        $rentals = $this->simulateFilter($sampleUsers, '', 'rental_provider', 'all', 'all');
        $this->assert(
            count($rentals) === 1 && $rentals[0]['name'] === 'Miguel Yongzon',
            'testRoleFilterLogic: filtering by role rental_provider finds only Miguel Yongzon'
        );
    }

    private function testStatusFilterLogic(): void
    {
        $sampleUsers = $this->getMockUsers();

        $suspended = $this->simulateFilter($sampleUsers, '', 'all', 'suspended', 'all');
        $this->assert(
            count($suspended) === 1 && $suspended[0]['name'] === 'Suspended User',
            'testStatusFilterLogic: filtering by status suspended finds suspended account'
        );

        $active = $this->simulateFilter($sampleUsers, '', 'all', 'active', 'all');
        $this->assert(
            count($active) === 4,
            'testStatusFilterLogic: filtering by status active finds all active accounts'
        );
    }

    private function testDateFilterAndSortingLogic(): void
    {
        $sampleUsers = $this->getMockUsers();

        // Test newest first sorting
        $sortedDesc = $this->simulateFilter($sampleUsers, '', 'all', 'all', 'sort_desc');
        $this->assert(
            $sortedDesc[0]['name'] === 'Christian Yongzon',
            'testDateFilterAndSortingLogic: sort_desc places newest account first'
        );

        // Test oldest first sorting
        $sortedAsc = $this->simulateFilter($sampleUsers, '', 'all', 'all', 'sort_asc');
        $this->assert(
            $sortedAsc[0]['name'] === 'Suspended User',
            'testDateFilterAndSortingLogic: sort_asc places oldest account first'
        );
    }

    private function testCombinedMultiFilterLogic(): void
    {
        $sampleUsers = $this->getMockUsers();

        // Search 'Yongzon' + Role 'rental_provider' + Status 'active'
        $filtered = $this->simulateFilter($sampleUsers, 'Yongzon', 'rental_provider', 'active', 'all');
        $this->assert(
            count($filtered) === 1 && $filtered[0]['name'] === 'Miguel Yongzon',
            'testCombinedMultiFilterLogic: combining Search + Role + Status matches exactly one user'
        );

        // Search 'Yongzon' + Role 'tourist' + Status 'active'
        $filteredTourist = $this->simulateFilter($sampleUsers, 'Yongzon', 'tourist', 'active', 'all');
        $this->assert(
            count($filteredTourist) === 1 && $filteredTourist[0]['name'] === 'Christian Yongzon',
            'testCombinedMultiFilterLogic: combining Search + Role matches Christian'
        );
    }

    private function testNonMatchingQueryYieldsZeroResults(): void
    {
        $sampleUsers = $this->getMockUsers();

        $filteredNone = $this->simulateFilter($sampleUsers, 'NonExistentAccount123456', 'all', 'all', 'all');
        $this->assert(
            count($filteredNone) === 0,
            'testNonMatchingQueryYieldsZeroResults: query matching no users returns empty result set'
        );
    }

    /**
     * @param array<int,array<string,mixed>> $users
     * @return array<int,array<string,mixed>>
     */
    private function simulateFilter(array $users, string $query, string $selectedRole, string $selectedStatus, string $selectedDate): array
    {
        $q = strtolower(trim($query));
        $now = time();
        $startOfToday = strtotime('today midnight');
        $sevenDaysAgo = $now - (7 * 86400);
        $thirtyDaysAgo = $now - (30 * 86400);
        $startOfYear = strtotime(date('Y') . '-01-01 00:00:00');

        $result = [];

        foreach ($users as $u) {
            $role = (string) ($u['role'] ?? 'tourist');
            $status = (string) ($u['status'] ?? 'active');
            $created = (int) ($u['created_timestamp'] ?? 0);
            $isWarned = (int) ($u['guide_warned'] ?? 0) === 1;

            // Role match
            $matchesRole = $selectedRole === 'all'
                || ($selectedRole === 'warned' && $isWarned)
                || $role === $selectedRole;
            if (!$matchesRole) {
                continue;
            }

            // Status match
            $matchesStatus = $selectedStatus === 'all'
                || ($selectedStatus === 'warned' && $isWarned)
                || $status === $selectedStatus;
            if (!$matchesStatus) {
                continue;
            }

            // Date match
            $matchesDate = true;
            if ($selectedDate === 'today') {
                $matchesDate = $created >= $startOfToday;
            } elseif ($selectedDate === '7days') {
                $matchesDate = $created >= $sevenDaysAgo;
            } elseif ($selectedDate === '30days') {
                $matchesDate = $created >= $thirtyDaysAgo;
            } elseif ($selectedDate === 'this_year') {
                $matchesDate = $created >= $startOfYear;
            }
            if (!$matchesDate) {
                continue;
            }

            // Search query match
            $matchesSearch = true;
            if ($q !== '') {
                $name = strtolower((string) ($u['name'] ?? ''));
                $email = strtolower((string) ($u['email'] ?? ''));
                $roleLabel = strtolower((string) ($u['role_label'] ?? $role));

                $matchesSearch = strpos($name, $q) !== false
                    || strpos($email, $q) !== false
                    || strpos($roleLabel, $q) !== false;
            }

            if ($matchesSearch) {
                $result[] = $u;
            }
        }

        // Sorting
        if ($selectedDate === 'sort_desc') {
            usort($result, fn($a, $b) => $b['created_timestamp'] <=> $a['created_timestamp']);
        } elseif ($selectedDate === 'sort_asc') {
            usort($result, fn($a, $b) => $a['created_timestamp'] <=> $b['created_timestamp']);
        }

        return $result;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getMockUsers(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Christian Yongzon',
                'email' => 'ben@sample.com',
                'role' => 'tourist',
                'role_label' => 'Tourist',
                'status' => 'active',
                'guide_warned' => 0,
                'created_timestamp' => time() - 86400, // 1 day ago
            ],
            [
                'id' => 2,
                'name' => 'johnivangwapo',
                'email' => 'johnivan5558@gmail.com',
                'role' => 'tourist',
                'role_label' => 'Tourist',
                'status' => 'active',
                'guide_warned' => 0,
                'created_timestamp' => time() - (2 * 86400), // 2 days ago
            ],
            [
                'id' => 3,
                'name' => 'Miguel Yongzon',
                'email' => 'migyong@sample.com',
                'role' => 'rental_provider',
                'role_label' => 'Rental Partner',
                'status' => 'active',
                'guide_warned' => 0,
                'created_timestamp' => time() - (3 * 86400), // 3 days ago
            ],
            [
                'id' => 4,
                'name' => 'Maria Santos',
                'email' => 'maria.guide@sample.com',
                'role' => 'guide',
                'role_label' => 'Tour Guide',
                'status' => 'active',
                'guide_warned' => 0,
                'created_timestamp' => time() - (10 * 86400), // 10 days ago
            ],
            [
                'id' => 5,
                'name' => 'Suspended User',
                'email' => 'bad.actor@sample.com',
                'role' => 'tourist',
                'role_label' => 'Tourist',
                'status' => 'suspended',
                'guide_warned' => 0,
                'created_timestamp' => time() - (60 * 86400), // 60 days ago
            ],
        ];
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
}

$test = new UserListSearchAndFilterTest();
$success = $test->run();
exit($success ? 0 : 1);
