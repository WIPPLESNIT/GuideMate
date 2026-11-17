<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Models\Booking;
use App\Models\Listing;
use App\Models\Review;

/**
 * Test Guide Public Profile and Profile Editing Improvements (Web Version)
 */
final class GuideProfileAndPublicViewTest
{
    private array $results = [];

    public function run(): void
    {
        $this->testDatabaseSchemaAndSeed();
        $this->testUserModelPersistence();
        $this->testGuideRatingStatsAndReviews();
        $this->testBreadcrumbNavigationAndActiveNav();
        $this->testGuideAccountProfileRemovalOfPublicButton();
        $this->testCompleteProfileEditingFormFields();
        $this->testRatingManagementSystemGeneratedOnly();
        $this->testLiveControllerExecution();
        $this->printSummary();
    }

    private function assert(bool $condition, string $description): void
    {
        if ($condition) {
            $this->results[] = ['status' => 'PASS', 'desc' => $description];
            echo "  [PASS] {$description}\n";
        } else {
            $this->results[] = ['status' => 'FAIL', 'desc' => $description];
            echo "  [FAIL] {$description}\n";
        }
    }

    private function testDatabaseSchemaAndSeed(): void
    {
        echo "\n--- 1. Testing Database Schema & Seed ---\n";
        $columns = Database::all("SHOW COLUMNS FROM users LIKE 'experience'");
        $this->assert(count($columns) === 1, "users table has 'experience' column");

        $columns = Database::all("SHOW COLUMNS FROM users LIKE 'languages'");
        $this->assert(count($columns) === 1, "users table has 'languages' column");

        $columns = Database::all("SHOW COLUMNS FROM users LIKE 'specialties'");
        $this->assert(count($columns) === 1, "users table has 'specialties' column");

        $guide = User::find(3);
        $this->assert($guide !== null, "Marc Repuesto (user 3) exists");
        if ($guide) {
            $this->assert(!empty($guide['experience']), "Marc Repuesto has experience: " . ($guide['experience'] ?? ''));
            $this->assert(!empty($guide['languages']), "Marc Repuesto has languages: " . ($guide['languages'] ?? ''));
            $this->assert(!empty($guide['specialties']), "Marc Repuesto has specialties: " . ($guide['specialties'] ?? ''));
        }
    }

    private function testUserModelPersistence(): void
    {
        echo "\n--- 2. Testing UserModel updateProfile Persistence ---\n";
        $guide = User::find(3);

        // Update with modified personal and professional values
        User::updateProfile(3, [
            'name' => 'Marc Repuesto',
            'phone' => '09280056394',
            'location' => 'Moalboal, Cebu',
            'experience' => '5+ Years',
            'languages' => 'English, Cebuano, Filipino',
            'specialties' => 'Snorkeling, Island Tours, Local Dining, Adventure Tours, Canyoneering',
            'bio' => 'Local guide specializing in snorkeling tours and dining experiences around Moalboal, Cebu.',
        ]);

        $updated = User::find(3);
        $this->assert($updated['experience'] === '5+ Years', "experience updated to 5+ Years");
        $this->assert(str_contains($updated['languages'], 'Filipino'), "languages updated and persisted");
        $this->assert(str_contains($updated['specialties'], 'Canyoneering'), "specialties updated and persisted");

        // Restore original reference values
        User::updateProfile(3, [
            'name' => 'Marc Repuesto',
            'phone' => '09280056394',
            'location' => 'Moalboal, Cebu',
            'experience' => '3+ Years',
            'languages' => 'English, Cebuano',
            'specialties' => 'Snorkeling, Island Tours, Local Dining, Adventure Tours',
            'bio' => 'Local guide specializing in snorkeling tours and dining experiences around Moalboal, Cebu.',
        ]);
        $restored = User::find(3);
        $this->assert($restored['experience'] === '3+ Years', "reference experience restored to 3+ Years");
    }

    private function testGuideRatingStatsAndReviews(): void
    {
        echo "\n--- 3. Testing Rating Management & Traveler Reviews ---\n";
        $stats = Booking::guideRatingStats(3);
        $this->assert(is_array($stats), "guideRatingStats returned array");
        $this->assert(array_key_exists('avg_rating', $stats), "stats contains calculated avg_rating");
        $this->assert(array_key_exists('review_count', $stats), "stats contains review_count");

        $reviews = Review::forOwner(3);
        $this->assert(is_array($reviews), "Review::forOwner returns traveler reviews");
    }

