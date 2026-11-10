<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Models\Listing;

class WebImprovementsBugFixesTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): void
    {
        echo "========================================================\n";
        echo " GuideMate - Web Improvements and Bug Fixes Test\n";
        echo "========================================================\n\n";

        $this->testIssue1DropdownDisplayAndOverflow();
        $this->testIssue2MyListingsSaveChangesWorkflow();
        $this->testIssue3HotelStayListingsInPackageBuilder();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function testIssue1DropdownDisplayAndOverflow(): void
    {
        echo "--- 1. Guide Account: Booking Dropdown Display & Overflow ---\n";

        $css = file_get_contents(__DIR__ . '/../public/assets/css/style.css');
        $bookingsView = file_get_contents(__DIR__ . '/../app/Views/guide/bookings.php');
        $mainJs = file_get_contents(__DIR__ . '/../public/assets/js/main.js');

        // Check CSS for guide-bookings overflow: visible !important
        $this->assert(
            str_contains($css, '.guide-bookings-panel') && str_contains($css, '.guide-bookings-wrap'),
            'CSS defines .guide-bookings-panel and .guide-bookings-wrap'
        );
        $this->assert(
            preg_match('/\.guide-bookings-wrap\s*\{[^}]*overflow:\s*visible\s*!important/s', $css) === 1,
            '.guide-bookings-wrap has overflow: visible !important to prevent table row clipping'
        );
        $this->assert(
            preg_match('/\.res-action-dropdown\s*\{[^}]*position:\s*fixed/s', $css) === 1,
            '.res-action-dropdown uses position: fixed for viewport escape'
        );
        $this->assert(
            str_contains($mainJs, "menu.style.position = 'fixed'") && str_contains($mainJs, "menu.style.zIndex = '99999'"),
            'main.js explicitly enforces position: fixed and z-index: 99999 on dropdown open'
        );
        $this->assert(
            str_contains($bookingsView, 'guide-bookings-wrap') && str_contains($bookingsView, 'style="overflow:visible;"'),
            'bookings.php panel and wrapper have inline overflow: visible'
        );
    }

    private function testIssue2MyListingsSaveChangesWorkflow(): void
    {
        echo "\n--- 2. Guide Account: Regular Listing Save Changes Workflow ---\n";

        $guideController = file_get_contents(__DIR__ . '/../app/Controllers/GuideController.php');
        $listingsView = file_get_contents(__DIR__ . '/../app/Views/guide/listings.php');

        $this->assert(
            !str_contains($guideController, "'/dashboard/listings/' . \$this->input('listing_id') . '/edit'"),
            'GuideController no longer redirects to /dashboard/listings/{id}/edit on validation errors'
        );
        $this->assert(
            str_contains($guideController, "'Listing updated successfully.'"),
            'GuideController returns success message on update'
        );
        $this->assert(
            str_contains($listingsView, "fetch(form.action, {") && str_contains($listingsView, "'X-Requested-With': 'XMLHttpRequest'"),
            'listings.php submits edit listing form via AJAX with XMLHttpRequest header'
        );
        $this->assert(
            str_contains($listingsView, 'closeModal()') && str_contains($listingsView, 'window.location.reload()'),
            'listings.php closes modal and reloads page on successful save without navigating to full edit page'
        );
        $this->assert(
            str_contains($listingsView, 'errorBox.textContent = msg;') && str_contains($listingsView, 'errorBox.style.display = \'block\';'),
            'listings.php displays validation errors in errorBox and keeps modal open'
        );
    }

    private function testIssue3HotelStayListingsInPackageBuilder(): void
    {
        echo "\n--- 3. Travel Package Builder: Hotel & Stay Listings ---\n";

        $guideController = file_get_contents(__DIR__ . '/../app/Controllers/GuideController.php');
        $packageBuilderView = file_get_contents(__DIR__ . '/../app/Views/guide/package-builder.php');

        $this->assert(
            !str_contains($guideController, 'l.check_in_time'),
            'GuideController queries no longer contain invalid l.check_in_time column'
        );
        $this->assert(
            !str_contains($guideController, 'l.check_out_time'),
            'GuideController queries no longer contain invalid l.check_out_time column'
        );

        // Test actual database query for hotels in category 'hotels'
        $hotels = Database::all("
            SELECT l.id, l.user_id, l.title, l.area, l.address, l.cover_image, l.summary, l.description,
                   u.name as partner_name,
                   (SELECT COUNT(*) FROM hotel_rooms hr WHERE hr.listing_id = l.id AND hr.status = 'available' AND (hr.available_units > 0 OR hr.available_units IS NULL)) as available_room_count,
                   (SELECT MIN(hr.price_per_night) FROM hotel_rooms hr WHERE hr.listing_id = l.id AND hr.status = 'available' AND (hr.available_units > 0 OR hr.available_units IS NULL) AND hr.price_per_night > 0) as min_price
            FROM listings l
            JOIN categories c ON c.id = l.category_id
            JOIN users u ON u.id = l.user_id
            WHERE l.status = 'approved' AND c.slug = 'hotels'
            HAVING available_room_count > 0
            ORDER BY l.title ASC
        ");

        $this->assert(
            count($hotels) >= 1,
            'Database query successfully returns approved hotel listings with available rooms (found ' . count($hotels) . ' hotels)'
        );

        $firstHotel = $hotels[0];
        $this->assert(!empty($firstHotel['title']), 'Hotel record has valid title (' . ($firstHotel['title'] ?? '') . ')');
        $this->assert(!empty($firstHotel['area']), 'Hotel record has valid area (' . ($firstHotel['area'] ?? '') . ')');
        $this->assert(!empty($firstHotel['cover_image']), 'Hotel record has valid cover_image');
        $this->assert((float)($firstHotel['min_price'] ?? 0) > 0, 'Hotel record has valid min_price (' . ($firstHotel['min_price'] ?? 0) . ')');

        // Test hotel rooms query
        $hotelRooms = Database::all("
            SELECT hr.id, hr.listing_id, hr.name as room_name, hr.price_per_night, hr.max_guests, hr.bed_type, hr.room_size, hr.amenities, hr.image as room_image, hr.description as room_description,
                   l.title as hotel_name, l.area as hotel_area, l.cover_image as hotel_cover,
                   u.name as partner_name
            FROM hotel_rooms hr
            JOIN listings l ON l.id = hr.listing_id
            JOIN categories c ON c.id = l.category_id
            JOIN users u ON u.id = l.user_id
            WHERE l.status = 'approved' AND c.slug = 'hotels'
              AND hr.status = 'available'
              AND (hr.available_units > 0 OR hr.available_units IS NULL)
            ORDER BY hr.price_per_night ASC
        ");

        $this->assert(
            count($hotelRooms) >= 1,
            'Database query successfully returns available rooms for hotels (found ' . count($hotelRooms) . ' rooms)'
        );

        $this->assert(
            str_contains($packageBuilderView, 'pkg-hotel-property-card') && str_contains($packageBuilderView, '$hDesc'),
            'package-builder.php renders hotel cards with description and database fields'
        );
        $this->assert(
            str_contains($packageBuilderView, 'hotel-room-item') && str_contains($packageBuilderView, '$rDesc'),
            'package-builder.php renders room cards with description and dynamic amenities'
        );
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

(new WebImprovementsBugFixesTest())->run();
