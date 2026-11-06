<?php
/**
 * Test Suite: Admin Travel Package Inspection / Details View
 *
 * Verifies that the administrator travel package inspection view:
 * 1. Correctly loads and processes package data matching Reference Images 1 to 5.
 * 2. Renders all key components (Live Tourist View hero, Host card, All-In-One Inclusions,
 *    Day-by-Day Itinerary timeline and tabs, Accommodation & Rental details, Policies & Requirements).
 * 3. Gracefully handles missing/optional fields (e.g. No Hotel, No Vehicle).
 * 4. Strips internal metadata/comment markers (e.g. <!-- PACKAGE_SERVICES: ... -->).
 * 5. Preserves standard listing review when viewing single tours ($isPackage = false).
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Controllers\AdminController;
use App\Controllers\GuideController;

class AdminPackageInspectionViewTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "====================================================\n";
        echo "Running Admin Package Inspection View Test Suite\n";
        echo "====================================================\n\n";

        $this->testBuildPackageInspectionDataComplete();
        $this->testBuildPackageInspectionDataGracefulExclusions();
        $this->testViewRenderingPackageLayout();
        $this->testViewRenderingNoRawComments();
        $this->testViewRenderingStandardListing();

        echo "\n----------------------------------------------------\n";
        echo "Test Results: {$this->passed} Passed, {$this->failed} Failed\n";
        echo "----------------------------------------------------\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$message}\n";
        } else {
            $this->failed++;
            echo "  [FAIL] {$message}\n";
        }
    }

    private function getReflectionMethod(string $class, string $method): ReflectionMethod
    {
        $ref = new ReflectionMethod($class, $method);
        $ref->setAccessible(true);
        return $ref;
    }

    public function testBuildPackageInspectionDataComplete(): void
    {
        echo "1. Testing buildPackageInspectionData() with complete package data:\n";

        $itinerary = json_encode([
            [
                'day' => 1,
                'title' => 'Day 1: Coastal Arrival & Moalboal Sunset Marine Sanctuary',
                'description' => 'Meet your guide host Marc, pick up your scooter, check in to your resort, and snorkel with turtles.',
                'schedule' => [
                    ['time' => '01:00 PM', 'activity' => 'Resort check-in & scooter handover briefing'],
                    ['time' => '03:00 PM', 'activity' => 'Guided snorkeling session at Panagsama marine sanctuary'],
                    ['time' => '05:30 PM', 'activity' => 'Golden hour sunset viewing and leisure dinner'],
                ],
                'highlights' => [
                    'Pick up your Yamaha NMAX scooter in Moalboal (or complimentary town greeting)',
                    'Check-in at Sea Breeze Oceanfront Resort and freshen up',
                    'Afternoon guided snorkeling: Swim alongside millions of sardines and resident sea turtles',
                    'Sunset seafood dinner recommendation along Panagsama beachfront strip',
                ],
            ],
            [
                'day' => 2,
                'title' => 'Day 2: Full Kawasan Canyoneering Expedition',
                'description' => 'Full day river canyoneering, cliff jumps, and boodle fight lunch.',
                'schedule' => [
                    ['time' => '08:00 AM', 'activity' => 'Breakfast and canyon departure'],
                    ['time' => '09:00 AM', 'activity' => 'Cliff jumping and river descent'],
                ],
                'highlights' => [
                    '7 cliff jumps (3m to 12m)',
                    'Boodle fight lunch',
                ],
            ],
        ]);

        $mockListing = [
            'id' => 1191,
            'title' => '3D2N South Cebu Expedition: Canyoneering, Moalboal Reef & NMAX Scooter',
            'user_id' => 5,
            'category_slug' => 'packages',
            'duration' => '3 Days / 2 Nights',
            'capacity' => 8,
            'price' => 14800.00,
            'price_unit' => 'package',
            'area' => 'Moalboal & Badian, South Cebu',
            'address' => 'Moalboal Beachfront Strip, Cebu',
            'cover_image' => 'uploads/packages/cebu.jpg',
            'status' => 'pending',
            'avg_rating' => 4.9,
            'review_count' => 38,
            'description' => '<!-- PACKAGE_OVERVIEW: {"category_label":"Adventure & Nature","badge":"Best Seller","assurance":"Guaranteed"} -->' . "\n"
                . "Immerse yourself in the thrill of South Cebu. This comprehensive 3-day travel bundle combines the world-famous Kawasan Falls Canyoneering.\n\n"
                . "Experience Tags: canyoneering, moalboal, snorkeling, nmax, cebu\n\n"
                . "--- Day-by-Day Itinerary ---\n" . $itinerary . "\n\n"
                . "--- Policies & Requirements ---\n"
                . "Free cancellation up to 48 hours before Day 1 start time. 50% refund within 24-48 hours.\n"
                . "- Valid Philippine Driver's License or International Driving Permit\n"
                . "- Minimum age of 18 for scooter driver\n"
                . "- Moderate physical fitness required for canyoneering\n",
            'included' => "Activities: Kawasan Falls Natural Park, Full Kawasan Canyoneering & Cliff Jumps\n"
                . "Accommodation: Sea Breeze Oceanfront Resort\n"
                . "Transportation: Yamaha NMAX 155cc\n"
                . "All Environmental & Local Tourism Fees (Includes river rescue gear and recovery hot lunch)\n"
                . '<!-- PACKAGE_SERVICES: {"activity_ids":[],"hotel_room_id":-1,"vehicle_id":-1} -->',
            'not_included' => "Airfare to and from Cebu (Mactan)\nPersonal souvenir shopping & alcoholic drinks\nOptional zip-line upgrade at Kawasan starting point (₱600)",
        ];

        $mockGuide = [
            'id' => 5,
            'name' => 'Marc Repuesto',
            'email' => 'marc.guide@guidemate.ph',
            'phone' => '+63 917 123 4567',
            'bio' => 'Native Moalboal local with 7+ years of professional outdoor guiding experience. Specializes in canyoneering safety, marine photography, and authentic Cebuano travel experiences.',
            'avatar' => 'uploads/avatars/marc.jpg',
        ];

        $controller = new AdminController();
        $buildMethod = $this->getReflectionMethod(AdminController::class, 'buildPackageInspectionData');
        $data = $buildMethod->invoke($controller, $mockListing, $mockGuide);

        $this->assert($data['duration_code'] === '3D2N', 'Duration code correctly parsed as 3D2N');
        $this->assert($data['duration_days'] === 3, 'Duration days correctly calculated as 3');
        $this->assert($data['duration_nights'] === 2, 'Duration nights correctly calculated as 2');
        $this->assert($data['category_label'] === 'Adventure & Nature', 'Category label correctly extracted as Adventure & Nature');
        $this->assert($data['badge'] === 'Best Seller', 'Badge correctly extracted as Best Seller');
        $this->assert($data['assurance'] === 'Guaranteed', 'Assurance correctly extracted as Guaranteed');
        $this->assert($data['group_size'] === 'Up to 8', 'Group size correctly formatted as Up to 8');
        $this->assert(count($data['tags']) === 5, '5 experience tags parsed correctly');
        $this->assert($data['host']['name'] === 'Marc Repuesto', 'Host name correctly populated');
        $this->assert(str_contains($data['host']['bio'], 'Native Moalboal local'), 'Host bio quote correctly populated');
        $this->assert(count($data['itinerary']) === 2, '2 itinerary days parsed from JSON');
        $this->assert(count($data['itinerary'][0]['schedule']) === 3, 'Day 1 has 3 timeline schedule items');
        $this->assert(count($data['itinerary'][0]['highlights']) === 4, 'Day 1 has 4 highlight items');
        $this->assert(count($data['exclusions']) === 3, '3 exclusions parsed correctly');
        $this->assert(!empty($data['cancellation_policy']), 'Cancellation policy extracted');
        $this->assert(count($data['requirements']) >= 3, 'At least 3 requirements parsed');
    }

    public function testBuildPackageInspectionDataGracefulExclusions(): void
    {
        echo "\n2. Testing graceful handling when Hotel and Vehicle are omitted:\n";

        $mockListing = [
            'id' => 1192,
            'title' => '1-Day Kawasan Adventure Tour',
            'user_id' => 5,
            'category_slug' => 'packages',
            'duration' => '1 Day',
            'capacity' => 12,
            'price' => 3500.00,
            'price_unit' => 'package',
            'area' => 'Badian, Cebu',
            'cover_image' => '',
            'status' => 'pending',
            'description' => "Day tour canyoneering.\n",
            'included' => "Activities: Kawasan Falls\n<!-- PACKAGE_SERVICES: {\"activity_ids\":[],\"hotel_room_id\":0,\"vehicle_id\":0} -->",
            'not_included' => '',
        ];

        $controller = new AdminController();
        $buildMethod = $this->getReflectionMethod(AdminController::class, 'buildPackageInspectionData');
        $data = $buildMethod->invoke($controller, $mockListing, null);

        $this->assert($data['duration_code'] === '1D', 'Duration code is 1D for single-day tour');
        $this->assert($data['duration_days'] === 1, 'Duration days is 1');
        $this->assert($data['duration_nights'] === 0, 'Duration nights is 0');
        $this->assert($data['hotel_included'] === false, 'Hotel correctly marked not included when hotel_room_id is 0');
        $this->assert($data['rental_included'] === false, 'Rental correctly marked not included when vehicle_id is 0');
        $this->assert(!empty($data['itinerary']), 'Default structured itinerary provided for 1 day');
        $this->assert(!empty($data['custom_perks']), 'Default perks provided when custom perks omitted');
        $this->assert(!empty($data['exclusions']), 'Default exclusions provided when exclusions empty');
    }

    public function testViewRenderingPackageLayout(): void
    {
        echo "\n3. Testing guide-listing-show.php HTML rendering for Travel Package:\n";

        $mockListing = [
            'id' => 1191,
            'title' => '3D2N South Cebu Expedition',
            'user_id' => 5,
            'category_slug' => 'packages',
            'duration' => '3 Days / 2 Nights',
            'capacity' => 8,
            'price' => 14800.00,
            'price_unit' => 'package',
            'area' => 'Moalboal & Badian, South Cebu',
            'cover_image' => 'uploads/packages/cebu.jpg',
            'status' => 'pending',
            'created_at' => '2026-11-06 10:00:00',
            'description' => 'Comprehensive 3-day travel bundle.',
            'included' => '',
            'not_included' => '',
        ];

        $mockGuide = [
            'id' => 5,
            'name' => 'Marc Repuesto',
            'email' => 'marc.guide@guidemate.ph',
            'phone' => '+63 917 123 4567',
            'avatar' => '',
            'bio' => 'Native Moalboal local with 7+ years of professional outdoor guiding experience.',
        ];

        $controller = new AdminController();
        $buildMethod = $this->getReflectionMethod(AdminController::class, 'buildPackageInspectionData');
        $packageData = $buildMethod->invoke($controller, $mockListing, $mockGuide);

        // Render view in output buffer
        $listing = $mockListing;
        $guide = $mockGuide;
        $reviewer = null;
        $schedules = [];
        $gallery = [];
        $isPackage = true;
        $packageInclusions = $packageData['inclusions'];

        ob_start();
        include __DIR__ . '/../app/Views/admin/guide-listing-show.php';
        $html = ob_get_clean();

        // 1. Verify Image 1 replica components
        $this->assert(str_contains($html, 'Live Tourist View (Image 1)'), 'Renders Image 1 replica header');
        $this->assert(str_contains($html, '3D2N South Cebu Expedition'), 'Renders package title');
        $this->assert(str_contains($html, 'Duration'), 'Renders Duration attribute column');
        $this->assert(str_contains($html, 'Group Size'), 'Renders Group Size attribute column');
        $this->assert(str_contains($html, 'Assurance'), 'Renders Assurance attribute column');
        $this->assert(str_contains($html, 'About This Package'), 'Renders About This Package section');

        // 2. Verify Image 2 replica components
        $this->assert(str_contains($html, 'Licensed Regional Tour Guide'), 'Renders host role');
        $this->assert(str_contains($html, 'DOT Accredited'), 'Renders host DOT accreditation');
        $this->assert(str_contains($html, 'Message Guide Host'), 'Renders Message Guide Host button');

        // 3. Verify Image 3 replica components
        $this->assert(str_contains($html, 'All-In-One Inclusions'), 'Renders All-In-One Inclusions header');
        $this->assert(str_contains($html, "What's Not Included:"), "Renders What's Not Included section");

        // 4. Verify Image 4 replica components
        $this->assert(str_contains($html, 'Day-by-Day Itinerary'), 'Renders Day-by-Day Itinerary header');
        $this->assert(str_contains($html, 'pkg-itinerary-tab'), 'Renders interactive day tabs');
        $this->assert(str_contains($html, 'switchPkgDay'), 'Includes tab switching JavaScript');
        $this->assert(str_contains($html, 'Day Highlights:'), 'Renders Day Highlights section');

        // 5. Verify Image 5 replica components
        $this->assert(str_contains($html, 'Included Accommodation Details'), 'Renders Accommodation details');
        $this->assert(str_contains($html, 'Included Rental Vehicle'), 'Renders Rental vehicle details');
        $this->assert(str_contains($html, 'Policies &amp; Requirements') || str_contains($html, 'Policies & Requirements'), 'Renders Policies & Requirements');
        $this->assert(str_contains($html, 'Cancellation Policy'), 'Renders Cancellation Policy');
        $this->assert(str_contains($html, 'Trip Requirements &amp; Reminders') || str_contains($html, 'Trip Requirements & Reminders'), 'Renders Trip Requirements');

        // 6. Verify Admin review sidebar
        $this->assert(str_contains($html, 'Review Decision'), 'Renders Review Decision sidebar');
        $this->assert(str_contains($html, 'Approve &amp; Publish Listing') || str_contains($html, 'Approve & Publish Listing'), 'Renders Approve action');
        $this->assert(str_contains($html, 'Package Financials'), 'Renders Package Financials panel in sidebar');
    }

    public function testViewRenderingNoRawComments(): void
    {
        echo "\n4. Testing that no raw internal HTML comments leak into the rendered view:\n";

        $mockListing = [
            'id' => 1193,
            'title' => 'Test Package with Comments',
            'user_id' => 5,
            'category_slug' => 'packages',
            'duration' => '2 Days',
            'capacity' => 6,
            'price' => 5000.00,
            'price_unit' => 'package',
            'area' => 'Cebu',
            'cover_image' => '',
            'status' => 'pending',
            'description' => '<!-- PACKAGE_OVERVIEW: {"badge":"New"} -->Description here',
            'included' => '<!-- PACKAGE_SERVICES: {"activity_ids":[1]} -->Perk 1',
            'not_included' => 'Not included',
        ];

        $controller = new AdminController();
        $buildMethod = $this->getReflectionMethod(AdminController::class, 'buildPackageInspectionData');
        $packageData = $buildMethod->invoke($controller, $mockListing, null);

        $listing = $mockListing;
        $guide = null;
        $reviewer = null;
        $schedules = [];
        $gallery = [];
        $isPackage = true;
        $packageInclusions = $packageData['inclusions'];

        ob_start();
        include __DIR__ . '/../app/Views/admin/guide-listing-show.php';
        $html = ob_get_clean();

        $this->assert(!str_contains($html, '<!-- PACKAGE_SERVICES'), 'Does not leak <!-- PACKAGE_SERVICES in package view');
        $this->assert(!str_contains($html, '<!-- PACKAGE_OVERVIEW'), 'Does not leak <!-- PACKAGE_OVERVIEW in package view');

        // Now test standard listing view ($isPackage = false)
        $isPackage = false;
        $packageData = null;

        ob_start();
        include __DIR__ . '/../app/Views/admin/guide-listing-show.php';
        $htmlStandard = ob_get_clean();

        $this->assert(!str_contains($htmlStandard, '<!-- PACKAGE_SERVICES'), 'Does not leak <!-- PACKAGE_SERVICES in standard view');
    }

    public function testViewRenderingStandardListing(): void
    {
        echo "\n5. Testing standard single tour/attraction listing view (\$isPackage = false):\n";

        $mockListing = [
            'id' => 501,
            'title' => 'Kawasan Falls Half Day Trek',
            'user_id' => 3,
            'category_slug' => 'things-to-do',
            'duration' => '4 Hours',
            'capacity' => 15,
            'price' => 1200.00,
            'price_unit' => 'person',
            'area' => 'Badian, Cebu',
            'cover_image' => 'uploads/tours/kawasan.jpg',
            'status' => 'approved',
            'description' => 'Exciting canyoneering and trekking tour.',
            'included' => "Safety Helmet\nLife Vest\nLicensed Guide",
            'not_included' => "Lockers\nFood & Drinks",
        ];

        $listing = $mockListing;
        $guide = ['id' => 3, 'name' => 'John Guide', 'email' => 'john@guide.com'];
        $reviewer = null;
        $schedules = [];
        $gallery = [];
        $isPackage = false;
        $packageData = null;

        ob_start();
        include __DIR__ . '/../app/Views/admin/guide-listing-show.php';
        $html = ob_get_clean();

        $this->assert(str_contains($html, 'Things to Do'), 'Renders Things to Do badge');
        $this->assert(str_contains($html, 'Kawasan Falls Half Day Trek'), 'Renders tour title');
        $this->assert(str_contains($html, 'Safety Helmet'), 'Renders included item');
        $this->assert(str_contains($html, 'Food &amp; Drinks') || str_contains($html, 'Food & Drinks'), 'Renders not included item');
        $this->assert(!str_contains($html, 'Live Tourist View (Image 1)'), 'Does not render package hero for single tour');
    }
}

$test = new AdminPackageInspectionViewTest();
$test->run();