    private function testBreadcrumbNavigationAndActiveNav(): void
    {
        echo "\n--- 4. Testing Breadcrumbs & Active Nav Item ---\n";
        $viewContent = file_get_contents(__DIR__ . '/../app/Views/listings/guide-profile.php');

        // Check breadcrumb elements
        $this->assert(str_contains($viewContent, 'class="breadcrumb"'), "guide-profile.php contains breadcrumb navigation");
        $this->assert(str_contains($viewContent, "url('/')"), "breadcrumb has clickable Home link redirecting to homepage");
        $this->assert(str_contains($viewContent, "url('/tour-guides')"), "breadcrumb has clickable Tour Guides link returning to tour guides");
        $this->assert(str_contains($viewContent, '$guide[\'name\']'), "breadcrumb displays guide name as current page");

        // Check non-clickable guide name in breadcrumb
        $this->assert(str_contains($viewContent, 'aria-current="page"'), "guide name in breadcrumb has aria-current='page' and is not a link");

        // Check removal of old button
        $this->assert(!str_contains($viewContent, '← Back to Tour Guides'), "'← Back to Tour Guides' button was removed from guide profile");

        // Check breadcrumb background is transparent (no dark page-head background)
        $this->assert(!str_contains($viewContent, 'class="page-head"'), "guide-profile.php does not use dark 'page-head' background for breadcrumbs");
        $this->assert(str_contains($viewContent, 'background:transparent'), "guide profile breadcrumb container has transparent background");

        // Check active navigation helper
        $_SERVER['REQUEST_URI'] = '/guides/3';
        $activeClass = active_when('/tour-guides');
        $this->assert($activeClass === 'active', "active_when('/tour-guides') returns 'active' when viewing /guides/{id}");

        $_SERVER['REQUEST_URI'] = '/guide/3';
        $activeClass2 = active_when('/tour-guides');
        $this->assert($activeClass2 === 'active', "active_when('/tour-guides') returns 'active' when viewing /guide/{id}");
    }

    private function testGuideAccountProfileRemovalOfPublicButton(): void
    {
        echo "\n--- 5. Testing Removal of Public Profile Button from Guide Account ---\n";
        $showFile = file_get_contents(__DIR__ . '/../app/Views/profile/show.php');
        $this->assert(!str_contains($showFile, 'View Public Profile'), "show.php does not have 'View Public Profile' button");

        $editFile = file_get_contents(__DIR__ . '/../app/Views/profile/edit.php');
        $this->assert(!str_contains($editFile, 'View Public Profile'), "edit.php does not have 'View Public Profile' button in profile section");
    }

    private function testCompleteProfileEditingFormFields(): void
    {
        echo "\n--- 6. Testing Complete Profile Editing Form Fields ---\n";
        $showFile = file_get_contents(__DIR__ . '/../app/Views/profile/show.php');
        $editFile = file_get_contents(__DIR__ . '/../app/Views/profile/edit.php');

        foreach (['show.php' => $showFile, 'edit.php' => $editFile] as $filename => $content) {
            // Personal Information
            $this->assert(str_contains($content, 'Personal Information'), "{$filename} includes 'Personal Information' section");
            $this->assert(str_contains($content, 'Profile Photo'), "{$filename} includes 'Profile Photo' field");
            $this->assert(str_contains($content, 'Full Name'), "{$filename} includes 'Full Name' field");
            $this->assert(str_contains($content, 'Email Address'), "{$filename} includes 'Email Address' field");
            $this->assert(str_contains($content, 'Phone Number'), "{$filename} includes 'Phone Number' field");
            $this->assert(str_contains($content, 'Location'), "{$filename} includes 'Location' field");

            // Professional Information
            $this->assert(str_contains($content, 'Professional Information'), "{$filename} includes 'Professional Information' section");
            $this->assert(str_contains($content, 'Years of Experience'), "{$filename} includes 'Years of Experience' field");
            $this->assert(str_contains($content, 'Languages Spoken'), "{$filename} includes 'Languages Spoken' field");
            $this->assert(str_contains($content, 'Areas of Specialization / Expertise'), "{$filename} includes 'Areas of Specialization / Expertise' field");
            $this->assert(str_contains($content, 'About You / Professional Biography'), "{$filename} includes 'About You / Professional Biography' field");

            // Form retrieves existing data
            $this->assert(str_contains($content, "old('experience', \$user['experience'] ?? '')"), "{$filename} retrieves existing experience");
            $this->assert(str_contains($content, "old('languages', \$user['languages'] ?? '')"), "{$filename} retrieves existing languages");
            $this->assert(str_contains($content, "old('specialties', \$user['specialties'] ?? '')"), "{$filename} retrieves existing specialties");
            $this->assert(str_contains($content, "old('bio', \$user['bio'] ?? '')"), "{$filename} retrieves existing bio");
        }
    }

