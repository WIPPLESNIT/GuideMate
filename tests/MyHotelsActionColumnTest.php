<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Auth;
use App\Models\Listing;

final class MyHotelsActionColumnTest
{
    private int $passed = 0;
    private int $failed = 0;

    public function run(): bool
    {
        echo "========================================================\n";
        echo " GuideMate My Hotels Table Action Column Suite\n";
        echo "========================================================\n\n";

        $this->runTest('testTableMarkupAndColActionsClasses', [$this, 'testTableMarkupAndColActionsClasses']);
        $this->runTest('testCssStylesForActionColumnAndButtons', [$this, 'testCssStylesForActionColumnAndButtons']);
        $this->runTest('testRenderPendingAndApprovedRows', [$this, 'testRenderPendingAndApprovedRows']);
        $this->runTest('testNoStatusLeakInActionButtons', [$this, 'testNoStatusLeakInActionButtons']);

        echo "\n--------------------------------------------------------\n";
        echo sprintf("Results: %d passed, %d failed\n", $this->passed, $this->failed);
        echo "--------------------------------------------------------\n";

        return $this->failed === 0;
    }

    private function runTest(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo " [PASS] {$name}\n";
        } catch (\Throwable $e) {
            $this->failed++;
            echo " [FAIL] {$name}: {$e->getMessage()}\n";
        }
    }

    private function assert(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    public function testTableMarkupAndColActionsClasses(): void
    {
        $viewContent = file_get_contents(BASE_DIR . '/app/Views/hotel/hotels.php');
        $this->assert($viewContent !== false, 'Failed to read app/Views/hotel/hotels.php');

        // Verify table has my-hotels-table class
        $this->assert(
            str_contains($viewContent, 'class="table my-hotels-table"'),
            'Hotels table must have class "table my-hotels-table"'
        );

        // Verify header has col-actions class with consistent width
        $this->assert(
            str_contains($viewContent, '<th class="col-actions" style="text-align:right;width:140px;min-width:140px;">Actions</th>'),
            'Action header must have class "col-actions" with width:140px'
        );

        // Verify table cell has col-actions class with consistent width
        $this->assert(
            str_contains($viewContent, '<td class="col-actions" style="text-align:right;white-space:nowrap;width:140px;min-width:140px;">'),
            'Action cell must have class "col-actions" with width:140px'
        );

        // Verify container uses hotel-action-buttons class
        $this->assert(
            str_contains($viewContent, '<div class="hotel-action-buttons">'),
            'Actions container must have class "hotel-action-buttons"'
        );

        // Verify disabled Room button has appropriate accessibility attributes
        $this->assert(
            str_contains($viewContent, 'class="btn btn-ghost btn-sm btn-hotel-room-disabled"'),
            'Disabled room button must use btn-hotel-room-disabled class'
        );
        $this->assert(
            str_contains($viewContent, 'title="Room management is unavailable until this hotel listing is approved by an administrator."'),
            'Disabled room button must have clear informative title'
        );

        // Verify enabled Room button uses btn-hotel-room
        $this->assert(
            str_contains($viewContent, 'class="btn btn-ghost btn-sm btn-hotel-room"'),
            'Enabled room button must use btn-hotel-room class'
        );
    }

    public function testCssStylesForActionColumnAndButtons(): void
    {
        $cssContent = file_get_contents(BASE_DIR . '/public/assets/css/style.css');
        $this->assert($cssContent !== false, 'Failed to read public/assets/css/style.css');

        $this->assert(
            str_contains($cssContent, '.my-hotels-table'),
            'CSS must contain .my-hotels-table'
        );
        $this->assert(
            str_contains($cssContent, '.my-hotels-table th.col-actions'),
            'CSS must contain .my-hotels-table th.col-actions rule'
        );
        $this->assert(
            str_contains($cssContent, '.hotel-action-buttons'),
            'CSS must contain .hotel-action-buttons rule'
        );
        $this->assert(
            str_contains($cssContent, '.btn-hotel-room {'),
            'CSS must contain .btn-hotel-room rule'
        );
        $this->assert(
            str_contains($cssContent, '.btn-hotel-room-disabled {'),
            'CSS must contain .btn-hotel-room-disabled rule'
        );
        $this->assert(
            str_contains($cssContent, 'cursor: not-allowed'),
            'CSS must contain cursor: not-allowed for disabled room button'
        );
    }

    public function testRenderPendingAndApprovedRows(): void
    {
        $hotels = [
            [
                'id' => 101,
                'title' => 'Pending Luxury Stay',
                'slug' => 'pending-luxury-stay',
                'area' => 'Moalboal',
                'address' => 'Panagsama Beach',
                'price' => 150.00,
                'status' => 'pending',
                'room_count' => 0,
                'cover_image' => '',
                'rejection_reason' => null,
            ],
            [
                'id' => 102,
                'title' => 'Approved Grand Hotel',
                'slug' => 'approved-grand-hotel',
                'area' => 'Mactan',
                'address' => 'Punta Engano',
                'price' => 3500.00,
                'status' => 'approved',
                'room_count' => 3,
                'cover_image' => '',
                'rejection_reason' => null,
            ],
            [
                'id' => 103,
                'title' => 'Rejected Budget Inn',
                'slug' => 'rejected-budget-inn',
                'area' => 'Oslob',
                'address' => 'Tan-awan',
                'price' => 800.00,
                'status' => 'rejected',
                'room_count' => 0,
                'cover_image' => '',
                'rejection_reason' => 'Photo too blurry',
            ]
        ];

        $user = [
            'id' => 50,
            'name' => 'Test Hotel Partner',
            'role' => 'hotel_provider',
            'status' => 'approved'
        ];
        $status = 'approved';
        $isVerified = true;

        ob_start();
        include BASE_DIR . '/app/Views/hotel/hotels.php';
        $html = ob_get_clean();

        $this->assert(is_string($html) && $html !== '', 'Rendered output should be non-empty');

        // Check Pending Row
        $this->assert(
            str_contains($html, 'Pending Luxury Stay'),
            'Rendered HTML must include pending hotel title'
        );
        $this->assert(
            str_contains($html, 'btn-hotel-room-disabled'),
            'Pending hotel must render disabled room button'
        );
        $this->assert(
            str_contains($html, 'aria-disabled="true"'),
            'Pending hotel must render aria-disabled="true"'
        );

        // Check Approved Row
        $this->assert(
            str_contains($html, 'Approved Grand Hotel'),
            'Rendered HTML must include approved hotel title'
        );
        $this->assert(
            str_contains($html, 'btn-hotel-room'),
            'Approved hotel must render enabled room button'
        );
        $this->assert(
            str_contains($html, '/dashboard/hotels/102/rooms'),
            'Approved hotel room button must link to room management URL'
        );

        // Check Rejected Row
        $this->assert(
            str_contains($html, 'Rejected Budget Inn'),
            'Rendered HTML must include rejected hotel title'
        );
        $this->assert(
            str_contains($html, 'Photo too blurry'),
            'Rejected hotel must display rejection reason'
        );
    }

    public function testNoStatusLeakInActionButtons(): void
    {
        $hotels = [
            [
                'id' => 201,
                'title' => 'Testing Status Text',
                'slug' => 'testing-status-text',
                'area' => 'Cebu City',
                'address' => 'IT Park',
                'price' => 500.00,
                'status' => 'pending',
                'room_count' => 0,
                'cover_image' => '',
                'rejection_reason' => null,
            ]
        ];

        $user = ['id' => 60, 'name' => 'Partner', 'role' => 'hotel_provider', 'status' => 'approved'];
        $status = 'approved';
        $isVerified = true;

        ob_start();
        include BASE_DIR . '/app/Views/hotel/hotels.php';
        $html = ob_get_clean();

        // Ensure the old stretching text is not present anywhere in the action buttons
        $this->assert(
            !str_contains($html, 'Room (Pending Approval)'),
            'Action button must NOT contain "Room (Pending Approval)"'
        );
        $this->assert(
            !str_contains($html, 'Room (Pending)'),
            'Action button must NOT contain "Room (Pending)"'
        );
        $this->assert(
            !str_contains($html, 'Room (Approved)'),
            'Action button must NOT contain "Room (Approved)"'
        );
    }
}

// Self-run when executed directly via CLI
if (php_sapi_name() === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $suite = new MyHotelsActionColumnTest();
    exit($suite->run() ? 0 : 1);
}
