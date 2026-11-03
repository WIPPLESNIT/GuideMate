<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

class AdminReviewTablesSimplifiedListingColumnTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " Admin Review Tables Simplified Listing Column Tests\n";
        echo "========================================================\n\n";

        $this->testHotelListingReviewColumnContents();
        $this->testVehicleListingReviewColumnContents();
        $this->testCssAlignmentRules();
        $this->testPreservationOfOtherColumnsAndStructure();
        $this->testRenderMockHotelsTable();
        $this->testRenderMockVehiclesTable();

        echo "\n--------------------------------------------------------\n";
        echo "Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "--------------------------------------------------------\n\n";

        return $this->failed === 0;
    }

    private function assert(bool $condition, string $message): void
    {
        if ($condition) {
            echo " [PASS] {$message}\n";
            $this->passed++;
        } else {
            echo " [FAIL] {$message}\n";
            $this->failed++;
        }
    }

    private function testHotelListingReviewColumnContents(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');

        // Hotel column must contain the title and image
        $this->assert(
            str_contains($view, '<strong class="vehicle-title"><?= e($h[\'title\']) ?></strong>'),
            'Hotel column displays the hotel title / name'
        );
        $this->assert(
            str_contains($view, 'class="vehicle-thumb"'),
            'Hotel column displays the hotel picture / thumbnail'
        );

        // Hotel column must NOT contain address or description / amenities
        $this->assert(
            !str_contains($view, '$h[\'area\']') || !str_contains($view, '<div class="hint vehicle-sub">'),
            'Hotel column does NOT contain address or area sub-details'
        );
        $this->assert(
            !str_contains($view, 'array_slice($amenities'),
            'Hotel column does NOT contain description / amenities tags'
        );
    }

    private function testVehicleListingReviewColumnContents(): void
    {
        $view = file_get_contents(BASE_DIR . '/app/Views/admin/vehicles.php');

        // Vehicle column must contain the title/name and image
        $this->assert(
            str_contains($view, '<strong class="vehicle-title"><?= e($v[\'name\']) ?></strong>'),
            'Vehicle column displays the vehicle title / name'
        );
        $this->assert(
            str_contains($view, 'class="vehicle-thumb"'),
            'Vehicle column displays the vehicle picture / thumbnail'
        );

        // Vehicle column must NOT contain address, make/year, or specs/description
        $this->assert(
            !str_contains($view, '$v[\'location\']'),
            'Vehicle column does NOT contain address / location details'
        );
        $this->assert(
            !str_contains($view, '$v[\'year\']') && !str_contains($view, '$v[\'make\']'),
            'Vehicle column does NOT contain year / make description details'
        );
        $this->assert(
            !str_contains($view, 'array_slice($specs'),
            'Vehicle column does NOT contain description / specs tags'
        );
    }

    private function testCssAlignmentRules(): void
    {
        $css = file_get_contents(BASE_DIR . '/public/assets/css/style.css');

        $this->assert(
            str_contains($css, '.vehicle-meta-wrap') && str_contains($css, 'align-items: center;'),
            'style.css configures align-items: center on .vehicle-meta-wrap for vertical centering of thumb and name'
        );
        $this->assert(
            str_contains($css, 'gap: 0.85rem;'),
            'style.css preserves clean consistent gap between thumbnail and listing name'
        );
    }

    private function testPreservationOfOtherColumnsAndStructure(): void
    {
        $hotelView = file_get_contents(BASE_DIR . '/app/Views/admin/hotels.php');
        $vehicleView = file_get_contents(BASE_DIR . '/app/Views/admin/vehicles.php');

        // Check hotels table headers
        $this->assert(str_contains($hotelView, '<th class="col-vehicle">Hotel</th>'), 'Hotels table preserves Hotel column header');
        $this->assert(str_contains($hotelView, '<th class="col-partner">Partner</th>'), 'Hotels table preserves Partner column header');
        $this->assert(str_contains($hotelView, '<th class="col-type">Location / Rate</th>'), 'Hotels table preserves Location / Rate column header');
        $this->assert(str_contains($hotelView, '<th class="col-status">Status</th>'), 'Hotels table preserves Status column header');
        $this->assert(str_contains($hotelView, '<th class="col-audit">Dates / Audit</th>'), 'Hotels table preserves Dates / Audit column header');
        $this->assert(str_contains($hotelView, '<th class="col-actions" style="text-align:center;">Actions</th>'), 'Hotels table preserves Actions column header');

        // Check vehicles table headers
        $this->assert(str_contains($vehicleView, '<th class="col-vehicle">Vehicle</th>'), 'Vehicles table preserves Vehicle column header');
        $this->assert(str_contains($vehicleView, '<th class="col-partner">Partner</th>'), 'Vehicles table preserves Partner column header');
        $this->assert(str_contains($vehicleView, '<th class="col-type">Type / Rate</th>'), 'Vehicles table preserves Type / Rate column header');
        $this->assert(str_contains($vehicleView, '<th class="col-status">Status</th>'), 'Vehicles table preserves Status column header');
        $this->assert(str_contains($vehicleView, '<th class="col-audit">Dates / Audit</th>'), 'Vehicles table preserves Dates / Audit column header');
        $this->assert(str_contains($vehicleView, '<th class="col-actions" style="text-align:center;">Actions</th>'), 'Vehicles table preserves Actions column header');
    }

    private function testRenderMockHotelsTable(): void
    {
        $status = 'all';
        $pendingCount = 0;
        $hotels = [
            [
                'id' => 701,
                'title' => 'Resubmit Property Resort',
                'area' => 'Moalboal',
                'address' => 'Panagsama Beach, Moalboal, Cebu',
                'cover_image' => 'https://example.com/resort.jpg',
                'price' => 2000.00,
                'status' => 'pending',
                'provider_name' => 'Resubmit Partner',
                'provider_email' => 'partner@example.com',
                'included' => 'WiFi, Breakfast, Beach Access',
                'room_count' => 0,
                'submitted_at' => '2026-11-03 04:44:00',
                'created_at' => '2026-11-03 04:44:00',
                'reviewed_at' => null,
                'reviewer_name' => null,
            ]
        ];

        ob_start();
        include BASE_DIR . '/app/Views/admin/hotels.php';
        $html = ob_get_clean();

        // Hotel name and thumb are rendered
        $this->assert(str_contains($html, 'Resubmit Property Resort'), 'Rendered HTML contains hotel title');
        $this->assert(str_contains($html, 'class="vehicle-thumb"'), 'Rendered HTML contains hotel thumbnail');

        // Address and amenities are NOT rendered in the Hotel column cell
        preg_match('/<td class="vehicle-cell col-vehicle"[^>]*>(.*?)<\/td>/s', $html, $matches);
        $hotelCellHtml = $matches[1] ?? '';

        $this->assert(!str_contains($hotelCellHtml, 'Panagsama Beach'), 'Hotel column cell does not contain address');
        $this->assert(!str_contains($hotelCellHtml, 'WiFi · Breakfast'), 'Hotel column cell does not contain amenities description');
        $this->assert(str_contains($hotelCellHtml, 'Resubmit Property Resort'), 'Hotel column cell strictly retains hotel name');
    }

    private function testRenderMockVehiclesTable(): void
    {
        $status = 'all';
        $pendingCount = 0;
        $vehicles = [
            [
                'id' => 801,
                'name' => '2022 Honda Click 125I V2 Automatic',
                'location' => 'Minglanilla, Cebu',
                'year' => 2022,
                'make' => 'Honda',
                'type' => 'Scooter',
                'price_per_day' => 1000.00,
                'status' => 'approved',
                'provider_name' => 'Miguel Yongzon',
                'provider_email' => 'mig@example.com',
                'specs' => 'Automatic Transmission, Helmet Included',
                'image' => 'https://example.com/bike.jpg',
                'submitted_at' => '2026-10-30 23:50:00',
                'created_at' => '2026-10-30 23:50:00',
                'reviewed_at' => '2026-10-31 08:25:00',
                'reviewer_name' => 'Administrator',
                'rejection_reason' => null,
            ]
        ];

        ob_start();
        include BASE_DIR . '/app/Views/admin/vehicles.php';
        $html = ob_get_clean();

        // Vehicle name and thumb are rendered
        $this->assert(str_contains($html, '2022 Honda Click 125I V2 Automatic'), 'Rendered HTML contains vehicle title');
        $this->assert(str_contains($html, 'class="vehicle-thumb"'), 'Rendered HTML contains vehicle thumbnail');

        // Address and specs are NOT rendered in the Vehicle column cell
        preg_match('/<td class="vehicle-cell col-vehicle"[^>]*>(.*?)<\/td>/s', $html, $matches);
        $vehicleCellHtml = $matches[1] ?? '';

        $this->assert(!str_contains($vehicleCellHtml, 'Minglanilla, Cebu'), 'Vehicle column cell does not contain address/location');
        $this->assert(!str_contains($vehicleCellHtml, 'Automatic Transmission'), 'Vehicle column cell does not contain specs/description');
        $this->assert(str_contains($vehicleCellHtml, '2022 Honda Click 125I V2 Automatic'), 'Vehicle column cell strictly retains vehicle name');
    }
}

$test = new AdminReviewTablesSimplifiedListingColumnTest();
exit($test->run() ? 0 : 1);
