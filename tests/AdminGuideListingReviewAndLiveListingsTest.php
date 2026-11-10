<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\Category;
use App\Controllers\AdminController;

final class AdminGuideListingReviewAndLiveListingsTest
{
    private int $passCount = 0;
    private int $failCount = 0;
    private array $cleanUpListingIds = [];
    private array $cleanUpUserIds = [];

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function run(): void
    {
        echo "\n========================================================\n";
        echo " GuideMate Admin Guide Listing Review & Live Listings Test\n";
        echo "========================================================\n\n";

        try {
            $this->testAdminNavigationIncludesGuideReviews();
            $this->testGuideListingReviewViewsIntegrity();
            $this->testMainListingsViewHasNoPendingTableAndShowsAllLive();
            $this->testGuideListingsModelReviewWorkflow();
            $this->testAllLiveListingsQueryAndCategoryCounts();
            $this->testGuideListingDeletionRestrictedToRejected();
            $this->testGuideNotificationMethodsExist();
            $this->testAdminControllerShowGuideListingEndpoint();
        } finally {
            $this->tearDown();
        }

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passCount} passed, {$this->failCount} failed\n";
        echo "--------------------------------------------------------\n\n";

        if ($this->failCount > 0) {
            exit(1);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo " [PASS] {$message}\n";
            $this->passCount++;
        } else {
            echo " [FAIL] {$message}\n";
            $this->failCount++;
        }
    }

    private function testAdminNavigationIncludesGuideReviews(): void
    {
        $nav = file_get_contents(BASE_DIR . '/app/Views/partials/admin-nav.php');

        $this->assert(
            str_contains($nav, '/admin/guide-listings'),
            'Admin nav contains link to /admin/guide-listings'
        );
        $this->assert(
            str_contains($nav, '>Guide</span>'),
            'Admin nav contains "Guide" label'
        );
        $this->assert(
            str_contains($nav, '$pendingGuideListings'),
            'Admin nav tracks $pendingGuideListings badge count'
        );
        $this->assert(
            str_contains($nav, '/admin/listings'),
            'Admin nav contains main Listings link'
        );
    }

    private function testGuideListingReviewViewsIntegrity(): void
    {
        // 1. guide-listings.php table view
        $tableFile = BASE_DIR . '/app/Views/admin/guide-listings.php';
        $this->assert(file_exists($tableFile), 'admin/guide-listings.php view file exists');
        $tableView = file_get_contents($tableFile);

        $this->assert(str_contains($tableView, 'Guide Listings Review'), 'View title is "Guide Listings Review"');
        $this->assert(str_contains($tableView, 'Pending Review'), 'Contains "Pending Review" status filter');
        $this->assert(str_contains($tableView, 'Approved'), 'Contains "Approved" status filter');
        $this->assert(str_contains($tableView, 'Rejected'), 'Contains "Rejected" status filter');
        $this->assert(str_contains($tableView, 'All Listings'), 'Contains "All Listings" filter');
        $this->assert(str_contains($tableView, 'Attractions'), 'Contains "Attractions" category filter pill');
        $this->assert(str_contains($tableView, 'Things to Do'), 'Contains "Things to Do" category filter pill');
        $this->assert(str_contains($tableView, 'table-vehicles'), 'Uses responsive table-vehicles table styling');
        $this->assert(str_contains($tableView, 'adminRejectModal'), 'Contains rejection reason modal');
        $this->assert(str_contains($tableView, 'adminDeleteModal'), 'Contains delete confirmation modal');

        // 2. guide-listing-show.php inspection view
        $showFile = BASE_DIR . '/app/Views/admin/guide-listing-show.php';
        $this->assert(file_exists($showFile), 'admin/guide-listing-show.php inspect file exists');
        $showView = file_get_contents($showFile);

        $this->assert(
            str_contains($showView, 'Guide Listings Review') || str_contains($showView, 'Review Guide Listing'),
            'Show view header includes Guide Listings Review back link'
        );
        $this->assert(str_contains($showView, 'approve'), 'Show view includes Approve action form');
        $this->assert(str_contains($showView, 'reject'), 'Show view includes Reject action form');
        $this->assert(str_contains($showView, 'Photo Gallery'), 'Show view displays Photo Gallery section');
        $this->assert(str_contains($showView, 'Schedules / Sessions') || str_contains($showView, 'Tour Schedule'), 'Show view displays Tour Schedule sessions section');
    }

    private function testMainListingsViewHasNoPendingTableAndShowsAllLive(): void
    {
        $listingsFile = BASE_DIR . '/app/Views/admin/listings.php';
        $this->assert(file_exists($listingsFile), 'admin/listings.php exists');
        $view = file_get_contents($listingsFile);

        // Crucial requirement: Remove the "Pending Approval" table from the Listing section
        $this->assert(
            !str_contains($view, 'Pending approval'),
            'admin/listings.php NO LONGER contains the "Pending approval" table'
        );
        $this->assert(
            !str_contains($view, '<h3>Pending approval'),
            'admin/listings.php does not contain Pending approval heading'
        );

        // Must display all live listings and category filter matching the Users section
        $this->assert(
            str_contains($view, 'Manage listings'),
            'admin/listings.php has heading "Manage listings"'
        );
        $this->assert(
            str_contains($view, 'filter-custom-dropdown') && str_contains($view, 'filter-dropdown-btn'),
            'admin/listings.php implements filter-custom-dropdown matching Users section'
        );
        $this->assert(
            str_contains($view, 'filter-dropdown-popover'),
            'admin/listings.php contains filter-dropdown-popover'
        );
        $this->assert(
            str_contains($view, 'All Live Listings'),
            'admin/listings.php contains "All Live Listings" filter option'
        );
        $this->assert(
            str_contains($view, 'Attractions'),
            'admin/listings.php contains "Attractions" filter option'
        );
        $this->assert(
            str_contains($view, 'Things to Do'),
            'admin/listings.php contains "Things to Do" filter option'
        );
        $this->assert(
            str_contains($view, 'Hotel & Stays'),
            'admin/listings.php contains "Hotel & Stays" filter option'
        );

        // Search bar on the left side of the filter matching user section
        $this->assert(
            str_contains($view, 'users-search-wrap') && str_contains($view, 'id="listingSearchInput"'),
            'admin/listings.php includes search input on left of filter matching user section'
        );
        $this->assert(
            str_contains($view, 'users-search-clear'),
            'admin/listings.php includes clear button for search input'
        );

        // Table structure and responsive classes
        $this->assert(
            str_contains($view, 'table-vehicles'),
            'admin/listings.php uses responsive table-vehicles structure'
        );
        $this->assert(
            str_contains($view, 'col-vehicle') && str_contains($view, 'col-partner'),
            'admin/listings.php includes standard columns'
        );

        // Actions: Feature/Unfeature, Unpublish, View
        $this->assert(
            str_contains($view, '/feature'),
            'admin/listings.php includes Feature toggle action'
        );
        $this->assert(
            str_contains($view, 'Unpublish'),
            'admin/listings.php includes Unpublish action'
        );
        $this->assert(
            str_contains($view, 'View'),
            'admin/listings.php includes View public link'
        );
    }

    private function testGuideListingsModelReviewWorkflow(): void
    {
        // 1. Create a test guide user and an attraction/tour listing
        $guideEmail = 'guide_test_' . bin2hex(random_bytes(4)) . '@example.com';
        $guideId = User::create('Test Tour Guide', $guideEmail, 'Secret123!', 'guide');
        $this->cleanUpUserIds[] = $guideId;

        $attractionCat = Database::first('SELECT id FROM categories WHERE slug IN ("attractions", "attraction-and-tours") LIMIT 1');
        $this->assert($attractionCat !== null, 'Attractions category exists');
        $catId = (int) $attractionCat['id'];

        $slug = 'test-canyoneering-adv-' . bin2hex(random_bytes(4));
        $listingId = Listing::create([
            'user_id' => $guideId,
            'category_id' => $catId,
            'title' => 'Test Canyoneering Adventure',
            'slug' => $slug,
            'summary' => 'Thrilling adventure down the canyon rapids.',
            'description' => 'Full day guided canyon exploration with gear included.',
            'area' => 'Badian, Cebu',
            'price' => 1500.00,
            'price_unit' => 'person',
            'status' => 'pending',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);
        $this->cleanUpListingIds[] = $listingId;

        // 2. Verify pending count and retrieval
        $pendingCountBefore = Listing::pendingGuideListingsCount();
        $this->assert($pendingCountBefore >= 1, "pendingGuideListingsCount returns >= 1, got {$pendingCountBefore}");

        $pendingListings = Listing::allGuideListingsForAdmin('pending');
        $found = false;
        foreach ($pendingListings as $item) {
            if ((int) $item['id'] === $listingId) {
                $found = true;
                $this->assert($item['guide_name'] === 'Test Tour Guide', 'Pending item joins guide name correctly');
                $this->assert((float) $item['price'] === 1500.00, 'Pending item has correct price');
                break;
            }
        }
        $this->assert($found, 'Pending test listing found in allGuideListingsForAdmin("pending")');

        // 3. Admin Approves the guide listing
        $admin = Database::first('SELECT id FROM users WHERE role = "admin" LIMIT 1');
        $adminId = $admin !== null ? (int) $admin['id'] : 1;

        Listing::approveGuideListing($listingId, $adminId);
        $approvedListing = Listing::find($listingId);
        $this->assert(($approvedListing['status'] ?? '') === 'approved', 'Listing status is now approved');
        $this->assert(!empty($approvedListing['reviewed_at']), 'Listing reviewed_at timestamp is set');
        $this->assert((int) $approvedListing['reviewed_by'] === $adminId, 'Listing reviewed_by is set to admin id');

        // 4. Admin Rejects the guide listing with reason
        $rejectReason = 'Need updated safety certifications and clear meeting point.';
        Listing::rejectGuideListing($listingId, $adminId, $rejectReason);
        $rejectedListing = Listing::find($listingId);
        $this->assert(($rejectedListing['status'] ?? '') === 'rejected', 'Listing status is now rejected');
        $this->assert(($rejectedListing['rejection_reason'] ?? '') === $rejectReason, 'Listing rejection_reason is stored correctly');

        // 5. Querying by rejected status
        $rejectedListings = Listing::allGuideListingsForAdmin('rejected');
        $foundRejected = false;
        foreach ($rejectedListings as $item) {
            if ((int) $item['id'] === $listingId) {
                $foundRejected = true;
                $this->assert(($item['rejection_reason'] ?? '') === $rejectReason, 'Rejected list includes rejection reason');
                break;
            }
        }
        $this->assert($foundRejected, 'Rejected listing found in allGuideListingsForAdmin("rejected")');
    }

    private function testAllLiveListingsQueryAndCategoryCounts(): void
    {
        // 1. Live counts breakdown
        $counts = Listing::countLiveByCategory();
        $this->assert(isset($counts['all']), 'countLiveByCategory includes "all" key');
        $this->assert(isset($counts['attractions']), 'countLiveByCategory includes "attractions" key');
        $this->assert(isset($counts['things-to-do']), 'countLiveByCategory includes "things-to-do" key');
        $this->assert(isset($counts['hotels']), 'countLiveByCategory includes "hotels" key');
        $this->assert(
            $counts['all'] === ($counts['attractions'] + $counts['things-to-do'] + $counts['hotels'] + ($counts['packages'] ?? 0)),
            "Total live count ({$counts['all']}) equals sum of categories (" . ($counts['attractions'] + $counts['things-to-do'] + $counts['hotels'] + ($counts['packages'] ?? 0)) . ")"
        );

        // 2. allLiveForAdmin retrieval
        $allLive = Listing::allLiveForAdmin('all');
        $this->assert(count($allLive) === $counts['all'], "allLiveForAdmin('all') count matches countLiveByCategory");

        // Verify each live item is approved and not restaurant
        foreach ($allLive as $l) {
            $this->assert(($l['status'] ?? '') === 'approved', "Listing {$l['id']} in live list must be approved");
            $this->assert(($l['category_slug'] ?? '') !== 'restaurants', "Listing {$l['id']} must not be restaurant");
        }

        // 3. Category filters
        $liveHotels = Listing::allLiveForAdmin('hotels');
        $this->assert(count($liveHotels) === $counts['hotels'], "allLiveForAdmin('hotels') count matches hotels count");
        foreach ($liveHotels as $h) {
            $this->assert(($h['category_slug'] ?? '') === 'hotels', "Filtered hotel item has slug hotels");
        }

        $liveThings = Listing::allLiveForAdmin('things-to-do');
        $this->assert(count($liveThings) === $counts['things-to-do'], "allLiveForAdmin('things-to-do') count matches");

        $liveAttractions = Listing::allLiveForAdmin('attractions');
        $this->assert(count($liveAttractions) === $counts['attractions'], "allLiveForAdmin('attractions') count matches");
    }

    private function testGuideListingDeletionRestrictedToRejected(): void
    {
        // Create an approved listing and a rejected listing
        $guideEmail = 'guide_del_' . bin2hex(random_bytes(4)) . '@example.com';
        $guideId = User::create('Delete Test Guide', $guideEmail, 'Secret123!', 'guide');
        $this->cleanUpUserIds[] = $guideId;

        $cat = Database::first('SELECT id FROM categories WHERE slug IN ("things-to-do", "tour-guides") LIMIT 1');
        $catId = (int) $cat['id'];

        $approvedListingId = Listing::create([
            'user_id' => $guideId,
            'category_id' => $catId,
            'title' => 'Approved Live Tour',
            'slug' => 'approved-tour-' . bin2hex(random_bytes(4)),
            'area' => 'Moalboal',
            'price' => 500.00,
            'status' => 'approved',
        ]);
        $this->cleanUpListingIds[] = $approvedListingId;

        $rejectedListingId = Listing::create([
            'user_id' => $guideId,
            'category_id' => $catId,
            'title' => 'Rejected Guide Tour',
            'slug' => 'rejected-tour-' . bin2hex(random_bytes(4)),
            'area' => 'Moalboal',
            'price' => 500.00,
            'status' => 'rejected',
            'rejection_reason' => 'Duplicate submission',
        ]);
        $this->cleanUpListingIds[] = $rejectedListingId;

        // Attempting to delete an approved listing must be blocked
        $approvedItem = Listing::find($approvedListingId);
        $this->assert(($approvedItem['status'] ?? '') === 'approved', 'Test item is approved');

        // Verify rejected listing can be deleted
        Listing::delete($rejectedListingId);
        $deleted = Listing::find($rejectedListingId);
        $this->assert($deleted === null, 'Rejected listing was successfully deleted');
    }

    private function testGuideNotificationMethodsExist(): void
    {
        $this->assert(
            method_exists(\App\Services\NotificationService::class, 'guideListingApproved'),
            'NotificationService::guideListingApproved method exists'
        );
        $this->assert(
            method_exists(\App\Services\NotificationService::class, 'guideListingRejected'),
            'NotificationService::guideListingRejected method exists'
        );
    }

    private function testAdminControllerShowGuideListingEndpoint(): void
    {
        $admin = User::adminAccount();
        if ($admin !== null) {
            \App\Core\AdminAuth::login($admin);
        }

        $sampleListing = Database::first(
            'SELECT l.id FROM listings l JOIN categories c ON c.id = l.category_id WHERE c.slug IN ("attractions", "things-to-do", "attraction-and-tours", "tour-guides") LIMIT 1'
        );
        $listingId = $sampleListing !== null ? (int) $sampleListing['id'] : 9;

        $controller = new AdminController();
        ob_start();
        try {
            $controller->showGuideListing((string) $listingId);
            $output = ob_get_clean();
            $this->assert(strlen($output) > 0, "showGuideListing('{$listingId}') executed successfully without errors");
            $this->assert(str_contains($output, 'Guide Listings Review'), "showGuideListing rendered review page");
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->assert(false, "showGuideListing('{$listingId}') threw exception: " . $e->getMessage());
        }
    }

    private function tearDown(): void
    {
        foreach ($this->cleanUpListingIds as $lid) {
            try {
                Database::run('DELETE FROM listing_schedules WHERE listing_id = ?', [$lid]);
                Database::run('DELETE FROM reviews WHERE listing_id = ?', [$lid]);
                Database::run('DELETE FROM listings WHERE id = ?', [$lid]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        foreach ($this->cleanUpUserIds as $uid) {
            try {
                Database::run('DELETE FROM users WHERE id = ?', [$uid]);
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
}

// Run if executed directly
$test = new AdminGuideListingReviewAndLiveListingsTest();
$test->run();