    private function testRatingManagementSystemGeneratedOnly(): void
    {
        echo "\n--- 7. Testing Rating is System-Generated Only ---\n";
        $showFile = file_get_contents(__DIR__ . '/../app/Views/profile/show.php');
        $editFile = file_get_contents(__DIR__ . '/../app/Views/profile/edit.php');

        // Form does NOT contain input name="rating"
        $this->assert(!str_contains($showFile, 'name="rating"'), "show.php modal form does NOT contain a rating input");
        $this->assert(!str_contains($editFile, 'name="rating"'), "edit.php modal form does NOT contain a rating input");

        // ProfileController::update does not accept or update rating
        $ctrlFile = file_get_contents(__DIR__ . '/../app/Controllers/ProfileController.php');
        $this->assert(!str_contains($ctrlFile, "input('rating'"), "ProfileController update method does NOT accept manual rating inputs");
    }

    private function testLiveControllerExecution(): void
    {
        echo "\n--- 8. Testing Live Controller Executions & Breadcrumb Rendering ---\n";

        // Web guideProfile live execution
        $_SERVER['REQUEST_URI'] = '/guides/3';
        ob_start();
        try {
            (new \App\Controllers\ListingController())->guideProfile('3');
            $html = ob_get_clean();

            $this->assert(str_contains($html, 'Marc Repuesto'), "rendered guideProfile contains Marc Repuesto");
            $this->assert(str_contains($html, '<nav class="breadcrumb"'), "rendered guideProfile contains breadcrumb nav");
            $this->assert(str_contains($html, 'Home</a>'), "rendered guideProfile contains Home breadcrumb");
            $this->assert(str_contains($html, 'Tour Guides</a>'), "rendered guideProfile contains Tour Guides breadcrumb");
            $this->assert(str_contains($html, '<span style="color:var(--ink-800);font-weight:600;" aria-current="page">Marc Repuesto</span>'), "rendered guideProfile has non-clickable guide name in breadcrumb");
            $this->assert(!str_contains($html, '← Back to Tour Guides'), "rendered guideProfile has no 'Back to Tour Guides' button");
            $this->assert(str_contains($html, 'About the Guide'), "rendered guideProfile contains 'About the Guide'");
            $this->assert(str_contains($html, 'Experience'), "rendered guideProfile contains Experience");
            $this->assert(str_contains($html, 'Languages'), "rendered guideProfile contains Languages");
            $this->assert(str_contains($html, 'Specialties'), "rendered guideProfile contains Specialties");
            $this->assert(str_contains($html, 'Available Listings'), "rendered guideProfile contains Available Listings");
            $this->assert(str_contains($html, 'Traveler Reviews'), "rendered guideProfile contains Traveler Reviews");
        } catch (\Throwable $e) {
            ob_end_clean();
            $this->assert(false, "ListingController::guideProfile threw: " . $e->getMessage());
        }
    }

    private function printSummary(): void
    {
        echo "\n============================================\n";
        $passCount = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failCount = count(array_filter($this->results, fn($r) => $r['status'] === 'FAIL'));
        echo "TOTAL TESTS: " . count($this->results) . " | PASSED: {$passCount} | FAILED: {$failCount}\n";
        echo "============================================\n";

        if ($failCount > 0) {
            exit(1);
        }
    }
}

$test = new GuideProfileAndPublicViewTest();
$test->run();
