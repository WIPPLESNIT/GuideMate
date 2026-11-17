<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\User;
use App\Controllers\ListingController;
use App\Controllers\ApiController;

/**
 * Verification Test for Guide Profile Component (Web & App)
 * Testing Database-Driven Fields and "Not Provided" Independent Fallbacks
 */
final class GuideProfileDatabaseDrivenFieldsTest
{
    private array $results = [];

    public function run(): void
    {
        echo "\n=================================================================\n";
        echo "   GUIDE PROFILE DATABASE-DRIVEN FIELDS & FALLBACKS TEST\n";
        echo "=================================================================\n\n";

        $this->testHardcodedFallbacksRemovedFromCodebases();
        $this->testCompleteDatabaseRecordWebRendering();
        $this->testPartiallyCompletedDatabaseRecordWebRendering();
        $this->testEmptyRecordWebRendering();
        $this->testNullAndMissingFieldsSafety();
        $this->testApiGuideProfilePayloadConsistency();
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

    private function renderWebView(array $guide, array $stats = [], array $listings = [], array $reviews = []): string
    {
        extract([
            'guide' => $guide,
            'stats' => $stats,
            'listings' => $listings,
            'reviews' => $reviews,
        ]);

        ob_start();
        include __DIR__ . '/../app/Views/listings/guide-profile.php';
        return (string) ob_get_clean();
    }

    private function testHardcodedFallbacksRemovedFromCodebases(): void
    {
        echo "--- 1. Testing Hardcoded Fallbacks Removed From Codebases ---\n";
        $webView = file_get_contents(__DIR__ . '/../app/Views/listings/guide-profile.php');
        $appView = file_get_contents(__DIR__ . '/../app/guide/[id].tsx');

        // Web view hardcoded fallbacks check
        $this->assert(!str_contains($webView, "'Experienced'"), "Web: 'Experienced' fallback removed");
        $this->assert(!str_contains($webView, "': Experienced'"), "Web: no ': Experienced' fallback");
        $this->assert(!str_contains($webView, "'English'"), "Web: 'English' fallback removed");
        $this->assert(!str_contains($webView, "'⭐ New Guide'"), "Web: '⭐ New Guide' fallback replaced with Not Provided");
        $this->assert(!str_contains($webView, "<em>No bio provided yet.</em>"), "Web: '<em>No bio provided yet.</em>' replaced with Not Provided");
        $this->assert(!str_contains($webView, "No specialties specified yet."), "Web: 'No specialties specified yet.' replaced with Not Provided");

        // App view hardcoded fallbacks check
        $this->assert(!str_contains($appView, "'3+ Years'"), "App: '3+ Years' hardcoded fallback removed");
        $this->assert(!str_contains($appView, "'English, Cebuano'"), "App: 'English, Cebuano' hardcoded fallback removed");
        $this->assert(!str_contains($appView, "'Local guide in Cebu ready to show you the best sights and hidden gems.'"), "App: hardcoded bio fallback removed");
        $this->assert(!str_contains($appView, "'⭐ New Guide'"), "App: '⭐ New Guide' fallback replaced with Not Provided");
        $this->assert(!str_contains($appView, "No specific specialties listed yet."), "App: 'No specific specialties listed yet.' replaced with Not Provided");

        // Both codebases use 'Not Provided'
        $this->assert(str_contains($webView, "'Not Provided'"), "Web: uses 'Not Provided' as standard fallback");
        $this->assert(str_contains($appView, "'Not Provided'"), "App: uses 'Not Provided' as standard fallback");
    }

    private function testCompleteDatabaseRecordWebRendering(): void
    {
        echo "\n--- 2. Testing Complete Database Record (Marc Repuesto - user 3) ---\n";
        $guide = User::find(3);
        $this->assert($guide !== null, "User 3 exists in database");

        if (!$guide) return;

        $guide['rating'] = 4.9;
        $guide['review_count'] = 12;

        $html = $this->renderWebView($guide);

        $this->assert(str_contains($html, 'Marc Repuesto'), "Complete record: shows correct Name");
        $this->assert(str_contains($html, 'Moalboal, Cebu'), "Complete record: shows correct Location");
        $this->assert(str_contains($html, '3+ Years'), "Complete record: shows correct Experience");
        $this->assert(str_contains($html, 'English, Cebuano'), "Complete record: shows correct Languages");
        $this->assert(str_contains($html, 'Snorkeling'), "Complete record: shows correct Specialty 'Snorkeling'");
        $this->assert(str_contains($html, 'Island Tours'), "Complete record: shows correct Specialty 'Island Tours'");
        $this->assert(str_contains($html, 'Local Dining'), "Complete record: shows correct Specialty 'Local Dining'");
        $this->assert(str_contains($html, 'Adventure Tours'), "Complete record: shows correct Specialty 'Adventure Tours'");
        $this->assert(str_contains($html, 'Local guide specializing in snorkeling'), "Complete record: shows correct Bio");
        $this->assert(str_contains($html, '⭐ 4.9 / 5.0 (12)'), "Complete record: shows correct calculated Rating");
        $this->assert(str_contains($html, 'June 2026'), "Complete record: shows correct Member Since date");
    }

    private function testPartiallyCompletedDatabaseRecordWebRendering(): void
    {
        echo "\n--- 3. Testing Partially Completed Database Record (John Lloyd Noya - user 2466) ---\n";
        $guide = User::find(2466);
        $this->assert($guide !== null, "User 2466 exists in database");

        if (!$guide) return;

        $html = $this->renderWebView($guide);

        // Stored value should show
        $this->assert(str_contains($html, 'John Lloyd Noya'), "Partial record: shows stored Name 'John Lloyd Noya'");

        // Missing fields must show 'Not Provided' independently
        $this->assert(str_contains($html, '📍 Not Provided'), "Partial record: shows '📍 Not Provided' for missing location");
        $this->assert(str_contains($html, 'Not Provided'), "Partial record: shows 'Not Provided' for missing bio");
        $this->assert(preg_match('/Experience<\/span>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Partial record: shows 'Not Provided' for missing Experience");
        $this->assert(preg_match('/Languages<\/span>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Partial record: shows 'Not Provided' for missing Languages");
        $this->assert(preg_match('/Rating<\/span>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Partial record: shows 'Not Provided' for 0 / missing Rating");
        $this->assert(preg_match('/Specialties<\/h3>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Partial record: shows 'Not Provided' for missing Specialties");

        // Stored created_at should still show its date even though other fields are missing
        $this->assert(str_contains($html, 'November 2026'), "Partial record: shows stored created_at 'November 2026' independently of missing fields");
    }

    private function testEmptyRecordWebRendering(): void
    {
        echo "\n--- 4. Testing Empty / Unavailable Record Web Rendering ---\n";
        $emptyGuide = [];

        $html = $this->renderWebView($emptyGuide);

        $this->assert(str_contains($html, 'Not Provided'), "Empty record: renders successfully with 'Not Provided'");
        $this->assert(preg_match('/<h1[^>]*>\s*Not Provided\s*<\/h1>/i', $html) === 1, "Empty record: Name displays 'Not Provided'");
        $this->assert(str_contains($html, '📍 Not Provided'), "Empty record: Location displays 'Not Provided'");
        $this->assert(preg_match('/Experience<\/span>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Empty record: Experience displays 'Not Provided'");
        $this->assert(preg_match('/Languages<\/span>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Empty record: Languages displays 'Not Provided'");
        $this->assert(preg_match('/Rating<\/span>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Empty record: Rating displays 'Not Provided'");
        $this->assert(preg_match('/Member Since<\/span>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Empty record: Member Since displays 'Not Provided'");
        $this->assert(preg_match('/Specialties<\/h3>\s*<div[^>]*>\s*Not Provided\s*<\/div>/i', $html) === 1, "Empty record: Specialties displays 'Not Provided'");
    }

    private function testNullAndMissingFieldsSafety(): void
    {
        echo "\n--- 5. Testing Null and Missing Fields Safety (No Notices/Warnings) ---\n";
        $nullGuide = [
            'id' => null,
            'name' => null,
            'avatar' => null,
            'bio' => null,
            'location' => null,
            'experience' => null,
            'languages' => null,
            'specialties' => null,
            'role' => null,
            'created_at' => null,
            'rating' => null,
            'review_count' => null,
            'guide_status' => null,
        ];

        // Track PHP errors
        $previousErrorHandler = set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        });

        try {
            $html = $this->renderWebView($nullGuide);
            $this->assert(true, "Renders null record without triggering PHP notices or warnings");
            $this->assert(str_contains($html, '📍 Not Provided'), "Null record: displays '📍 Not Provided'");
            $this->assert(preg_match('/<h1[^>]*>\s*Not Provided\s*<\/h1>/i', $html) === 1, "Null record: displays 'Not Provided' for name");
        } catch (\Throwable $e) {
            $this->assert(false, "Null record caused error: " . $e->getMessage());
        } finally {
            if ($previousErrorHandler !== null) {
                set_error_handler($previousErrorHandler);
            } else {
                restore_error_handler();
            }
        }
    }

    private function testApiGuideProfilePayloadConsistency(): void
    {
        echo "\n--- 6. Testing ApiController guideProfile Data Source Mapping ---\n";

        // Check ApiController guideProfile returns genuine database fields
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $api = new ApiController();

        ob_start();
        $api->guideProfile('3');
        $json = ob_get_clean();
        $data = json_decode($json, true);

        $this->assert(is_array($data) && isset($data['guide']), "API returns guide payload");
        $guide = $data['guide'] ?? [];

        $this->assert($guide['name'] === 'Marc Repuesto', "API maps genuine database name: " . ($guide['name'] ?? ''));
        $this->assert($guide['experience'] === '3+ Years', "API maps genuine database experience: " . ($guide['experience'] ?? ''));
        $this->assert(str_contains($guide['languages'], 'English'), "API maps genuine database languages: " . ($guide['languages'] ?? ''));
        $this->assert(str_contains($guide['specialties'], 'Snorkeling'), "API maps genuine database specialties: " . ($guide['specialties'] ?? ''));
        $this->assert($guide['location'] === 'Moalboal, Cebu', "API maps genuine database location: " . ($guide['location'] ?? ''));
        $this->assert(!empty($guide['bio']), "API maps genuine database bio");
    }

    private function printSummary(): void
    {
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['status'] === 'PASS'));
        $failed = $total - $passed;

        echo "\n============================================\n";
        echo "TOTAL TESTS: {$total} | PASSED: {$passed} | FAILED: {$failed}\n";
        echo "============================================\n";

        if ($failed > 0) {
            exit(1);
        }
    }
}

$test = new GuideProfileDatabaseDrivenFieldsTest();
$test->run();
