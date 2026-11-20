<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Listing;
use App\Models\Category;
use App\Controllers\AdminController;

final class AvailableAddonsAndServicesLabelConsistencyTest
{
    private int $passCount = 0;
    private int $failCount = 0;
    private array $cleanUpListingIds = [];
    private array $cleanUpUserIds = [];

    public function run(): void
    {
        echo "\n====================================================================\n";
        echo " GuideMate Available Add-ons & Services Label Consistency Test Suite\n";
        echo "====================================================================\n\n";

        try {
            $this->testTemplateFilesConsistency();
            $this->testLanguageFilesConsistency();
            $this->testThingsToDoAdminInspectionView();
            $this->testAttractionAdminInspectionView();
            $this->testPublicListingViewConsistency();
            $this->testNoUnintendedReferencesToOldLabel();
        } finally {
            $this->tearDown();
        }

        echo "\n--------------------------------------------------------------------\n";
        echo "Results: {$this->passCount} passed, {$this->failCount} failed\n";
        echo "--------------------------------------------------------------------\n\n";

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

    private function testTemplateFilesConsistency(): void
    {
        echo "1. Checking template files consistency across user-facing and admin interfaces...\n";

        // 1. Admin guide listing inspection view
        $adminView = file_get_contents(BASE_DIR . '/app/Views/admin/guide-listing-show.php');
        $this->assert(
            str_contains($adminView, "Available Add-ons & Services"),
            'Admin inspection view (guide-listing-show.php) contains "Available Add-ons & Services"'
        );
        $this->assert(
            !str_contains($adminView, 'Not Included / House Rules'),
            'Admin inspection view does NOT contain "Not Included / House Rules"'
        );
        $this->assert(
            !str_contains($adminView, '✕ <?= e($ni) ?>'),
            'Admin inspection view does NOT use ✕ mark for add-on pills'
        );
        $this->assert(
            !str_contains($adminView, 'color:#fca5a5;padding:0.35rem 0.75rem'),
            'Admin inspection view does NOT use red text highlight (#fca5a5) for add-on pills'
        );

        // 2. Guide listing edit form
        $formView = file_get_contents(BASE_DIR . '/app/Views/guide/form.php');
        $this->assert(
            str_contains($formView, "Available Add-ons & Services (one item per line)"),
            'Guide form view (form.php) contains "Available Add-ons & Services (one item per line)"'
        );

        // 3. Guide listing creation modal
        $listingsView = file_get_contents(BASE_DIR . '/app/Views/guide/listings.php');
        $this->assert(
            str_contains($listingsView, "Available Add-ons & Services (one item per line)"),
            'Guide listings modal (listings.php) contains "Available Add-ons & Services (one item per line)"'
        );

        // 4. Public listing view
        $publicView = file_get_contents(BASE_DIR . '/app/Views/listings/show.php');
        $this->assert(
            str_contains($publicView, 'Available Add-ons & Services'),
            'Public listing view (listings/show.php) contains "Available Add-ons & Services"'
        );
        $this->assert(
            !str_contains($publicView, 'unless it is listed under “Not included”'),
            'Public listing view hint updated to not say "unless it is listed under Not included"'
        );

        // 5. App listing screen
        $appSlugView = file_get_contents(BASE_DIR . '/app/listing/[slug].tsx');
        $this->assert(
            str_contains($appSlugView, "'Available Add-ons & Services'"),
            'App listing screen (listing/[slug].tsx) contains "Available Add-ons & Services"'
        );
    }

    private function testLanguageFilesConsistency(): void
    {
        echo "\n2. Checking translation dictionaries in app/lang/...\n";

        $en = require BASE_DIR . '/app/lang/en.php';
        $this->assert(
            isset($en['g_not_included_title']) && $en['g_not_included_title'] === 'Available Add-ons & Services',
            'app/lang/en.php defines g_not_included_title as "Available Add-ons & Services"'
        );
        $this->assert(
            isset($en['g_not_included_label']) && str_contains($en['g_not_included_label'], 'Available Add-ons & Services'),
            'app/lang/en.php defines g_not_included_label with "Available Add-ons & Services"'
        );

        $tl = require BASE_DIR . '/app/lang/tl.php';
        $this->assert(
            isset($tl['g_not_included_title']) && $tl['g_not_included_title'] === 'Available Add-ons & Services',
            'app/lang/tl.php defines g_not_included_title'
        );
    }

    private function testThingsToDoAdminInspectionView(): void
    {
        echo "\n3. Testing admin inspection view HTML output for a 'Things to Do' listing...\n";

        $suffix = bin2hex(random_bytes(3));
        $guideId = Database::insert(
            "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('ATV Guide $suffix', 'atv_$suffix@example.com', 'hash', 'guide', 1, NOW())"
        );
        $this->cleanUpUserIds[] = $guideId;

        $catThings = (int) (Database::first("SELECT id FROM categories WHERE slug = 'things-to-do' LIMIT 1")['id'] ?? 1);

        $included = "4x4 ATV unit and fuel\nLead trail guide and safety marshal\nDOT-certified helmet";
        $addons = "Tandem passenger seat upgrade (₱450)\nAction camera helmet mount rental (₱350)\nRoundtrip shuttle pickup (₱300)";

        $listingId = Database::insert("
            INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, duration, capacity, included, not_included, status, created_at)
            VALUES (?, ?, '4x4 ATV Adventure Trail $suffix', '4x4-atv-adventure-$suffix', 'Thrilling off-road trail', 'Full description of ATV ride', 'Cebu', 2500.00, 'per person', '2 hours', 10, ?, ?, 'pending', NOW())
        ", [$guideId, $catThings, $included, $addons]);
        $this->cleanUpListingIds[] = $listingId;

        $controller = new AdminController();
        ob_start();
        try {
            $controller->showGuideListing((string) $listingId);
            $html = ob_get_clean();

            $this->assert(
                str_contains($html, "Available Add-ons &amp; Services") || str_contains($html, "Available Add-ons & Services"),
                'Admin inspection view displays "Available Add-ons & Services" section title for Things to Do listing'
            );
            $this->assert(
                !str_contains($html, "Not Included / House Rules"),
                'Admin inspection view does NOT contain "Not Included / House Rules" for Things to Do listing'
            );
            $this->assert(
                str_contains($html, "Tandem passenger seat upgrade (₱450)"),
                'Admin inspection view displays add-on items correctly'
            );
            $this->assert(
                str_contains($html, "+ Tandem passenger seat upgrade (₱450)"),
                'Admin inspection view displays add-on items with + symbol instead of ✕'
            );
            $this->assert(
                !str_contains($html, "✕ Tandem passenger seat upgrade"),
                'Admin inspection view does not render ✕ for add-on items'
            );
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->assert(false, "showGuideListing for Things to Do threw exception: " . $e->getMessage());
        }
    }

    private function testAttractionAdminInspectionView(): void
    {
        echo "\n4. Testing admin inspection view HTML output for an 'Attraction' listing...\n";

        $suffix = bin2hex(random_bytes(3));
        $guideId = Database::insert(
            "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Attraction Guide $suffix', 'attr_$suffix@example.com', 'hash', 'guide', 1, NOW())"
        );
        $this->cleanUpUserIds[] = $guideId;

        $catAttractions = (int) (Database::first("SELECT id FROM categories WHERE slug = 'attractions' LIMIT 1")['id'] ?? 2);

        $included = "General admission entrance ticket to Tops Lookout\nAll-day access to viewing deck\nRestroom access";
        $addons = "Roundtrip shuttle transfer from IT Park (₱200)\nSunset viewing table reservation with drink (₱350)\nDigital souvenir photo print package (₱150)";

        $listingId = Database::insert("
            INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, duration, capacity, included, not_included, status, created_at)
            VALUES (?, ?, 'Tops Lookout Cebu Experience $suffix', 'tops-lookout-cebu-$suffix', 'Panoramic 360-degree views', 'Full attraction details', 'Busay, Cebu City', 150.00, 'per person', 'Flexible', 50, ?, ?, 'pending', NOW())
        ", [$guideId, $catAttractions, $included, $addons]);
        $this->cleanUpListingIds[] = $listingId;

        $controller = new AdminController();
        ob_start();
        try {
            $controller->showGuideListing((string) $listingId);
            $html = ob_get_clean();

            $this->assert(
                str_contains($html, "Available Add-ons &amp; Services") || str_contains($html, "Available Add-ons & Services"),
                'Admin inspection view displays "Available Add-ons & Services" section title for Attraction listing'
            );
            $this->assert(
                !str_contains($html, "Not Included / House Rules"),
                'Admin inspection view does NOT contain "Not Included / House Rules" for Attraction listing'
            );
            $this->assert(
                str_contains($html, "Roundtrip shuttle transfer from IT Park (₱200)"),
                'Admin inspection view displays attraction add-on items correctly'
            );
            $this->assert(
                str_contains($html, "+ Roundtrip shuttle transfer from IT Park (₱200)"),
                'Admin inspection view displays attraction add-on items with + symbol instead of ✕'
            );
            $this->assert(
                !str_contains($html, "✕ Roundtrip shuttle transfer"),
                'Admin inspection view does not render ✕ for attraction add-on items'
            );
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->assert(false, "showGuideListing for Attraction threw exception: " . $e->getMessage());
        }
    }

    private function testPublicListingViewConsistency(): void
    {
        echo "\n5. Testing public listing view rendered HTML...\n";

        $suffix = bin2hex(random_bytes(3));
        $guideId = Database::insert(
            "INSERT INTO users (name, email, password, role, is_active, created_at) VALUES ('Public Guide $suffix', 'pubguide_$suffix@example.com', 'hash', 'guide', 1, NOW())"
        );
        $this->cleanUpUserIds[] = $guideId;

        $catThings = (int) (Database::first("SELECT id FROM categories WHERE slug = 'things-to-do' LIMIT 1")['id'] ?? 1);

        $listingId = Database::insert("
            INSERT INTO listings (user_id, category_id, title, slug, summary, description, area, price, price_unit, duration, capacity, included, not_included, status, created_at)
            VALUES (?, ?, 'Public Test Experience $suffix', 'pub-test-exp-$suffix', 'Summary', 'Description', 'Cebu', 1000.00, 'per person', '3 hours', 10, 'Entrance fee', 'GoPro rental (₱500)', 'approved', NOW())
        ", [$guideId, $catThings]);
        $this->cleanUpListingIds[] = $listingId;

        $listing = Listing::find($listingId);
        $host = User::find($guideId);
        $isHotel = false;
        $isRental = false;
        $isBookable = true;
        $lat = null;
        $lng = null;
        $summary = ['count' => 0, 'avg' => 0];
        $gallery = [];
        $reviews = [];
        $canReview = false;

        ob_start();
        include BASE_DIR . '/app/Views/listings/show.php';
        $html = ob_get_clean();

        $this->assert(
            str_contains($html, 'Available Add-ons &amp; Services') || str_contains($html, 'Available Add-ons & Services'),
            'Public listing page renders "Available Add-ons & Services"'
        );
        $this->assert(
            !str_contains($html, 'Not Included / House Rules'),
            'Public listing page does NOT contain "Not Included / House Rules"'
        );
    }

    private function testNoUnintendedReferencesToOldLabel(): void
    {
        echo "\n6. Verifying no unintended references to 'Not Included / House Rules' remain in any file...\n";

        $target = 'Not Included' . ' / ' . 'House Rules';
        exec('git grep -i -F "' . $target . '" -- ":(exclude)tests/*" 2>nul', $outputLines, $returnCode);
        $this->assert(
            $returnCode === 1 && empty($outputLines),
            'Zero occurrences of "Not Included / House Rules" remain in repository'
        );
    }

    private function tearDown(): void
    {
        foreach ($this->cleanUpListingIds as $lid) {
            try {
                Database::run('DELETE FROM listing_schedules WHERE listing_id = ?', [$lid]);
                Database::run('DELETE FROM reviews WHERE listing_id = ?', [$lid]);
                Database::run('DELETE FROM listings WHERE id = ?', [$lid]);
            } catch (\Throwable $e) {}
        }
        foreach ($this->cleanUpUserIds as $uid) {
            try {
                Database::run('DELETE FROM users WHERE id = ?', [$uid]);
            } catch (\Throwable $e) {}
        }
    }
}

(new AvailableAddonsAndServicesLabelConsistencyTest())->run();
